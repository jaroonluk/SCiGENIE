<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

$sheet = new SheetService();
$faqs = $sheet->all();
$category = trim((string) ($_GET['category'] ?? ''));
$matcher = new Matcher();

json_response([
    'ok' => true,
    'category' => $category,
    'suggestions' => $matcher->suggestions($faqs, $category, 8),
]);
