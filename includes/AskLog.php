<?php

declare(strict_types=1);

class AskLog
{
    private string $file;

    public function __construct()
    {
        $dir = dirname(__DIR__) . '/data';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $this->file = $dir . '/ask-log.jsonl';
    }

    public function record(array $entry): void
    {
        $row = [
            'id' => uniqid('ask-', true),
            'time' => date('c'),
            'visitor' => $this->visitorLabel($entry['visitor'] ?? ''),
            'category' => trim((string) ($entry['category'] ?? '')),
            'question' => trim((string) ($entry['question'] ?? '')),
            'matched' => !empty($entry['matched']),
            'faq' => trim((string) ($entry['faq'] ?? '')),
            'answer' => mb_substr(trim((string) ($entry['answer'] ?? '')), 0, 600),
            'sample' => !empty($entry['sample']),
        ];
        if ($row['question'] === '' || !$this->canWrite()) {
            return;
        }
        $line = json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
        $fp = @fopen($this->file, 'ab');
        if ($fp === false) {
            return;
        }
        flock($fp, LOCK_EX);
        fwrite($fp, $line);
        flock($fp, LOCK_UN);
        fclose($fp);
    }

    private function canWrite(): bool
    {
        if (is_file($this->file)) {
            return is_writable($this->file);
        }
        $dir = dirname($this->file);
        return is_dir($dir) && is_writable($dir);
    }

    public function all(int $limit = 2000, bool $realOnly = true): array
    {
        if (!is_file($this->file)) {
            return [];
        }
        $lines = file($this->file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        $rows = [];
        foreach ($lines as $line) {
            $row = json_decode($line, true);
            if (!is_array($row) || empty($row['question'])) {
                continue;
            }
            if ($realOnly && !empty($row['sample'])) {
                continue;
            }
            $rows[] = $row;
        }
        if (count($rows) > $limit) {
            $rows = array_slice($rows, -$limit);
        }
        return array_reverse($rows);
    }

    public function summarize(array $rows, int $days = 14): array
    {
        $since = strtotime('-' . max(1, $days) . ' days midnight');
        $filtered = [];
        foreach ($rows as $row) {
            $ts = strtotime((string) ($row['time'] ?? '')) ?: 0;
            if ($ts >= $since) {
                $filtered[] = $row;
            }
        }

        $byDay = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $key = date('Y-m-d', strtotime("-{$i} days"));
            $byDay[$key] = 0;
        }
        $byHour = array_fill(0, 24, 0);
        $faqCounts = [];
        $visitors = [];
        $matched = 0;
        $unmatched = 0;

        foreach ($filtered as $row) {
            $ts = strtotime((string) $row['time']) ?: time();
            $day = date('Y-m-d', $ts);
            if (isset($byDay[$day])) {
                $byDay[$day]++;
            }
            $byHour[(int) date('G', $ts)]++;
            $topic = trim((string) (($row['faq'] ?: $row['question']) ?? ''));
            if ($topic !== '') {
                $faqCounts[$topic] = ($faqCounts[$topic] ?? 0) + 1;
            }
            $visitors[(string) ($row['visitor'] ?? '')] = true;
            if (!empty($row['matched'])) {
                $matched++;
            } else {
                $unmatched++;
            }
        }

        arsort($faqCounts);
        $top = [];
        $rank = 1;
        foreach (array_slice($faqCounts, 0, 10, true) as $label => $count) {
            $top[] = ['rank' => $rank++, 'label' => $label, 'count' => $count];
        }

        $peakHour = array_keys($byHour, max($byHour) ?: 0)[0] ?? 0;
        $topFaq = $top[0]['label'] ?? 'ยังไม่มีข้อมูล';

        return [
            'days' => $days,
            'total' => count($filtered),
            'unique_visitors' => count(array_filter(array_keys($visitors))),
            'matched' => $matched,
            'unmatched' => $unmatched,
            'match_rate' => count($filtered) ? round($matched * 100 / count($filtered)) : 0,
            'peak_hour' => (int) $peakHour,
            'top_question' => $topFaq,
            'by_day' => [
                'labels' => array_map([$this, 'thaiDay'], array_keys($byDay)),
                'keys' => array_keys($byDay),
                'values' => array_values($byDay),
            ],
            'by_hour' => [
                'labels' => array_map(static fn ($h) => sprintf('%02d:00', $h), range(0, 23)),
                'values' => array_values($byHour),
            ],
            'top' => $top,
            'unmatched_groups' => $this->grouped($filtered, false),
            'matched_groups' => $this->grouped($filtered, true),
        ];
    }

