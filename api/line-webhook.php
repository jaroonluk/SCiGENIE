<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    json_response(['ok' => true, 'service' => 'scigenie-line']);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'error' => 'Method not allowed'], 405);
}

if (!LineBot::configured()) {
    json_response(['ok' => false, 'error' => 'LINE OA ยังไม่ได้ตั้งค่า'], 503);
}

$raw = (string) file_get_contents('php://input');
$signature = (string) ($_SERVER['HTTP_X_LINE_SIGNATURE'] ?? '');
if (!LineBot::verifySignature($raw, $signature)) {
    json_response(['ok' => false, 'error' => 'invalid signature'], 400);
}

$payload = json_decode($raw, true);
$events = is_array($payload) ? ($payload['events'] ?? []) : [];
flush_http_response(200, '{"ok":true}');
if (is_array($events)) {
    foreach ($events as $event) {
        if (is_array($event)) {
            try {
                LineBot::handleEvent($event);
            } catch (Throwable $e) {
                error_log('SCiGENIE LINE: ' . $e->getMessage());
            }
        }
    }
}
exit;
