<?php

declare(strict_types=1);

class SheetService
{
    private string $dataDir;
    private string $cacheFile;
    private string $metaFile;
    private string $localFile;

    private string $overridesFile;
    private string $deletedFile;

    public function __construct()
    {
        $this->dataDir = dirname(__DIR__) . '/data';
        $this->cacheFile = $this->dataDir . '/faq-cache.json';
        $this->metaFile = $this->dataDir . '/faq-cache.meta.json';
        $this->localFile = $this->dataDir . '/faq-local.json';
        $this->overridesFile = $this->dataDir . '/faq-overrides.json';
        $this->deletedFile = $this->dataDir . '/faq-deleted.json';
        if (!is_dir($this->dataDir)) {
            mkdir($this->dataDir, 0777, true);
        }
    }

    public function all(bool $forceRefresh = false): array
    {
        $sheetRows = $this->applyOverrides($this->fromSheet($forceRefresh));
        $localRows = $this->fromLocal();
        return $this->merge($sheetRows, $localRows);
    }

    public function findById(string $id): ?array
    {
        foreach ($this->all() as $row) {
            if ((string) ($row['id'] ?? '') === $id) {
                return $row;
            }
        }
        return null;
    }

    public function categories(bool $forceRefresh = false): array
    {
        $counts = [];
        foreach ($this->all($forceRefresh) as $row) {
            $cat = $row['category'] ?: 'ทั่วไป';
            $counts[$cat] = ($counts[$cat] ?? 0) + 1;
        }
        $items = [];
        foreach ($counts as $name => $count) {
            $meta = Categories::meta($name);
            $items[] = [
                'name' => $name,
                'short' => $meta['short'],
                'icon' => $meta['icon'],
                'hint' => $meta['hint'],
                'count' => $count,
            ];
        }
        usort($items, static fn ($a, $b) => $b['count'] <=> $a['count']);
        return $items;
    }

    public function add(array $item): array
    {
        $row = [
            'id' => 'local-' . uniqid('', true),
            'category' => trim((string) ($item['category'] ?? '')),
            'question' => trim((string) ($item['question'] ?? '')),
            'keyword' => trim((string) ($item['keyword'] ?? '')),
            'answer' => trim((string) ($item['answer'] ?? '')),
            'keyword_back' => trim((string) ($item['keyword_back'] ?? '')),
            'source' => 'local',
            'created_at' => date('c'),
        ];

        if ($row['category'] === '' || $row['question'] === '' || $row['answer'] === '' || $row['keyword'] === '') {
            throw new InvalidArgumentException('กรุณากรอกหมวด คำถาม คีย์เวิร์ด และคำตอบให้ครบ');
        }

        $local = $this->fromLocal();
        $local[] = $row;
        $this->writeJson($this->localFile, $local);

        $sheet = $this->syncGoogle('append', $row, $row);

        return [
            'item' => $row,
            'sheet' => $sheet,
        ];
    }

