<?php

declare(strict_types=1);

class OpenAiFaqVerifier
{
    private const TIMEOUT_SEC = 5;
    private const MAX_CANDIDATES = 5;
    private const MAX_ANSWER_CHARS = 1500;
    private static bool $unavailable = false;

    public static function configured(): bool
    {
        return self::apiKey() !== '' && !self::$unavailable;
    }

    /**
     * @param list<array<string, mixed>> $candidates
     * @return array{chosen_id: ?string, confidence: string, reason: string}|null
     */
    public static function verify(string $question, string $category, array $candidates): ?array
    {
        if (!self::configured()) {
            return null;
        }

        $payload = self::candidatePayload($candidates);
        if ($payload === []) {
            return [
                'chosen_id' => null,
                'confidence' => 'low',
                'reason' => 'ไม่มีรายการ FAQ ให้ตรวจสอบ',
            ];
        }

        $user = "คำถามผู้ใช้:\n"
            . trim($question) . "\n\n"
            . "หมวดที่ผู้ใช้อยู่:\n"
            . ($category !== '' ? $category : '(ไม่ระบุ)') . "\n\n"
            . "รายการ FAQ ที่ระบบหาได้:\n"
            . json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n\n"
            . "งานของคุณ\n"
            . "- เลือกได้เพียง 1 ข้อ และเฉพาะเมื่อ FAQ นั้นตอบคำถามนี้ได้จริง\n"
            . "- ถ้าไม่มีข้อใดตรงพอ ให้ chosen_id เป็น null\n\n"
            . "เกณฑ์ผ่าน\n"
            . "- เจตนาตรงกัน ไม่ใช่แค่มีคำร่วมกัน\n"
            . "- คำตอบมีข้อมูลที่ผู้ใช้ถาม ไม่ใช่เรื่องใกล้เคียง\n"
            . "- ถ้าผู้ใช้ถามตัวเลข วัน เงื่อนไข หรือช่องทางติดต่อ คำตอบต้องมีข้อมูลนั้นชัดเจน\n"
            . "- ถ้าผู้ใช้อยู่ในหมวดแล้วพิมพ์เฉพาะชื่อโครงการ/หัวข้อ (เช่น พสวท, สควค, Portfolio) ให้เลือก FAQ ที่เกี่ยวกับหัวข้อนั้นมากที่สุด\n"
            . "- อย่าปฏิเสธเพียงเพราะไม่มีเครื่องหมายคำถาม หรือเป็นคำสั้น ๆ ในหมวดที่เลือกแล้ว\n\n"
            . "รูปแบบ JSON:\n"
            . "{\n"
            . "  \"chosen_id\": \"id หรือ null\",\n"
            . "  \"confidence\": \"high | medium | low\",\n"
            . "  \"reason\": \"เหตุผลสั้น ๆ ภาษาไทย\"\n"
            . '}';

        $raw = self::chat($user);
        if ($raw === null) {
            return null;
        }

        return self::parse($raw, $payload);
    }

    /**
     * @param list<array<string, mixed>> $candidates
     */
    public static function accept(?array $verdict, array $candidates): bool
    {
        if ($verdict === null) {
            return false;
        }
        $id = $verdict['chosen_id'] ?? null;
        $confidence = strtolower(trim((string) ($verdict['confidence'] ?? '')));
        if ($id === null || $id === '' || $confidence === 'low') {
            return false;
        }
        foreach ($candidates as $row) {
            if ((string) ($row['id'] ?? '') === (string) $id) {
                return true;
            }
        }
        return false;
    }

    public static function isParseFailure(?array $verdict): bool
    {
        if ($verdict === null) {
            return false;
        }
        return str_contains((string) ($verdict['reason'] ?? ''), 'รูปแบบคำตอบจากโมเดลไม่ถูกต้อง');
    }

    public static function explicitReject(?array $verdict): bool
    {
        if ($verdict === null || self::isParseFailure($verdict)) {
            return false;
        }
        $id = $verdict['chosen_id'] ?? null;
        $confidence = strtolower(trim((string) ($verdict['confidence'] ?? '')));
        return ($id === null || $id === '') && $confidence === 'low';
    }

