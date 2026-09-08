<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    json_response(['ok' => true]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(['ok' => false, 'error' => 'Method not allowed'], 405);
}

$input = $_SERVER['REQUEST_METHOD'] === 'POST' ? request_json() : $_GET;
$text = trim((string) ($input['text'] ?? $input['question'] ?? $input['message'] ?? ''));
$userId = trim((string) ($input['user_id'] ?? $input['userId'] ?? $input['visitor'] ?? ''));

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
