<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
Auth::requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    json_response([
        'ok' => true,
        'ready' => WebSources::ready(),
        'items' => WebSources::all(),
    ]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'error' => 'Method not allowed'], 405);
}

$input = request_json();
if (!Auth::verifyCsrf($input['csrf'] ?? null)) {
    json_response(['ok' => false, 'error' => 'CSRF token ไม่ถูกต้อง'], 403);
}

$action = trim((string) ($input['action'] ?? 'save'));

try {
    if ($action === 'delete') {
        WebSources::delete((int) ($input['id'] ?? 0));
        (new AskLog())->recordAdmin('web_source_delete', [
            'id' => (int) ($input['id'] ?? 0),
        ]);
        json_response(['ok' => true, 'items' => WebSources::all(), 'message' => 'ลบแหล่งข้อมูลแล้ว']);
    }
    $item = WebSources::save($input);
    (new AskLog())->recordAdmin('web_source_save', [
        'id' => (int) ($item['id'] ?? 0),
        'title' => (string) ($item['title'] ?? ''),
        'url' => (string) ($item['url'] ?? ''),
    ]);
    json_response([
        'ok' => true,
        'item' => $item,
        'items' => WebSources::all(),
        'message' => 'บันทึกแหล่งข้อมูลแล้ว',
    ]);
} catch (InvalidArgumentException $e) {
    json_response(['ok' => false, 'error' => $e->getMessage()], 422);
} catch (RuntimeException $e) {
    json_response(['ok' => false, 'error' => $e->getMessage()], 503);
} catch (Throwable $e) {
    error_log('SCiGENIE web source save: ' . $e->getMessage());
    json_response(['ok' => false, 'error' => 'ไม่สามารถบันทึกแหล่งข้อมูลได้ในขณะนี้'], 500);
}
