<?php

declare(strict_types=1);

class FaqService
{
    private static ?array $faqCache = null;
    private static int $faqCacheTime = 0;
    private static ?array $categoriesCache = null;
    private static bool $ready = false;

    public function all(bool $forceRefresh = false): array
    {
        $ttl = max(60, (int) config('cache_ttl', 600));
        $now = time();
        if (!$forceRefresh && self::$faqCache !== null && ($now - self::$faqCacheTime) < $ttl) {
            return self::$faqCache;
        }
        $this->ensureReady();
        $sql = 'SELECT f.id, c.name AS category, f.question, f.keyword, f.answer, f.keyword_back
                FROM faqs f
                INNER JOIN categories c ON c.id = f.category_id
                ORDER BY c.sort_order, c.name, f.id';
        $rows = Db::app()->query($sql)->fetchAll();
        foreach ($rows as &$row) {
            $row['id'] = (string) $row['id'];
            $row['source'] = 'db';
        }
        unset($row);
        self::$faqCache = $rows;
        self::$faqCacheTime = $now;
        return $rows;
    }

    public function match(string $question, string $category = ''): array
    {
        $result = (new Matcher())->ask($this->all(), $question, $category);
        return WebSources::enrich($result, $question);
    }

    public function suggest(string $category = '', int $limit = 6): array
    {
        $limit = max(1, min(20, $limit));
        if ($category !== '') {
            $this->ensureReady();
            $stmt = Db::app()->prepare(
                'SELECT f.id, c.name AS category, f.question
                 FROM faqs f
                 INNER JOIN categories c ON c.id = f.category_id
                 WHERE c.name = :category AND f.question NOT LIKE :greet
                 ORDER BY f.id
                 LIMIT ' . (int) $limit
            );
            $stmt->execute(['category' => $category, 'greet' => '%สวัสดี%']);
            $out = [];
            foreach ($stmt->fetchAll() as $row) {
                $out[] = [
                    'id' => (string) $row['id'],
                    'category' => (string) $row['category'],
                    'question' => (string) $row['question'],
                ];
            }
            if ($out !== []) {
                return $out;
            }
        }
        return (new Matcher())->suggestions($this->all(), $category, $limit);
    }

    public function findById(string $id): ?array
    {
        $this->ensureReady();
        $stmt = Db::app()->prepare(
            'SELECT f.id, c.name AS category, f.question, f.keyword, f.answer, f.keyword_back
             FROM faqs f
             INNER JOIN categories c ON c.id = f.category_id
             WHERE f.id = :id
             LIMIT 1'
        );
        $stmt->execute(['id' => (int) $id]);
        $row = $stmt->fetch();
        if (!is_array($row)) {
            return null;
        }
        $row['id'] = (string) $row['id'];
        $row['source'] = 'db';
        return $row;
    }

    public function categories(bool $forceRefresh = false): array
    {
        if (!$forceRefresh && self::$categoriesCache !== null) {
            return self::$categoriesCache;
        }
        $this->ensureReady();
        $sql = 'SELECT c.id, c.name, c.short_name, c.icon, c.hint, COUNT(f.id) AS count
                FROM categories c
                LEFT JOIN faqs f ON f.category_id = c.id
                GROUP BY c.id, c.name, c.short_name, c.icon, c.hint, c.sort_order
                ORDER BY c.sort_order, c.name';
        $items = [];
        foreach (Db::app()->query($sql)->fetchAll() as $row) {
            $meta = Categories::meta((string) $row['name']);
            $items[] = [
                'id' => (int) $row['id'],
                'name' => (string) $row['name'],
                'short' => (string) ($row['short_name'] !== '' ? $row['short_name'] : $meta['short']),
                'icon' => (string) ($row['icon'] !== '' ? $row['icon'] : $meta['icon']),
                'hint' => (string) ($row['hint'] !== '' ? $row['hint'] : $meta['hint']),
                'count' => (int) $row['count'],
            ];
        }
        self::$categoriesCache = $items;
        return $items;
    }

    public static function clearCache(): void
    {
        self::$faqCache = null;
        self::$faqCacheTime = 0;
        self::$categoriesCache = null;
    }

    public function add(array $item): array
    {
        $this->ensureReady();
        $fields = $this->validatedFields($item);
        $categoryId = $this->categoryId($fields['category'], true);
        $stmt = Db::app()->prepare(
            'INSERT INTO faqs (category_id, question, keyword, keyword_back, answer)
             VALUES (:category_id, :question, :keyword, :keyword_back, :answer)'
        );
        $stmt->execute([
            'category_id' => $categoryId,
            'question' => $fields['question'],
            'keyword' => $fields['keyword'],
            'keyword_back' => $fields['keyword_back'],
            'answer' => $fields['answer'],
        ]);
        $row = $this->findById((string) Db::app()->lastInsertId());
        self::clearCache();
        return [
            'item' => $row,
            'sheet' => ['ok' => true, 'mode' => 'db', 'message' => 'บันทึกคำถามลงฐานข้อมูลแล้ว'],
        ];
    }

