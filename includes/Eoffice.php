<?php

declare(strict_types=1);

class Eoffice
{
    private const INACTIVE_LEVELS = ['4', '5'];

    public static function findByEmail(string $email): ?array
    {
        $email = strtolower(trim($email));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        $sql = 'SELECT username, userid, title, fname, lname, email, email_other, position, pd_level
                FROM tbluser
                WHERE LOWER(TRIM(IFNULL(email, ""))) = :email
                   OR LOWER(TRIM(IFNULL(email_other, ""))) = :email2
                LIMIT 1';
        $stmt = Db::eoffice()->prepare($sql);
        $stmt->execute(['email' => $email, 'email2' => $email]);
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    public static function search(string $q, int $limit = 20): array
    {
        $q = trim($q);
        if (mb_strlen($q) < 2) {
            return [];
        }
        $like = '%' . self::escapeLike($q) . '%';
        $sql = 'SELECT username, fname, lname, email, email_other, position, pd_level
                FROM tbluser
                WHERE (
                    email LIKE :q1 ESCAPE "\\\\"
                    OR email_other LIKE :q2 ESCAPE "\\\\"
                    OR fname LIKE :q3 ESCAPE "\\\\"
                    OR lname LIKE :q4 ESCAPE "\\\\"
                    OR username LIKE :q5 ESCAPE "\\\\"
                    OR CONCAT(fname, " ", lname) LIKE :q6 ESCAPE "\\\\"
                )
                AND IFNULL(pd_level, "0") NOT IN ("4", "5")
                ORDER BY fname, lname
                LIMIT ' . max(1, min(40, $limit));
        $stmt = Db::eoffice()->prepare($sql);
        $stmt->execute([
            'q1' => $like,
            'q2' => $like,
            'q3' => $like,
            'q4' => $like,
            'q5' => $like,
            'q6' => $like,
        ]);
        $out = [];
        foreach ($stmt->fetchAll() as $row) {
            $email = strtolower(trim((string) ($row['email'] ?: $row['email_other'])));
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }
            $out[] = [
                'username' => (string) $row['username'],
                'name' => trim((string) $row['fname'] . ' ' . (string) $row['lname']),
                'email' => $email,
                'position' => (string) ($row['position'] ?? ''),
            ];
        }
        return $out;
    }

    public static function isActive(?array $row): bool
    {
        if ($row === null) {
            return false;
        }
        $level = trim((string) ($row['pd_level'] ?? '0'));
        return !in_array($level, self::INACTIVE_LEVELS, true);
    }

    public static function displayName(array $row): string
    {
        $name = trim((string) ($row['fname'] ?? '') . ' ' . (string) ($row['lname'] ?? ''));
        return $name !== '' ? $name : (string) ($row['username'] ?? '');
    }

    private static function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
