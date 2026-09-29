<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

$faqs = (new FaqService())->all();
$m = new Matcher();
$cat = 'รับเข้านักศึกษา';
$qs = ['พสวท.', 'พสวท', 'พสวท เปิดรับสมัครเมื่อไหร่'];
foreach ($qs as $q) {
    $r = $m->ask($faqs, $q, $cat);
    echo 'Q: ' . $q . PHP_EOL;
    echo '  matched=' . (!empty($r['matched']) ? 'yes' : 'no') . PHP_EOL;
    if (!empty($r['matched'])) {
        echo '  faq=' . ($r['item']['question'] ?? '') . PHP_EOL;
        echo '  score=' . ($r['item']['score'] ?? '') . PHP_EOL;
    } else {
        foreach (($r['items'] ?? []) as $i => $item) {
            echo '  cand' . ($i + 1) . ': score=' . ($item['score'] ?? '') . ' ' . ($item['question'] ?? '') . PHP_EOL;
        }
    }
    echo PHP_EOL;
}
