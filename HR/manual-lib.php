<?php
declare(strict_types=1);

function esc(string $s): string
{
    return htmlspecialchars($s, ENT_XML1 | ENT_COMPAT, 'UTF-8');
}

function rPr(bool $bold = false, int $size = 32): string
{
    $b = $bold ? '<w:b/>' : '';
    return '<w:rPr><w:rFonts w:ascii="TH Sarabun New" w:hAnsi="TH Sarabun New" w:eastAsia="TH Sarabun New" w:cs="TH Sarabun New"/>'
        . $b
        . '<w:sz w:val="' . $size . '"/><w:szCs w:val="' . $size . '"/></w:rPr>';
}

function runs(string $text, bool $bold = false, int $size = 32): string
{
    $pr = rPr($bold, $size);
    $parts = preg_split("/\n/", $text) ?: [''];
    $xml = '';
    foreach ($parts as $i => $line) {
        if ($i > 0) {
            $xml .= '<w:r>' . $pr . '<w:br/></w:r>';
        }
        $xml .= '<w:r>' . $pr . '<w:t xml:space="preserve">' . esc($line) . '</w:t></w:r>';
    }
    return $xml;
}

function p(string $text, string $align = 'both', bool $bold = false, int $size = 32, int $after = 160, int $before = 0): string
{
    $jc = match ($align) {
        'center' => 'center',
        'left' => 'left',
        'right' => 'right',
        default => 'both',
    };
    return '<w:p><w:pPr><w:jc w:val="' . $jc . '"/>'
        . '<w:spacing w:before="' . $before . '" w:after="' . $after . '" w:line="276" w:lineRule="auto"/>'
        . '</w:pPr>' . runs($text, $bold, $size) . '</w:p>';
}

function pageBreak(): string
{
    return '<w:p><w:r><w:br w:type="page"/></w:r></w:p>';
}

function tableXml(array $headers, array $rows): string
{
    $cols = count($headers);
    $width = (int) floor(9360 / max(1, $cols));
    $xml = '<w:tbl><w:tblPr><w:tblW w:w="9360" w:type="dxa"/><w:tblBorders>'
        . '<w:top w:val="single" w:sz="8" w:color="8A6A12"/>'
        . '<w:left w:val="single" w:sz="8" w:color="8A6A12"/>'
        . '<w:bottom w:val="single" w:sz="8" w:color="8A6A12"/>'
        . '<w:right w:val="single" w:sz="8" w:color="8A6A12"/>'
        . '<w:insideH w:val="single" w:sz="4" w:color="C4A35A"/>'
        . '<w:insideV w:val="single" w:sz="4" w:color="C4A35A"/>'
        . '</w:tblBorders></w:tblPr><w:tblGrid>';
    for ($i = 0; $i < $cols; $i++) {
        $xml .= '<w:gridCol w:w="' . $width . '"/>';
    }
    $xml .= '</w:tblGrid>';

    $renderRow = static function (array $cells, bool $header) use ($width): string {
        $out = '<w:tr>';
        foreach ($cells as $cell) {
            $shd = $header ? '<w:shd w:val="clear" w:fill="FFF4D2"/>' : '';
            $out .= '<w:tc><w:tcPr><w:tcW w:w="' . $width . '" w:type="dxa"/>' . $shd
                . '<w:vAlign w:val="center"/></w:tcPr>'
                . p((string) $cell, 'left', $header, 32, 40, 40)
                . '</w:tc>';
        }
        return $out . '</w:tr>';
    };

    $xml .= $renderRow($headers, true);
    foreach ($rows as $row) {
        $xml .= $renderRow($row, false);
    }
    return $xml . '</w:tbl>' . p('', 'left', false, 20, 120);
}

function renderSections(array $sections): string
{
    $body = '';
    foreach ($sections as $sec) {
        $level = $sec['level'];
        if ($level === 'spacer') {
            $body .= p(' ', 'left', false, 20, 80);
            continue;
        }
        if ($level === 'pagebreak') {
            $body .= pageBreak();
            continue;
        }
        if ($level === 'cover_kicker') {
            $body .= p((string) $sec['text'], 'center', false, 32, 80);
            continue;
        }
        if ($level === 'cover_title') {
            $body .= p((string) $sec['text'], 'center', true, 44, 80);
            continue;
        }
        if ($level === 'cover_sub') {
            $body .= p((string) $sec['text'], 'center', true, 36, 60);
            continue;
        }
        if ($level === 'h1') {
            $body .= p((string) $sec['text'], 'left', true, 36, 160, 200);
            continue;
        }
        if ($level === 'h2') {
            $body .= p((string) $sec['text'], 'left', true, 32, 100, 160);
            continue;
        }
        if ($level === 'h3') {
            $body .= p((string) $sec['text'], 'left', true, 32, 80, 80);
            continue;
        }
        if ($level === 'table') {
            $body .= tableXml($sec['headers'], $sec['rows']);
            continue;
        }
        $body .= p((string) $sec['text'], (string) ($sec['align'] ?? 'both'), !empty($sec['bold']), 32, 140);
    }
    return $body;
}

