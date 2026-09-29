<?php

declare(strict_types=1);

class FaqEmbedder
{
    private const TIMEOUT_SEC = 8;
    private const LOCAL_MODEL = 'local-hash-v2';
    private const LOCAL_DIM = 384;
    private static bool $unavailable = false;
    private static bool $embeddingsBroken = false;
    private static bool $schemaReady = false;

    /** @var array<string, list<float>> */
    private static array $vectorCache = [];

    public static function configured(): bool
    {
        if (self::usesLocalModel() || self::$embeddingsBroken) {
            return true;
        }
        return self::apiKey() !== '' && !self::$unavailable;
    }

    public static function embeddingsAvailable(): bool
    {
        return self::usesLocalModel() || self::$embeddingsBroken || (self::apiKey() !== '' && !self::$unavailable);
    }

    public static function usesLocalModel(): bool
    {
        return str_starts_with(self::model(), 'local-');
    }

    public static function ensureSchema(): void
    {
        if (self::$schemaReady) {
            return;
        }
        try {
            $pdo = Db::app();
            $exists = $pdo->query("SHOW TABLES LIKE 'faq_embeddings'")->fetchColumn();
            if (!$exists) {
                $pdo->exec(
                    'CREATE TABLE `faq_embeddings` (
                      `faq_id` int UNSIGNED NOT NULL,
                      `model` varchar(120) NOT NULL DEFAULT \'\',
                      `vector_json` mediumtext NOT NULL,
                      `content_hash` varchar(64) NOT NULL DEFAULT \'\',
                      `updated_at` timestamp NULL DEFAULT current_timestamp ON UPDATE CURRENT_TIMESTAMP,
                      PRIMARY KEY (`faq_id`),
                      CONSTRAINT `fk_faq_embeddings_faq` FOREIGN KEY (`faq_id`) REFERENCES `faqs` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
                );
            }
            self::$schemaReady = true;
        } catch (Throwable $e) {
            error_log('SCiGENIE FaqEmbedder schema: ' . mb_substr($e->getMessage(), 0, 200));
        }
    }

    /**
     * @param array<string, mixed> $faq
     */
    public static function contentText(array $faq): string
    {
        $parts = [
            trim((string) ($faq['question'] ?? '')),
            trim((string) ($faq['keyword'] ?? '')),
            trim((string) ($faq['keyword_back'] ?? '')),
            mb_substr(trim(strip_tags((string) ($faq['answer'] ?? ''))), 0, 400),
        ];
        return trim(implode("\n", array_filter($parts, static fn ($p) => $p !== '')));
    }

    /**
     * @param array<string, mixed> $faq
     */
    public static function contentHash(array $faq): string
    {
        return hash('sha256', self::contentText($faq) . '|' . self::effectiveModel());
    }

    /**
     * @return list<float>|null
     */
    public static function embedText(string $text): ?array
    {
        $text = trim($text);
        if ($text === '') {
            return null;
        }

        if (self::usesLocalModel() || self::$embeddingsBroken) {
            return self::embedTextLocal($text);
        }

        if (self::apiKey() === '') {
            self::$embeddingsBroken = true;
            return self::embedTextLocal($text);
        }

        $payload = [
            'model' => self::model(),
            'input' => $text,
        ];
        $raw = self::httpPost(self::embeddingsUrl(), $payload);
        if ($raw === null || $raw === '') {
            self::$embeddingsBroken = true;
            error_log('SCiGENIE embeddings: empty response, using local-hash-v1');
            return self::embedTextLocal($text);
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            self::$embeddingsBroken = true;
            return self::embedTextLocal($text);
        }
        if (isset($decoded['error']) || isset($decoded['message'])) {
            $msg = (string) ($decoded['error']['message'] ?? $decoded['message'] ?? $raw);
            error_log('SCiGENIE embeddings: ' . mb_substr($msg, 0, 300));
            $lower = strtolower($msg);
            if (str_contains($lower, 'api key') || str_contains($msg, '401')) {
                self::$unavailable = true;
            }
            // Gateway without /embeddings (e.g. Cannot POST:/embeddings) → local vectors
            self::$embeddingsBroken = true;
            return self::embedTextLocal($text);
        }
        $vector = $decoded['data'][0]['embedding'] ?? null;
        if (!is_array($vector) || $vector === []) {
            self::$embeddingsBroken = true;
            return self::embedTextLocal($text);
        }
        $out = [];
        foreach ($vector as $v) {
            $out[] = (float) $v;
        }
        return $out;
    }

