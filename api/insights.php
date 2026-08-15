<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
Auth::requireLogin();

$days = (int) ($_GET['days'] ?? 14);
if (!in_array($days, [7, 14, 30], true)) {
    $days = 14;
}

$sheet = new SheetService();
$log = new AskLog();
$log->seedIfEmpty($sheet->all());
$rows = $log->all();
$summary = $log->summarize($rows, $days);

json_response([
    'ok' => true,
    'summary' => $summary,
    'rows' => array_slice($rows, 0, 300),
]);