function buildDocx(string $out, array $sections): void
{
    $body = renderSections($sections);
    $documentXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<w:document xmlns:mc="http://schemas.openxmlformats.org/markup-compatibility/2006" '
        . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" '
        . 'xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" '
        . 'xmlns:w14="http://schemas.microsoft.com/office/word/2010/wordml" mc:Ignorable="w14">'
        . '<w:body>' . $body
        . '<w:sectPr>'
        . '<w:headerReference w:type="default" r:id="rIdHeader"/>'
        . '<w:footerReference w:type="default" r:id="rIdFooter"/>'
        . '<w:headerReference w:type="first" r:id="rIdHeaderFirst"/>'
        . '<w:footerReference w:type="first" r:id="rIdFooterFirst"/>'
        . '<w:pgSz w:w="11906" w:h="16838"/>'
        . '<w:pgMar w:top="1134" w:right="1134" w:bottom="1134" w:left="1418" w:header="568" w:footer="568" w:gutter="0"/>'
        . '<w:titlePg/>'
        . '<w:pgNumType w:start="1"/>'
        . '</w:sectPr></w:body></w:document>';

    $styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
        . '<w:docDefaults><w:rPrDefault><w:rPr>'
        . '<w:rFonts w:ascii="TH Sarabun New" w:hAnsi="TH Sarabun New" w:eastAsia="TH Sarabun New" w:cs="TH Sarabun New"/>'
        . '<w:sz w:val="32"/><w:szCs w:val="32"/>'
        . '</w:rPr></w:rPrDefault>'
        . '<w:pPrDefault><w:pPr><w:spacing w:after="140" w:line="276" w:lineRule="auto"/><w:jc w:val="both"/></w:pPr></w:pPrDefault>'
        . '</w:docDefaults></w:styles>';

    $header = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<w:hdr xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
        . p('คู่มือการพัฒนาระบบและสถาปัตยกรรมเทคโนโลยี  |  SCiGENIE  |  เอกสารใช้ภายใน', 'right', false, 24, 40)
        . '</w:hdr>';
    $headerFirst = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<w:hdr xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
        . p(' ', 'left', false, 20, 0)
        . '</w:hdr>';

    $pageRun = '<w:r>' . rPr(false, 24) . '<w:fldChar w:fldCharType="begin"/></w:r>'
        . '<w:r>' . rPr(false, 24) . '<w:instrText xml:space="preserve"> PAGE </w:instrText></w:r>'
        . '<w:r>' . rPr(false, 24) . '<w:fldChar w:fldCharType="end"/></w:r>';
    $footer = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<w:ftr xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
        . '<w:p><w:pPr><w:jc w:val="center"/></w:pPr>'
        . '<w:r>' . rPr(false, 24) . '<w:t>คณะวิทยาศาสตร์ มหาวิทยาลัยขอนแก่น    หน้า </w:t></w:r>'
        . $pageRun
        . '</w:p></w:ftr>';
    $footerFirst = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<w:ftr xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:p/></w:ftr>';

    $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
        . '<Default Extension="xml" ContentType="application/xml"/>'
        . '<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'
        . '<Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>'
        . '<Override PartName="/word/header1.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.header+xml"/>'
        . '<Override PartName="/word/header2.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.header+xml"/>'
        . '<Override PartName="/word/footer1.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.footer+xml"/>'
        . '<Override PartName="/word/footer2.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.footer+xml"/>'
        . '</Types>';

    $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>'
        . '</Relationships>';

    $docRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rIdStyles" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
        . '<Relationship Id="rIdHeader" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/header" Target="header1.xml"/>'
        . '<Relationship Id="rIdHeaderFirst" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/header" Target="header2.xml"/>'
        . '<Relationship Id="rIdFooter" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/footer" Target="footer1.xml"/>'
        . '<Relationship Id="rIdFooterFirst" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/footer" Target="footer2.xml"/>'
        . '</Relationships>';

    if (is_file($out)) {
        @unlink($out);
    }
    $zip = new ZipArchive();
    if ($zip->open($out, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        fwrite(STDERR, "cannot create docx\n");
        exit(1);
    }
    $zip->addFromString('[Content_Types].xml', $contentTypes);
    $zip->addFromString('_rels/.rels', $rels);
    $zip->addFromString('word/document.xml', $documentXml);
    $zip->addFromString('word/_rels/document.xml.rels', $docRels);
    $zip->addFromString('word/styles.xml', $styles);
    $zip->addFromString('word/header1.xml', $header);
    $zip->addFromString('word/header2.xml', $headerFirst);
    $zip->addFromString('word/footer1.xml', $footer);
    $zip->addFromString('word/footer2.xml', $footerFirst);
    $zip->close();
    echo "created={$out}\n";
    echo 'size=' . filesize($out) . "\n";
}
