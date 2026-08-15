<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    json_response(['ok' => true]);
}

$input = $_SERVER['REQUEST_METHOD'] === 'POST' ? request_json() : $_GET;
$question = trim((string) ($input['question'] ?? ''));
$category = trim((string) ($input['category'] ?? ''));
$visitor = trim((string) ($input['visitor'] ?? ''));

$sheet = new SheetService();
$matcher = new Matcher();
$result = $matcher->ask($sheet->all(), $question, $category);
$result['ok'] = true;
$result['category'] = $category;
$result['question'] = $question;

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
