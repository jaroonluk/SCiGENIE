<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$s = new SheetService();
$l = new AskLog();
$l->seedIfEmpty($s->all());
$rows = $l->all();
$sum = $l->summarize($rows, 14);
echo 'rows=' . count($rows) . PHP_EOL;
echo 'total=' . $sum['total'] . PHP_EOL;
echo 'top=' . ($sum['top'][0]['label'] ?? '-') . PHP_EOL;
echo 'peak=' . $sum['peak_hour'] . PHP_EOL;
echo 'file=' . (is_file(__DIR__ . '/../data/ask-log.jsonl') ? 'yes' : 'no') . PHP_EOL;
