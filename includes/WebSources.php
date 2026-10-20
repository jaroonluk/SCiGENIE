<?php

declare(strict_types=1);

class WebSources
{
    private const CACHE_TTL = 43200;
    private const FETCH_TIMEOUT = 4;
    private const DEADLINE_SEC = 22;
    private const MAX_FETCHES = 6;
    private const MAX_TEXT = 24000;

    public static function enrich(array $result, string $question): array
    {
        if (!empty($result['matched'])) {
            return $result;
        }
        $question = trim($question);
        if ($question === '' || self::skipQuestion($question)) {
            return $result;
        }
        try {
            $found = self::find($question);
        } catch (Throwable $e) {
            error_log('SCiGENIE web source: ' . mb_substr($e->getMessage(), 0, 200));
            return $result;
        }
        return $found ?? $result;
    }

    public static function ready(): bool
    {
        try {
            Db::app()->query('SELECT 1 FROM web_sources LIMIT 1');
            return true;
        } catch (Throwable $e) {
            if (self::isMissingTable($e)) {
                return false;
            }
            throw $e;
        }
    }

    public static function installSql(): string
    {
        $path = dirname(__DIR__) . '/sql/web-sources.sql';
        if (!is_file($path)) {
            return '';
        }
        return trim((string) file_get_contents($path));
    }

    public static function all(): array
    {
        try {
            $rows = Db::app()->query(
                'SELECT id, title, url, note, is_active, sort_order
                 FROM web_sources
                 ORDER BY sort_order, id'
            )->fetchAll();
        } catch (Throwable $e) {
            if (self::isMissingTable($e)) {
                return [];
            }
            throw $e;
        }
        $out = [];
        foreach ($rows as $row) {
            $out[] = self::publicRow($row);
        }
        return $out;
    }

    public static function save(array $input): array
    {
        self::requireTable();
        $id = (int) ($input['id'] ?? 0);
        $title = trim((string) ($input['title'] ?? ''));
        $url = self::cleanUrl((string) ($input['url'] ?? ''));
        $note = trim((string) ($input['note'] ?? ''));
        $active = !empty($input['is_active']) ? 1 : 0;
        $sort = (int) ($input['sort_order'] ?? 0);
        if ($title === '' || mb_strlen($title) > 191) {
            throw new InvalidArgumentException('กรุณาใส่ชื่อแหล่งข้อมูล ไม่เกิน 191 ตัวอักษร');
        }
        if ($url === '' || !self::isPublicHttpUrl($url)) {
            throw new InvalidArgumentException('ลิงก์ต้องขึ้นต้นด้วย http:// หรือ https:// และไม่ใช่ที่อยู่ในเครื่อง');
        }
        if (mb_strlen($url) > 500) {
            throw new InvalidArgumentException('ลิงก์ยาวเกิน 500 ตัวอักษร');
        }
        if (mb_strlen($note) > 255) {
            $note = mb_substr($note, 0, 255);
        }
        $pdo = Db::app();
        try {
            if ($id > 0) {
                $prev = $pdo->prepare('SELECT url FROM web_sources WHERE id = :id LIMIT 1');
                $prev->execute(['id' => $id]);
                $oldUrl = (string) ($prev->fetchColumn() ?: '');
                $stmt = $pdo->prepare(
                    'UPDATE web_sources
                     SET title = :title, url = :url, note = :note, is_active = :active, sort_order = :sort
                     WHERE id = :id'
                );
                $stmt->execute([
                    'title' => $title,
                    'url' => $url,
                    'note' => $note,
                    'active' => $active,
                    'sort' => $sort,
                    'id' => $id,
                ]);
                if ($stmt->rowCount() === 0 && $oldUrl === '') {
                    throw new InvalidArgumentException('ไม่พบรายการที่ต้องการแก้ไข');
                }
                if ($oldUrl !== '' && $oldUrl !== $url) {
                    self::forgetCache($oldUrl);
                }
                self::forgetCache($url);
            } else {
                $stmt = $pdo->prepare(
                    'INSERT INTO web_sources (title, url, note, is_active, sort_order)
                     VALUES (:title, :url, :note, :active, :sort)'
                );
                $stmt->execute([
                    'title' => $title,
                    'url' => $url,
                    'note' => $note,
                    'active' => $active,
                    'sort' => $sort,
                ]);
                $id = (int) $pdo->lastInsertId();
            }
        } catch (PDOException $e) {
            if (str_contains($e->getMessage(), 'uq_web_sources_url') || str_contains($e->getMessage(), 'Duplicate')) {
                throw new InvalidArgumentException('ลิงก์นี้มีอยู่ในรายการแล้ว');
            }
            throw $e;
        }
        $row = $pdo->prepare('SELECT id, title, url, note, is_active, sort_order FROM web_sources WHERE id = :id');
        $row->execute(['id' => $id]);
        $saved = $row->fetch();
        if (!is_array($saved)) {
            throw new RuntimeException('บันทึกแล้วแต่ยังอ่านรายการไม่พบ');
        }
        return self::publicRow($saved);
    }

