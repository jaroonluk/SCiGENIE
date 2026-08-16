<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
Auth::requireLogin();

$sheet = new SheetService();
$force = isset($_GET['refresh']);
$faqs = Auth::filterFaqs($sheet->all($force));
$category = trim((string) ($_GET['category'] ?? ''));
$q = trim((string) ($_GET['q'] ?? ''));

if ($category !== '') {
    $faqs = array_values(array_filter($faqs, static fn ($row) => ($row['category'] ?? '') === $category));
}
if ($q !== '') {
    $n = mb_strtolower($q);
    $faqs = array_values(array_filter($faqs, static function ($row) use ($n) {
        $hay = mb_strtolower(($row['question'] ?? '') . ' ' . ($row['keyword'] ?? '') . ' ' . ($row['answer'] ?? ''));
        return mb_strpos($hay, $n) !== false;
    }));
}

json_response([
    'ok' => true,
    'total' => count($faqs),
    'items' => array_values($faqs),
    'source' => 'db',
]);
