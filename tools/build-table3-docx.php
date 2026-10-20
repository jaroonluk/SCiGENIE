<?php
declare(strict_types=1);

function esc(string $s): string
{
    return htmlspecialchars($s, ENT_XML1 | ENT_COMPAT, 'UTF-8');
}

function rPr(bool $bold = false, int $size = 28): string
{
    $b = $bold ? '<w:b/>' : '';
    return '<w:rPr><w:rFonts w:ascii="TH Sarabun New" w:hAnsi="TH Sarabun New" w:eastAsia="TH Sarabun New" w:cs="TH Sarabun New"/>'
        . $b
        . '<w:sz w:val="' . $size . '"/><w:szCs w:val="' . $size . '"/></w:rPr>';
}

function para(string $text, string $align = 'left', bool $bold = false, int $size = 28, int $before = 40, int $after = 40): string
{
    return '<w:p><w:pPr><w:jc w:val="' . $align . '"/>'
        . '<w:spacing w:before="' . $before . '" w:after="' . $after . '" w:line="240" w:lineRule="auto"/>'
        . '</w:pPr><w:r>' . rPr($bold, $size) . '<w:t xml:space="preserve">' . esc($text) . '</w:t></w:r></w:p>';
}

function cell(string $text, int $width, bool $header = false): string
{
    $shd = $header ? '<w:shd w:val="clear" w:color="auto" w:fill="F4E4C4"/>' : '';
    $align = $header ? 'center' : 'left';
    return '<w:tc><w:tcPr><w:tcW w:w="' . $width . '" w:type="dxa"/>' . $shd
        . '<w:vAlign w:val="center"/></w:tcPr>'
        . para($text, $align, $header, $header ? 28 : 28, 60, 60)
        . '</w:tc>';
}

$widths = [2400, 3200, 3400, 5570];
$headers = ['ปัญหาที่พบ', 'สาเหตุที่วิเคราะห์', 'แนวทางแก้ไข', 'ผลการทดสอบหลังปรับปรุง'];
$rows = [
    [
        'ไม่พบข้อมูลในคลังความรู้ 31 ครั้ง',
        'ข้อมูลยังไม่ครอบคลุมประเด็นที่ถาม',
        'เพิ่ม FAQ และตรวจสอบโดยเจ้าของข้อมูล',
        'ทดสอบคำถามที่ยังไม่มีในคลัง 8 ตัวอย่าง ระบบไม่ตอบผิดประเด็น และขอให้เลือกหมวด ทั้ง 8 ตัวอย่างยังไม่พบคำตอบ ต้องเพิ่ม FAQ ก่อนจึงจะตอบได้',
    ],
    [
        'ถ้อยคำต่างจากมาตรฐาน 12 ครั้ง',
        'การจับคู่คำถามไม่ครอบคลุมภาษาที่ใช้จริง',
        'เพิ่มคำพ้องหรือปรับเกณฑ์จับคู่',
        'ทดสอบถ้อยคำพ้อง 7 ตัวอย่าง จับคู่ถูกประเด็นครบ ทั้งที่อยู่ เบอร์โทร ค่าเทอม การแต่งกายเข้าสอบ และโครงการ พสวท.',
    ],
    [
        'คำถามไม่ชัดเจน 20 ครั้ง',
        'ข้อความไม่เพียงพอในการระบุประเด็น',
        'ปรับข้อความแนะนำให้ผู้ใช้ระบุรายละเอียด',
        'ทดสอบคำถามสั้นไม่ระบุประเด็น 7 ตัวอย่าง ระบบขอให้เลือกหมวด 5 ตัวอย่าง อีก 2 ตัวอย่างจับคู่ได้แต่ประเด็นยังกว้าง',
    ],
    [
        'สะกดผิดหรือใช้คำย่อ 8 ครั้ง',
        'ข้อความไม่ตรงกับคำสำคัญที่กำหนด',
        'เพิ่มกระบวนการปรับรูปแบบข้อความ',
        'คำย่อ พสวท และ พสวท. จับคู่ได้ทั้งสองแบบ คำสะกดผิดชัด 4 ตัวอย่างยังไม่จับคู่ ระบบขอให้เลือกหมวดแทนการตอบผิด',
    ],
    [
        'คำถามหลายประเด็น 8 ครั้ง',
        'ข้อความมีมากกว่าหนึ่งเจตนา',
        'พิจารณาการแยกประเด็นหรือขอรายละเอียดเพิ่ม',
        'ทดสอบข้อความที่มีมากกว่าหนึ่งเจตนา 2 ตัวอย่าง ระบบไม่ตอบทันที และขอให้เลือกหมวดเพื่อระบุประเด็น',
    ],
    [
        'ข้อมูลไม่เป็นปัจจุบัน 2 ครั้ง',
        'คลังความรู้ยังไม่ได้ปรับตามข้อมูลล่าสุด',
        'ประสานผู้รับผิดชอบข้อมูลให้ปรับปรุง',
        'ปรับช่วงรับสมัครโครงการ พสวท. เป็นวันที่ 10–15 กุมภาพันธ์ 2570 แล้ว เมื่อถามซ้ำระบบตอบตามข้อมูลที่ปรับปรุง',
    ],
    [
        'ข้อจำกัดกลไกค้นหา 1 ครั้ง',
        'เกณฑ์ค้นหาอาจไม่รองรับรูปแบบข้อความ',
        'ตรวจสอบคะแนนและลำดับการคัดเลือก',
        'คำว่า พสวท จับคู่ถูก แต่คำว่า ภาษาอังกฤษ และคำถามเรื่องหลักสูตรที่เปิดรับ ยังถูกจัดเข้าคำตอบโครงการ พสวท. เกณฑ์คะแนนยังต้องปรับ',
    ],
    [
        'อื่น ๆ / ยังไม่ทราบสาเหตุ 46 ครั้ง',
        'ยังไม่สามารถสรุปสาเหตุ',
        'ตรวจสอบ Log และจำแนกเพิ่มเติม',
        'ตรวจ Log แล้วส่วนใหญ่เป็นคำขอบคุณ ข้อความทดสอบ และข้อความที่ไม่ใช่คำถาม คำขอบคุณจับคู่รายการตอบรับได้ ข้อความทดสอบระบบไม่สร้างคำตอบ',
    ],
];

