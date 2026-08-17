<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';

if (Auth::check()) {
    header('Location: ../../admin/index.php');
    exit;
}

if ((string) config('google_client_id') === '') {
    header('Location: ../../admin/login.php?error=oauth_error');
    exit;
}

$state = bin2hex(random_bytes(16));
$_SESSION['scigenie_oauth_state'] = $state;

try {
    header('Location: ' . GoogleOAuth::authorizeUrl($state));
} catch (Throwable $e) {
    header('Location: ../../admin/login.php?error=oauth_error');
}
exit;
