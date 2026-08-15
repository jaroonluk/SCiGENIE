<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$s = new SheetService();
$added = $s->add([
    'category' => 'ทั่วไป',
    'question' => 'ทดสอบแก้ไขลบ ' . time(),
    'keyword' => 'ทดสอบแก้ไขลบ',
    'answer' => 'คำตอบเดิม',
    'keyword_back' => '',
]);
$id = (string) $added['item']['id'];
$upd = $s->update($id, [
    'category' => 'ทั่วไป',
    'question' => 'ทดสอบแก้ไขลบแล้ว',
    'keyword' => 'ทดสอบแก้ไขลบ, อัปเดต',
    'answer' => 'คำตอบใหม่',
    'keyword_back' => 'update',
]);
$found = $s->findById($id);
$del = $s->delete($id);
$gone = $s->findById($id);
echo 'add=' . $id . PHP_EOL;
echo 'upd=' . ($upd['item']['answer'] ?? '') . PHP_EOL;
echo 'found=' . ($found['answer'] ?? '') . PHP_EOL;
echo 'gone=' . ($gone === null ? 'yes' : 'no') . PHP_EOL;
echo 'logo=' . (is_file(__DIR__ . '/../assets/img/logo.jpg') ? 'yes' : 'no') . PHP_EOL;
