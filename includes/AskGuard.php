<?php

declare(strict_types=1);

/**
 * Rejects probe-like questions on the public ask API before they are matched,
 * logged, or sent to a model. FAQ lookup itself uses prepared statements.
 */
class AskGuard
{
    public const MAX_QUESTION = 500;
    public const MAX_CATEGORY = 80;
    public const MAX_ID = 64;

    public static function question(string $value): string
    {
        $value = trim(str_replace("\0", '', $value));
        if ($value === '') {
            return '';
        }
        if (mb_strlen($value) > self::MAX_QUESTION) {
            throw new InvalidArgumentException('คำถามยาวเกิน ' . self::MAX_QUESTION . ' ตัวอักษร');
        }
        if (self::suspicious($value)) {
            throw new InvalidArgumentException('ไม่สามารถประมวลผลข้อความนี้ได้');
        }
        return $value;
    }

    public static function category(string $value): string
    {
        $value = trim(str_replace("\0", '', $value));
        if ($value === '') {
            return '';
        }
        if (mb_strlen($value) > self::MAX_CATEGORY || self::suspicious($value)) {
            throw new InvalidArgumentException('หมวดไม่ถูกต้อง');
        }
        return $value;
    }

    public static function token(string $value): string
    {
        $value = trim($value);
        if ($value === '' || strlen($value) > self::MAX_ID) {
            return '';
        }
        if (!preg_match('/^[A-Za-z0-9._:-]+$/', $value)) {
            return '';
        }
        return $value;
    }

    public static function channel(string $value): string
    {
        $value = strtolower(trim($value));
        return in_array($value, ['web', 'line', 'facebook'], true) ? $value : '';
    }

    public static function throttle(string $bucket, int $limit = 40, int $window = 60): void
    {
        $limit = max(1, $limit);
        $window = max(1, $window);
        $dir = dirname(__DIR__) . '/data/rate';
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            return;
        }
        $file = $dir . '/' . hash('sha256', $bucket . '|' . self::clientIp()) . '.json';
        $fp = @fopen($file, 'c+');
        if ($fp === false) {
            return;
        }
        try {
            if (!flock($fp, LOCK_EX)) {
                return;
            }
            $raw = stream_get_contents($fp);
            $decoded = is_string($raw) ? json_decode($raw, true) : null;
            $now = time();
            $hits = [];
            if (is_array($decoded['hits'] ?? null)) {
                foreach ($decoded['hits'] as $ts) {
                    $ts = (int) $ts;
                    if ($ts > $now - $window) {
                        $hits[] = $ts;
                    }
                }
            }
            if (count($hits) >= $limit) {
                throw new RuntimeException('ส่งคำถามถี่เกินไป กรุณารอสักครู่แล้วลองใหม่');
            }
            $hits[] = $now;
            rewind($fp);
            ftruncate($fp, 0);
            fwrite($fp, json_encode(['hits' => $hits]));
            fflush($fp);
        } finally {
            flock($fp, LOCK_UN);
            fclose($fp);
        }
    }

    public static function suspicious(string $value): bool
    {
        if ($value === '' || str_contains($value, "\0")) {
            return $value !== '';
        }
        $flat = strtolower(preg_replace('/\s+/', ' ', $value) ?? $value);
        foreach ([
            'union select',
            'union all select',
            'information_schema',
            'load_file(',
            'into outfile',
            'into dumpfile',
            'sleep(',
            'benchmark(',
            'waitfor delay',
            'xp_cmdshell',
            'extractvalue(',
            'updatexml(',
            'or 1=1',
            "or '1'='1",
            'or "1"="1',
            ';drop ',
            '; drop ',
            ';delete ',
            '; delete ',
            ';update ',
            '; update ',
            '@@version',
        ] as $needle) {
            if (str_contains($flat, $needle)) {
                return true;
            }
        }
        return preg_match("/['\"]\\s*(or|and)\\s+['\"\\d]/i", $value) === 1
            || (preg_match('/(--|#|\\/\\*)/', $value) === 1 && preg_match('/[\'\";]/', $value) === 1);
    }

    private static function clientIp(): string
    {
        $remote = trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
        if (filter_var($remote, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return $remote;
        }
        foreach (explode(',', (string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? '')) as $part) {
            $ip = trim($part);
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return $ip;
            }
        }
        return $remote !== '' ? $remote : 'unknown';
    }
}
