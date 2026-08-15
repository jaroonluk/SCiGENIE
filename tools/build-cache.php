<?php
require_once __DIR__ . '/../includes/Csv.php';

$csv = file_get_contents(__DIR__ . '/sheet.csv');
$rows = Csv::parse($csv);
$header = array_shift($rows);
$faqs = [];
$cats = [];
foreach ($rows as $i => $row) {
    $cat = trim($row[0] ?? '');
    $q = trim($row[1] ?? '');
    if ($cat === '' && $q === '') {
        continue;
    }
    $faqs[] = [
        'id' => $i + 1,
        'category' => $cat,
        'question' => $q,
        'keyword' => trim($row[2] ?? ''),
        'answer' => trim($row[3] ?? ''),
        'keyword_back' => trim($row[4] ?? ''),
    ];
    if ($cat !== '') {
        $cats[$cat] = true;
    }
}
$dir = __DIR__ . '/../data';
if (!is_dir($dir)) {
    mkdir($dir, 0777, true);
}
file_put_contents($dir . '/faq-cache.json', json_encode($faqs, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
echo 'faqs=' . count($faqs) . PHP_EOL;
echo 'categories=' . implode(' | ', array_keys($cats)) . PHP_EOL;
