<?php

declare(strict_types=1);

function request_is_https(): bool
{
    $forwarded = strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
    if (str_contains($forwarded, 'https')) {
        return true;
    }
    $ssl = strtolower((string) ($_SERVER['HTTP_X_FORWARDED_SSL'] ?? $_SERVER['HTTP_FRONT_END_HTTPS'] ?? ''));
    if (in_array($ssl, ['on', '1', 'true', 'yes'], true)) {
        return true;
    }
    $host = strtolower(preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? '')) ?? '');
    if ($host === 'sci.kku.ac.th' || $host === 'www.sci.kku.ac.th') {
        return true;
    }
    return !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
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
ini_set('log_errors', '1');
if (request_is_https()) {
    ini_set('display_errors', '0');
}
if (ob_get_level() === 0) {
    ob_start();
}

load_dotenv(dirname(__DIR__) . '/.env');

function env_value(string $name, string $default = ''): string
{
    $value = getenv($name);
    if (is_string($value) && $value !== '') {
        return $value;
    }
    $fallback = $_ENV[$name] ?? $_SERVER[$name] ?? $default;
    return is_string($fallback) && $fallback !== '' ? $fallback : $default;
}

$config = require __DIR__ . '/config.php';

function load_dotenv(string $path): void
{
    if (!is_file($path) || !is_readable($path)) {
        return;
    }
    $lines = file($path, FILE_IGNORE_NEW_LINES) ?: [];
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }
        [$name, $value] = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);
        if ($name === '') {
            continue;
        }
        if (
            (str_starts_with($value, '"') && str_ends_with($value, '"'))
            || (str_starts_with($value, "'") && str_ends_with($value, "'"))
        ) {
            $value = substr($value, 1, -1);
        }
        if (getenv($name) === false || getenv($name) === '') {
            putenv($name . '=' . $value);
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
}

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

function public_request_path(): string
{
    foreach (['HTTP_X_ORIGINAL_URI', 'HTTP_X_FORWARDED_URI', 'HTTP_X_REWRITE_URL', 'REQUEST_URI'] as $key) {
        $raw = (string) ($_SERVER[$key] ?? '');
        if ($raw === '') {
            continue;
        }
        $path = str_replace('\\', '/', parse_url($raw, PHP_URL_PATH) ?: $raw);
        if ($path !== '') {
            return $path;
        }
    }
    return str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/')) ?: '/';
}

function app_base_path(): string
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    $fromEnv = trim((string) (getenv('APP_BASE_PATH') ?: config('app_base_path', '')));
    if ($fromEnv !== '') {
        $cached = normalize_app_base_path($fromEnv);
        return $cached;
    }

    $host = strtolower(preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? '')) ?? '');
    if ($host === 'sci.kku.ac.th' || $host === 'www.sci.kku.ac.th') {
        $cached = '/app/SCiGENIE';
        return $cached;
    }

    $uri = public_request_path();
    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    foreach ([$uri, $script] as $path) {
        if (preg_match('#^(.*?)/(api|admin|auth|fb)(/|$)#', $path, $m)) {
            $cached = normalize_app_base_path(rtrim($m[1], '/'));
            return $cached;
        }
    }

    $dir = rtrim(str_replace('\\', '/', dirname($script !== '' ? $script : $uri)), '/');
    $cached = normalize_app_base_path(($dir === '/' || $dir === '.' || $dir === '\\') ? '' : $dir);
    return $cached;
}

function normalize_app_base_path(string $base): string
{
    $base = trim(str_replace('\\', '/', $base));
    if ($base === '' || $base === '/') {
        return '';
    }
    $base = '/' . trim($base, '/');
    if (preg_match('#^/app/scig[ei]nie$#i', $base)) {
        return '/app/SCiGENIE';
    }
    return $base;
}

function asset_version(string $relative): string
{
    $file = dirname(__DIR__) . '/' . ltrim($relative, '/');
    return is_file($file) ? (string) filemtime($file) : '1';
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
    $host = strtolower(preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? '')) ?? '');
    if ($host === 'sci.kku.ac.th' || $host === 'www.sci.kku.ac.th') {
        return 'https://sci.kku.ac.th/app/SCiGENIE/auth/google/callback.php';
    }
    $fromEnv = trim((string) (getenv('GOOGLE_REDIRECT_URI') ?: config('google_redirect_uri', '')));
    if ($fromEnv !== '') {
        return $fromEnv;
    }
    return rtrim(app_base_url(), '/') . '/auth/google/callback.php';
}

function logo_url(): string
{
    return app_base_path() . '/assets/img/SCiGENIE_logo.png';
}

function json_response(array $data, int $status = 200): void
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Headers: Content-Type');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function flush_http_response(int $status = 200, string $body = '{"ok":true}'): void
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Connection: close');
    header('Content-Length: ' . strlen($body));
    echo $body;
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
        return;
    }
    if (function_exists('litespeed_finish_request')) {
        litespeed_finish_request();
        return;
    }
    flush();
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
