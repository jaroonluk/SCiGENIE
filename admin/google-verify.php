<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'error' => 'oauth_error'], 405);
}

if (Auth::check()) {
    json_response(['ok' => true, 'redirect' => 'index.php']);
}

$payload = request_json();
$csrf = (string) ($payload['csrf'] ?? '');
$expected = (string) ($_SESSION['scigenie_login_csrf'] ?? '');
if ($expected === '' || !hash_equals($expected, $csrf)) {
    json_response(['ok' => false, 'error' => 'invalid_state'], 400);
}

try {
    if (!empty($payload['credential'])) {
        $profile = GoogleOAuth::profileFromIdToken((string) $payload['credential']);
    } elseif (!empty($payload['access_token'])) {
        $profile = GoogleOAuth::profileFromAccessToken((string) $payload['access_token']);
    } else {
        json_response(['ok' => false, 'error' => 'missing_code'], 400);
    }
} catch (RuntimeException $e) {
    json_response(['ok' => false, 'error' => $e->getMessage()], 401);
} catch (Throwable $e) {
    json_response(['ok' => false, 'error' => 'oauth_error'], 500);
}

$login = Auth::loginGoogle($profile);
if (empty($login['ok'])) {
    json_response([
        'ok' => false,
        'error' => (string) ($login['error'] ?? 'email_not_allowed'),
        'email' => (string) ($login['email'] ?? $profile['email'] ?? ''),
    ], 403);
}

unset($_SESSION['scigenie_login_csrf']);
json_response(['ok' => true, 'redirect' => 'index.php']);
