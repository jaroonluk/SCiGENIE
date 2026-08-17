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

    public static function requireSuper(): void
    {
        self::requireLogin();
        if (!self::isSuper()) {
            if (self::isApi()) {
                json_response(['ok' => false, 'error' => 'เฉพาะผู้ดูแลระบบเท่านั้น'], 403);
            }
            header('Location: ' . app_base_path() . '/admin/index.php');
            exit;
        }
    }

    public static function loginGoogle(array $profile): array
    {
        $email = strtolower(trim((string) ($profile['email'] ?? '')));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'error' => 'email_unverified', 'email' => $email];
        }

        try {
            $staff = Eoffice::findByEmail($email);
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => 'eoffice_unavailable', 'email' => $email];
        }

        if ($staff === null) {
            return ['ok' => false, 'error' => 'not_in_eoffice', 'email' => $email];
        }
        if (!Eoffice::isActive($staff)) {
            return ['ok' => false, 'error' => 'eoffice_inactive', 'email' => $email];
        }

        try {
            $faq = new FaqService();
            $faq->ensureReady();
            $admin = self::resolveAdmin($email, $staff, $profile);
        } catch (Throwable $e) {
            error_log('SCiGENIE login: ' . $e->getMessage());
            return ['ok' => false, 'error' => 'oauth_error', 'email' => $email];
        }

        if ($admin === null || empty($admin['is_active'])) {
            return ['ok' => false, 'error' => 'email_not_allowed', 'email' => $email];
        }

        $name = trim((string) ($profile['name'] ?? ''));
        if ($name === '') {
            $name = Eoffice::displayName($staff);
        }

        $_SESSION['scigenie_admin'] = $email;
        $_SESSION['scigenie_admin_id'] = (int) $admin['id'];
        $_SESSION['scigenie_admin_name'] = $name;
        $_SESSION['scigenie_admin_picture'] = trim((string) ($profile['picture'] ?? ''));
        $_SESSION['scigenie_admin_role'] = (string) $admin['role'];
        $_SESSION['scigenie_admin_cats'] = $admin['categories'];
        $_SESSION['scigenie_eoffice_username'] = (string) ($staff['username'] ?? '');
        $_SESSION['scigenie_csrf'] = bin2hex(random_bytes(16));

        $stmt = Db::app()->prepare('UPDATE admin_users SET last_login_at = NOW(), name = :name, eoffice_username = :username WHERE id = :id');
        $stmt->execute([
            'name' => $name,
            'username' => (string) ($staff['username'] ?? ''),
            'id' => (int) $admin['id'],
        ]);

        return ['ok' => true];
    }

    public static function isSuper(): bool
    {
        return ($_SESSION['scigenie_admin_role'] ?? '') === 'super';
    }

    public static function allowedCategories(): ?array
    {
        if (self::isSuper()) {
            return null;
        }
        $cats = $_SESSION['scigenie_admin_cats'] ?? [];
        return is_array($cats) ? array_values(array_map('strval', $cats)) : [];
    }

    public static function canManageCategory(string $name): bool
    {
        $allowed = self::allowedCategories();
        if ($allowed === null) {
            return true;
        }
        return in_array($name, $allowed, true);
    }

    public static function requireCategory(string $name): void
    {
        self::requireLogin();
        if (!self::canManageCategory($name)) {
            json_response(['ok' => false, 'error' => 'ไม่มีสิทธิ์จัดการหมวดนี้'], 403);
        }
    }

    public static function user(): array
    {
        return [
            'id' => (int) ($_SESSION['scigenie_admin_id'] ?? 0),
            'email' => (string) ($_SESSION['scigenie_admin'] ?? ''),
            'name' => (string) ($_SESSION['scigenie_admin_name'] ?? ''),
            'picture' => (string) ($_SESSION['scigenie_admin_picture'] ?? ''),
            'role' => (string) ($_SESSION['scigenie_admin_role'] ?? 'manager'),
            'categories' => self::allowedCategories(),
            'eoffice_username' => (string) ($_SESSION['scigenie_eoffice_username'] ?? ''),
        ];
    }

    public static function logout(): void
    {
        unset(
            $_SESSION['scigenie_admin'],
            $_SESSION['scigenie_admin_id'],
            $_SESSION['scigenie_admin_name'],
            $_SESSION['scigenie_admin_picture'],
            $_SESSION['scigenie_admin_role'],
            $_SESSION['scigenie_admin_cats'],
            $_SESSION['scigenie_eoffice_username'],
            $_SESSION['scigenie_csrf'],
            $_SESSION['scigenie_oauth_state']
        );
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

    public static function filterFaqs(array $faqs): array
    {
        $allowed = self::allowedCategories();
        if ($allowed === null) {
            return $faqs;
        }
        return array_values(array_filter(
            $faqs,
            static fn ($row) => in_array((string) ($row['category'] ?? ''), $allowed, true)
        ));
    }

    public static function filterCategories(array $categories): array
    {
        $allowed = self::allowedCategories();
        if ($allowed === null) {
            return $categories;
        }
        return array_values(array_filter(
            $categories,
            static fn ($row) => in_array((string) ($row['name'] ?? ''), $allowed, true)
        ));
    }

    public static function listAdmins(): array
    {
        $sql = 'SELECT u.id, u.email, u.eoffice_username, u.name, u.role, u.is_active, u.last_login_at,
                       GROUP_CONCAT(c.name ORDER BY c.sort_order, c.name SEPARATOR "||") AS categories
                FROM admin_users u
                LEFT JOIN admin_user_categories auc ON auc.admin_user_id = u.id
                LEFT JOIN categories c ON c.id = auc.category_id
                GROUP BY u.id
                ORDER BY u.role DESC, u.name, u.email';
        $rows = [];
        foreach (Db::app()->query($sql)->fetchAll() as $row) {
            $cats = trim((string) ($row['categories'] ?? ''));
            $row['categories'] = $cats === '' ? [] : explode('||', $cats);
            $row['id'] = (int) $row['id'];
            $row['is_active'] = (int) $row['is_active'];
            $rows[] = $row;
        }
        return $rows;
    }

    public static function saveAdmin(array $input): array
    {
        $email = strtolower(trim((string) ($input['email'] ?? '')));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('กรุณาระบุอีเมลให้ถูกต้อง');
        }
        $role = (string) ($input['role'] ?? 'manager') === 'super' ? 'super' : 'manager';
        $active = !empty($input['is_active']) ? 1 : 0;
        $categoryNames = array_values(array_unique(array_filter(array_map('strval', (array) ($input['categories'] ?? [])))));
        if ($role === 'manager' && $categoryNames === []) {
            throw new InvalidArgumentException('กรุณาเลือกอย่างน้อย 1 หมวดที่ให้จัดการ');
        }

        try {
            $staff = Eoffice::findByEmail($email);
        } catch (Throwable $e) {
            throw new RuntimeException('เชื่อมฐานข้อมูลบุคลากรไม่สำเร็จ');
        }
        if ($staff === null) {
            throw new InvalidArgumentException('ไม่พบอีเมลนี้ในระบบ eoffice');
        }
        if (!Eoffice::isActive($staff)) {
            throw new InvalidArgumentException('สถานะบุคลากรนี้ไม่สามารถเพิ่มได้');
        }

        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '') {
            $name = Eoffice::displayName($staff);
        }

        $pdo = Db::app();
        $id = (int) ($input['id'] ?? 0);
        if ($id > 0) {
            $stmt = $pdo->prepare(
                'UPDATE admin_users
                 SET email = :email, eoffice_username = :username, name = :name, role = :role, is_active = :active
                 WHERE id = :id'
            );
            $stmt->execute([
                'email' => $email,
                'username' => (string) ($staff['username'] ?? ''),
                'name' => $name,
                'role' => $role,
                'active' => $active,
                'id' => $id,
            ]);
        } else {
            $exists = $pdo->prepare('SELECT id FROM admin_users WHERE email = :email LIMIT 1');
            $exists->execute(['email' => $email]);
            $foundId = $exists->fetchColumn();
            if ($foundId) {
                throw new InvalidArgumentException('อีเมลนี้ถูกเพิ่มในระบบแล้ว');
            }
            $stmt = $pdo->prepare(
                'INSERT INTO admin_users (email, eoffice_username, name, role, is_active)
                 VALUES (:email, :username, :name, :role, :active)'
            );
            $stmt->execute([
                'email' => $email,
                'username' => (string) ($staff['username'] ?? ''),
                'name' => $name,
                'role' => $role,
                'active' => $active,
            ]);
            $id = (int) $pdo->lastInsertId();
        }

        $pdo->prepare('DELETE FROM admin_user_categories WHERE admin_user_id = :id')->execute(['id' => $id]);
        if ($role === 'manager') {
            $faq = new FaqService();
            $ins = $pdo->prepare('INSERT INTO admin_user_categories (admin_user_id, category_id) VALUES (:uid, :cid)');
            foreach ($categoryNames as $catName) {
                $ins->execute(['uid' => $id, 'cid' => $faq->categoryId($catName, false)]);
            }
        }

        return ['id' => $id];
    }

    public static function deleteAdmin(int $id): void
    {
        $me = (int) ($_SESSION['scigenie_admin_id'] ?? 0);
        if ($id === $me) {
            throw new InvalidArgumentException('ไม่สามารถลบบัญชีของตนเองได้');
        }
        $stmt = Db::app()->prepare('DELETE FROM admin_users WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    private static function resolveAdmin(string $email, array $staff, array $profile): ?array
    {
        $row = self::findAdminByEmail($email);
        if ($row === null) {
            $boot = array_map('strtolower', (array) config('bootstrap_super_emails', []));
            if (!in_array($email, $boot, true)) {
                return null;
            }
            $name = trim((string) ($profile['name'] ?? '')) ?: Eoffice::displayName($staff);
            $ins = Db::app()->prepare(
                'INSERT INTO admin_users (email, eoffice_username, name, role, is_active)
                 VALUES (:email, :username, :name, "super", 1)'
            );
            $ins->execute([
                'email' => $email,
                'username' => (string) ($staff['username'] ?? ''),
                'name' => $name,
            ]);
            $row = self::findAdminByEmail($email);
        }
        return $row;
    }

    private static function findAdminByEmail(string $email): ?array
    {
        $stmt = Db::app()->prepare('SELECT * FROM admin_users WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => $email]);
        $row = $stmt->fetch();
        if (!is_array($row)) {
            return null;
        }
        $cats = [];
        $cstmt = Db::app()->prepare(
            'SELECT c.name FROM admin_user_categories auc
             INNER JOIN categories c ON c.id = auc.category_id
             WHERE auc.admin_user_id = :id'
        );
        $cstmt->execute(['id' => (int) $row['id']]);
        foreach ($cstmt->fetchAll() as $cat) {
            $cats[] = (string) $cat['name'];
        }
        $row['categories'] = $cats;
        return $row;
    }

    private static function isApi(): bool
    {
        return str_contains(str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? ''), '/api/');
    }
}
