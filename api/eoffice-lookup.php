<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
Auth::requireSuper();

$q = trim((string) ($_GET['q'] ?? ''));
try {
    json_response(['ok' => true, 'items' => Eoffice::search($q)]);
} catch (Throwable $e) {
    json_response(['ok' => false, 'error' => 'เชื่อมฐานข้อมูลบุคลากรไม่สำเร็จ', 'items' => []], 503);
}
