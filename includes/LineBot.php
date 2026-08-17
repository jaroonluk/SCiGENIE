<?php

declare(strict_types=1);

class LineBot
{
    private const REPLY_URL = 'https://api.line.me/v2/bot/message/reply';
    private const MAX_TEXT = 4900;
    private const MENU_WORDS = ['เมนู', 'หมวด', 'เริ่มใหม่', 'menu', 'help', 'ช่วยเหลือ', 'สวัสดี', 'hello'];

    public static function verifySignature(string $raw, string $signature): bool
    {
        $secret = (string) config('line_channel_secret', '');
        if ($secret === '' || $signature === '') {
            return false;
        }
        $digest = base64_encode(hash_hmac('sha256', $raw, $secret, true));
        return hash_equals($digest, $signature);
    }

    public static function configured(): bool
    {
        return (string) config('line_channel_secret', '') !== ''
            && (string) config('line_channel_access_token', '') !== '';
    }

    public static function handleEvent(array $event): void
    {
        $type = (string) ($event['type'] ?? '');
        $replyToken = (string) ($event['replyToken'] ?? '');
        $userId = (string) ($event['source']['userId'] ?? '');
        if ($replyToken === '') {
            return;
        }

        if ($type === 'follow' || $type === 'join') {
            self::reply($replyToken, self::welcomeMessages());
            return;
        }
        if ($type === 'postback') {
            $data = (string) ($event['postback']['data'] ?? '');
            self::handlePostback($replyToken, $userId, $data);
            return;
        }
        if ($type !== 'message') {
            return;
        }
        $msgType = (string) ($event['message']['type'] ?? '');
        if ($msgType !== 'text') {
            self::reply($replyToken, [self::textMessage('พิมพ์เป็นข้อความได้เลยค่ะ หรือพิมพ์ “เมนู” เพื่อเลือกหมวด')]);
            return;
        }
        $text = trim((string) ($event['message']['text'] ?? ''));
        self::handleText($replyToken, $userId, $text);
    }

    public static function replyTextForAsk(array $result): string
    {
        $messages = self::answerMessages($result, '');
        $parts = [];
        foreach ($messages as $msg) {
            if (($msg['type'] ?? '') === 'text') {
                $parts[] = (string) ($msg['text'] ?? '');
            }
        }
        return trim(implode("\n\n", $parts));
    }

    private static function handlePostback(string $replyToken, string $userId, string $data): void
    {
        parse_str($data, $params);
        $cat = trim((string) ($params['cat'] ?? ''));
        $faqId = trim((string) ($params['faq'] ?? ''));
        if ($cat !== '') {
            self::setCategory($userId, $cat);
            self::reply($replyToken, self::categorySelectedMessages($cat));
            return;
        }
        if ($faqId !== '') {
            $faq = (new SheetService())->findById($faqId);
            if ($faq) {
                $catName = (string) ($faq['category'] ?? '');
                self::setCategory($userId, $catName);
                $result = (new Matcher())->ask([(array) $faq], (string) $faq['question'], $catName);
                $result['matched'] = true;
                $result['item'] = [
                    'id' => $faq['id'],
                    'category' => $faq['category'],
                    'question' => $faq['question'],
                    'answer' => $faq['answer'],
                ];
                self::logAsk($userId, $catName, (string) $faq['question'], $result);
                self::reply($replyToken, self::answerMessages($result, $catName));
                return;
            }
        }
        self::reply($replyToken, self::welcomeMessages());
    }

    private static function handleText(string $replyToken, string $userId, string $text): void
    {
        if ($text === '' || self::isMenu($text)) {
            self::reply($replyToken, self::welcomeMessages());
            return;
        }

        $cat = self::matchCategory($text);
        if ($cat !== null) {
            self::setCategory($userId, $cat);
            self::reply($replyToken, self::categorySelectedMessages($cat));
            return;
        }

        $sessionCat = self::getCategory($userId);
        $faqs = (new SheetService())->all();
        $result = (new Matcher())->ask($faqs, $text, $sessionCat);
        self::logAsk($userId, $sessionCat, $text, $result);
        self::reply($replyToken, self::answerMessages($result, $sessionCat));
    }