    public function grouped(array $rows, bool $matched, int $limit = 80): array
    {
        $buckets = [];
        foreach ($rows as $row) {
            if (!empty($row['matched']) !== $matched) {
                continue;
            }
            $question = trim((string) ($row['question'] ?? ''));
            $faq = trim((string) ($row['faq'] ?? ''));
            $label = $matched ? ($faq !== '' ? $faq : $question) : $question;
            $key = mb_strtolower(preg_replace('/\s+/u', ' ', $label) ?? $label);
            if ($key === '') {
                continue;
            }
            if (!isset($buckets[$key])) {
                $buckets[$key] = [
                    'question' => $label,
                    'category' => (string) ($row['category'] ?? ''),
                    'count' => 0,
                    'last_time' => (string) ($row['time'] ?? ''),
                    'samples' => [],
                    'faq' => $faq,
                ];
            }
            $buckets[$key]['count']++;
            $ts = strtotime((string) ($row['time'] ?? '')) ?: 0;
            $last = strtotime($buckets[$key]['last_time']) ?: 0;
            if ($ts >= $last) {
                $buckets[$key]['last_time'] = (string) ($row['time'] ?? '');
                $buckets[$key]['question'] = $label;
                $buckets[$key]['category'] = (string) ($row['category'] ?? '');
                $buckets[$key]['faq'] = $faq;
            }
            if ($question !== '' && count($buckets[$key]['samples']) < 4 && !in_array($question, $buckets[$key]['samples'], true)) {
                $buckets[$key]['samples'][] = $question;
            }
        }
        $out = array_values($buckets);
        usort($out, static function ($a, $b) {
            return $b['count'] <=> $a['count'] ?: strcmp((string) $b['last_time'], (string) $a['last_time']);
        });
        return array_slice($out, 0, $limit);
    }

    public function seedIfEmpty(array $faqs): void
    {
        if (is_file($this->file) && filesize($this->file) > 0) {
            return;
        }
        $pool = array_values(array_filter($faqs, static fn ($row) => mb_strlen((string) ($row['question'] ?? '')) < 80));
        if (!$pool) {
            return;
        }
        $visitors = ['A3F21C', 'B91K04', 'C22LQ8', 'D7M15P', 'E44N0A', 'F18H2S'];
        $hours = [8, 9, 10, 11, 13, 14, 15, 16, 19, 20];
        $lines = [];
        for ($i = 0; $i < 48; $i++) {
            $faq = $pool[$i % count($pool)];
            $ts = strtotime(sprintf('-%d days %02d:%02d:00', $i % 12, $hours[$i % count($hours)], ($i * 7) % 50)) ?: time();
            $matched = ($i % 7) !== 0;
            $row = [
                'id' => 'ask-sample-' . $i,
                'time' => date('c', $ts),
                'visitor' => $visitors[$i % count($visitors)],
                'category' => $faq['category'] ?? 'ทั่วไป',
                'question' => $matched ? (string) $faq['question'] : 'ขอข้อมูลเพิ่มเติมหน่อยค่ะ',
                'matched' => $matched,
                'faq' => $matched ? (string) $faq['question'] : '',
                'answer' => mb_substr($matched ? (string) $faq['answer'] : Matcher::noMatchMessage(), 0, 600),
                'sample' => true,
            ];
            $lines[] = json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        file_put_contents($this->file, implode("\n", $lines) . "\n");
    }

    // --- Admin activity log ---

    private string $adminFile;

    private function adminLogFile(): string
    {
        if (!isset($this->adminFile)) {
            $this->adminFile = dirname(__DIR__) . '/data/admin-log.jsonl';
        }
        return $this->adminFile;
    }

    public function recordAdmin(string $action, array $detail = []): void
    {
        $row = [
            'id' => uniqid('adm-', true),
            'time' => date('c'),
            'admin_email' => (string) ($_SESSION['scigenie_admin'] ?? ''),
            'admin_name' => (string) ($_SESSION['scigenie_admin_name'] ?? ''),
            'admin_role' => (string) ($_SESSION['scigenie_admin_role'] ?? ''),
            'action' => $action,
            'detail' => $detail,
        ];
        $file = $this->adminLogFile();
        $dir = dirname($file);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $line = json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
        $fp = @fopen($file, 'ab');
        if ($fp === false) {
            return;
        }
        flock($fp, LOCK_EX);
        fwrite($fp, $line);
        flock($fp, LOCK_UN);
        fclose($fp);
    }

    public function adminLog(int $limit = 200): array
    {
        $file = $this->adminLogFile();
        if (!is_file($file)) {
            return [];
        }
        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        $rows = [];
        foreach ($lines as $line) {
            $row = json_decode($line, true);
            if (is_array($row)) {
                $rows[] = $row;
            }
        }
        if (count($rows) > $limit) {
            $rows = array_slice($rows, -$limit);
        }
        return array_reverse($rows);
    }

    public static function actionLabel(string $action): string
    {
        return match ($action) {
            'login' => 'เข้าสู่ระบบ',
            'faq_add' => 'เพิ่มคำถาม',
            'faq_edit' => 'แก้ไขคำถาม',
            'faq_delete' => 'ลบคำถาม',
            'user_add' => 'เพิ่มผู้ใช้',
            'user_edit' => 'แก้ไขผู้ใช้',
            'user_delete' => 'ลบผู้ใช้',
            default => $action,
        };
    }

    public static function roleLabel(string $role): string
    {
        return $role === 'super' ? 'ผู้ดูแลระบบ' : 'ผู้จัดการหมวด';
    }

    public function visitorLabel(string $raw): string
    {
        $raw = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $raw) ?? '');
        if ($raw === '') {
            $raw = strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
        }
        return substr($raw, 0, 8);
    }

    public function thaiDay(string $ymd): string
    {
        $ts = strtotime($ymd) ?: time();
        $months = ['ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
        return (int) date('j', $ts) . ' ' . $months[(int) date('n', $ts) - 1];
    }
}
