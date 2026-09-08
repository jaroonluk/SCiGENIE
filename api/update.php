<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
Auth::requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'error' => 'Method not allowed'], 405);
}

$input = request_json();
if (!Auth::verifyCsrf($input['csrf'] ?? null)) {
    json_response(['ok' => false, 'error' => 'CSRF token ไม่ถูกต้อง'], 403);
}

$id = trim((string) ($input['id'] ?? ''));
if ($id === '') {
    json_response(['ok' => false, 'error' => 'ไม่พบรหัสคำถาม'], 422);
}

try {
    $sheet = new SheetService();
    $current = $sheet->findById($id);
    if ($current === null) {
        throw new InvalidArgumentException('ไม่พบคำถามที่ต้องการแก้ไข');
    }
    Auth::requireCategory((string) ($current['category'] ?? ''));
    Auth::requireCategory(trim((string) ($input['category'] ?? $current['category'] ?? '')));
    $result = $sheet->update($id, $input);
    (new AskLog())->recordAdmin('faq_edit', [
        'id' => $id,
        'category' => trim((string) ($input['category'] ?? $current['category'] ?? '')),
        'question' => mb_substr(trim((string) ($input['question'] ?? '')), 0, 120),
    ]);
    json_response([
        'ok' => true,
        'item' => $result['item'],
        'sheet' => $result['sheet'],
        'message' => $result['sheet']['message'] ?? 'แก้ไขคำถามเรียบร้อย',
    ]);
} catch (InvalidArgumentException $e) {
    json_response(['ok' => false, 'error' => $e->getMessage()], 422);
} catch (Throwable $e) {
    json_response(['ok' => false, 'error' => 'ไม่สามารถแก้ไขได้ในขณะนี้'], 500);
}