    private static function welcomeMessages(): array
    {
        $phone = (string) config('contact_phone', '043-009-700');
        $extra = [];
        $i = 0;
        foreach (Categories::MAP as $name => $meta) {
            $i++;
            if ($i > 13) {
                $extra[] = (string) $meta['short'];
            }
        }
        $text = "สวัสดีค่ะ ยินดีต้อนรับสู่ SCiGENIE\nผู้ช่วยตอบคำถามคณะวิทยาศาสตร์ มหาวิทยาลัยขอนแก่น\n\nพิมพ์คำถามได้เลย หรือเลือกหมวดด้านล่างค่ะ";
        if ($extra !== []) {
            $text .= "\nหมวดอื่น: " . implode(', ', $extra) . ' (พิมพ์ชื่อหมวดได้)';
        }
        $text .= "\n\nหากไม่พบคำตอบ โทร {$phone}";
        return [self::textMessage($text, self::categoryQuickReplies())];
    }

    private static function categorySelectedMessages(string $category): array
    {
        $short = Categories::short($category);
        $faqs = (new SheetService())->all();
        $suggestions = (new Matcher())->suggestions($faqs, $category, 8);
        $text = "เลือกหมวด «{$short}» แล้วค่ะ พิมพ์คำถามได้เลย หรือกดคำถามแนะนำด้านล่าง";
        return [self::textMessage($text, self::questionQuickReplies($suggestions, $category))];
    }

    private static function answerMessages(array $result, string $category): array
    {
        $phone = (string) config('contact_phone', '043-009-700');
        $email = (string) config('contact_email', 'sc.inbox@kku.ac.th');
        $quick = [];

        if (!empty($result['matched']) && !empty($result['item']['answer'])) {
            $item = $result['item'];
            $title = trim((string) ($item['question'] ?? ''));
            $body = self::plainText((string) $item['answer']);
            $prefix = '';
            if (!empty($result['other_category'])) {
                $prefix = 'พบคำตอบในหมวด «' . Categories::short((string) ($item['category'] ?? '')) . "»\n\n";
            }
            if ($title !== '' && mb_strlen($title) < 90) {
                $prefix .= $title . "\n\n";
            }
            $text = self::clip($prefix . $body);
            foreach (($result['related'] ?? []) as $row) {
                $quick[] = $row;
            }
            $quick[] = ['question' => 'เมนู'];
            return [self::textMessage($text, self::questionQuickReplies($quick, $category))];
        }

        $msg = trim((string) ($result['message'] ?? 'ยังไม่พบคำตอบที่ตรงกับคำถามนี้'));
        $msg .= "\n\nหากยังไม่พบคำตอบ โทร {$phone} หรืออีเมล {$email}";
        $suggestions = $result['suggestions'] ?? [];
        $suggestions[] = ['question' => 'เมนู'];
        return [self::textMessage($msg, self::questionQuickReplies($suggestions, $category))];
    }

    private static function categoryQuickReplies(): array
    {
        $items = [];
        foreach (Categories::MAP as $name => $meta) {
            $label = self::label((string) $meta['short']);
            $items[] = [
                'type' => 'action',
                'action' => [
                    'type' => 'postback',
                    'label' => $label,
                    'data' => http_build_query(['cat' => $name], '', '&', PHP_QUERY_RFC3986),
                    'displayText' => 'เลือกหมวด' . $label,
                ],
            ];
            if (count($items) >= 13) {
                break;
            }
        }
        return $items;
    }

    private static function questionQuickReplies(array $suggestions, string $category): array
    {
        $items = [];
        foreach ($suggestions as $row) {
            $q = trim((string) ($row['question'] ?? ''));
            if ($q === '') {
                continue;
            }
            if ($q === 'เมนู') {
                $items[] = [
                    'type' => 'action',
                    'action' => [
                        'type' => 'message',
                        'label' => 'เมนูหมวด',
                        'text' => 'เมนู',
                    ],
                ];
                continue;
            }
            $items[] = [
                'type' => 'action',
                'action' => [
                    'type' => 'message',
                    'label' => self::label($q),
                    'text' => $q,
                ],
            ];
            if (count($items) >= 12) {
                break;
            }
        }
        if ($category !== '' && count($items) < 13) {
            $items[] = [
                'type' => 'action',
                'action' => [
                    'type' => 'message',
                    'label' => 'เมนูหมวด',
                    'text' => 'เมนู',
                ],
            ];
        }
        return array_slice($items, 0, 13);
    }

