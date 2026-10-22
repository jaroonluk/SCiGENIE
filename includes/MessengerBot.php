<?php

declare(strict_types=1);

class MessengerBot
{
    private const SEND_URL = 'https://graph.facebook.com/v21.0/me/messages';
    private const MAX_TEXT = 2000;
    private const MAX_MENU_TEXT = 640;
    private const MENU_WORDS = ['เมนู', 'หมวด', 'เริ่มใหม่', 'menu', 'help', 'ช่วยเหลือ', 'สวัสดี', 'hello'];
    private static ?FaqService $faqService = null;
    private static bool $pageSubscribed = false;

    public static function configured(): bool
    {
        return (string) config('facebook_page_access_token', '') !== ''
            && (string) config('facebook_app_secret', '') !== '';
    }

    public static function verifyToken(): string
    {
        return (string) config('facebook_verify_token', 'scigenie-kku-fb-2026');
    }

    public static function verifySubscription(string $token): bool
    {
        $expected = self::verifyToken();
        return $expected !== '' && $token !== '' && hash_equals($expected, $token);
    }

    public static function verifySignature(string $raw, string $header): bool
    {
        $secret = (string) config('facebook_app_secret', '');
        if ($secret === '' || $header === '') {
            return false;
        }
        if (str_starts_with($header, 'sha256=')) {
            $given = substr($header, 7);
            $digest = hash_hmac('sha256', $raw, $secret);
            return hash_equals($digest, $given);
        }
        if (str_starts_with($header, 'sha1=')) {
            $given = substr($header, 5);
            $digest = hash_hmac('sha1', $raw, $secret);
            return hash_equals($digest, $given);
        }
        return false;
    }

    public static function handlePayload(array $payload): void
    {
        if (($payload['object'] ?? '') !== 'page') {
            return;
        }
        $entries = $payload['entry'] ?? [];
        if (!is_array($entries)) {
            return;
        }
        self::subscribePage();
        foreach ($entries as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            foreach (['messaging' => false, 'standby' => true] as $key => $standby) {
                $events = $entry[$key] ?? [];
                if (!is_array($events)) {
                    continue;
                }
                foreach ($events as $event) {
                    if (is_array($event)) {
                        self::handleEvent($event, $standby);
                    }
                }
            }
        }
    }

    public static function handleEvent(array $event, bool $standby = false): void
    {
        if (!empty($event['message']['is_echo'])) {
            $userId = (string) ($event['recipient']['id'] ?? '');
            if (HumanPass::isHumanEcho($event['message'], self::ourAppId(), self::inboxAppId())) {
                $fresh = !HumanPass::active(self::userSession($userId));
                self::holdHuman($userId);
                if ($fresh) {
                    self::passThread($userId);
                }
            }
            return;
        }

        $senderId = (string) ($event['sender']['id'] ?? '');
        if ($senderId === '') {
            return;
        }
        if (isset($event['take_thread_control']) || isset($event['pass_thread_control'])) {
            self::applyHandover($senderId, $event);
            return;
        }
        if (isset($event['delivery']) || isset($event['read'])) {
            return;
        }
        if ($standby) {
            self::handleStandby($senderId, $event);
            return;
        }

        try {
            if (isset($event['postback']['payload'])) {
                self::handlePostback($senderId, (string) $event['postback']['payload']);
                return;
            }
            $quickPayload = (string) ($event['message']['quick_reply']['payload'] ?? '');
            if ($quickPayload !== '') {
                self::handlePostback($senderId, $quickPayload);
                return;
            }
            $text = trim((string) ($event['message']['text'] ?? ''));
            if ($text === '') {
                if (self::holdIfHuman($senderId)) {
                    return;
                }
                self::send($senderId, [
                    self::textMessage('พิมพ์เป็นข้อความได้เลยค่ะ หรือพิมพ์ “เมนู” เพื่อเลือกหมวด'),
                ]);
                return;
            }
            $wasHuman = HumanPass::active(self::userSession($senderId));
            $messages = self::processText($senderId, $text);
            self::send($senderId, $messages);
            if (!$wasHuman && HumanPass::active(self::userSession($senderId))) {
                self::passThread($senderId);
            }
        } catch (Throwable $e) {
            error_log('SCiGENIE Facebook: ' . $e->getMessage());
            self::send($senderId, [self::textMessage(
                'ระบบตอบคำถามขัดข้องชั่วคราว กรุณาลองอีกครั้ง หรือโทร ' . (string) config('contact_phone', '043-009-700')
            )]);
        }
    }

