<?php

declare(strict_types=1);

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
$file = __DIR__ . $uri;

$aliases = [
    '/auth/google/callback' => '/auth/google/callback.php',
    '/auth/google/start' => '/auth/google/start.php',
];

if (isset($aliases[$uri])) {
    require __DIR__ . $aliases[$uri];
    return true;
}

if ($uri !== '/' && is_file($file)) {
    return false;
}

if (is_dir($file) && is_file(rtrim($file, '/\\') . DIRECTORY_SEPARATOR . 'index.php')) {
    require rtrim($file, '/\\') . DIRECTORY_SEPARATOR . 'index.php';
    return true;
}

if ($uri === '/' || $uri === '') {
    require __DIR__ . '/index.php';
    return true;
}

http_response_code(404);
header('Content-Type: text/plain; charset=utf-8');
echo 'Not Found';
return true;