    /**
     * Local character n-gram hashing (no external API). Works when gateway has no /embeddings.
     *
     * @return list<float>
     */
    public static function embedTextLocal(string $text): array
    {
        $dim = self::LOCAL_DIM;
        $vec = array_fill(0, $dim, 0.0);
        $norm = mb_strtolower($text);
        $norm = preg_replace('/\s+/u', '', $norm) ?? $norm;
        $norm = preg_replace('/[^\p{L}\p{N}]+/u', '', $norm) ?? $norm;
        $len = mb_strlen($norm);
        if ($len === 0) {
            $vec[0] = 1.0;
            return $vec;
        }

        for ($n = 2; $n <= 3; $n++) {
            if ($len < $n) {
                continue;
            }
            for ($i = 0; $i <= $len - $n; $i++) {
                $gram = mb_substr($norm, $i, $n);
                $h = abs(crc32($gram . '#' . $n));
                $idx = $h % $dim;
                $sign = (($h >> 16) & 1) === 1 ? 1.0 : -1.0;
                $vec[$idx] += $sign;
            }
        }

        // Whole-token boosts for Latin/numbers
        if (preg_match_all('/[a-z0-9]{2,}/u', mb_strtolower($text), $m) > 0) {
            foreach ($m[0] as $tok) {
                $h = abs(crc32('tok:' . $tok));
                $idx = $h % $dim;
                $vec[$idx] += 2.0;
            }
        }

        $normSq = 0.0;
        for ($i = 0; $i < $dim; $i++) {
            $normSq += $vec[$i] * $vec[$i];
        }
        if ($normSq > 0.0) {
            $inv = 1.0 / sqrt($normSq);
            for ($i = 0; $i < $dim; $i++) {
                $vec[$i] *= $inv;
            }
        }
        return $vec;
    }

    /**
     * @param array<string, mixed> $faq
     */
    public static function upsertFaq(array $faq): bool
    {
        self::ensureSchema();
        if (!self::configured() || !self::$schemaReady || !self::embeddingsAvailable()) {
            return false;
        }
        $id = (int) ($faq['id'] ?? 0);
        if ($id < 1) {
            return false;
        }
        $hash = self::contentHash($faq);
        $model = self::effectiveModel();

        try {
            $stmt = Db::app()->prepare('SELECT content_hash, model FROM faq_embeddings WHERE faq_id = :id LIMIT 1');
            $stmt->execute(['id' => $id]);
            $row = $stmt->fetch();
            if (is_array($row)
                && (string) ($row['content_hash'] ?? '') === $hash
                && (string) ($row['model'] ?? '') === $model
            ) {
                return true;
            }
        } catch (Throwable $e) {
            error_log('SCiGENIE FaqEmbedder read: ' . mb_substr($e->getMessage(), 0, 200));
            return false;
        }

        $vector = self::embedText(self::contentText($faq));
        if ($vector === null) {
            return false;
        }
        // Re-read effective model after embedText may switch to local fallback
        $model = self::effectiveModel();
        $hash = self::contentHash($faq);
        $json = json_encode($vector, JSON_UNESCAPED_UNICODE);
        if (!is_string($json)) {
            return false;
        }

        try {
            $ins = Db::app()->prepare(
                'INSERT INTO faq_embeddings (faq_id, model, vector_json, content_hash)
                 VALUES (:id, :model, :vector, :hash)
                 ON DUPLICATE KEY UPDATE model = VALUES(model), vector_json = VALUES(vector_json), content_hash = VALUES(content_hash)'
            );
            $ins->execute([
                'id' => $id,
                'model' => $model,
                'vector' => $json,
                'hash' => $hash,
            ]);
            self::$vectorCache[(string) $id] = $vector;
            return true;
        } catch (Throwable $e) {
            error_log('SCiGENIE FaqEmbedder upsert: ' . mb_substr($e->getMessage(), 0, 200));
            return false;
        }
    }

