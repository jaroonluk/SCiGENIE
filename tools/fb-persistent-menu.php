<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

if (!MessengerBot::configured()) {
    fwrite(STDERR, "Set FACEBOOK_PAGE_ACCESS_TOKEN and FACEBOOK_APP_SECRET in .env first.\n");
    exit(1);
}

$ok = MessengerBot::setupPersistentMenu(true);
echo $ok ? "Persistent menu saved.\n" : "Failed to save persistent menu. Check error log.\n";
exit($ok ? 0 : 1);
