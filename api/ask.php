<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    json_response(['ok' => true]);
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
    AskGuard::throttle('ask');
    $question = AskGuard::question((string) ($input['question'] ?? $input['text'] ?? ''));
    $category = AskGuard::category((string) ($input['category'] ?? ''));
    $visitor = AskGuard::token((string) ($input['visitor'] ?? ''));
    $channel = AskGuard::channel((string) ($input['channel'] ?? $input['source'] ?? ''));
    $userId = AskGuard::token((string) ($input['user_id'] ?? $input['userId'] ?? ''));
} catch (InvalidArgumentException $e) {
    json_response(['ok' => false, 'error' => $e->getMessage()], 400);
} catch (RuntimeException $e) {
    json_response(['ok' => false, 'error' => $e->getMessage()], 429);
}

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
$result = WebSources::enrich($matcher->ask($sheet->all(), $question, $category), $question);
$result['ok'] = true;
$matchedCat = trim((string) ($result['item']['category'] ?? ''));
$result['category'] = (!empty($result['matched']) && $matchedCat !== '') ? $matchedCat : $category;
$result['question'] = $question;
$result['reply_text'] = LineBot::replyTextForAsk($result);

if ($question !== '') {
    $item = $result['item'] ?? [];
    $log = new AskLog();
    $log->record([
        'visitor' => $visitor,
        'channel' => 'web',
        'category' => $result['category'],
        'question' => $question,
        'matched' => !empty($result['matched']),
        'faq' => $item['question'] ?? '',
        'answer' => $item['answer'] ?? ($result['message'] ?? ''),
    ]);
}

json_response($result);