    private static function textMessage(string $text, array $quick = []): array
    {
        $msg = [
            'type' => 'text',
            'text' => self::clip($text),
        ];
        if ($quick !== []) {
            $msg['quickReply'] = ['items' => $quick];
        }
        return $msg;
    }

    private static function reply(string $replyToken, array $messages): void
    {
        $token = (string) config('line_channel_access_token', '');
        if ($token === '' || $messages === []) {
            return;
        }
        $payload = json_encode([
            'replyToken' => $replyToken,
            'messages' => array_slice($messages, 0, 5),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($payload)) {
            return;
        }
        $ctx = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\nAuthorization: Bearer {$token}\r\n",
                'content' => $payload,
                'timeout' => 8,
                'ignore_errors' => true,
            ],
        ]);
        @file_get_contents(self::REPLY_URL, false, $ctx);
    }

    private static function isMenu(string $text): bool
    {
        $n = mb_strtolower(preg_replace('/\s+/u', '', $text) ?? $text);
        foreach (self::MENU_WORDS as $word) {
            if ($n === $word) {
                return true;
            }
        }
        return false;
    }

    private static function matchCategory(string $text): ?string
    {
        $n = mb_strtolower(trim($text));
        foreach (Categories::MAP as $name => $meta) {
            $short = mb_strtolower((string) $meta['short']);
            if ($n === mb_strtolower($name) || $n === $short || $n === 'เลือกหมวด' . $short) {
                return $name;
            }
        }
        return null;
    }

    private static function getCategory(string $userId): string
    {
        if ($userId === '') {
            return '';
        }
        $all = self::sessions();
        $cat = trim((string) ($all[$userId]['category'] ?? ''));
        $ts = (int) ($all[$userId]['ts'] ?? 0);
        if ($cat === '' || $ts < time() - 86400) {
            return '';
        }
        return $cat;
    }

    private static function setCategory(string $userId, string $category): void
    {
        if ($userId === '') {
            return;
        }
        $all = self::sessions();
        $all[$userId] = ['category' => $category, 'ts' => time()];
        self::saveSessions($all);
    }

    private static function sessions(): array
    {
        $file = self::sessionFile();
        if (!is_file($file)) {
            return [];
        }
        $data = json_decode((string) file_get_contents($file), true);
        return is_array($data) ? $data : [];
    }

    private static function saveSessions(array $all): void
    {
        $file = self::sessionFile();
        $dir = dirname($file);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        file_put_contents($file, json_encode($all, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
    }

    private static function sessionFile(): string
    {
        return dirname(__DIR__) . '/data/line-sessions.json';
    }

    private static function logAsk(string $userId, string $category, string $question, array $result): void
    {
        $item = $result['item'] ?? [];
        (new AskLog())->record([
            'visitor' => 'LINE' . substr(preg_replace('/[^A-Za-z0-9]/', '', $userId) ?? '', -5),
            'category' => $category,
            'question' => $question,
            'matched' => !empty($result['matched']),
            'faq' => $item['question'] ?? '',
            'answer' => $item['answer'] ?? ($result['message'] ?? ''),
        ]);
    }

    private static function plainText(string $html): string
    {
        $text = str_replace(['<br>', '<br/>', '<br />', '</p>', '</div>'], "\n", $html);
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace("/[ \t]+\n/", "\n", $text) ?? $text;
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;
        return trim($text);
    }

    private static function clip(string $text): string
    {
        if (mb_strlen($text) <= self::MAX_TEXT) {
            return $text;
        }
        return mb_substr($text, 0, self::MAX_TEXT - 12) . "\n…(ต่อ)";
    }

    private static function label(string $text): string
    {
        $text = trim($text);
        if (mb_strlen($text) <= 20) {
            return $text;
        }
        return mb_substr($text, 0, 19) . '…';
    }
}