    public static function deleteFaq(int|string $faqId): void
    {
        self::ensureSchema();
        $id = (int) $faqId;
        if ($id < 1 || !self::$schemaReady) {
            return;
        }
        try {
            Db::app()->prepare('DELETE FROM faq_embeddings WHERE faq_id = :id')->execute(['id' => $id]);
            unset(self::$vectorCache[(string) $id]);
        } catch (Throwable $e) {
            error_log('SCiGENIE FaqEmbedder delete: ' . mb_substr($e->getMessage(), 0, 200));
        }
    }

    /**
     * @param list<array<string, mixed>> $faqs
     * @return array{updated: int, skipped: int, failed: int}
     */
    public static function ensureAll(array $faqs): array
    {
        self::ensureSchema();
        $updated = 0;
        $skipped = 0;
        $failed = 0;
        if (!self::configured() || !self::$schemaReady || !self::embeddingsAvailable()) {
            return ['updated' => 0, 'skipped' => count($faqs), 'failed' => 0];
        }

        $model = self::effectiveModel();
        $existing = [];
        try {
            foreach (Db::app()->query('SELECT faq_id, content_hash, model FROM faq_embeddings')->fetchAll() as $row) {
                $existing[(string) $row['faq_id']] = $row;
            }
        } catch (Throwable $e) {
            error_log('SCiGENIE FaqEmbedder list: ' . mb_substr($e->getMessage(), 0, 200));
            return ['updated' => 0, 'skipped' => 0, 'failed' => count($faqs)];
        }

        foreach ($faqs as $faq) {
            $id = (string) ($faq['id'] ?? '');
            if ($id === '') {
                $failed++;
                continue;
            }
            $hash = self::contentHash($faq);
            $row = $existing[$id] ?? null;
            if ($row !== null
                && (string) ($row['content_hash'] ?? '') === $hash
                && (string) ($row['model'] ?? '') === $model
            ) {
                $skipped++;
                continue;
            }
            if (self::upsertFaq($faq)) {
                $updated++;
                $model = self::effectiveModel();
            } else {
                $failed++;
                if (self::$unavailable && !self::usesLocalModel() && !self::$embeddingsBroken) {
                    break;
                }
            }
        }

        return ['updated' => $updated, 'skipped' => $skipped, 'failed' => $failed];
    }

    /**
     * @param list<array<string, mixed>> $faqs
     * @return list<array{faq: array<string, mixed>, similarity: float}>
     */
    public static function search(string $question, array $faqs, int $limit = 5): array
    {
        $question = trim($question);
        $limit = max(1, min(10, $limit));
        if ($question === '' || $faqs === [] || !self::configured()) {
            return [];
        }

        self::ensureSchema();
        if (self::embeddingsAvailable()) {
            $queryVec = self::embedText($question);
            if ($queryVec !== null) {
                $minSim = self::minSimilarity();
                $vectors = self::loadVectorsForFaqs($faqs);
                $hits = [];
                foreach ($faqs as $faq) {
                    $id = (string) ($faq['id'] ?? '');
                    if ($id === '' || !isset($vectors[$id])) {
                        continue;
                    }
                    $sim = self::cosine($queryVec, $vectors[$id]);
                    if ($sim < $minSim) {
                        continue;
                    }
                    $hits[] = [
                        'faq' => $faq,
                        'similarity' => $sim,
                    ];
                }
                usort($hits, static fn ($a, $b) => $b['similarity'] <=> $a['similarity']);
                return array_slice($hits, 0, $limit);
            }
        }

        return self::searchViaChat($question, $faqs, $limit);
    }