    public function update(string $id, array $item): array
    {
        $this->ensureReady();
        $fields = $this->validatedFields($item);
        $current = $this->findById($id);
        if ($current === null) {
            throw new InvalidArgumentException('ไม่พบคำถามที่ต้องการแก้ไข');
        }
        $categoryId = $this->categoryId($fields['category'], true);
        $stmt = Db::app()->prepare(
            'UPDATE faqs
             SET category_id = :category_id, question = :question, keyword = :keyword,
                 keyword_back = :keyword_back, answer = :answer
             WHERE id = :id'
        );
        $stmt->execute([
            'category_id' => $categoryId,
            'question' => $fields['question'],
            'keyword' => $fields['keyword'],
            'keyword_back' => $fields['keyword_back'],
            'answer' => $fields['answer'],
            'id' => (int) $id,
        ]);
        $row = $this->findById($id);
        self::clearCache();
        return [
            'item' => $row,
            'sheet' => ['ok' => true, 'mode' => 'db', 'message' => 'แก้ไขคำถามในฐานข้อมูลแล้ว'],
        ];
    }

    public function delete(string $id): array
    {
        $this->ensureReady();
        $current = $this->findById($id);
        if ($current === null) {
            throw new InvalidArgumentException('ไม่พบคำถามที่ต้องการลบ');
        }
        $stmt = Db::app()->prepare('DELETE FROM faqs WHERE id = :id');
        $stmt->execute(['id' => (int) $id]);
        self::clearCache();
        return [
            'id' => $id,
            'sheet' => ['ok' => true, 'mode' => 'db', 'message' => 'ลบคำถามจากฐานข้อมูลแล้ว'],
        ];
    }

    public function categoryId(string $name, bool $create = false): int
    {
        $name = trim($name);
        if ($name === '') {
            throw new InvalidArgumentException('กรุณาเลือกหมวด');
        }
        $stmt = Db::app()->prepare('SELECT id FROM categories WHERE name = :name LIMIT 1');
        $stmt->execute(['name' => $name]);
        $id = $stmt->fetchColumn();
        if ($id !== false) {
            return (int) $id;
        }
        if (!$create) {
            throw new InvalidArgumentException('ไม่พบหมวดนี้');
        }
        $meta = Categories::meta($name);
        $ins = Db::app()->prepare(
            'INSERT INTO categories (name, short_name, icon, hint, sort_order)
             VALUES (:name, :short_name, :icon, :hint, :sort_order)'
        );
        $ins->execute([
            'name' => $name,
            'short_name' => $meta['short'],
            'icon' => $meta['icon'],
            'hint' => $meta['hint'],
            'sort_order' => 100,
        ]);
        return (int) Db::app()->lastInsertId();
    }

    public function ensureReady(): void
    {
        if (self::$ready) {
            return;
        }
        $this->ensureSchema();
        $this->seedIfEmpty();
        FaqEmbedder::ensureSchema();
        self::$ready = true;
    }

    private function validatedFields(array $item): array
    {
        $fields = [
            'category' => trim((string) ($item['category'] ?? '')),
            'question' => trim((string) ($item['question'] ?? '')),
            'keyword' => trim((string) ($item['keyword'] ?? '')),
            'answer' => trim((string) ($item['answer'] ?? '')),
            'keyword_back' => trim((string) ($item['keyword_back'] ?? '')),
        ];
        if ($fields['category'] === '' || $fields['question'] === '' || $fields['answer'] === '' || $fields['keyword'] === '') {
            throw new InvalidArgumentException('กรุณากรอกหมวด คำถาม คีย์เวิร์ด และคำตอบให้ครบ');
        }
        return $fields;
    }

    private function ensureSchema(): void
    {
        $file = dirname(__DIR__) . '/sql/schema.sql';
        if (!is_file($file)) {
            return;
        }
        $pdo = Db::app();
        $check = $pdo->query("SHOW TABLES LIKE 'faqs'")->fetchColumn();
        if ($check) {
            return;
        }
        $sql = (string) file_get_contents($file);
        $sql = preg_replace('/^CREATE DATABASE.*?;/mi', '', $sql) ?? $sql;
        $sql = preg_replace('/^USE\s+\w+\s*;/mi', '', $sql) ?? $sql;
        foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
            if ($stmt !== '') {
                $pdo->exec($stmt);
            }
        }
    }

    private function seedIfEmpty(): void
    {
        $count = (int) Db::app()->query('SELECT COUNT(*) FROM faqs')->fetchColumn();
        if ($count > 0) {
            return;
        }
        $sort = 1;
        foreach (Categories::MAP as $name => $meta) {
            $stmt = Db::app()->prepare(
                'INSERT IGNORE INTO categories (name, short_name, icon, hint, sort_order)
                 VALUES (:name, :short_name, :icon, :hint, :sort_order)'
            );
            $stmt->execute([
                'name' => $name,
                'short_name' => $meta['short'],
                'icon' => $meta['icon'],
                'hint' => $meta['hint'],
                'sort_order' => $sort++,
            ]);
        }

        $cache = dirname(__DIR__) . '/data/faq-cache.json';
        if (!is_file($cache)) {
            return;
        }
        $rows = json_decode((string) file_get_contents($cache), true);
        if (!is_array($rows)) {
            return;
        }
        $ins = Db::app()->prepare(
            'INSERT INTO faqs (category_id, question, keyword, keyword_back, answer)
             VALUES (:category_id, :question, :keyword, :keyword_back, :answer)'
        );
        foreach ($rows as $row) {
            $cat = trim((string) ($row['category'] ?? 'ทั่วไป')) ?: 'ทั่วไป';
            $q = trim((string) ($row['question'] ?? ''));
            $ans = trim((string) ($row['answer'] ?? ''));
            if ($q === '' || $ans === '') {
                continue;
            }
            $ins->execute([
                'category_id' => $this->categoryId($cat, true),
                'question' => $q,
                'keyword' => trim((string) ($row['keyword'] ?? '')),
                'keyword_back' => trim((string) ($row['keyword_back'] ?? '')),
                'answer' => $ans,
            ]);
        }
    }
}
