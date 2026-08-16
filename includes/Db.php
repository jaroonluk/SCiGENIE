<?php

declare(strict_types=1);

class Db
{
    private static ?PDO $app = null;
    private static ?PDO $eoffice = null;

    public static function app(): PDO
    {
        if (self::$app instanceof PDO) {
            return self::$app;
        }
        self::$app = self::connect(
            (string) config('db_host', 'db'),
            (int) config('db_port', 3306),
            (string) config('db_name', 'scigeniedb'),
            (string) config('db_user', 'scigenie'),
            (string) config('db_pass', 'scigenie')
        );
        return self::$app;
    }

    public static function eoffice(): PDO
    {
        if (self::$eoffice instanceof PDO) {
            return self::$eoffice;
        }
        self::$eoffice = self::connect(
            (string) config('eoffice_db_host', 'sci-office-db'),
            (int) config('eoffice_db_port', 3306),
            (string) config('eoffice_db_name', 'eoffice'),
            (string) config('eoffice_db_user', 'root'),
            (string) config('eoffice_db_pass', '')
        );
        return self::$eoffice;
    }

    private static function connect(string $host, int $port, string $name, string $user, string $pass): PDO
    {
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $name);
        return new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }
}
