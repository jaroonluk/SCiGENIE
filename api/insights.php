<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
Auth::requireLogin();

$days = (int) ($_GET['days'] ?? 14);
if (!in_array($days, [7, 14, 30], true)) {
    $days = 14;
}
$from = trim((string) ($_GET['from'] ?? ''));
$to = trim((string) ($_GET['to'] ?? ''));

$log = new AskLog();
$bounds = $log->rangeBounds($days, $from !== '' || $to !== '' ? $from : null, $from !== '' || $to !== '' ? $to : null);
if (!$bounds['ok']) {
    json_response(['ok' => false, 'error' => $bounds['error']], 422);
}

$rows = Auth::filterAskRows($log->all(8000, true));
$categoryNames = [];
foreach (Auth::filterCategories((new SheetService())->categories()) as $cat) {
    $name = trim((string) ($cat['name'] ?? ''));
    if ($name !== '') {
        $categoryNames[] = $name;
    }
}
$summary = $log->summarize(
    $rows,
    $days,
    $bounds['mode'] === 'custom' ? $bounds['from'] : null,
    $bounds['mode'] === 'custom' ? $bounds['to'] : null,
    $categoryNames
);
$window = [];
foreach ($rows as $row) {
    $ts = strtotime((string) ($row['time'] ?? '')) ?: 0;
    if ($ts >= $bounds['from_ts'] && $ts <= $bounds['to_ts']) {
        $window[] = $row;
    }
}

json_response([
    'ok' => true,
    'summary' => $summary,
    'rows' => array_slice($window, 0, 2000),
    'scope' => Auth::isSuper() ? 'all' : Auth::allowedCategories(),
]);
