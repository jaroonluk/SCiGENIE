<?php

declare(strict_types=1);

function request_is_https(): bool
{
    $forwarded = strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
    return $forwarded === 'https'
        || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    if (request_is_https()) {
        ini_set('session.cookie_secure', '1');
    }
    session_start();
}

date_default_timezone_set('Asia/Bangkok');
mb_internal_encoding('UTF-8');

$config = require __DIR__ . '/config.php';

spl_autoload_register(static function (string $class): void {
    $path = __DIR__ . '/' . $class . '.php';
    if (is_file($path)) {
        require_once $path;
    }
});

function config(?string $key = null, mixed $default = null): mixed
{
    global $config;
    if ($key === null) {
        return $config;
    }
    return $config[$key] ?? $default;
}

function app_base_path(): string
{
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    if (preg_match('#/(api|admin|auth)/#', $script)) {
        return preg_replace('#/(api|admin|auth)/.*$#', '', $script) ?: '';
    }
    return rtrim(str_replace('\\', '/', dirname($script)), '/');
}

function app_base_url(): string
{
    $scheme = request_is_https() ? 'https' : 'http';
    $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
    return $scheme . '://' . $host . app_base_path();
}

function google_js_origin(): string
{
    $fromEnv = trim((string) (getenv('GOOGLE_JS_ORIGIN') ?: config('google_js_origin', '')));
    if ($fromEnv !== '') {
        return rtrim($fromEnv, '/');
    }
    $parts = parse_url(app_base_url()) ?: [];
    $scheme = (string) ($parts['scheme'] ?? 'https');
    $host = (string) ($parts['host'] ?? '');
    $port = isset($parts['port']) ? ':' . $parts['port'] : '';
    return $scheme . '://' . $host . $port;
}

function google_redirect_uri(): string
{
    $fromEnv = trim((string) (getenv('GOOGLE_REDIRECT_URI') ?: config('google_redirect_uri', '')));
    if ($fromEnv !== '') {
        return $fromEnv;
    }
    return rtrim(app_base_url(), '/') . '/auth/google/callback.php';
}

function logo_url(): string
{
    return app_base_path() . '/assets/img/logo.jpg';
}

function json_response(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Headers: Content-Type');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function request_json(): array
{
    $raw = file_get_contents('php://input') ?: '';
    if ($raw === '') {
        return $_POST;
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}
