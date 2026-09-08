<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
Auth::requireLogin();

$days = (int) ($_GET['days'] ?? 14);
if (!in_array($days, [7, 14, 30], true)) {
    $days = 14;
}

$log = new AskLog();
$rows = Auth::filterAskRows($log->all(4000, true));
$summary = $log->summarize($rows, $days);

json_response([
    'ok' => true,
    'summary' => $summary,
    'rows' => Auth::isSuper() ? array_slice($rows, 0, 400) : [],
    'scope' => Auth::isSuper() ? 'all' : Auth::allowedCategories(),
]);
