<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
Auth::requireSuper();

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    json_response(['ok' => true]);
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    json_response(['ok' => true, 'users' => Auth::listAdmins()]);
}

$input = request_json();
if (!Auth::verifyCsrf($input['csrf'] ?? $_GET['csrf'] ?? null)) {
    json_response(['ok' => false, 'error' => 'CSRF token ไม่ถูกต้อง'], 403);
}

try {
    if ($method === 'POST') {
        $isNew = empty($input['id']);
        $saved = Auth::saveAdmin($input);
        (new AskLog())->recordAdmin($isNew ? 'user_add' : 'user_edit', [
            'target_email' => trim((string) ($input['email'] ?? '')),
            'target_name' => trim((string) ($input['name'] ?? '')),
            'role' => (string) ($input['role'] ?? 'manager'),
        ]);
        json_response(['ok' => true, 'id' => $saved['id'], 'users' => Auth::listAdmins(), 'message' => 'บันทึกผู้ใช้แล้ว']);
    }
    if ($method === 'DELETE') {
        $id = (int) ($input['id'] ?? $_GET['id'] ?? 0);
        if ($id < 1) {
            json_response(['ok' => false, 'error' => 'ไม่พบผู้ใช้'], 422);
        }
        $allUsers = Auth::listAdmins();
        $target = '';
        foreach ($allUsers as $u) {
            if ((int) $u['id'] === $id) { $target = (string) $u['email']; break; }
        }
        Auth::deleteAdmin($id);
        (new AskLog())->recordAdmin('user_delete', ['target_email' => $target]);
        json_response(['ok' => true, 'users' => Auth::listAdmins(), 'message' => 'ลบผู้ใช้แล้ว']);
    }
    json_response(['ok' => false, 'error' => 'Method not allowed'], 405);
} catch (InvalidArgumentException $e) {
    json_response(['ok' => false, 'error' => $e->getMessage()], 422);
} catch (Throwable $e) {
    json_response(['ok' => false, 'error' => 'ไม่สามารถบันทึกผู้ใช้ได้ในขณะนี้'], 500);
}
