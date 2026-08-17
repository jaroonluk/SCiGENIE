<?php

declare(strict_types=1);

class GoogleOAuth
{
    public static function authorizeUrl(string $state): string
    {
        $clientId = (string) config('google_client_id');
        if ($clientId === '') {
            throw new RuntimeException('oauth_error');
        }
        return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
            'client_id' => $clientId,
            'redirect_uri' => google_redirect_uri(),
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'prompt' => 'select_account',
            'access_type' => 'online',
            'include_granted_scopes' => 'true',
            'state' => $state,
        ], '', '&', PHP_QUERY_RFC3986);
    }

    public static function profileFromCode(string $code): array
    {
        $code = trim($code);
        $clientId = (string) config('google_client_id');
        $secret = (string) config('google_client_secret');
        if ($code === '' || $clientId === '' || $secret === '') {
            throw new RuntimeException('token_failed');
        }

        $token = self::httpJson('https://oauth2.googleapis.com/token', [
            'method' => 'POST',
            'fields' => [
                'code' => $code,
                'client_id' => $clientId,
                'client_secret' => $secret,
                'redirect_uri' => google_redirect_uri(),
                'grant_type' => 'authorization_code',
            ],
        ]);
        $access = trim((string) ($token['access_token'] ?? ''));
        if ($access === '') {
            throw new RuntimeException('token_failed');
        }
        return self::profileFromAccessToken($access);
    }

    public static function profileFromAccessToken(string $accessToken): array
    {
        $accessToken = trim($accessToken);
        if ($accessToken === '') {
            throw new RuntimeException('token_failed');
        }

        $info = self::httpJson('https://www.googleapis.com/oauth2/v3/userinfo', [
            'method' => 'GET',
            'headers' => ['Authorization: Bearer ' . $accessToken],
        ]);

        return self::profileFromUserinfo($info);
    }

    public static function profileFromIdToken(string $jwt): array
    {
        $jwt = trim($jwt);
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            throw new RuntimeException('token_failed');
        }

        $header = json_decode(self::b64url($parts[0]), true);
        $payload = json_decode(self::b64url($parts[1]), true);
        if (!is_array($header) || !is_array($payload)) {
            throw new RuntimeException('token_failed');
        }

        $kid = (string) ($header['kid'] ?? '');
        $pem = self::googleCertPem($kid);
        $sig = self::b64urlRaw($parts[2]);
        $ok = openssl_verify($parts[0] . '.' . $parts[1], $sig, $pem, OPENSSL_ALGO_SHA256);
        if ($ok !== 1) {
            throw new RuntimeException('token_failed');
        }

        $aud = (string) ($payload['aud'] ?? '');
        $iss = (string) ($payload['iss'] ?? '');
        $exp = (int) ($payload['exp'] ?? 0);
        $clientId = (string) config('google_client_id');
        if ($aud !== $clientId) {
            throw new RuntimeException('token_failed');
        }
        if (!in_array($iss, ['https://accounts.google.com', 'accounts.google.com'], true)) {
            throw new RuntimeException('token_failed');
        }
        if ($exp < time() - 30) {
            throw new RuntimeException('token_failed');
        }

        return self::profileFromUserinfo($payload);
    }

    private static function profileFromUserinfo(array $info): array
    {
        $email = strtolower(trim((string) ($info['email'] ?? '')));
        $verified = $info['email_verified'] ?? false;
        if ($email === '') {
            throw new RuntimeException('userinfo_failed');
        }
        if ($verified !== true && $verified !== 'true') {
            throw new RuntimeException('email_unverified');
        }

        return [
            'email' => $email,
            'name' => trim((string) ($info['name'] ?? '')),
            'picture' => trim((string) ($info['picture'] ?? '')),
        ];
    }

    private static function googleCertPem(string $kid): string
    {
        $raw = self::request('https://www.googleapis.com/oauth2/v1/certs', 'GET', [], '');
        $certs = json_decode($raw, true);
        if (!is_array($certs) || $kid === '' || empty($certs[$kid])) {
            throw new RuntimeException('token_failed');
        }
        return (string) $certs[$kid];
    }

    private static function b64url(string $data): string
    {
        return self::b64urlRaw($data);
    }

    private static function b64urlRaw(string $data): string
    {
        $pad = strlen($data) % 4;
        if ($pad > 0) {
            $data .= str_repeat('=', 4 - $pad);
        }
        $raw = base64_decode(strtr($data, '-_', '+/'), true);
        return is_string($raw) ? $raw : '';
    }

    private static function httpJson(string $url, array $opt): array
    {
        $method = strtoupper((string) ($opt['method'] ?? 'GET'));
        $headers = (array) ($opt['headers'] ?? []);
        $body = '';
        if ($method === 'POST') {
            $body = http_build_query((array) ($opt['fields'] ?? []));
            $headers[] = 'Content-Type: application/x-www-form-urlencoded';
        }

        $raw = self::request($url, $method, $headers, $body);
        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }

    private static function request(string $url, string $method, array $headers, string $body): string
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT => 20,
                CURLOPT_CUSTOMREQUEST => $method,
                CURLOPT_HTTPHEADER => $headers,
            ]);
            if ($method === 'POST') {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            }
            $raw = curl_exec($ch);
            curl_close($ch);
            return is_string($raw) ? $raw : '';
        }

        $header = $headers !== [] ? implode("\r\n", $headers) . "\r\n" : '';
        $ctx = stream_context_create([
            'http' => [
                'method' => $method,
                'header' => $header,
                'content' => $body,
                'timeout' => 20,
                'ignore_errors' => true,
            ],
        ]);
        $raw = @file_get_contents($url, false, $ctx);
        return is_string($raw) ? $raw : '';
    }
}
