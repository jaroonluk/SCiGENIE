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

try {
    Auth::requireCategory(trim((string) ($input['category'] ?? '')));
    $sheet = new SheetService();
    $result = $sheet->add($input);
    if (!empty($result['item'])) {
        FaqEmbedder::upsertFaq($result['item']);
    }
    (new AskLog())->recordAdmin('faq_add', [
        'id' => (string) ($result['item']['id'] ?? ''),
        'category' => trim((string) ($input['category'] ?? '')),
        'question' => mb_substr(trim((string) ($input['question'] ?? '')), 0, 120),
    ]);
    json_response([
        'ok' => true,
        'item' => $result['item'],
        'sheet' => $result['sheet'],
        'message' => $result['sheet']['message'] ?? 'บันทึกคำถามเรียบร้อย',
    ]);
} catch (InvalidArgumentException $e) {
    json_response(['ok' => false, 'error' => $e->getMessage()], 422);
} catch (Throwable $e) {
    json_response(['ok' => false, 'error' => 'ไม่สามารถบันทึกได้ในขณะนี้'], 500);
}
