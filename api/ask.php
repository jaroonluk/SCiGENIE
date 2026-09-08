<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    json_response(['ok' => true]);
}

$input = $_SERVER['REQUEST_METHOD'] === 'POST' ? request_json() : $_GET;
$question = trim((string) ($input['question'] ?? $input['text'] ?? ''));
$category = trim((string) ($input['category'] ?? ''));
$visitor = trim((string) ($input['visitor'] ?? ''));
$channel = strtolower(trim((string) ($input['channel'] ?? $input['source'] ?? '')));
$userId = trim((string) ($input['user_id'] ?? $input['userId'] ?? ''));

if ($channel === 'line' || $userId !== '') {
    $out = LineBot::processForApi($userId !== '' ? $userId : ($visitor !== '' ? $visitor : 'line-guest'), $question);
    json_response([
        'ok' => true,
        'question' => $question,
        'category' => $out['category'],
        'reply_text' => $out['reply_text'],
    ]);
}

$sheet = new SheetService();
$matcher = new Matcher();
$result = $matcher->ask($sheet->all(), $question, $category);
$result['ok'] = true;
$result['category'] = $category;
$result['question'] = $question;
$result['reply_text'] = LineBot::replyTextForAsk($result);

if ($question !== '') {
    $item = $result['item'] ?? [];
    $log = new AskLog();
    $log->record([
        'visitor' => $visitor,
        'category' => $category,
        'question' => $question,
        'matched' => !empty($result['matched']),
        'faq' => $item['question'] ?? '',
        'answer' => $item['answer'] ?? ($result['message'] ?? ''),
    ]);
}

json_response($result);