    public function update(string $id, array $item): array
    {
        $fields = $this->validatedFields($item);
        $current = $this->findById($id);
        if ($current === null) {
            throw new InvalidArgumentException('ไม่พบคำถามที่ต้องการแก้ไข');
        }

        $updated = array_merge($current, $fields, [
            'id' => $id,
            'updated_at' => date('c'),
        ]);

        if (($current['source'] ?? 'sheet') === 'local' || str_starts_with($id, 'local-')) {
            $local = $this->fromLocal();
            $found = false;
            foreach ($local as $i => $row) {
                if ((string) ($row['id'] ?? '') === $id) {
                    $local[$i] = array_merge($row, $updated, ['source' => 'local']);
                    $updated = $local[$i];
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                throw new InvalidArgumentException('ไม่พบคำถามในคลังเครื่อง');
            }
            $this->writeJson($this->localFile, $local);
        } else {
            $overrides = $this->fromOverrides();
            $overrides[$id] = array_merge($updated, [
                'source' => 'sheet',
                'original_category' => $current['original_category'] ?? $current['category'],
                'original_question' => $current['original_question'] ?? $current['question'],
            ]);
            $this->writeJson($this->overridesFile, $overrides);
            $updated = $overrides[$id];
        }

        $sheet = $this->syncGoogle('update', $current, $updated);
        return ['item' => $updated, 'sheet' => $sheet];
    }

    public function delete(string $id): array
    {
        $current = $this->findById($id);
        if ($current === null) {
            throw new InvalidArgumentException('ไม่พบคำถามที่ต้องการลบ');
        }

        if (($current['source'] ?? 'sheet') === 'local' || str_starts_with($id, 'local-')) {
            $local = array_values(array_filter(
                $this->fromLocal(),
                static fn ($row) => (string) ($row['id'] ?? '') !== $id
            ));
            $this->writeJson($this->localFile, $local);
        } else {
            $deleted = $this->fromDeleted();
            if (!in_array($id, $deleted, true)) {
                $deleted[] = $id;
            }
            $this->writeJson($this->deletedFile, $deleted);
            $overrides = $this->fromOverrides();
            unset($overrides[$id]);
            $this->writeJson($this->overridesFile, $overrides);
        }

        $sheet = $this->syncGoogle('delete', $current, $current);
        return ['id' => $id, 'sheet' => $sheet];
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

    private function fromSheet(bool $forceRefresh): array
    {
        $ttl = (int) config('cache_ttl', 180);
        $fresh = false;
        if (!$forceRefresh && is_file($this->cacheFile)) {
            if (is_file($this->metaFile)) {
                $meta = json_decode((string) file_get_contents($this->metaFile), true) ?: [];
                $fresh = isset($meta['time']) && (time() - (int) $meta['time']) < $ttl;
            } else {
                $fresh = true;
            }
        }

        if ($fresh) {
            return $this->readJson($this->cacheFile);
        }

        $fetched = $this->fetchCsv();
        if ($fetched !== null) {
            file_put_contents($this->cacheFile, json_encode($fetched, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
            file_put_contents($this->metaFile, json_encode(['time' => time(), 'count' => count($fetched)]));
            return $fetched;
        }

        return $this->readJson($this->cacheFile);
    }

    private function fetchCsv(): ?array
    {
        $id = config('sheet_id');
        $gid = config('sheet_gid', '0');
        $url = "https://docs.google.com/spreadsheets/d/{$id}/gviz/tq?tqx=out:csv&gid={$gid}";
        $ctx = stream_context_create([
            'http' => [
                'header' => "User-Agent: SCiGENIE/1.0\r\n",
                'timeout' => 20,
                'follow_location' => 1,
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
            ],
        ]);
        $csv = @file_get_contents($url, false, $ctx);
        if ($csv === false || $csv === '') {
            return null;
        }

        $rows = Csv::parse($csv);
        if (!$rows) {
            return null;
        }
        array_shift($rows);
        $faqs = [];
        foreach ($rows as $i => $row) {
            $cat = trim((string) ($row[0] ?? ''));
            $q = trim((string) ($row[1] ?? ''));
            $ans = trim((string) ($row[3] ?? ''));
            if ($cat === '' && $q === '') {
                continue;
            }
            if ($q === '' || $ans === '') {
                continue;
            }
            $faqs[] = [
                'id' => $i + 1,
                'category' => $cat !== '' ? $cat : 'ทั่วไป',
                'question' => $q,
                'keyword' => trim((string) ($row[2] ?? '')),
                'answer' => $ans,
                'keyword_back' => trim((string) ($row[4] ?? '')),
                'source' => 'sheet',
            ];
        }
        return $faqs;
    }

    private function fromLocal(): array
    {
        return $this->readJson($this->localFile);
    }

    private function fromOverrides(): array
    {
        $data = $this->readJson($this->overridesFile);
        return is_array($data) ? $data : [];
    }

    private function fromDeleted(): array
    {
        $data = $this->readJson($this->deletedFile);
        return array_map('strval', array_values($data));
    }

    private function applyOverrides(array $sheet): array
    {
        $deleted = array_flip($this->fromDeleted());
        $overrides = $this->fromOverrides();
        $out = [];
        foreach ($sheet as $row) {
            $id = (string) ($row['id'] ?? '');
            if ($id !== '' && isset($deleted[$id])) {
                continue;
            }
            if ($id !== '' && isset($overrides[$id]) && is_array($overrides[$id])) {
                $row = array_merge($row, $overrides[$id], ['id' => $id, 'source' => 'sheet']);
            }
            $out[] = $row;
        }
        return $out;
    }

    private function merge(array $sheet, array $local): array
    {
        $keys = [];
        $out = [];
        foreach ($sheet as $row) {
            $key = $this->key($row);
            $keys[$key] = true;
            $out[] = $row;
        }
        foreach ($local as $row) {
            $key = $this->key($row);
            if (isset($keys[$key])) {
                continue;
            }
            $row['source'] = $row['source'] ?? 'local';
            $out[] = $row;
        }
        return $out;
    }

    private function key(array $row): string
    {
        return mb_strtolower(trim($row['category'] . '|' . $row['question']));
    }

    private function readJson(string $file): array
    {
        if (!is_file($file)) {
            return [];
        }
        $data = json_decode((string) file_get_contents($file), true);
        return is_array($data) ? $data : [];
    }

    private function writeJson(string $file, array $data): void
    {
        file_put_contents($file, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
    }

    private function syncGoogle(string $action, array $original, array $row): array
    {
        $url = trim((string) config('apps_script_url', ''));
        $fallback = [
            'append' => 'บันทึกในระบบแล้ว แต่ยังไม่ได้เชื่อม Google Apps Script จึงยังไม่ถูกเพิ่มลงชีต',
            'update' => 'แก้ไขในระบบแล้ว แต่ยังไม่ได้เชื่อม Google Sheet',
            'delete' => 'ลบในระบบแล้ว แต่ยังไม่ได้เชื่อม Google Sheet',
        ];
        if ($url === '') {
            return [
                'ok' => false,
                'mode' => 'local',
                'message' => $fallback[$action] ?? 'บันทึกในระบบแล้ว',
            ];
        }

        $payload = json_encode([
            'action' => $action,
            'category' => $row['category'] ?? '',
            'question' => $row['question'] ?? '',
            'keyword' => $row['keyword'] ?? '',
            'answer' => $row['answer'] ?? '',
            'keyword_back' => $row['keyword_back'] ?? '',
            'original_category' => $original['original_category'] ?? $original['category'] ?? '',
            'original_question' => $original['original_question'] ?? $original['question'] ?? '',
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $ctx = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\nUser-Agent: SCiGENIE/1.0\r\n",
                'content' => $payload,
                'timeout' => 25,
                'follow_location' => 1,
                'ignore_errors' => true,
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
            ],
        ]);
        $res = @file_get_contents($url, false, $ctx);
        $done = [
            'append' => 'เพิ่มคำถามลง Google Sheet เรียบร้อย',
            'update' => 'แก้ไขคำถามใน Google Sheet เรียบร้อย',
            'delete' => 'ลบคำถามใน Google Sheet เรียบร้อย',
        ];
        if ($res === false) {
            return [
                'ok' => false,
                'mode' => 'local',
                'message' => 'เชื่อม Google Sheet ไม่สำเร็จ จึงบันทึกไว้ในระบบชั่วคราว',
            ];
        }
        $json = json_decode($res, true);
        if (is_array($json) && !empty($json['ok'])) {
            return [
                'ok' => true,
                'mode' => 'sheet',
                'message' => $done[$action] ?? 'อัปเดต Google Sheet เรียบร้อย',
            ];
        }
        return [
            'ok' => false,
            'mode' => 'local',
            'message' => 'Google Sheet ตอบกลับไม่สำเร็จ จึงบันทึกไว้ในระบบชั่วคราว',
            'raw' => mb_substr((string) $res, 0, 300),
        ];
    }
}
