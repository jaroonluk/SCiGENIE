<?php
require dirname(__DIR__) . '/includes/bootstrap.php';

$sheet = new SheetService();
$r = $sheet->match('ค่าเทอมเท่าไหร่', 'ลงทะเบียน');
echo ($r['matched'] ? 'YES' : 'NO') . PHP_EOL;
echo ($r['item']['question'] ?? '') . PHP_EOL;
echo mb_substr(LineBot::replyTextForAsk($r), 0, 180) . PHP_EOL;

$cats = $sheet->categories();
echo 'cats=' . count($cats) . PHP_EOL;
echo implode(',', array_map(static fn ($c) => $c['short'], $cats)) . PHP_EOL;

$id = (string) ($r['item']['id'] ?? '');
$found = $id !== '' ? $sheet->findById($id) : null;
echo 'byId=' . ($found['question'] ?? 'none') . PHP_EOL;
echo 'source=' . ($found['source'] ?? '') . PHP_EOL;