    public static function processText(string $userId, string $text): array
    {
        $text = trim($text);
        if (HumanPass::isResume($text)) {
            self::clearSession($userId);
            return self::welcomeMessages($userId);
        }
        if (HumanPass::isPause($text)) {
            $fresh = !HumanPass::active(self::userSession($userId));
            self::holdHuman($userId);
            return $fresh ? [self::textMessage(HumanPass::ack(), [self::menuQuickReply()])] : [];
        }
        if (self::holdIfHuman($userId)) {
            return [];
        }
        if ($text === '' || self::isMenu($text)) {
            self::clearSession($userId);
            return self::welcomeMessages($userId);
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

    private static function handlePostback(string $userId, string $payload): void
    {
        $payload = trim($payload);
        if ($payload === 'HUMAN' || HumanPass::isPause($payload)) {
            $fresh = !HumanPass::active(self::userSession($userId));
            self::holdHuman($userId);
            if ($fresh) {
                self::send($userId, [self::textMessage(HumanPass::ack(), [self::menuQuickReply()])]);
                self::passThread($userId);
            }
            return;
        }
        self::endHuman($userId);
        if ($payload === '' || $payload === 'MENU' || $payload === 'GET_STARTED' || self::isMenu($payload)) {
            self::clearSession($userId);
            self::send($userId, self::welcomeMessages($userId));
            return;
        }
        if (str_starts_with($payload, 'CATIDX:')) {
            $name = self::categoryByIndex($userId, (int) substr($payload, 7));
            if ($name !== '') {
                self::send($userId, self::categorySelectedMessages($userId, $name));
                return;
            }
        }
        if (str_starts_with($payload, 'QIDX:')) {
            $question = self::questionByIndex($userId, (int) substr($payload, 5));
            if ($question !== '') {
                self::send($userId, self::processText($userId, $question));
                return;
            }
        }
        if (str_starts_with($payload, 'CAT:')) {
            $name = rawurldecode(substr($payload, 4));
            $cat = self::matchCategory($name, false) ?? $name;
            self::send($userId, self::categorySelectedMessages($userId, $cat));
            return;
        }
        if (str_starts_with($payload, 'Q:')) {
            $question = rawurldecode(substr($payload, 2));
            self::send($userId, self::processText($userId, $question));
        }
    }

    private static function welcomeMessages(string $userId = ''): array
    {
        $categories = self::uniqueCategories(self::categories());
        self::setCatIndex($userId, $categories);
        $messages = self::categoryTemplateMessages($categories, $userId);
        if ($messages === []) {
            return [self::textMessage('🟡 SCiGENIE — ยังไม่มีหมวดคำถามในระบบ')];
        }
        return self::withStaffQuickReply($messages);
    }

    /**
     * @param array<string, mixed> $result
     * @return list<array<string, mixed>>
     */
    private static function needCategoryMessages(string $userId, array $result = []): array
    {
        $text = trim((string) ($result['message'] ?? Matcher::needCategoryMessage()));
        $text .= "\n\n" . HumanPass::passHint();
        return array_merge([self::textMessage($text)], self::welcomeMessages($userId));
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
        return self::needCategoryMessages($userId, $result);
    }

    private static function categorySelectedMessages(string $userId, string $category): array
    {
        $known = false;
        foreach (self::categories() as $cat) {
            if ((string) ($cat['name'] ?? '') === $category) {
                $known = true;
                break;
            }
        }
        if (!$known) {
            $matched = self::matchCategory($category, false);
            if ($matched === null) {
                return self::welcomeMessages($userId);
            }
            $category = $matched;
        }

        self::setCategory($userId, $category);
        self::setSuggestions($userId, []);
        $emoji = Categories::emojiByName($category);
        $short = self::categoryShort($category);
        $text = "เลือกหมวด {$emoji} {$short} แล้วค่ะ\nพิมพ์คำถามที่ช่องข้อความได้เลยค่ะ";
        $messages = [self::textMessage($text)];
        if ($userId !== '') {
            $menuUrl = self::signedMenuUrl($userId, $category);
            $messages[] = self::genericCarousel([[
                'title' => self::clipTitle($emoji . ' ' . $short, 80),
                'subtitle' => 'พิมพ์คำถามได้เลย ไม่ต้องเลือกจากรายการ',
                'buttons' => [
                    self::webUrlButton('พิมพ์เต็มจอ', $menuUrl),
                    [
                        'type' => 'postback',
                        'title' => '◀ เมนูหมวด',
                        'payload' => 'MENU',
                    ],
                ],
            ]], [self::menuQuickReply()]);
        } else {
            $messages[] = self::backMenuMessage();
        }
        return $messages;
    }

    private static function answerMessages(string $userId, array $result, string $category): array
    {
        $emoji = $category !== '' ? Categories::emojiByName($category) : '';
        $short = $category !== '' ? self::categoryShort($category) : '';
        $head = $short !== '' ? trim($emoji . ' หมวด: ' . $short) . "\n\n" : '';

        if (!empty($result['matched']) && !empty($result['item']['answer'])) {
            $body = self::plainText((string) $result['item']['answer']);
            self::setSuggestions($userId, []);
            return [
                self::textMessage(self::clip($head . "คำตอบ\n" . $body)),
                self::backMenuMessage(),
            ];
        }

        if (!empty($result['need_category'])) {
            return self::categoryPrompt($userId, $result);
        }

        $msg = $head . trim((string) ($result['message'] ?? Matcher::noMatchMessage()));
        self::setSuggestions($userId, []);
        return self::withStaffQuickReply([
            self::textMessage($msg),
            self::backMenuMessage(),
        ]);
    }

    private static function categoryTemplateMessages(array $categories, string $userId = ''): array
    {
        $categories = array_values($categories);
        if ($categories === []) {
            return [];
        }

        $groupTitles = [
            'การเรียนและนักศึกษา',
            'สอบ ทะเบียน จบศึกษา',
            'สหกิจ บัณฑิต ทุน',
            'สวัสดิการและบริการ',
            'วารสารและกิจกรรม',
        ];
        $chunks = array_values(array_chunk($categories, 3));
        $totalPages = count($chunks);
        $elements = [];
        if ($userId !== '') {
            $menuUrl = self::signedMenuUrl($userId);
            $elements[] = [
                'title' => 'ถาม–ตอบ SCiGENIE',
                'subtitle' => 'เลือกหมวด แล้วพิมพ์คำถามได้เลย',
                'image_url' => self::assetUrl('assets/img/card-qa.png'),
                'default_action' => self::webUrlAction($menuUrl),
                'buttons' => [self::webUrlButton('เปิดเมนูเต็มจอ', $menuUrl)],
            ];
        }
        $index = 0;
        foreach ($chunks as $page => $chunk) {
            $buttons = [];
            foreach ($chunk as $cat) {
                $name = trim((string) ($cat['name'] ?? ''));
                if ($name === '') {
                    continue;
                }
                $short = trim((string) (($cat['short'] ?? '') !== '' ? $cat['short'] : $name));
                $emoji = Categories::emojiByName($name);
                $buttons[] = [
                    'type' => 'postback',
                    'title' => self::clipTitle($emoji . ' ' . $short, 20),
                    'payload' => 'CATIDX:' . $index,
                ];
                $index++;
            }
            if ($buttons === []) {
                continue;
            }
            $pageLabel = $totalPages > 1 ? ($page + 1) . '/' . $totalPages . ' · ' : '';
            $elements[] = [
                'title' => self::clipTitle('🟡 ' . ($groupTitles[$page] ?? 'เลือกหมวดคำถาม'), 80),
                'subtitle' => self::clipTitle($pageLabel . 'แตะหมวดด้านล่างได้เลย', 80),
                'buttons' => array_slice($buttons, 0, 3),
            ];
        }
        if ($elements === []) {
            return [];
        }

        return [
            self::textMessage("🟡 SCiGENIE\nคณะวิทยาศาสตร์ มหาวิทยาลัยขอนแก่น\n\nเลือกหมวดด้านล่าง แล้วพิมพ์คำถามได้เลยค่ะ"),
            self::genericCarousel($elements),
        ];
    }

    private static function questionTemplateMessages(array $questions, string $category, string $userId = ''): array
    {
        $questions = array_values(self::uniqueTexts($questions));
        if ($questions === []) {
            return [];
        }

        $emoji = $category !== '' ? Categories::emojiByName($category) : '💬';
        $short = $category !== '' ? self::categoryShort($category) : 'คำถามแนะนำ';
        $elements = [];
        $slot = 10;
        if ($userId !== '') {
            $menuUrl = self::signedMenuUrl($userId, $category);
            $elements[] = [
                'title' => self::clipTitle($emoji . ' ' . $short, 80),
                'subtitle' => 'เปิดเต็มจอ แล้วพิมพ์หรือแตะคำถามได้เลย',
                'image_url' => self::assetUrl('assets/img/card-qa.png'),
                'default_action' => self::webUrlAction($menuUrl),
                'buttons' => [self::webUrlButton('ดูรายการเต็มจอ', $menuUrl)],
            ];
            $slot--;
        }
        $slot--; // back card
        foreach (array_slice($questions, 0, max(1, $slot)) as $i => $q) {
            $q = trim((string) $q);
            if ($q === '') {
                continue;
            }
            $elements[] = [
                'title' => self::clipTitle($q, 80),
                'subtitle' => self::clipTitle($emoji . '  ' . $short . ' · แตะดูคำตอบ', 80),
                'buttons' => [[
                    'type' => 'postback',
                    'title' => 'ดูคำตอบ',
                    'payload' => 'QIDX:' . $i,
                ]],
            ];
        }
        $elements[] = [
            'title' => 'กลับเมนูหมวด',
            'subtitle' => 'เลือกหมวดคำถามอื่น',
            'buttons' => [[
                'type' => 'postback',
                'title' => '◀ เมนูหมวด',
                'payload' => 'MENU',
            ]],
        ];

        return [self::genericCarousel($elements, [self::menuQuickReply()])];
    }

    private static function backMenuMessage(string $text = '🟡 SCiGENIE'): array
    {
        return self::buttonTemplate($text, [[
            'type' => 'postback',
            'title' => '◀ เมนูหมวด',
            'payload' => 'MENU',
        ]], [self::menuQuickReply()]);
    }

    private static function genericCarousel(array $elements, array $quickReplies = []): array
    {
        $msg = [
            'attachment' => [
                'type' => 'template',
                'payload' => [
                    'template_type' => 'generic',
                    'image_aspect_ratio' => 'horizontal',
                    'elements' => array_slice(array_values($elements), 0, 10),
                ],
            ],
        ];
        if ($quickReplies !== []) {
            $msg['quick_replies'] = array_slice($quickReplies, 0, 13);
        }
        return $msg;
    }

    private static function buttonTemplate(string $text, array $buttons, array $quickReplies = []): array
    {
        $msg = [
            'attachment' => [
                'type' => 'template',
                'payload' => [
                    'template_type' => 'button',
                    'text' => self::clip($text, 640),
                    'buttons' => array_slice($buttons, 0, 3),
                ],
            ],
        ];
        if ($quickReplies !== []) {
            $msg['quick_replies'] = array_slice($quickReplies, 0, 13);
        }
        return $msg;
    }

    private static function webUrlButton(string $title, string $url): array
    {
        return array_merge(self::webUrlAction($url), [
            'title' => self::clipTitle($title, 20),
            'webview_share_button' => 'hide',
        ]);
    }

    private static function webUrlAction(string $url): array
    {
        return [
            'type' => 'web_url',
            'url' => $url,
            'webview_height_ratio' => 'full',
        ];
    }

    private static function assetUrl(string $relative): string
    {
        return rtrim(app_base_url(), '/') . '/' . ltrim($relative, '/');
    }

    public static function signedMenuUrl(string $userId, string $category = ''): string
    {
        $url = rtrim(app_base_url(), '/') . '/fb/menu.php?t=' . rawurlencode(self::menuToken($userId));
        if ($category !== '') {
            $url .= '&c=' . rawurlencode($category);
        }
        return $url;
    }

    public static function menuToken(string $userId): string
    {
        $exp = (string) (time() + 7 * 86400);
        $data = $userId . '.' . $exp;
        return $data . '.' . hash_hmac('sha256', $data, self::menuSecret());
    }

    public static function userFromMenuToken(string $token): string
    {
        $token = trim($token);
        if ($token === '' || !str_contains($token, '.')) {
            return '';
        }
        $parts = explode('.', $token);
        if (count($parts) < 3) {
            return '';
        }
        $sig = (string) array_pop($parts);
        $exp = (string) array_pop($parts);
        $userId = implode('.', $parts);
        if ($userId === '' || !ctype_digit($exp)) {
            return '';
        }
        $expected = hash_hmac('sha256', $userId . '.' . $exp, self::menuSecret());
        if (!hash_equals($expected, $sig) || (int) $exp < time()) {
            return '';
        }
        return $userId;
    }

    public static function replyFromWeb(string $userId, string $question): void
    {
        $question = trim($question);
        if ($userId === '' || $question === '') {
            return;
        }
        self::send($userId, self::processText($userId, $question));
    }

    public static function matchFromWeb(string $userId, string $question, string $category = ''): array
    {
        $question = trim($question);
        $category = trim($category);
        if ($question === '') {
            return ['ok' => false, 'matched' => false, 'message' => 'กรุณาพิมพ์คำถามที่ต้องการสอบถาม'];
        }
        if ($category === '') {
            $category = self::getCategory($userId);
        }
        $asCat = self::matchCategory($question, false);
        if ($asCat !== null) {
            self::setCategory($userId, $asCat);
            return [
                'ok' => true,
                'matched' => false,
                'select_category' => $asCat,
                'category' => $asCat,
                'short' => self::categoryShort($asCat),
            ];
        }
        $result = self::faq()->match($question, $category);
        $result['ok'] = true;
        $result['category'] = $category;
        $result['question'] = $question;
        if (!empty($result['matched']) && !empty($result['item']['category'])) {
            $foundCat = trim((string) $result['item']['category']);
            self::setCategory($userId, $foundCat);
            $result['category'] = $foundCat;
        }
        self::logAsk($userId, (string) ($result['category'] ?? $category), $question, $result);
        $preview = self::plainText((string) (($result['item']['answer'] ?? '') !== '' ? $result['item']['answer'] : ($result['message'] ?? '')));
        if ($preview !== '') {
            $title = trim((string) ($result['item']['question'] ?? ''));
            $text = $title !== '' ? $title . "\n\n" . $preview : $preview;
            self::send($userId, [self::textMessage(self::clip($text))]);
        }
        return $result;
    }

    private static function menuSecret(): string
    {
        $secret = (string) config('facebook_app_secret', '');
        if ($secret !== '') {
            return $secret;
        }
        return (string) config('facebook_verify_token', 'scigenie-kku-fb-2026');
    }

    private static function menuQuickReply(): array
    {
        return [
            'content_type' => 'text',
            'title' => '◀ เมนูหมวด',
            'payload' => 'MENU',
        ];
    }

    private static function humanQuickReply(): array
    {
        return [
            'content_type' => 'text',
            'title' => 'คุยกับเจ้าหน้าที่',
            'payload' => 'HUMAN',
        ];
    }

    /**
     * @param list<array<string, mixed>> $messages
     * @return list<array<string, mixed>>
     */
    private static function withStaffQuickReply(array $messages): array
    {
        if ($messages === []) {
            return $messages;
        }
        $last = array_key_last($messages);
        $quick = $messages[$last]['quick_replies'] ?? [];
        if (!is_array($quick)) {
            $quick = [];
        }
        $quick[] = self::humanQuickReply();
        $messages[$last]['quick_replies'] = array_slice($quick, 0, 13);
        return $messages;
    }

    private static function textMessage(string $text, array $quickReplies = [], bool $menuStyle = false): array
    {
        $limit = $menuStyle ? self::MAX_MENU_TEXT : self::MAX_TEXT;
        $msg = [
            'text' => self::clip($text, $limit),
        ];
        if ($quickReplies !== []) {
            $msg['quick_replies'] = $quickReplies;
        }
        return $msg;
    }

    private static function send(string $recipientId, array $messages): void
    {
        $token = (string) config('facebook_page_access_token', '');
        if ($token === '' || $recipientId === '' || $messages === []) {
            return;
        }
        foreach (array_slice($messages, 0, 5) as $message) {
            if (self::postGraph('me/messages', [
                'recipient' => ['id' => $recipientId],
                'messaging_type' => 'RESPONSE',
                'message' => $message,
            ])) {
                continue;
            }
            $retry = self::withoutImages($message);
            if ($retry !== $message && self::postGraph('me/messages', [
                'recipient' => ['id' => $recipientId],
                'messaging_type' => 'RESPONSE',
                'message' => $retry,
            ])) {
                continue;
            }
            $fallback = self::messageToText($message);
            if ($fallback !== '') {
                $quick = $message['quick_replies'] ?? [];
                self::postGraph('me/messages', [
                    'recipient' => ['id' => $recipientId],
                    'messaging_type' => 'RESPONSE',
                    'message' => self::textMessage($fallback, is_array($quick) ? $quick : []),
                ]);
            }
        }
    }

    private static function withoutImages(array $message): array
    {
        $elements = $message['attachment']['payload']['elements'] ?? null;
        if (!is_array($elements)) {
            return $message;
        }
        foreach ($elements as &$el) {
            unset($el['image_url']);
        }
        unset($el);
        $message['attachment']['payload']['elements'] = $elements;
        return $message;
    }

    private static function messageToText(array $message): string
    {
        $text = trim((string) ($message['text'] ?? ''));
        if ($text !== '') {
            return $text;
        }
        $payload = $message['attachment']['payload'] ?? [];
        if (!is_array($payload)) {
            return '';
        }
        $lines = [];
        $payloadText = trim((string) ($payload['text'] ?? ''));
        if ($payloadText !== '') {
            $lines[] = $payloadText;
        }
        $elements = $payload['elements'] ?? null;
        if (is_array($elements)) {
            foreach ($elements as $el) {
                if (!is_array($el)) {
                    continue;
                }
                $title = trim((string) ($el['title'] ?? ''));
                $sub = trim((string) ($el['subtitle'] ?? ''));
                if ($title !== '') {
                    $lines[] = $title;
                }
                if ($sub !== '') {
                    $lines[] = $sub;
                }
                foreach (($el['buttons'] ?? []) as $btn) {
                    if (!is_array($btn)) {
                        continue;
                    }
                    $label = trim((string) ($btn['title'] ?? ''));
                    if ($label !== '') {
                        $lines[] = '• ' . $label;
                    }
                }
            }
        }
        return trim(implode("\n", $lines));
    }

    public static function subscribePage(): bool
    {
        if (self::$pageSubscribed) {
            return true;
        }
        $flag = dirname(__DIR__) . '/data/fb-subscribed-v2.flag';
        if (is_file($flag) && (time() - (int) @filemtime($flag)) < 86400) {
            self::$pageSubscribed = true;
            return true;
        }
        $fail = dirname(__DIR__) . '/data/fb-subscribe.fail';
        if (is_file($fail) && (time() - (int) @filemtime($fail)) < 3600) {
            return false;
        }
        $fields = 'messages,messaging_postbacks,messaging_optins,message_echoes,messaging_handovers';
        $ok = self::postGraph('me/subscribed_apps', ['subscribed_fields' => $fields]);
        if (!$ok) {
            $ok = self::postGraph('me/subscribed_apps', [
                'subscribed_fields' => 'messages,messaging_postbacks,messaging_optins,message_echoes',
            ]);
        }
        if ($ok) {
            self::$pageSubscribed = true;
            @touch($flag);
            if (is_file($fail)) {
                @unlink($fail);
            }
            return true;
        }
        @touch($fail);
        return false;
    }

    private static function takeThreadControl(string $recipientId): void
    {
        if ($recipientId === '') {
            return;
        }
        self::postGraph('me/take_thread_control', [
            'recipient' => ['id' => $recipientId],
        ]);
    }

    private static function passThread(string $recipientId): void
    {
        if ($recipientId === '') {
            return;
        }
        $inbox = self::inboxAppId();
        $target = ctype_digit($inbox) ? (int) $inbox : $inbox;
        self::postGraph('me/pass_thread_control', [
            'recipient' => ['id' => $recipientId],
            'target_app_id' => $target,
            'metadata' => 'scigenie-human',
        ]);
    }

    private static function applyHandover(string $userId, array $event): void
    {
        $inbox = self::inboxAppId();
        $ours = self::ourAppId();
        if (isset($event['take_thread_control'])) {
            $owner = trim((string) ($event['take_thread_control']['new_owner_app_id'] ?? ''));
            if ($ours !== '' && $owner === $ours) {
                self::endHuman($userId);
                return;
            }
            self::holdHuman($userId);
            return;
        }
        $owner = trim((string) ($event['pass_thread_control']['new_owner_app_id'] ?? ''));
        if ($owner !== '' && ($owner === $inbox || $owner === HumanPass::INBOX_APP_ID)) {
            self::holdHuman($userId);
            return;
        }
        self::endHuman($userId);
    }

    private static function handleStandby(string $userId, array $event): void
    {
        $payload = trim((string) ($event['postback']['payload'] ?? $event['message']['quick_reply']['payload'] ?? ''));
        $text = trim((string) ($event['message']['text'] ?? ''));
        if ($payload === 'HUMAN' || HumanPass::isPause($text) || HumanPass::isPause($payload)) {
            self::holdHuman($userId);
            return;
        }
        $resume = $payload === 'MENU'
            || $payload === 'GET_STARTED'
            || HumanPass::isResume($text)
            || HumanPass::isResume($payload);
        $botAction = $payload !== '' && (str_starts_with($payload, 'CAT') || str_starts_with($payload, 'Q'));
        if ($resume || $botAction) {
            self::takeThreadControl($userId);
            self::endHuman($userId);
            if ($payload !== '') {
                self::handlePostback($userId, $payload);
                return;
            }
            self::send($userId, self::processText($userId, $text));
            return;
        }
        self::holdHuman($userId);
    }

    private static function inboxAppId(): string
    {
        $id = trim((string) config('facebook_inbox_app_id', ''));
        return $id !== '' ? $id : HumanPass::INBOX_APP_ID;
    }

    private static function ourAppId(): string
    {
        static $memo = null;
        if (is_string($memo)) {
            return $memo;
        }
        $fromEnv = trim((string) config('facebook_app_id', ''));
        if ($fromEnv !== '') {
            return $memo = $fromEnv;
        }
        $file = dirname(__DIR__) . '/data/fb-app-id.txt';
        if (is_file($file) && (time() - (int) @filemtime($file)) < 7 * 86400) {
            $cached = trim((string) @file_get_contents($file));
            if ($cached !== '') {
                return $memo = $cached;
            }
        }
        $fail = dirname(__DIR__) . '/data/fb-app-id.fail';
        if (is_file($fail) && (time() - (int) @filemtime($fail)) < 3600) {
            return $memo = '';
        }
        $id = self::fetchAppId();
        if ($id === '') {
            @touch($fail);
            return $memo = '';
        }
        if (is_file($fail)) {
            @unlink($fail);
        }
        @file_put_contents($file, $id, LOCK_EX);
        return $memo = $id;
    }

    private static function fetchAppId(): string
    {
        $token = (string) config('facebook_page_access_token', '');
        if ($token === '') {
            return '';
        }
        $url = 'https://graph.facebook.com/v21.0/debug_token?input_token='
            . rawurlencode($token)
            . '&access_token='
            . rawurlencode($token);
        $raw = self::httpGet($url);
        if ($raw === null) {
            return '';
        }
        $decoded = json_decode($raw, true);
        return trim((string) (is_array($decoded) ? ($decoded['data']['app_id'] ?? '') : ''));
    }

    private static function httpGet(string $url): ?string
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 5,
            ]);
            $out = curl_exec($ch);
            return is_string($out) ? $out : null;
        }
        $ctx = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 5,
                'ignore_errors' => true,
            ],
        ]);
        $out = @file_get_contents($url, false, $ctx);
        return is_string($out) ? $out : null;
    }

    private static function postGraph(string $path, array $body): bool
    {
        $token = (string) config('facebook_page_access_token', '');
        if ($token === '') {
            return false;
        }
        $payload = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($payload)) {
            return false;
        }
        $url = 'https://graph.facebook.com/v21.0/' . ltrim($path, '/') . '?access_token=' . rawurlencode($token);
        $result = self::httpPost($url, $payload);
        if ($result === null) {
            error_log('SCiGENIE Facebook Graph empty: ' . $path);
            return false;
        }
        $decoded = json_decode($result, true);
        if (is_array($decoded) && isset($decoded['error'])) {
            $msg = (string) ($decoded['error']['message'] ?? $result);
            error_log('SCiGENIE Facebook Graph ' . $path . ': ' . mb_substr($msg, 0, 300));
            return false;
        }
        return true;
    }

    private static function httpPost(string $url, string $payload): ?string
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_POSTFIELDS => $payload,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 5,
            ]);
            $out = curl_exec($ch);
            return is_string($out) ? $out : null;
        }
        $ctx = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\n",
                'content' => $payload,
                'timeout' => 5,
                'ignore_errors' => true,
            ],
        ]);
        $out = @file_get_contents($url, false, $ctx);
        return is_string($out) ? $out : null;
    }

    public static function setupPersistentMenu(bool $force = false): bool
    {
        $token = (string) config('facebook_page_access_token', '');
        if ($token === '') {
            return false;
        }
        $flag = dirname(__DIR__) . '/data/fb-menu-v2.flag';
        if (!$force && is_file($flag) && (time() - (int) @filemtime($flag)) < 86400) {
            return true;
        }
        $ok = self::postGraph('me/messenger_profile', [
            'get_started' => ['payload' => 'MENU'],
            'greeting' => [[
                'locale' => 'default',
                'text' => 'สวัสดี {{user_first_name}} จาก SCiGENIE คณะวิทยาศาสตร์ มข. กดเริ่มต้น แล้วเลือกหมวดได้เลยค่ะ',
            ]],
            'persistent_menu' => [[
                'locale' => 'default',
                'composer_input_disabled' => false,
                'call_to_actions' => [
                    [
                        'type' => 'postback',
                        'title' => 'เลือกหมวดคำถาม',
                        'payload' => 'MENU',
                    ],
                    [
                        'type' => 'postback',
                        'title' => 'คุยกับเจ้าหน้าที่',
                        'payload' => 'HUMAN',
                    ],
                ],
            ]],
        ]);
        self::postGraph('me/messenger_profile', [
            'ice_breakers' => [
                [
                    'question' => 'เลือกหมวดคำถาม',
                    'payload' => 'MENU',
                ],
                [
                    'question' => 'คุยกับเจ้าหน้าที่',
                    'payload' => 'HUMAN',
                ],
            ],
        ]);
        if ($ok) {
            $dir = dirname($flag);
            if (!is_dir($dir)) {
                @mkdir($dir, 0777, true);
            }
            @touch($flag);
        }
        return $ok;
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
            error_log('SCiGENIE Facebook categories: ' . $e->getMessage());
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
        $row = self::userSession($userId);
        self::writeSession($userId, [
            'category' => $category,
            'suggest' => [],
            'cats' => $row['cats'] ?? [],
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

    private static function setCatIndex(string $userId, array $categories): void
    {
        $names = [];
        foreach ($categories as $cat) {
            $name = trim((string) ($cat['name'] ?? ''));
            if ($name !== '') {
                $names[] = $name;
            }
        }
        $row = self::userSession($userId);
        $row['cats'] = $names;
        $row['ts'] = time();
        self::writeSession($userId, $row);
    }

    private static function categoryByIndex(string $userId, int $index): string
    {
        $row = self::userSession($userId);
        $cats = $row['cats'] ?? [];
        if (!is_array($cats) || $cats === []) {
            $cats = [];
            foreach (self::categories() as $cat) {
                $cats[] = (string) ($cat['name'] ?? '');
            }
        }
        return trim((string) ($cats[$index] ?? ''));
    }

    private static function questionByIndex(string $userId, int $index): string
    {
        $row = self::userSession($userId);
        $suggest = $row['suggest'] ?? [];
        if (!is_array($suggest)) {
            return '';
        }
        return trim((string) ($suggest[$index] ?? ''));
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
            error_log('SCiGENIE Facebook session: ' . $e->getMessage());
        }
    }

    private static function sessionFile(): string
    {
        return dirname(__DIR__) . '/data/fb-sessions.json';
    }

    private static function logAsk(string $userId, string $category, string $question, array $result): void
    {
        try {
            $item = $result['item'] ?? [];
            (new AskLog())->record([
                'visitor' => 'FB' . substr(preg_replace('/[^A-Za-z0-9]/', '', $userId) ?? '', -5),
                'channel' => 'facebook',
                'category' => $category,
                'question' => $question,
                'matched' => !empty($result['matched']),
                'faq' => $item['question'] ?? '',
                'answer' => $item['answer'] ?? ($result['message'] ?? ''),
            ]);
        } catch (Throwable $e) {
            error_log('SCiGENIE Facebook log: ' . $e->getMessage());
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

    private static function clip(string $text, int $max = 0): string
    {
        $limit = $max > 0 ? $max : self::MAX_TEXT;
        if (mb_strlen($text) <= $limit) {
            return $text;
        }
        return mb_substr($text, 0, $limit - 12) . "\n…(ต่อ)";
    }

    private static function clipTitle(string $text, int $max): string
    {
        $text = trim($text);
        if (mb_strlen($text) <= $max) {
            return $text;
        }
        return mb_substr($text, 0, $max - 1) . '…';
    }

    private static function uniqueTexts(array $items): array
    {
        $out = [];
        $seen = [];
        foreach ($items as $item) {
            $text = trim((string) $item);
            if ($text === '') {
                continue;
            }
            $key = mb_strtolower($text);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = $text;
        }
        return $out;
    }

    private static function uniqueCategories(array $rows): array
    {
        $out = [];
        $seen = [];
        foreach ($rows as $row) {
            $name = trim((string) ($row['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $key = mb_strtolower($name);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = $row;
        }
        return $out;
    }
}
