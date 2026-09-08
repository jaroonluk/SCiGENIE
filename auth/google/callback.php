<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';

$login = '../../admin/login.php';
$admin = '../../admin/index.php';

function oauth_fail(string $login, string $code, string $email = ''): void
{
    $q = http_build_query(array_filter([
        'error' => $code,
        'email' => $email,
    ]));
    header('Location: ' . $login . '?' . $q);
    exit;
}

if (Auth::check()) {
    header('Location: ' . $admin);
    exit;
}

if (isset($_GET['error'])) {
    $err = (string) $_GET['error'];
    oauth_fail($login, $err === 'access_denied' ? 'denied' : 'oauth_error');
}

$state = (string) ($_GET['state'] ?? '');
$expected = (string) ($_SESSION['scigenie_oauth_state'] ?? '');
if ($expected === '' || $state === '' || !hash_equals($expected, $state)) {
    oauth_fail($login, 'invalid_state');
}

$code = trim((string) ($_GET['code'] ?? ''));
if ($code === '') {
    oauth_fail($login, 'missing_code');
}

try {
    $profile = GoogleOAuth::profileFromCode($code);
} catch (RuntimeException $e) {
    oauth_fail($login, $e->getMessage());
} catch (Throwable $e) {
    oauth_fail($login, 'oauth_error');
}

$loginResult = Auth::loginGoogle($profile);
unset($_SESSION['scigenie_oauth_state']);
if (empty($loginResult['ok'])) {
    oauth_fail(
        $login,
        (string) ($loginResult['error'] ?? 'email_not_allowed'),
        (string) ($loginResult['email'] ?? $profile['email'] ?? '')
    );
}

header('Location: ' . $admin);
exit;
