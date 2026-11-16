<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    json_response(['ok' => true]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(['ok' => false, 'error' => 'Method not allowed'], 405);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw = file_get_contents('php://input') ?: '';
    if ($raw !== '') {
        $input = json_decode($raw, true);
        if (!is_array($input)) {
            json_response(['ok' => false, 'error' => 'รูปแบบข้อมูลไม่ถูกต้อง'], 400);
        }
    } else {
        $input = $_POST;
    }
} else {
    $input = $_GET;
}

try {
    AskGuard::throttle('line-ask');
    $text = AskGuard::question((string) ($input['text'] ?? $input['question'] ?? $input['message'] ?? ''));
    $userId = AskGuard::token((string) ($input['user_id'] ?? $input['userId'] ?? $input['visitor'] ?? ''));
} catch (InvalidArgumentException $e) {
    json_response(['ok' => false, 'error' => $e->getMessage()], 400);
} catch (RuntimeException $e) {
    json_response(['ok' => false, 'error' => $e->getMessage()], 429);
}

if ($text === '') {
    json_response(['ok' => false, 'error' => 'กรุณาส่งข้อความคำถาม'], 422);
}

$out = LineBot::processForApi($userId !== '' ? $userId : 'line-guest', $text);
json_response([
    'ok' => true,
    'question' => $text,
    'category' => $out['category'],
    'reply_text' => $out['reply_text'],
]);
