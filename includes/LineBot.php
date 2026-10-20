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

        try {
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
                if (self::holdIfHuman($userId)) {
                    return;
                }
                self::reply($replyToken, [self::textMessage('พิมพ์เป็นข้อความได้เลยค่ะ หรือพิมพ์ “เมนู” เพื่อเลือกหมวด')]);
                return;
            }
            $text = trim((string) ($event['message']['text'] ?? ''));
            self::reply($replyToken, self::processText($userId, $text));
        } catch (Throwable $e) {
            error_log('SCiGENIE LINE: ' . $e->getMessage());
            self::reply($replyToken, [self::textMessage(
                'ระบบตอบคำถามขัดข้องชั่วคราว กรุณาลองอีกครั้ง หรือโทร ' . (string) config('contact_phone', '043-009-700')
            )]);
        }
    }

    public static function replyTextForAsk(array $result): string
    {
        if (!empty($result['need_category'])) {
            return trim((string) ($result['message'] ?? Matcher::needCategoryMessage()));
        }
        return self::replyTextFromMessages(self::answerMessages('', $result, ''));
    }

    public static function replyTextFromMessages(array $messages): string
    {
        $parts = [];
        foreach ($messages as $msg) {
            if (($msg['type'] ?? '') === 'text') {
                $parts[] = (string) ($msg['text'] ?? '');
            }
        }
        return trim(implode("\n\n", $parts));
    }

    public static function processText(string $userId, string $text): array
    {
        $text = trim($text);
        if (HumanPass::isResume($text)) {
            self::clearSession($userId);
            return self::welcomeMessages();
        }
        if (HumanPass::isPause($text)) {
            return self::pauseForHuman($userId);
        }
        if (self::holdIfHuman($userId)) {
            return [];
        }
        if ($text === '' || self::isMenu($text)) {
            self::clearSession($userId);
            return self::welcomeMessages();
        }

        $fromNumber = self::pickSuggestion($userId, $text);
        if ($fromNumber !== null) {
            $text = $fromNumber;
        }

        $sessionCat = self::getCategory($userId);
        $cat = self::matchCategory($text, $sessionCat === '');
        if ($cat !== null) {
            return self::categorySelectedMessages($userId, $cat);
        }

        if ($sessionCat === '') {
            $result = self::faq()->match($text, '');
            if (!empty($result['matched']) && !empty($result['item']['answer'])) {
                $foundCat = trim((string) ($result['item']['category'] ?? ''));
                if ($foundCat !== '') {
                    self::setCategory($userId, $foundCat);
                }
                self::logAsk($userId, $foundCat, $text, $result);
                return self::answerMessages($userId, $result, $foundCat);
            }
            $messages = self::categoryPrompt($userId, $result);
            if ($messages !== []) {
                self::logAsk($userId, '', $text, $result);
            }
            return $messages;
        }

        $result = self::faq()->match($text, $sessionCat);
        if (!empty($result['matched']) && !empty($result['item']['answer'])) {
            $foundCat = trim((string) ($result['item']['category'] ?? ''));
            if ($foundCat !== '' && $foundCat !== $sessionCat) {
                self::setCategory($userId, $foundCat);
            }
            $replyCat = $foundCat !== '' ? $foundCat : $sessionCat;
            self::logAsk($userId, $replyCat, $text, $result);
            return self::answerMessages($userId, $result, $replyCat);
        }
        self::logAsk($userId, $sessionCat, $text, $result);
        return self::answerMessages($userId, $result, $sessionCat);
    }

    public static function processForApi(string $userId, string $text): array
    {
        $messages = self::processText($userId, $text);
        $reply = self::replyTextFromMessages($messages);
        foreach ($messages as $msg) {
            if (($msg['type'] ?? '') === 'flex' && !str_contains($reply, 'หมวดทั้งหมด')) {
                $reply = trim($reply . self::categoryListText(self::categories()));
                break;
            }
        }
        return [
            'reply_text' => $reply,
            'category' => self::getCategory($userId),
            'messages' => $messages,
        ];
    }

    private static function handlePostback(string $replyToken, string $userId, string $data): void
    {
        self::endHuman($userId);
        parse_str($data, $params);
        $cat = trim((string) ($params['cat'] ?? ''));
        $faqId = trim((string) ($params['faq'] ?? ''));
        if ($cat !== '') {
            self::reply($replyToken, self::categorySelectedMessages($userId, $cat));
            return;
        }
        if ($faqId !== '') {
            $faq = self::faq()->findById($faqId);
            if ($faq) {
                $catName = (string) ($faq['category'] ?? '');
                self::setCategory($userId, $catName);
                $result = [
                    'matched' => true,
                    'item' => [
                        'id' => $faq['id'],
                        'category' => $faq['category'],
                        'question' => $faq['question'],
                        'answer' => $faq['answer'],
                        'source' => $faq['source'] ?? 'db',
                    ],
                ];
                self::reply($replyToken, self::answerMessages($userId, $result, $catName));
                self::logAsk($userId, $catName, (string) $faq['question'], $result);
                return;
            }
        }
        self::reply($replyToken, self::welcomeMessages());
    }

    private static function welcomeMessages(): array
    {
        $categories = self::categories();
        $flex = self::categoryFlex($categories);
        if ($flex !== null) {
            $flex['quickReply'] = [
                'items' => array_merge([self::staffQuickReply()], self::menuQuickReplies()),
            ];
            return [$flex];
        }
        return [self::textMessage('SCiGENIE — ยังไม่มีหมวดคำถามในระบบ')];
    }

    /**
     * @param array<string, mixed> $result
     * @return list<array<string, mixed>>
     */
    private static function needCategoryMessages(array $result = []): array
    {
        $text = trim((string) ($result['message'] ?? Matcher::needCategoryMessage()));
        $text .= "\n\n" . HumanPass::passHint();
        $msgs = [self::textMessage($text)];
        $flex = self::categoryFlex(self::categories());
        if ($flex !== null) {
            $msgs[] = $flex;
        }
        $last = array_key_last($msgs);
        $msgs[$last]['quickReply'] = [
            'items' => array_merge([self::staffQuickReply()], self::menuQuickReplies()),
        ];
        return $msgs;
    }

    /**
     * @param array<string, mixed> $result
     * @return list<array<string, mixed>>
     */
    private static function categoryPrompt(string $userId, array $result): array
    {
        if ($userId !== '' && HumanPass::naggedRecently(self::userSession($userId))) {
            return [];
        }
        if ($userId !== '') {
            self::writeSession($userId, HumanPass::rememberNag(self::userSession($userId)));
        }
        return self::needCategoryMessages($result);
    }

    private static function categoryListText(array $categories): string
    {
        if ($categories === []) {
            return '';
        }
        $lines = ["\n\nหมวดทั้งหมด"];
        $i = 1;
        foreach ($categories as $cat) {
            $short = (string) (($cat['short'] ?? '') !== '' ? $cat['short'] : $cat['name']);
            $lines[] = $i . '. ' . self::categoryEmoji($cat) . ' ' . $short;
            $i++;
        }
        return implode("\n", $lines);
    }

    private static function categorySelectedMessages(string $userId, string $category): array
    {
        self::setCategory($userId, $category);
        self::setSuggestions($userId, []);
        $emoji = Categories::emojiByName($category);
        $short = self::categoryShort($category);
        return [self::textMessage(
            "เลือกหมวด {$emoji} {$short} แล้วค่ะ\nพิมพ์คำถามได้เลยค่ะ",
            self::menuQuickReplies()
        )];
    }

    private static function answerMessages(string $userId, array $result, string $category): array
    {
        $emoji = $category !== '' ? Categories::emojiByName($category) : '';
        $short = $category !== '' ? self::categoryShort($category) : '';
        $head = $short !== '' ? trim($emoji . ' หมวด: ' . $short) . "\n\n" : '';

        if (!empty($result['matched']) && !empty($result['item']['answer'])) {
            $body = self::plainText((string) $result['item']['answer']);
            self::setSuggestions($userId, []);
            return [self::textMessage(self::clip($head . "คำตอบ\n" . $body), self::menuQuickReplies())];
        }

        if (!empty($result['need_category'])) {
            return self::categoryPrompt($userId, $result);
        }

        $msg = $head . trim((string) ($result['message'] ?? Matcher::noMatchMessage()));
        $items = [];
        foreach (($result['items'] ?? []) as $row) {
            $q = trim((string) ($row['question'] ?? ''));
            if ($q !== '') {
                $items[] = $q;
            }
        }
        if ($items === []) {
            foreach (($result['suggestions'] ?? []) as $row) {
                $q = trim((string) ($row['question'] ?? ''));
                if ($q !== '') {
                    $items[] = $q;
                }
            }
        }
        $quick = $items !== [] ? array_merge(self::questionQuickReplies($items), self::menuQuickReplies()) : self::menuQuickReplies();
        $quick = array_slice(array_merge([self::staffQuickReply()], $quick), 0, 13);
        self::setSuggestions($userId, $items);
        return [self::textMessage($msg, $quick)];
    }

    private static function categoryQuickReplies(array $categories): array
    {
        $items = [];
        foreach ($categories as $cat) {
            $short = trim((string) (($cat['short'] ?? '') !== '' ? $cat['short'] : $cat['name']));
            if ($short === '') {
                continue;
            }
            $emoji = self::categoryEmoji($cat);
            $items[] = [
                'type' => 'action',
                'action' => [
                    'type' => 'message',
                    'label' => self::label(trim($emoji . ' ' . $short)),
                    'text' => $short,
                ],
            ];
            if (count($items) >= 13) {
                break;
            }
        }
        return $items;
    }

    private static function questionQuickReplies(array $questions): array
    {
        $items = [];
        $i = 1;
        foreach ($questions as $q) {
            $q = trim((string) $q);
            if ($q === '') {
                continue;
            }
            $send = mb_strlen($q) > 280 ? mb_substr($q, 0, 277) . '…' : $q;
            $items[] = [
                'type' => 'action',
                'action' => [
                    'type' => 'message',
                    'label' => self::label((string) $i . '. ' . $q),
                    'text' => $send,
                ],
            ];
            $i++;
            if (count($items) >= 11) {
                break;
            }
        }
        return $items;
    }

    private static function menuQuickReplies(): array
    {
        return [[
            'type' => 'action',
            'action' => [
                'type' => 'message',
                'label' => '📋 เมนูหมวด',
                'text' => 'เมนู',
            ],
        ]];
    }

    private static function staffQuickReply(): array
    {
        return [
            'type' => 'action',
            'action' => [
                'type' => 'message',
                'label' => 'คุยกับเจ้าหน้าที่',
                'text' => 'คุยกับเจ้าหน้าที่',
            ],
        ];
    }

    private static function questionListFlex(array $questions, string $category, string $heading): ?array
    {
        $questions = array_values(array_filter($questions, static fn ($q) => trim((string) $q) !== ''));
        if ($questions === []) {
            return null;
        }
        $emoji = $category !== '' ? Categories::emojiByName($category) : '💬';
        $short = $category !== '' ? self::categoryShort($category) : 'คำถามแนะนำ';
        $groups = array_values(array_chunk($questions, 3));
        $total = count($groups);
        $bubbles = [];
        $offset = 0;
        foreach ($groups as $page => $chunk) {
            $rows = [];
            foreach ($chunk as $q) {
                $rows[] = self::questionMenuRow($offset, (string) $q);
                $offset++;
            }
            $bubbles[] = [
                'type' => 'bubble',
                'size' => 'giga',
                'header' => self::flexHeader($emoji . '  ' . $short, $heading),
                'body' => [
                    'type' => 'box',
                    'layout' => 'vertical',
                    'spacing' => 'md',
                    'paddingAll' => '14px',
                    'backgroundColor' => '#FFFDF6',
                    'contents' => $rows,
                ],
                'footer' => self::flexFooter($page + 1, $total, true),
            ];
        }
        return [
            'type' => 'flex',
            'altText' => $heading . ' · ' . $short,
            'contents' => count($bubbles) === 1
                ? $bubbles[0]
                : ['type' => 'carousel', 'contents' => array_slice($bubbles, 0, 12)],
        ];
    }

    private static function backMenuFlex(string $subtitle = ''): array
    {
        $headerSub = $subtitle !== '' ? $subtitle : 'SCiGENIE';
        return [
            'type' => 'flex',
            'altText' => 'กลับเมนูหมวด',
            'contents' => [
                'type' => 'bubble',
                'size' => 'giga',
                'header' => self::flexHeader('🟡  SCiGENIE', $headerSub),
                'body' => [
                    'type' => 'box',
                    'layout' => 'vertical',
                    'paddingAll' => '20px',
                    'backgroundColor' => '#FFFDF6',
                    'justifyContent' => 'center',
                    'alignItems' => 'center',
                    'contents' => [[
                        'type' => 'text',
                        'text' => '📋',
                        'size' => '3xl',
                        'align' => 'center',
                    ]],
                ],
                'footer' => self::flexFooter(1, 1, true),
            ],
        ];
    }

    private static function flexHeader(string $label, string $title): array
    {
        return [
            'type' => 'box',
            'layout' => 'vertical',
            'backgroundColor' => '#1C1608',
            'paddingAll' => '12px',
            'contents' => [
                [
                    'type' => 'text',
                    'text' => $label,
                    'color' => '#FFD54A',
                    'size' => 'xs',
                    'weight' => 'bold',
                ],
                [
                    'type' => 'text',
                    'text' => $title,
                    'color' => '#FFF8E7',
                    'size' => 'md',
                    'weight' => 'bold',
                    'wrap' => true,
                    'margin' => 'sm',
                ],
            ],
        ];
    }

    private static function flexFooter(int $page, int $total, bool $showBack): array
    {
        $contents = [];
        if ($showBack) {
            $contents[] = [
                'type' => 'button',
                'style' => 'primary',
                'color' => '#D4A017',
                'height' => 'sm',
                'action' => [
                    'type' => 'message',
                    'label' => '◀  เมนูหมวด',
                    'text' => 'เมนู',
                ],
            ];
        }
        if ($total > 1) {
            $contents[] = [
                'type' => 'box',
                'layout' => 'horizontal',
                'margin' => 'md',
                'justifyContent' => 'center',
                'spacing' => 'md',
                'contents' => [
                    [
                        'type' => 'text',
                        'text' => '‹',
                        'size' => 'lg',
                        'color' => '#7A6840',
                        'flex' => 0,
                    ],
                    [
                        'type' => 'text',
                        'text' => $page . ' / ' . $total,
                        'size' => 'sm',
                        'weight' => 'bold',
                        'color' => '#5C4314',
                        'align' => 'center',
                        'flex' => 0,
                    ],
                    [
                        'type' => 'text',
                        'text' => '›',
                        'size' => 'lg',
                        'color' => '#7A6840',
                        'flex' => 0,
                    ],
                ],
            ];
        }
        return [
            'type' => 'box',
            'layout' => 'vertical',
            'spacing' => 'sm',
            'backgroundColor' => '#FFF8E7',
            'paddingAll' => '10px',
            'contents' => $contents !== [] ? $contents : [[
                'type' => 'filler',
            ]],
        ];
    }

    private static function questionMenuRow(int $index, string $question): array
    {
        $question = trim($question);
        $shown = mb_strlen($question) > 140 ? mb_substr($question, 0, 137) . '…' : $question;
        $send = mb_strlen($question) > 280 ? mb_substr($question, 0, 277) . '…' : $question;
        return [
            'type' => 'box',
            'layout' => 'horizontal',
            'spacing' => 'lg',
            'paddingAll' => '16px',
            'backgroundColor' => '#FFF8E7',
            'cornerRadius' => '18px',
            'alignItems' => 'center',
            'action' => [
                'type' => 'message',
                'label' => 'เลือกคำถาม',
                'text' => $send,
            ],
            'contents' => [
                [
                    'type' => 'box',
                    'layout' => 'vertical',
                    'width' => '44px',
                    'height' => '44px',
                    'backgroundColor' => '#FFD54A',
                    'cornerRadius' => '22px',
                    'justifyContent' => 'center',
                    'alignItems' => 'center',
                    'flex' => 0,
                    'contents' => [[
                        'type' => 'text',
                        'text' => (string) ($index + 1),
                        'size' => 'md',
                        'weight' => 'bold',
                        'color' => '#1C1608',
                        'align' => 'center',
                    ]],
                ],
                [
                    'type' => 'box',
                    'layout' => 'vertical',
                    'flex' => 1,
                    'contents' => [[
                        'type' => 'text',
                        'text' => $shown,
                        'weight' => 'bold',
                        'color' => '#1C1608',
                        'size' => 'md',
                        'wrap' => true,
                        'maxLines' => 5,
                    ]],
                ],
            ],
        ];
    }

    private static function categoryFlex(array $categories): ?array
    {
        if ($categories === []) {
            return null;
        }
        $groups = array_values(array_chunk($categories, 5));
        $total = count($groups);
        $titles = [
            'การเรียนและนักศึกษา',
            'ทุน สวัสดิการ สำเร็จศึกษา',
            'บริการวิชาการและคณะ',
        ];
        $bubbles = [];
        foreach ($groups as $page => $chunk) {
            $rows = [];
            foreach ($chunk as $cat) {
                $row = self::categoryMenuRow($cat);
                if ($row !== null) {
                    $rows[] = $row;
                }
            }
            if ($rows === []) {
                continue;
            }
            $title = $titles[$page] ?? 'เลือกหมวดคำถาม';
            $bubbles[] = [
                'type' => 'bubble',
                'size' => 'giga',
                'header' => self::flexHeader('🟡  SCiGENIE', $title),
                'body' => [
                    'type' => 'box',
                    'layout' => 'vertical',
                    'spacing' => 'sm',
                    'paddingAll' => '10px',
                    'backgroundColor' => '#FFFDF6',
                    'contents' => $rows,
                ],
                'footer' => self::flexFooter($page + 1, $total, false),
            ];
        }
        if ($bubbles === []) {
            return null;
        }
        return [
            'type' => 'flex',
            'altText' => 'เลือกหมวดคำถาม SCiGENIE',
            'contents' => count($bubbles) === 1
                ? $bubbles[0]
                : ['type' => 'carousel', 'contents' => array_slice($bubbles, 0, 12)],
        ];
    }

    private static function categoryMenuRow(array $cat): ?array
    {
        $short = trim((string) (($cat['short'] ?? '') !== '' ? $cat['short'] : $cat['name']));
        if ($short === '') {
            return null;
        }
        $hint = trim((string) ($cat['hint'] ?? ''));
        if ($hint === '') {
            $hint = 'เลือกเพื่อถามในหมวดนี้';
        }
        $count = (int) ($cat['count'] ?? 0);
        $sub = $count > 0 ? $hint . ' · ' . $count . ' ข้อ' : $hint;
        if (mb_strlen($sub) > 42) {
            $sub = mb_substr($sub, 0, 40) . '…';
        }
        $emoji = self::categoryEmoji($cat);
        return [
            'type' => 'box',
            'layout' => 'horizontal',
            'spacing' => 'md',
            'paddingAll' => '10px',
            'backgroundColor' => '#FFF8E7',
            'cornerRadius' => '14px',
            'alignItems' => 'center',
            'action' => [
                'type' => 'message',
                'label' => $short,
                'text' => $short,
            ],
            'contents' => [
                [
                    'type' => 'box',
                    'layout' => 'vertical',
                    'width' => '36px',
                    'height' => '36px',
                    'backgroundColor' => '#FFD54A',
                    'cornerRadius' => '10px',
                    'justifyContent' => 'center',
                    'alignItems' => 'center',
                    'flex' => 0,
                    'contents' => [[
                        'type' => 'text',
                        'text' => $emoji,
                        'size' => 'lg',
                        'align' => 'center',
                    ]],
                ],
                [
                    'type' => 'box',
                    'layout' => 'vertical',
                    'flex' => 1,
                    'contents' => [
                        [
                            'type' => 'text',
                            'text' => $short,
                            'weight' => 'bold',
                            'color' => '#5C4314',
                            'size' => 'sm',
                            'wrap' => true,
                        ],
                        [
                            'type' => 'text',
                            'text' => $sub,
                            'size' => 'xs',
                            'color' => '#7A6840',
                            'wrap' => true,
                            'margin' => 'xs',
                            'maxLines' => 1,
                        ],
                    ],
                ],
            ],
        ];
    }

    private static function categoryEmoji(array $cat): string
    {
        $icon = trim((string) ($cat['icon'] ?? ''));
        if ($icon !== '') {
            return Categories::emojiByIcon($icon);
        }
        return Categories::emojiByName((string) ($cat['name'] ?? ''));
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
                'timeout' => 5,
                'ignore_errors' => true,
            ],
        ]);
        @file_get_contents(self::REPLY_URL, false, $ctx);
        $status = 0;
        foreach (($http_response_header ?? []) as $headerLine) {
            if (preg_match('/^HTTP\/\S+\s+(\d+)/', $headerLine, $m) === 1) {
                $status = (int) $m[1];
                break;
            }
        }
        if ($status >= 400) {
            error_log('SCiGENIE LINE reply HTTP ' . $status);
        }
    }

    private static function isMenu(string $text): bool
    {
        $n = mb_strtolower(preg_replace('/\s+/u', '', $text) ?? $text);
        $n = preg_replace('/(ค่ะ|ครับ|คะ|นะคะ|นะครับ)+$/u', '', $n) ?? $n;
        if ($n === '') {
            return false;
        }
        if (str_starts_with($n, 'สวัสดี') || str_starts_with($n, 'หวัดดี')) {
            return true;
        }
        foreach (self::MENU_WORDS as $word) {
            if ($n === $word) {
                return true;
            }
        }
        return false;
    }

    private static function matchCategory(string $text, bool $allowIndex = true): ?string
    {
        $n = mb_strtolower(preg_replace('/\s+/u', '', $text) ?? $text);
        $n = preg_replace('/^(เลือก)?หมวด/u', '', $n) ?? $n;
        if ($allowIndex && preg_match('/^\d{1,2}$/', $n) === 1) {
            $idx = (int) $n - 1;
            $cats = self::categories();
            if (isset($cats[$idx]['name'])) {
                return (string) $cats[$idx]['name'];
            }
        }
        foreach (self::categories() as $cat) {
            $name = mb_strtolower(preg_replace('/\s+/u', '', (string) ($cat['name'] ?? '')) ?? '');
            $short = mb_strtolower(preg_replace('/\s+/u', '', (string) (($cat['short'] ?? '') !== '' ? $cat['short'] : $cat['name'])) ?? '');
            if ($n === $name || $n === $short) {
                return (string) $cat['name'];
            }
        }
        return null;
    }

    private static function categoryShort(string $name): string
    {
        foreach (self::categories() as $cat) {
            if ((string) ($cat['name'] ?? '') === $name) {
                $short = trim((string) ($cat['short'] ?? ''));
                return $short !== '' ? $short : $name;
            }
        }
        return Categories::short($name);
    }

    private static function categories(): array
    {
        static $cached = null;
        if (is_array($cached)) {
            return $cached;
        }
        try {
            $cached = self::faq()->categories();
        } catch (Throwable $e) {
            error_log('SCiGENIE LINE categories: ' . $e->getMessage());
            $cached = [];
            foreach (Categories::MAP as $name => $meta) {
                $cached[] = [
                    'name' => $name,
                    'short' => (string) $meta['short'],
                    'icon' => (string) $meta['icon'],
                    'hint' => (string) $meta['hint'],
                    'count' => 0,
                ];
            }
        }
        return $cached;
    }

    private static ?FaqService $faqService = null;

    private static function faq(): FaqService
    {
        if (self::$faqService === null) {
            self::$faqService = new FaqService();
        }
        return self::$faqService;
    }

    private static function getCategory(string $userId): string
    {
        $row = self::userSession($userId);
        $cat = trim((string) ($row['category'] ?? ''));
        $ts = (int) ($row['ts'] ?? 0);
        if ($cat === '' || $ts < time() - 86400) {
            return '';
        }
        return $cat;
    }

    private static function pickSuggestion(string $userId, string $text): ?string
    {
        if (preg_match('/^\d{1,2}$/', trim($text)) !== 1) {
            return null;
        }
        $row = self::userSession($userId);
        $suggest = $row['suggest'] ?? [];
        if (!is_array($suggest)) {
            return null;
        }
        $idx = (int) $text - 1;
        $q = trim((string) ($suggest[$idx] ?? ''));
        return $q !== '' ? $q : null;
    }

    private static function userSession(string $userId): array
    {
        if ($userId === '') {
            return [];
        }
        $all = self::sessions();
        $row = $all[$userId] ?? [];
        return is_array($row) ? $row : [];
    }

    private static function setCategory(string $userId, string $category): void
    {
        self::writeSession($userId, [
            'category' => $category,
            'suggest' => [],
            'ts' => time(),
        ]);
    }

    private static function setSuggestions(string $userId, array $questions): void
    {
        $row = self::userSession($userId);
        $row['suggest'] = array_values($questions);
        $row['ts'] = time();
        self::writeSession($userId, $row);
    }

    private static function pauseForHuman(string $userId): array
    {
        $fresh = !HumanPass::active(self::userSession($userId));
        self::holdHuman($userId);
        if (!$fresh) {
            return [];
        }
        return [self::textMessage(HumanPass::ack(), self::menuQuickReplies())];
    }

    private static function holdIfHuman(string $userId): bool
    {
        if (!HumanPass::active(self::userSession($userId))) {
            return false;
        }
        self::holdHuman($userId);
        return true;
    }

    private static function holdHuman(string $userId): void
    {
        if ($userId === '') {
            return;
        }
        self::writeSession($userId, HumanPass::hold(self::userSession($userId)));
    }

    private static function endHuman(string $userId): void
    {
        $row = self::userSession($userId);
        if (!isset($row['human_until'])) {
            return;
        }
        unset($row['human_until']);
        self::writeSession($userId, $row);
    }

    private static function clearSession(string $userId): void
    {
        self::writeSession($userId, ['category' => '', 'suggest' => [], 'ts' => time()]);
    }

    private static function writeSession(string $userId, array $row): void
    {
        if ($userId === '') {
            return;
        }
        $all = self::sessions();
        $all[$userId] = $row;
        self::saveSessions($all);
    }

    private static function sessions(): array
    {
        $file = self::sessionFile();
        if (!is_file($file) || !is_readable($file)) {
            return [];
        }
        $data = json_decode((string) @file_get_contents($file), true);
        return is_array($data) ? $data : [];
    }

    private static function saveSessions(array $all): void
    {
        try {
            $file = self::sessionFile();
            $dir = dirname($file);
            if (!is_dir($dir)) {
                @mkdir($dir, 0777, true);
            }
            if (!is_dir($dir) || !is_writable($dir)) {
                return;
            }
            @file_put_contents($file, json_encode($all, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
        } catch (Throwable $e) {
            error_log('SCiGENIE LINE session: ' . $e->getMessage());
        }
    }

    private static function sessionFile(): string
    {
        return dirname(__DIR__) . '/data/line-sessions.json';
    }

    private static function logAsk(string $userId, string $category, string $question, array $result): void
    {
        try {
            $item = $result['item'] ?? [];
            (new AskLog())->record([
                'visitor' => 'LINE' . substr(preg_replace('/[^A-Za-z0-9]/', '', $userId) ?? '', -5),
                'channel' => 'line',
                'category' => $category,
                'question' => $question,
                'matched' => !empty($result['matched']),
                'faq' => $item['question'] ?? '',
                'answer' => $item['answer'] ?? ($result['message'] ?? ''),
            ]);
        } catch (Throwable $e) {
            error_log('SCiGENIE LINE log: ' . $e->getMessage());
        }
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
