<?php

declare(strict_types=1);

class SheetService
{
    private string $dataDir;
    private string $cacheFile;
    private string $metaFile;
    private string $localFile;

    public function __construct()
    {
        $this->dataDir = dirname(__DIR__) . '/data';
        $this->cacheFile = $this->dataDir . '/faq-cache.json';
        $this->metaFile = $this->dataDir . '/faq-cache.meta.json';
        $this->localFile = $this->dataDir . '/faq-local.json';
        if (!is_dir($this->dataDir)) {
            mkdir($this->dataDir, 0777, true);
        }
    }

    public function all(bool $forceRefresh = false): array
    {
        $sheetRows = $this->fromSheet($forceRefresh);
        $localRows = $this->fromLocal();
        return $this->merge($sheetRows, $localRows);
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
            'id' => 'local-' . time(),
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
        file_put_contents($this->localFile, json_encode($local, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));

        $sheet = $this->appendToGoogle($row);

        return [
            'item' => $row,
            'sheet' => $sheet,
        ];
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

    private function appendToGoogle(array $row): array
    {
        $url = trim((string) config('apps_script_url', ''));
        if ($url === '') {
            return [
                'ok' => false,
                'mode' => 'local',
                'message' => 'บันทึกในระบบแล้ว แต่ยังไม่ได้เชื่อม Google Apps Script จึงยังไม่ถูกเพิ่มลงชีต',
            ];
        }

        $payload = json_encode([
            'action' => 'append',
            'category' => $row['category'],
            'question' => $row['question'],
            'keyword' => $row['keyword'],
            'answer' => $row['answer'],
            'keyword_back' => $row['keyword_back'],
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
                'message' => 'เพิ่มคำถามลง Google Sheet เรียบร้อย',
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