    /**
     * Fallback when embeddings API is unavailable: rank FAQ titles via chat model.
     *
     * @param list<array<string, mixed>> $faqs
     * @return list<array{faq: array<string, mixed>, similarity: float}>
     */
    private static function searchViaChat(string $question, array $faqs, int $limit): array
    {
        $pool = array_slice($faqs, 0, 40);
        $payload = [];
        $byId = [];
        foreach ($pool as $faq) {
            $id = trim((string) ($faq['id'] ?? ''));
            if ($id === '') {
                continue;
            }
            $byId[$id] = $faq;
            $payload[] = [
                'id' => $id,
                'question' => trim((string) ($faq['question'] ?? '')),
                'keyword' => trim((string) ($faq['keyword'] ?? '')),
            ];
        }
        if ($payload === []) {
            return [];
        }

        $user = "คำถามผู้ใช้:\n"
            . $question . "\n\n"
            . "รายการ FAQ:\n"
            . json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n\n"
            . "เลือก FAQ ที่ความหมายใกล้เคียงกับคำถามมากที่สุด ไม่เกิน {$limit} ข้อ\n"
            . "ตอบ JSON เท่านั้น:\n"
            . "{\"items\":[{\"id\":\"...\",\"similarity\":0.0}]}\n"
            . "similarity เป็นทศนิยม 0-1 เฉพาะข้อที่ใกล้เคียงจริง (>= 0.72)\n"
            . "ถ้าไม่มีข้อใกล้เคียง ให้ items เป็น []";

        $raw = self::chat($user);
        if ($raw === null || $raw === '') {
            return [];
        }
        $raw = preg_replace('/^```(?:json)?\s*|\s*```$/u', '', trim($raw)) ?? trim($raw);
        if (preg_match('/\{.*\}/s', $raw, $m) === 1) {
            $raw = $m[0];
        }
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            return [];
        }
        $items = $data['items'] ?? null;
        if (!is_array($items)) {
            return [];
        }
        $minSim = (float) config('semantic_min_sim', 0.72);
        $hits = [];
        foreach ($items as $row) {
            if (!is_array($row)) {
                continue;
            }
            $id = (string) ($row['id'] ?? '');
            $sim = (float) ($row['similarity'] ?? 0);
            if ($id === '' || !isset($byId[$id]) || $sim < $minSim) {
                continue;
            }
            $hits[] = [
                'faq' => $byId[$id],
                'similarity' => min(1.0, $sim),
            ];
        }
        usort($hits, static fn ($a, $b) => $b['similarity'] <=> $a['similarity']);
        return array_slice($hits, 0, $limit);
    }

    private static function chat(string $user): ?string
    {
        $payload = [
            'model' => self::chatModel(),
            'temperature' => 0,
            'max_tokens' => 280,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'คุณช่วยจัดอันดับ FAQ ตามความหมายใกล้เคียง ตอบเป็น JSON เท่านั้น',
                ],
                [
                    'role' => 'user',
                    'content' => $user,
                ],
            ],
        ];
        $raw = self::httpPost(self::chatCompletionsUrl(), $payload);
        if ($raw === null || $raw === '') {
            return null;
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return null;
        }
        if (isset($decoded['error'])) {
            error_log('SCiGENIE semantic chat: ' . mb_substr((string) ($decoded['error']['message'] ?? $raw), 0, 300));
            return null;
        }
        $text = trim((string) ($decoded['choices'][0]['message']['content'] ?? ''));
        return $text !== '' ? $text : null;
    }

    private static function chatCompletionsUrl(): string
    {
        $base = rtrim(trim((string) config('openai_base_url', 'https://api.openai.com/v1')), '/');
        if ($base === '') {
            $base = 'https://api.openai.com/v1';
        }
        if (str_ends_with($base, '/chat/completions')) {
            return $base;
        }
        if (str_ends_with($base, '/v1')) {
            return $base . '/chat/completions';
        }
        return $base . '/v1/chat/completions';
    }

    private static function chatModel(): string
    {
        $model = trim((string) config('openai_model', 'claude-haiku-4.5'));
        return $model !== '' ? $model : 'claude-haiku-4.5';
    }

    /**
     * @param list<array<string, mixed>> $faqs
     * @return array<string, list<float>>
     */
    private static function loadVectorsForFaqs(array $faqs): array
    {
        $ids = [];
        foreach ($faqs as $faq) {
            $id = (string) ($faq['id'] ?? '');
            if ($id !== '' && !isset(self::$vectorCache[$id])) {
                $ids[] = (int) $id;
            }
        }
        if ($ids !== [] && self::$schemaReady) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            try {
                $stmt = Db::app()->prepare(
                    "SELECT faq_id, vector_json FROM faq_embeddings WHERE faq_id IN ($placeholders) AND model = ?"
                );
                $params = $ids;
                    $params[] = self::effectiveModel();
                $stmt->execute($params);
                foreach ($stmt->fetchAll() as $row) {
                    $vec = json_decode((string) ($row['vector_json'] ?? ''), true);
                    if (!is_array($vec) || $vec === []) {
                        continue;
                    }
                    $out = [];
                    foreach ($vec as $v) {
                        $out[] = (float) $v;
                    }
                    self::$vectorCache[(string) $row['faq_id']] = $out;
                }
            } catch (Throwable $e) {
                error_log('SCiGENIE FaqEmbedder load: ' . mb_substr($e->getMessage(), 0, 200));
            }
        }

        // Lazy-create missing embeddings for this pool (best-effort, capped).
        $missing = 0;
        foreach ($faqs as $faq) {
            $id = (string) ($faq['id'] ?? '');
            if ($id === '' || isset(self::$vectorCache[$id])) {
                continue;
            }
            if ($missing >= 8) {
                break;
            }
            if (self::upsertFaq($faq)) {
                $missing++;
            } elseif (self::$unavailable) {
                break;
            }
        }

        $out = [];
        foreach ($faqs as $faq) {
            $id = (string) ($faq['id'] ?? '');
            if ($id !== '' && isset(self::$vectorCache[$id])) {
                $out[$id] = self::$vectorCache[$id];
            }
        }
        return $out;
    }

    /**
     * @param list<float> $a
     * @param list<float> $b
     */
    public static function cosine(array $a, array $b): float
    {
        $n = min(count($a), count($b));
        if ($n < 1) {
            return 0.0;
        }
        $dot = 0.0;
        $na = 0.0;
        $nb = 0.0;
        for ($i = 0; $i < $n; $i++) {
            $x = (float) $a[$i];
            $y = (float) $b[$i];
            $dot += $x * $y;
            $na += $x * $x;
            $nb += $y * $y;
        }
        if ($na <= 0.0 || $nb <= 0.0) {
            return 0.0;
        }
        return $dot / (sqrt($na) * sqrt($nb));
    }

    private static function embeddingsUrl(): string
    {
        $base = rtrim(trim((string) config('openai_base_url', 'https://api.openai.com/v1')), '/');
        if ($base === '') {
            $base = 'https://api.openai.com/v1';
        }
        if (str_ends_with($base, '/embeddings')) {
            return $base;
        }
        if (str_ends_with($base, '/v1')) {
            return $base . '/embeddings';
        }
        return $base . '/v1/embeddings';
    }

    private static function httpPost(string $url, array $payload): ?string
    {
        $key = self::apiKey();
        if ($key === '' || $url === '') {
            return null;
        }
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($body)) {
            return null;
        }
        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $key,
        ];

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_POSTFIELDS => $body,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 2,
                CURLOPT_TIMEOUT => self::TIMEOUT_SEC,
            ]);
            $out = curl_exec($ch);
            $err = curl_error($ch);
            curl_close($ch);
            if (!is_string($out) || $out === '') {
                if ($err !== '') {
                    error_log('SCiGENIE embeddings curl: ' . mb_substr($err, 0, 200));
                }
                return null;
            }
            return $out;
        }

        $ctx = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", $headers) . "\r\n",
                'content' => $body,
                'timeout' => self::TIMEOUT_SEC,
                'ignore_errors' => true,
            ],
        ]);
        $out = @file_get_contents($url, false, $ctx);
        return is_string($out) ? $out : null;
    }

    private static function apiKey(): string
    {
        return trim((string) config('openai_api_key', ''));
    }

    private static function model(): string
    {
        $model = trim((string) config('openai_embedding_model', self::LOCAL_MODEL));
        return $model !== '' ? $model : self::LOCAL_MODEL;
    }

    public static function effectiveModel(): string
    {
        if (self::usesLocalModel() || self::$embeddingsBroken) {
            return self::LOCAL_MODEL;
        }
        return self::model();
    }

    private static function minSimilarity(): float
    {
        $configured = (float) config('semantic_min_sim', 0.72);
        if (self::effectiveModel() === self::LOCAL_MODEL) {
            // Local hash vectors are denser; use a slightly lower floor unless user set lower.
            return min($configured, 0.45);
        }
        return $configured;
    }
}