$body = para('ตารางที่ 3', 'center', true, 36, 0, 40);
$body .= para('การวิเคราะห์ปัญหา แนวทางแก้ไข และผลการทดสอบระบบ SCiGENIE', 'center', true, 36, 0, 80);
$body .= para('ทดสอบซ้ำวันที่ 20 ตุลาคม 2569 กับคลังความรู้ 159 รายการ', 'center', false, 28, 0, 160);

$body .= '<w:tbl><w:tblPr>'
    . '<w:tblW w:w="14570" w:type="dxa"/>'
    . '<w:tblLayout w:type="fixed"/>'
    . '<w:tblBorders>'
    . '<w:top w:val="single" w:sz="8" w:space="0" w:color="8A6A12"/>'
    . '<w:left w:val="single" w:sz="8" w:space="0" w:color="8A6A12"/>'
    . '<w:bottom w:val="single" w:sz="8" w:space="0" w:color="8A6A12"/>'
    . '<w:right w:val="single" w:sz="8" w:space="0" w:color="8A6A12"/>'
    . '<w:insideH w:val="single" w:sz="6" w:space="0" w:color="C4A35A"/>'
    . '<w:insideV w:val="single" w:sz="6" w:space="0" w:color="C4A35A"/>'
    . '</w:tblBorders></w:tblPr><w:tblGrid>';
foreach ($widths as $w) {
    $body .= '<w:gridCol w:w="' . $w . '"/>';
}
$body .= '</w:tblGrid>';

$body .= '<w:tr><w:trPr><w:tblHeader/></w:trPr>';
foreach ($headers as $i => $h) {
    $body .= cell($h, $widths[$i], true);
}
$body .= '</w:tr>';
foreach ($rows as $row) {
    $body .= '<w:tr>';
    foreach ($row as $i => $text) {
        $body .= cell($text, $widths[$i], false);
    }
    $body .= '</w:tr>';
}
$body .= '</w:tbl>';

$body .= para('หมายเหตุ  ผลในคอลัมน์สุดท้ายมาจากการทดสอบซ้ำด้วยชุดคำถามตัวแทนจากบันทึกการใช้งานจริง ไม่ได้นับครบตามจำนวนครั้งในคอลัมน์ปัญหาที่พบ จากบันทึกคำถามจริง 336 รายการ ระบบตอบได้ 200 รายการ และตอบไม่ได้ 136 รายการ', 'both', false, 28, 200, 80);

$documentXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
    . '<w:body>' . $body
    . '<w:sectPr>'
    . '<w:pgSz w:w="16838" w:h="11906" w:orient="landscape"/>'
    . '<w:pgMar w:top="851" w:right="851" w:bottom="851" w:left="851" w:header="454" w:footer="454" w:gutter="0"/>'
    . '</w:sectPr></w:body></w:document>';

$styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
    . '<w:docDefaults><w:rPrDefault><w:rPr>'
    . '<w:rFonts w:ascii="TH Sarabun New" w:hAnsi="TH Sarabun New" w:eastAsia="TH Sarabun New" w:cs="TH Sarabun New"/>'
    . '<w:sz w:val="28"/><w:szCs w:val="28"/>'
    . '</w:rPr></w:rPrDefault></w:docDefaults></w:styles>';

$contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
    . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
    . '<Default Extension="xml" ContentType="application/xml"/>'
    . '<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'
    . '<Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>'
    . '</Types>';

$rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
    . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>'
    . '</Relationships>';

$docRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
    . '<Relationship Id="rIdStyles" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
    . '</Relationships>';

$out = __DIR__ . '/../HR/ตารางที่3-วิเคราะห์ปัญหาและผลการทดสอบ-SCiGENIE.docx';
if (is_file($out)) {
    unlink($out);
}
$zip = new ZipArchive();
if ($zip->open($out, ZipArchive::CREATE) !== true) {
    fwrite(STDERR, "cannot create\n");
    exit(1);
}
$zip->addFromString('[Content_Types].xml', $contentTypes);
$zip->addFromString('_rels/.rels', $rels);
$zip->addFromString('word/document.xml', $documentXml);
$zip->addFromString('word/_rels/document.xml.rels', $docRels);
$zip->addFromString('word/styles.xml', $styles);
$zip->close();
echo "created={$out}\nsize=" . filesize($out) . "\n";
