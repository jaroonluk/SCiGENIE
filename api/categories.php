<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    json_response(['ok' => true]);
}

$sheet = new SheetService();
$force = isset($_GET['refresh']);
json_response([
    'ok' => true,
    'categories' => $sheet->categories($force),
]);
