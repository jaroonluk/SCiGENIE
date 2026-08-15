<?php

declare(strict_types=1);

class Auth
{
    public static function check(): bool
    {
        return !empty($_SESSION['scigenie_admin']);
    }

    public static function requireLogin(): void
    {
        if (!self::check()) {
            if (self::isApi()) {
                json_response(['ok' => false, 'error' => 'กรุณาเข้าสู่ระบบผู้จัดการ'], 401);
            }
            header('Location: ' . app_base_path() . '/admin/login.php');
            exit;
        }
    }

    public static function login(string $user, string $pass): bool
    {
        if (!hash_equals((string) config('admin_user'), $user)) {
            return false;
        }
        if (!password_verify($pass, (string) config('admin_pass_hash'))) {
            return false;
        }
        $_SESSION['scigenie_admin'] = $user;
        $_SESSION['scigenie_csrf'] = bin2hex(random_bytes(16));
        return true;
    }

    public static function logout(): void
    {
        unset($_SESSION['scigenie_admin'], $_SESSION['scigenie_csrf']);
    }

    public static function csrf(): string
    {
        if (empty($_SESSION['scigenie_csrf'])) {
            $_SESSION['scigenie_csrf'] = bin2hex(random_bytes(16));
        }
        return $_SESSION['scigenie_csrf'];
    }

    public static function verifyCsrf(?string $token): bool
    {
        return is_string($token) && isset($_SESSION['scigenie_csrf'])
            && hash_equals($_SESSION['scigenie_csrf'], $token);
    }

    private static function isApi(): bool
    {
        return str_contains(str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? ''), '/api/');
    }
}
