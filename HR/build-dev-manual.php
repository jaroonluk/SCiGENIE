<?php
declare(strict_types=1);

require __DIR__ . '/manual-lib.php';
require __DIR__ . '/manual-ch1.php';
require __DIR__ . '/manual-ch2.php';
require __DIR__ . '/manual-ch3.php';
require __DIR__ . '/manual-ch4.php';
require __DIR__ . '/manual-ch5.php';
require __DIR__ . '/manual-ch6.php';

$sections = array_merge(
    manual_chapter_1(),
    manual_chapter_2(),
    manual_chapter_3(),
    manual_chapter_4(),
    manual_chapter_5(),
    manual_chapter_6()
);

$out = __DIR__ . '/SCiGENIE-คู่มือการพัฒนาระบบและสถาปัตยกรรมเทคโนโลยี.docx';
buildDocx($out, $sections);
echo 'sections=' . count($sections) . "\n";
