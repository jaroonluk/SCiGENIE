<?php
require_once __DIR__ . '/../includes/bootstrap.php';

$sheet = new SheetService();
$faqs = $sheet->all();
$m = new Matcher();

$tests = [
    ['ทั่วไป', 'คณะอยู่ที่ไหน'],
    ['ทั่วไป', 'เบอร์โทร'],
    ['ทั่วไป', 'สวัสดี'],
    ['ลงทะเบียน', 'ค่าเทอมเท่าไหร่'],
    ['การสอบ', 'ใส่ชุดอะไรไปสอบ'],
    ['รับเข้านักศึกษา', 'พสวท เปิดรับสมัครเมื่อไหร่'],
    ['สวัสดิการนักศึกษา', 'ย้ายสิทธิบัตรทอง'],
];

foreach ($tests as [$cat, $q]) {
    $r = $m->ask($faqs, $q, $cat);
    $title = $r['matched'] ? ($r['item']['question'] ?? '') : 'NO MATCH';
    echo $cat . ' | ' . $q . ' => ' . ($r['matched'] ? 'YES' : 'NO') . ' | ' . $title . PHP_EOL;
}
echo 'total=' . count($faqs) . PHP_EOL;
echo 'cats=' . count($sheet->categories()) . PHP_EOL;