    public static function delete(int $id): void
    {
        self::requireTable();
        if ($id <= 0) {
            throw new InvalidArgumentException('ไม่พบรายการที่ต้องการลบ');
        }
        $pdo = Db::app();
        $prev = $pdo->prepare('SELECT url FROM web_sources WHERE id = :id LIMIT 1');
        $prev->execute(['id' => $id]);
        $url = (string) ($prev->fetchColumn() ?: '');
        $stmt = $pdo->prepare('DELETE FROM web_sources WHERE id = :id');
        $stmt->execute(['id' => $id]);
        if ($url !== '') {
            self::forgetCache($url);
        }
    }

    public static function isPublicHttpUrl(string $url): bool
    {
        $url = self::cleanUrl($url);
        $parts = parse_url($url);
        if (!is_array($parts)) {
            return false;
        }
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));
        if (!in_array($scheme, ['http', 'https'], true) || $host === '') {
            return false;
        }
        if ($host === 'localhost' || str_ends_with($host, '.local') || str_ends_with($host, '.internal')) {
            return false;
        }
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return (bool) filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
        }
        return !str_contains($host, ':');
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function find(string $question): ?array
    {
        $sources = self::activeSources();
        if ($sources === []) {
            return null;
        }
        $needles = self::needles($question);
        if ($needles === []) {
            return null;
        }
        $deadline = microtime(true) + self::DEADLINE_SEC;
        $fetches = 0;
        $pages = [];
        foreach ($sources as $source) {
            if ($fetches >= self::MAX_FETCHES || microtime(true) >= $deadline) {
                break;
            }
            $doc = self::document((string) $source['url'], $deadline, $fetches);
            if ($doc === null) {
                continue;
            }
            $hit = self::bestHitLength((string) $doc['text'], $needles);
            $score = self::scoreText((string) $doc['text'], $needles);
            if ($hit >= 4 && $score >= 8) {
                $pages[] = self::pagePacket($source, $doc, $needles, $score, $hit);
            } else {
                $child = null;
                if (self::isCalendarSource($source, $doc) && self::wantsCalendar($question)) {
                    $child = self::discoverCalendarPdf((string) $source['url'], $question, $deadline, $fetches);
                }
                if ($child === null) {
                    $child = self::bestLink($doc['links'] ?? [], $needles, (string) $doc['url']);
                }
                if ($child !== null && $fetches < self::MAX_FETCHES && microtime(true) < $deadline) {
                    $childDoc = self::document($child, $deadline, $fetches);
                    if ($childDoc !== null) {
                        $childHit = self::bestHitLength((string) $childDoc['text'], $needles);
                        $childScore = self::scoreText((string) $childDoc['text'], $needles);
                        if ($childHit >= 4 && $childScore >= 8) {
                            $pages[] = self::pagePacket($source, $childDoc, $needles, $childScore, $childHit);
                        }
                    }
                }
            }
            $bestHit = 0;
            foreach ($pages as $page) {
                $bestHit = max($bestHit, (int) $page['hit']);
            }
            if ($bestHit >= 10) {
                break;
            }
        }
        if ($pages === []) {
            return null;
        }
        usort($pages, static fn ($a, $b) => ($b['score'] <=> $a['score']) ?: ($b['hit'] <=> $a['hit']));
        $pages = array_slice($pages, 0, 3);
        $direct = self::calendarReply($question, (string) $pages[0]['excerpt']);
        if ($direct !== null) {
            return self::result($question, $direct, (string) $pages[0]['title'], (string) $pages[0]['url']);
        }
        $fromModel = self::answerWithModel($question, $pages, $deadline);
        if ($fromModel !== null) {
            return $fromModel;
        }
        $best = $pages[0];
        if ((int) $best['hit'] < 6 || (int) $best['score'] < 12) {
            return null;
        }
        return self::result($question, (string) $best['excerpt'], (string) $best['title'], (string) $best['url']);
    }

    private static function answerWithModel(string $question, array $pages, float $deadline): ?array
    {
        $left = (int) floor($deadline - microtime(true));
        if ($left < 2 || !OpenAiFaqVerifier::configured()) {
            return null;
        }
        $packet = [];
        $allowed = [];
        foreach ($pages as $page) {
            $url = (string) $page['url'];
            $allowed[$url] = (string) $page['title'];
            $packet[] = [
                'title' => (string) $page['title'],
                'url' => $url,
                'text' => (string) $page['excerpt'],
            ];
        }
        $raw = OpenAiFaqVerifier::complete(
            "คุณคือ SCiGENIE ตอบคำถามจากข้อความเว็บที่ให้มาเท่านั้น\nห้ามใช้ความรู้ภายนอก และห้ามแต่งลิงก์ใหม่\nคำว่าปิดเทอมให้ตอบจากวันปิดภาคการศึกษา และคำว่าเปิดเทอมให้ตอบจากวันเปิดภาคการศึกษา ถ้ามีหลายภาคให้บอกทุกภาคพร้อมวันที่ที่อยู่ในข้อความ\nถ้าข้อความไม่มีคำตอบตรงคำถาม ให้ found เป็น false\nตอบเป็น JSON เท่านั้น",
            "คำถาม:\n" . $question . "\n\nหน้าเว็บ:\n"
            . json_encode($packet, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            . "\n\nรูปแบบ:\n{\"found\":true,\"answer\":\"คำตอบภาษาไทย\",\"url\":\"url จากรายการเท่านั้น\"}",
            420,
            min(6, $left)
        );
        if ($raw === null) {
            return null;
        }
        $raw = trim(preg_replace('/^```(?:json)?\s*|\s*```$/u', '', $raw) ?? $raw);
        if (preg_match('/\{.*\}/s', $raw, $m) === 1) {
            $raw = $m[0];
        }
        $data = json_decode($raw, true);
        if (!is_array($data) || empty($data['found'])) {
            return null;
        }
        $answer = trim((string) ($data['answer'] ?? ''));
        $url = trim((string) ($data['url'] ?? ''));
        if ($answer === '' || mb_strlen($answer) < 15 || !isset($allowed[$url])) {
            return null;
        }
        if (preg_match('/ไม่พบ|ไม่มีข้อมูลใน/u', $answer) === 1 && mb_strlen($answer) < 90) {
            return null;
        }
        return self::result($question, $answer, $allowed[$url], $url);
    }

    private static function result(string $question, string $answer, string $title, string $url): array
    {
        $answer = trim($answer);
        if (!str_contains($answer, $url)) {
            $label = $title !== '' ? $title : $url;
            $answer .= "\n\nแหล่งข้อมูล: {$label}\n{$url}";
        }
        return [
            'matched' => true,
            'web_source' => true,
            'confidence' => 'medium',
            'message' => '',
            'item' => [
                'id' => 'web',
                'category' => '',
                'question' => $question,
                'answer' => $answer,
                'source' => 'web',
                'source_url' => $url,
                'source_title' => $title,
            ],
            'related' => [],
            'suggestions' => [],
            'sources' => [[
                'title' => $title,
                'url' => $url,
            ]],
        ];
    }

    private static function pagePacket(array $source, array $doc, array $needles, int $score, int $hit): array
    {
        $title = trim((string) ($doc['title'] ?? ''));
        if ($title === '') {
            $title = (string) ($source['title'] ?? '');
        }
        $needle = '';
        $hay = mb_strtolower((string) $doc['text']);
        foreach ($needles as $item) {
            if (mb_strlen($item) > mb_strlen($needle) && mb_stripos($hay, mb_strtolower($item)) !== false) {
                $needle = $item;
            }
        }
        return [
            'title' => $title,
            'url' => (string) ($doc['url'] ?? $source['url']),
            'excerpt' => self::excerpt((string) $doc['text'], $needle),
            'score' => $score,
            'hit' => $hit,
        ];
    }

    private static function excerpt(string $text, string $needle): string
    {
        $text = trim($text);
        if ($needle !== '') {
            $pos = mb_stripos($text, $needle);
            if ($pos !== false) {
                $start = max(0, $pos - 220);
                $text = mb_substr($text, $start, 900);
            }
        }
        if (mb_strlen($text) > 900) {
            $text = mb_substr($text, 0, 900);
        }
        return trim($text);
    }

    private static function activeSources(): array
    {
        try {
            $rows = Db::app()->query(
                'SELECT id, title, url, note
                 FROM web_sources
                 WHERE is_active = 1
                 ORDER BY sort_order, id
                 LIMIT 20'
            )->fetchAll();
        } catch (Throwable $e) {
            if (self::isMissingTable($e)) {
                return [];
            }
            throw $e;
        }
        $out = [];
        foreach ($rows as $row) {
            $url = self::cleanUrl((string) ($row['url'] ?? ''));
            if ($url === '' || !self::isPublicHttpUrl($url)) {
                continue;
            }
            $out[] = [
                'title' => trim((string) ($row['title'] ?? '')),
                'url' => $url,
                'note' => trim((string) ($row['note'] ?? '')),
            ];
        }
        return $out;
    }

    private static function document(string $url, float $deadline, int &$fetches): ?array
    {
        $cached = self::readCache($url);
        if ($cached !== null) {
            return mb_strlen(trim((string) ($cached['text'] ?? ''))) >= 80 ? $cached : null;
        }
        if ($fetches >= self::MAX_FETCHES || microtime(true) >= $deadline) {
            return null;
        }
        $fetches++;
        $timeout = self::timeoutFor($url, $deadline);
        $loaded = self::fetch($url, $timeout);
        if ($loaded === null) {
            return null;
        }
        if (isset($loaded['pdf'])) {
            $text = trim(PdfText::extract($loaded['pdf']));
            if (mb_strlen($text) < 80) {
                return null;
            }
            $doc = [
                'url' => $loaded['final_url'],
                'title' => self::pdfTitle($loaded['final_url']),
                'text' => mb_strlen($text) > self::MAX_TEXT ? mb_substr($text, 0, self::MAX_TEXT) : $text,
                'links' => [],
            ];
            self::writeCache($url, $doc);
            return $doc;
        }
        if (!isset($loaded['html'])) {
            return null;
        }
        $doc = self::parseHtml($loaded['final_url'], $loaded['html']);
        if (trim((string) $doc['text']) === '') {
            $doc['text'] = '-';
        }
        self::writeCache($url, $doc);
        if (mb_strlen(trim((string) $doc['text'])) < 80) {
            return null;
        }
        return $doc;
    }

    private static function fetch(string $url, int $timeout): ?array
    {
        $fetchUrl = preg_replace('/#.*$/', '', $url) ?? $url;
        if (!self::isPublicHttpUrl($fetchUrl)) {
            return null;
        }
        $headers = [
            'Accept: text/html,application/xhtml+xml,application/pdf,application/json;q=0.9',
            'Accept-Language: th,en;q=0.8',
        ];
        $body = null;
        $contentType = '';
        $final = $fetchUrl;
        if (function_exists('curl_init')) {
            $ch = curl_init($fetchUrl);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 3,
                CURLOPT_CONNECTTIMEOUT => 2,
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_USERAGENT => 'SCiGENIE/1.0',
                CURLOPT_HTTPHEADER => $headers,
            ]);
            $out = curl_exec($ch);
            $contentType = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
            $effective = (string) curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
            curl_close($ch);
            if (is_string($out) && $out !== '') {
                $body = $out;
                if ($effective !== '') {
                    $final = $effective;
                }
            }
        } else {
            $ctx = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'header' => implode("\r\n", $headers) . "\r\nUser-Agent: SCiGENIE/1.0\r\n",
                    'timeout' => $timeout,
                    'ignore_errors' => true,
                ],
            ]);
            $out = @file_get_contents($fetchUrl, false, $ctx);
            if (is_string($out) && $out !== '') {
                $body = $out;
            }
        }
        if ($body === null || !self::isPublicHttpUrl($final)) {
            return null;
        }
        $kind = self::bodyKind($fetchUrl, $final, $contentType, $body);
        if ($kind === '') {
            return null;
        }
        if ($kind === 'pdf') {
            if (strlen($body) > 4000000) {
                return null;
            }
            return ['final_url' => $final, 'pdf' => $body];
        }
        if (strlen($body) > 500000) {
            $body = substr($body, 0, 500000);
        }
        if ($kind === 'json') {
            return ['final_url' => $final, 'json' => $body];
        }
        $body = self::toUtf8($body, $contentType);
        return ['final_url' => $final, 'html' => $body];
    }

    private static function timeoutFor(string $url, float $deadline): int
    {
        $path = strtolower((string) (parse_url($url, PHP_URL_PATH) ?: ''));
        $cap = (str_ends_with($path, '.pdf') || str_contains($url, '/wp-json/')) ? 12 : self::FETCH_TIMEOUT;
        return max(2, min($cap, (int) ceil($deadline - microtime(true))));
    }

    private static function bodyKind(string $requested, string $final, string $contentType, string $body): string
    {
        $type = strtolower($contentType);
        $path = strtolower((string) (parse_url($final, PHP_URL_PATH) ?: parse_url($requested, PHP_URL_PATH) ?: ''));
        if (str_contains($type, 'pdf') || str_ends_with($path, '.pdf') || str_starts_with($body, '%PDF')) {
            return 'pdf';
        }
        if (str_contains($type, 'json') || str_contains($requested, '/wp-json/')) {
            return 'json';
        }
        if ($type === '' || str_contains($type, 'html') || str_contains($type, 'text/plain') || str_contains($type, 'xml')) {
            return 'html';
        }
        return '';
    }

    private static function pdfTitle(string $url): string
    {
        $name = strtolower((string) (parse_url($url, PHP_URL_PATH) ?: ''));
        if (str_contains($name, 'undergraduate')) {
            return 'ปฏิทินการศึกษา ระดับปริญญาตรี';
        }
        if (str_contains($name, 'graduate')) {
            return 'ปฏิทินการศึกษา ระดับบัณฑิตศึกษา';
        }
        return 'ปฏิทินการศึกษา';
    }

    private static function isCalendarSource(array $source, array $doc): bool
    {
        $blob = mb_strtolower((string) ($source['url'] ?? '') . ' ' . (string) ($source['title'] ?? '') . ' ' . (string) ($doc['title'] ?? ''));
        return str_contains($blob, 'academiccalendar') || str_contains($blob, 'ปฏิทิน');
    }

    private static function calendarReply(string $question, string $text): ?string
    {
        $close = str_contains($question, 'ปิดเทอม') || str_contains($question, 'ปิดภาค');
        $open = str_contains($question, 'เปิดเทอม') || str_contains($question, 'เปิดภาค');
        if (!$close && !$open) {
            return null;
        }
        $labels = [];
        if ($close) {
            $labels[] = 'วันปิดภาคการศึกษา';
        }
        if ($open) {
            $labels[] = 'วันเปิดภาคการศึกษา';
        }
        $blocks = [];
        foreach ($labels as $label) {
            $pos = mb_strpos($text, $label);
            if ($pos === false) {
                continue;
            }
            $slice = mb_substr($text, $pos, 240);
            if (!preg_match_all('/วัน[ก-๙.]+ที่\s*\d{1,2}\s*[ก-๙.]+\s*\d{4}/u', $slice, $found) || $found[0] === []) {
                continue;
            }
            $terms = ['ภาคต้น', 'ภาคปลาย', 'ภาคพิเศษ'];
            $lines = [];
            foreach (array_slice($found[0], 0, 3) as $index => $date) {
                $term = $terms[$index] ?? ('ช่วงที่ ' . ($index + 1));
                $lines[] = $term . ': ' . trim(preg_replace('/\s+/u', ' ', $date) ?? $date);
            }
            $name = $label === 'วันปิดภาคการศึกษา' ? 'วันปิดภาคการศึกษา (ปิดเทอม)' : 'วันเปิดภาคการศึกษา (เปิดเทอม)';
            $blocks[] = $name . " ระดับปริญญาตรี\n" . implode("\n", $lines);
        }
        if ($blocks === []) {
            return null;
        }
        return implode("\n\n", $blocks);
    }

    private static function wantsCalendar(string $question): bool
    {
        return preg_match('/ปิดเทอม|เปิดเทอม|ปฏิทิน|ภาคการศึกษา|วันหยุด|สอบปลาย|สอบกลาง|ลงทะเบียน|ปิดภาค|เปิดภาค/u', $question) === 1;
    }

    private static function discoverCalendarPdf(string $pageUrl, string $question, float $deadline, int &$fetches): ?string
    {
        $parts = parse_url($pageUrl);
        if (!is_array($parts) || empty($parts['host'])) {
            return null;
        }
        $api = ($parts['scheme'] ?? 'https') . '://' . $parts['host'] . '/wp-json/wp/v2/media?search=calendar&per_page=10';
        if (!self::isPublicHttpUrl($api) || $fetches >= self::MAX_FETCHES || microtime(true) >= $deadline) {
            return null;
        }
        $fetches++;
        $loaded = self::fetch($api, self::timeoutFor($api, $deadline));
        if ($loaded === null || !isset($loaded['json'])) {
            return null;
        }
        $items = json_decode($loaded['json'], true);
        if (!is_array($items)) {
            return null;
        }
        return self::pickCalendarPdf($items, $question);
    }

    private static function pickCalendarPdf(array $items, string $question): ?string
    {
        $graduate = preg_match('/บัณฑิต|ปริญญาโท|ปริญญาเอก|graduate/ui', $question) === 1;
        $bestUrl = null;
        $best = 0;
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $url = self::cleanUrl((string) ($item['source_url'] ?? ''));
            $path = strtolower((string) (parse_url($url, PHP_URL_PATH) ?: ''));
            if ($url === '' || !str_ends_with($path, '.pdf') || !self::isPublicHttpUrl($url) || str_contains($path, 'announcement')) {
                continue;
            }
            $undergraduate = str_contains($path, 'undergraduate') || str_contains($path, 'undergrad');
            $score = str_contains($path, 'calendar') ? 3 : 0;
            if ($undergraduate) {
                $score += $graduate ? 1 : 8;
            } elseif (str_contains($path, 'graduate')) {
                $score += $graduate ? 8 : 2;
            }
            if (str_contains($path, 'updated')) {
                $score++;
            }
            if ($score > $best) {
                $best = $score;
                $bestUrl = $url;
            }
        }
        return $best >= 4 ? $bestUrl : null;
    }

    private static function parseHtml(string $finalUrl, string $html): array
    {
        $title = '';
        if (preg_match('/<title[^>]*>(.*?)<\/title>/isu', $html, $m) === 1) {
            $title = self::plain($m[1]);
        }
        $links = [];
        if (preg_match_all('/<a\s[^>]*href\s*=\s*([\'"])(.*?)\1[^>]*>(.*?)<\/a>/isu', $html, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $href = self::resolveUrl($finalUrl, html_entity_decode($match[2], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                $text = self::plain($match[3]);
                if ($href === null || $text === '') {
                    continue;
                }
                $links[] = ['url' => $href, 'text' => mb_substr($text, 0, 180)];
                if (count($links) >= 40) {
                    break;
                }
            }
        }
        $stripped = preg_replace('/<script\b[^>]*>.*?<\/script>/isu', ' ', $html) ?? $html;
        $stripped = preg_replace('/<style\b[^>]*>.*?<\/style>/isu', ' ', $stripped) ?? $stripped;
        $text = self::plain($stripped);
        if (mb_strlen($text) > self::MAX_TEXT) {
            $text = mb_substr($text, 0, self::MAX_TEXT);
        }
        return [
            'url' => $finalUrl,
            'title' => mb_substr($title, 0, 180),
            'text' => $text,
            'links' => $links,
        ];
    }

    private static function bestLink(array $links, array $needles, string $pageUrl): ?string
    {
        $host = strtolower((string) (parse_url($pageUrl, PHP_URL_HOST) ?: ''));
        $bestUrl = null;
        $best = 0;
        foreach ($links as $link) {
            if (!is_array($link)) {
                continue;
            }
            $url = (string) ($link['url'] ?? '');
            $linkHost = strtolower((string) (parse_url($url, PHP_URL_HOST) ?: ''));
            if ($url === '' || $linkHost === '' || $linkHost !== $host || self::skipAsset($url)) {
                continue;
            }
            $score = self::scoreText((string) ($link['text'] ?? '') . ' ' . $url, $needles);
            if ($score > $best) {
                $best = $score;
                $bestUrl = $url;
            }
        }
        return $best >= 6 ? $bestUrl : null;
    }

    private static function needles(string $question): array
    {
        $q = mb_strtolower(trim($question));
        $q = str_replace(
            ['สวัสดีค่ะ', 'สวัสดีครับ', 'ครับ', 'ค่ะ', 'คะ', 'นะคะ', 'นะครับ', 'ไหมคะ', 'ไหมครับ', 'ได้ไหม', 'หรือไม่', 'อย่างไร'],
            ' ',
            $q
        );
        $q = trim(preg_replace('/[^\p{L}\p{M}\p{N}\s]+/u', ' ', $q) ?? $q);
        $q = trim(preg_replace('/\s+/u', ' ', $q) ?? $q);
        $out = [];
        $compact = preg_replace('/\s+/u', '', $q) ?? $q;
        if (mb_strlen($compact) >= 4) {
            $out[] = $compact;
        }
        if (str_contains($compact, 'ปิดเทอม')) {
            $out[] = 'วันปิดภาคการศึกษา';
            $out[] = 'ปิดภาคการศึกษา';
        }
        if (str_contains($compact, 'เปิดเทอม')) {
            $out[] = 'วันเปิดภาคการศึกษา';
            $out[] = 'เปิดภาคการศึกษา';
        }
        foreach (preg_split('/\s+/u', $q) ?: [] as $part) {
            if (mb_strlen($part) >= 2) {
                $out[] = $part;
            }
        }
        return array_values(array_unique($out));
    }

    private static function scoreText(string $hay, array $needles): int
    {
        $hay = mb_strtolower($hay);
        $score = 0;
        foreach ($needles as $needle) {
            if (mb_strlen($needle) < 2) {
                continue;
            }
            $count = mb_substr_count($hay, mb_strtolower($needle));
            if ($count > 0) {
                $score += min(3, $count) * mb_strlen($needle);
            }
        }
        return $score;
    }

    private static function bestHitLength(string $hay, array $needles): int
    {
        $hay = mb_strtolower($hay);
        $best = 0;
        foreach ($needles as $needle) {
            $len = mb_strlen($needle);
            if ($len > $best && mb_stripos($hay, mb_strtolower($needle)) !== false) {
                $best = $len;
            }
        }
        return $best;
    }

    private static function skipQuestion(string $question): bool
    {
        $n = mb_strtolower(preg_replace('/\s+/u', '', $question) ?? $question);
        if (mb_strlen($n) < 4) {
            return true;
        }
        foreach (['สวัสดี', 'ขอบคุณ', 'เมนู', 'hello', 'menu'] as $word) {
            if ($n === $word) {
                return true;
            }
        }
        return false;
    }

    private static function skipAsset(string $url): bool
    {
        $path = strtolower((string) (parse_url($url, PHP_URL_PATH) ?: ''));
        return (bool) preg_match('/\.(jpg|jpeg|png|gif|webp|svg|css|js|zip|rar|docx?|xlsx?|pptx?|mp4|mp3)$/', $path);
    }

    private static function resolveUrl(string $base, string $href): ?string
    {
        $href = trim($href);
        if ($href === '' || str_starts_with($href, '#') || str_starts_with(strtolower($href), 'javascript:') || str_starts_with(strtolower($href), 'mailto:') || str_starts_with(strtolower($href), 'tel:')) {
            return null;
        }
        if (str_starts_with($href, '//')) {
            $scheme = parse_url($base, PHP_URL_SCHEME) ?: 'https';
            $href = $scheme . ':' . $href;
        } elseif (!preg_match('#^https?://#i', $href)) {
            $parts = parse_url($base);
            if (!is_array($parts) || empty($parts['host'])) {
                return null;
            }
            $scheme = $parts['scheme'] ?? 'https';
            $origin = $scheme . '://' . $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '');
            if (str_starts_with($href, '/')) {
                $href = $origin . $href;
            } else {
                $path = (string) ($parts['path'] ?? '/');
                $dir = str_contains($path, '/') ? substr($path, 0, (int) strrpos($path, '/') + 1) : '/';
                $href = $origin . $dir . $href;
            }
        }
        $href = preg_replace('/#.*$/', '', $href) ?? $href;
        return self::isPublicHttpUrl($href) ? $href : null;
    }

    private static function plain(string $html): string
    {
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
        return trim($text);
    }

    private static function toUtf8(string $body, string $contentType): string
    {
        $charset = '';
        if (preg_match('/charset=([^;\s]+)/i', $contentType, $m) === 1) {
            $charset = strtolower(trim($m[1], "\"'"));
        } elseif (preg_match('/charset=([^"\']+)/i', substr($body, 0, 2000), $m) === 1) {
            $charset = strtolower(trim($m[1]));
        }
        $aliases = [
            'windows-874' => 'TIS-620',
            'windows874' => 'TIS-620',
            'cp874' => 'TIS-620',
            'tis-620' => 'TIS-620',
            'tis620' => 'TIS-620',
        ];
        if (isset($aliases[$charset])) {
            $charset = $aliases[$charset];
        }
        if ($charset !== '' && !in_array($charset, ['utf-8', 'utf8'], true)) {
            try {
                $converted = mb_convert_encoding($body, 'UTF-8', $charset);
            } catch (Throwable $e) {
                $converted = false;
            }
            if (is_string($converted) && $converted !== '') {
                return $converted;
            }
        }
        return $body;
    }

    private static function cleanUrl(string $url): string
    {
        return trim(html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    private static function cacheFile(string $url): string
    {
        return dirname(__DIR__) . '/data/web-cache/' . hash('sha256', $url) . '.json';
    }

    private static function readCache(string $url): ?array
    {
        $file = self::cacheFile($url);
        if (!is_file($file) || (time() - (int) @filemtime($file)) > self::CACHE_TTL) {
            return null;
        }
        $data = json_decode((string) file_get_contents($file), true);
        if (!is_array($data) || trim((string) ($data['text'] ?? '')) === '') {
            return null;
        }
        return $data;
    }

    private static function writeCache(string $url, array $doc): void
    {
        $file = self::cacheFile($url);
        $dir = dirname($file);
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        if (!is_dir($dir) || !is_writable($dir)) {
            return;
        }
        $doc['requested'] = $url;
        $doc['fetched_at'] = time();
        @file_put_contents($file, json_encode($doc, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
    }

    private static function forgetCache(string $url): void
    {
        $file = self::cacheFile($url);
        if (is_file($file)) {
            @unlink($file);
        }
    }

    private static function requireTable(): void
    {
        if (!self::ready()) {
            throw new RuntimeException('ยังไม่มีตาราง web_sources กรุณารันคำสั่ง SQL ในไฟล์ sql/web-sources.sql บนฐานข้อมูลเดิมก่อน');
        }
    }

    private static function isMissingTable(Throwable $e): bool
    {
        $msg = $e->getMessage();
        return str_contains($msg, 'web_sources') && (
            str_contains($msg, '1146')
            || str_contains($msg, '42S02')
            || str_contains($msg, "doesn't exist")
            || str_contains($msg, 'does not exist')
        );
    }

    private static function publicRow(array $row): array
    {
        return [
            'id' => (int) ($row['id'] ?? 0),
            'title' => (string) ($row['title'] ?? ''),
            'url' => (string) ($row['url'] ?? ''),
            'note' => (string) ($row['note'] ?? ''),
            'is_active' => (int) ($row['is_active'] ?? 0) === 1,
            'sort_order' => (int) ($row['sort_order'] ?? 0),
        ];
    }
}
