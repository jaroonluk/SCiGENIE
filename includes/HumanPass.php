<?php

declare(strict_types=1);

/**
 * Hands a LINE or Messenger chat to a person and keeps SCiGENIE quiet.
 * The hold refreshes on each new user message, then expires after an idle hour.
 */
class HumanPass
{
    public const HOLD_SECONDS = 3600;
    public const NAG_SECONDS = 21600;

    /** Meta Page Inbox. Staff replies from the inbox use this app id. */
    public const INBOX_APP_ID = '263902037430900';

    public static function isResume(string $text): bool
    {
        $n = self::norm($text);
        if ($n === '') {
            return false;
        }
        foreach (['เมนู', 'menu', 'เริ่มบอท', 'กลับบอท', 'ถามบอท', 'เริ่มใหม่'] as $word) {
            if ($n === $word) {
                return true;
            }
        }
        return false;
    }

    public static function isPause(string $text): bool
    {
        $n = self::norm($text);
        if ($n === '') {
            return false;
        }
        foreach ([
            'คุยกับเจ้าหน้าที่',
            'ติดต่อเจ้าหน้าที่',
            'ขอคุยกับเจ้าหน้าที่',
            'หยุดบอท',
            'pass',
        ] as $word) {
            if ($n === $word) {
                return true;
            }
        }
        return false;
    }

    public static function active(array $row): bool
    {
        return (int) ($row['human_until'] ?? 0) > time();
    }

    public static function naggedRecently(array $row): bool
    {
        $ts = (int) ($row['nag_ts'] ?? 0);
        return $ts > time() - self::NAG_SECONDS;
    }

    public static function hold(array $row): array
    {
        $row['human_until'] = time() + self::HOLD_SECONDS;
        $row['ts'] = time();
        return $row;
    }

    public static function rememberNag(array $row): array
    {
        $row['nag_ts'] = time();
        $row['ts'] = time();
        return $row;
    }

    public static function passHint(): string
    {
        return 'หากให้เจ้าหน้าที่คุยต่อ พิมพ์ “คุยกับเจ้าหน้าที่” บอทจะหยุดแทรก พิมพ์ “เมนู” เมื่ออยากให้บอทตอบอีกครั้ง';
    }

    public static function ack(): string
    {
        return "รับเรื่องแล้วค่ะ ช่วงนี้ SCiGENIE จะไม่ตอบ เพื่อให้เจ้าหน้าที่คุยในแชทนี้ได้\nเมื่ออยากให้บอทตอบอีกครั้ง พิมพ์ “เมนู”";
    }

    /**
     * A page echo is from a person when it was not sent by this app.
     * Inbox replies are recognized even before this app's id is known.
     */
    public static function isHumanEcho(array $message, string $ourAppId, string $inboxAppId): bool
    {
        if (empty($message['is_echo'])) {
            return false;
        }
        $appId = trim((string) ($message['app_id'] ?? ''));
        if ($appId === '' || ($ourAppId !== '' && $appId === $ourAppId)) {
            return false;
        }
        if ($inboxAppId === '') {
            $inboxAppId = self::INBOX_APP_ID;
        }
        if ($appId === $inboxAppId || $appId === self::INBOX_APP_ID) {
            return true;
        }
        return $ourAppId !== '';
    }

    private static function norm(string $text): string
    {
        $n = mb_strtolower(trim($text));
        $n = preg_replace('/\s+/u', '', $n) ?? $n;
        $n = preg_replace('/(ค่ะ|ครับ|คะ|นะคะ|นะครับ|หน่อย|ด้วย)+$/u', '', $n) ?? $n;
        return $n;
    }
}