    /**
     * @param list<array<string, mixed>> $candidates
     * @return list<array{id: string, question: string, answer: string}>
     */
    private static function candidatePayload(array $candidates): array
    {
        $out = [];
        foreach (array_slice($candidates, 0, self::MAX_CANDIDATES) as $row) {
            $id = trim((string) ($row['id'] ?? ''));
            if ($id === '') {
                continue;
            }
            $answer = html_entity_decode(strip_tags((string) ($row['answer'] ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $answer = trim(preg_replace('/\s+/u', ' ', $answer) ?? $answer);
            if (mb_strlen($answer) > self::MAX_ANSWER_CHARS) {
                $answer = mb_substr($answer, 0, self::MAX_ANSWER_CHARS - 1) . '…';
            }
            $out[] = [
                'id' => $id,
                'question' => trim((string) ($row['question'] ?? '')),
                'answer' => $answer,
            ];
        }
        return $out;
    }

    private static function chat(string $user): ?string
    {
        $payload = [
            'model' => self::model(),
            'temperature' => 0,
            'max_tokens' => 220,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => "คุณเป็นผู้ตรวจสอบคำตอบ FAQ ของ SCiGENIE\nอย่าเขียนคำตอบใหม่จากความรู้ของตัวเอง\nตอบเป็น JSON เท่านั้น",
                ],
                [
                    'role' => 'user',
                    'content' => $user,
                ],
            ],
        ];
        if (self::useJsonObjectMode()) {
            $payload['response_format'] = ['type' => 'json_object'];
        }

        $raw = self::httpPost($payload);
        if ($raw === null || $raw === '') {
            return null;
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return null;
        }
        if (isset($decoded['error'])) {
            $msg = (string) ($decoded['error']['message'] ?? $raw);
            $lower = strtolower($msg);
            if (isset($payload['response_format']) && (str_contains($lower, 'response_format') || str_contains($lower, 'json_object'))) {
                unset($payload['response_format']);
                $raw = self::httpPost($payload);
                if ($raw === null || $raw === '') {
                    return null;
                }
                $decoded = json_decode($raw, true);
                if (!is_array($decoded) || isset($decoded['error'])) {
                    error_log('SCiGENIE OpenAI: ' . mb_substr((string) ($decoded['error']['message'] ?? $raw), 0, 300));
                    return null;
                }
            } else {
                error_log('SCiGENIE OpenAI: ' . mb_substr($msg, 0, 300));
                if (str_contains($lower, 'api key') || str_contains($msg, '401')) {
                    self::$unavailable = true;
                }
                return null;
            }
        }
        $text = trim((string) ($decoded['choices'][0]['message']['content'] ?? ''));
        return $text !== '' ? $text : null;
    }

    /**
     * @param list<array{id: string, question: string, answer: string}> $payload
     * @return array{chosen_id: ?string, confidence: string, reason: string}
     */
    private static function parse(string $text, array $payload): array
    {
        $text = trim($text);
        $text = preg_replace('/^```(?:json)?\s*|\s*```$/u', '', $text) ?? $text;
        if (preg_match('/\{.*\}/s', $text, $m) === 1) {
            $text = $m[0];
        }
        $data = json_decode($text, true);
        if (!is_array($data)) {
            return [
                'chosen_id' => null,
                'confidence' => 'low',
                'reason' => 'รูปแบบคำตอบจากโมเดลไม่ถูกต้อง',
            ];
        }

        $chosen = $data['chosen_id'] ?? null;
        if ($chosen === null || $chosen === '' || $chosen === 'null' || $chosen === false) {
            $chosen = null;
        } else {
            $chosen = (string) $chosen;
            $ok = false;
            foreach ($payload as $row) {
                if ($row['id'] === $chosen) {
                    $ok = true;
                    break;
                }
            }
            if (!$ok) {
                $chosen = null;
            }
        }

        $confidence = strtolower(trim((string) ($data['confidence'] ?? 'low')));
        if (!in_array($confidence, ['high', 'medium', 'low'], true)) {
            $confidence = 'low';
        }

        return [
            'chosen_id' => $chosen,
            'confidence' => $confidence,
            'reason' => mb_substr(trim((string) ($data['reason'] ?? '')), 0, 200),
        ];
    }

    private static function httpPost(array $payload): ?string
    {
        $key = self::apiKey();
        $url = self::chatCompletionsUrl();
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
                    error_log('SCiGENIE OpenAI curl: ' . mb_substr($err, 0, 200));
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

    private static function useJsonObjectMode(): bool
    {
        $base = strtolower(self::chatCompletionsUrl());
        return str_contains($base, 'api.openai.com');
    }

    private static function apiKey(): string
    {
        return trim((string) config('openai_api_key', ''));
    }

    private static function model(): string
    {
        $model = trim((string) config('openai_model', 'claude-haiku-4.5'));
        return $model !== '' ? $model : 'claude-haiku-4.5';
    }
}
