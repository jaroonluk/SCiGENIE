<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

echo "SCiGENIE rebuild FAQ embeddings\n";
echo 'config_model=' . (string) config('openai_embedding_model', 'local-hash-v1') . PHP_EOL;

if (!FaqEmbedder::configured()) {
    echo "ERROR: embedding not configured\n";
    exit(1);
}

$probe = FaqEmbedder::embedText('ทดสอบคณะวิทยาศาสตร์');
if ($probe === null) {
    echo "ERROR: embedText failed\n";
    exit(1);
}

echo 'effective_model=' . FaqEmbedder::effectiveModel() . PHP_EOL;
echo 'vector_dim=' . count($probe) . PHP_EOL;

$faqs = (new FaqService())->all(true);
echo 'faqs=' . count($faqs) . PHP_EOL;
$stats = FaqEmbedder::ensureAll($faqs);
echo 'updated=' . $stats['updated'] . ' skipped=' . $stats['skipped'] . ' failed=' . $stats['failed'] . PHP_EOL;
echo ($stats['failed'] > 0 && $stats['updated'] === 0) ? "DONE with errors\n" : "DONE\n";
exit($stats['failed'] > 0 && $stats['updated'] === 0 ? 1 : 0);
