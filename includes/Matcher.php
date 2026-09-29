<?php

declare(strict_types=1);

class Matcher
{
    private const MIN_SCORE = 12;

    private const SYNONYMS = [
        'ที่อยู่' => ['ที่ตั้ง', 'อยู่ที่ไหน', 'ที่ไหน', 'address', 'สถานที่', 'ที่ตั้งคณะ', 'ตั้งอยู่'],
        'เบอร์โทร' => ['โทร', 'โทรศัพท์', 'เบอร์', 'ติดต่อ', 'phone', 'tel', 'เบอร์โทรศัพท์'],
        'อีเมล' => ['email', 'mail', 'อีเมล์', 'อีเมลล์'],
        'แผนที่' => ['map', 'google map', 'พิกัด', 'พิกัดแผนที่', 'เส้นทาง'],
        'เวลาเปิด-ปิด' => ['เปิดกี่โมง', 'ปิดกี่โมง', 'เวลาทำการ', 'วันเปิด-ปิด', 'เปิดปิด'],
        'ลงทะเบียน' => ['reg', 'เพิ่มวิชา', 'ถอนวิชา', 'หน่วยกิต'],
        'ค่าธรรมเนียม' => ['ค่าเทอม', 'ค่าเล่าเรียน', 'tuition', 'ค่าธรรมเนียมการศึกษา'],
        'ผ่อนผัน' => ['ผ่อน', 'แบ่งจ่าย', 'ชำระช้า'],
        'เลื่อนสอบ' => ['ขาดสอบ', 'ไม่ได้เข้าสอบ', 'ลาสอบ', 'สอบชดเชย'],
        'แต่งกาย' => ['แต่งตัว', 'ชุดเข้าสอบ', 'ใส่ชุด', 'ชุดไปสอบ', 'ใส่ชุดอะไร', 'เครื่องแต่งกาย', 'ชุดแบบไหน', 'ใส่ชุดแบบไหน'],
        'กางเกงขาสั้น' => ['กางเกงสั้น', 'ขาสั้น', 'กระโปรงสั้น'],
        'กางเกงสั้น' => ['กางเกงขาสั้น', 'ขาสั้น'],
        'เสื้อไม่มีแขน' => ['เสื้อแขนกุด', 'แขนกุด', 'เสื้อกล้าม', 'เสื้อสายเดี่ยว', 'แขนกุ'],
        'เสื้อแขนกุด' => ['เสื้อไม่มีแขน', 'แขนกุด', 'ไม่มีแขน'],
        'ยีนส์' => ['กางเกงยีนส์', 'กางเกงสแล็ค', 'กางเกงสแลค', 'jeans', 'กางเกงขายาว'],
        'เกียรตินิยม' => ['เกียรตินิยมอันดับ', 'honors'],
        'สหกิจ' => ['สหกิจศึกษา', 'coop', 'ฝึกงาน'],
        'สวัสดิการ' => ['รักษาพยาบาล', 'บัตรทอง', 'สุขภาพ'],
        'ทุน' => ['ทุนการศึกษา', 'ทุนอุดหนุน'],
        'portfolio' => ['พอร์ต', 'แฟ้มสะสมงาน', 'tcas1'],
        'พสวท' => ['พสวท.', 'dpst'],
        'สควค' => ['สควค.', 'สควค'],
        'วมว' => ['วมว.', 'scius', 'วมว-มข', 'วมว.มข'],
    ];

    public function ask(array $faqs, string $question, string $category = '', bool $allowCrossCategory = true): array
    {
        $question = trim($question);
        if ($question === '') {
            return [
                'matched' => false,
                'message' => 'กรุณาพิมพ์คำถามที่ต้องการสอบถาม',
                'items' => [],
                'suggestions' => $this->suggestions($faqs, $category),
            ];
        }

        $pool = $faqs;
        if ($category !== '') {
            $pool = array_values(array_filter($faqs, static fn ($row) => ($row['category'] ?? '') === $category));
        }

        $scored = $this->scoreAll($pool, $question);
        $topScore = (int) ($scored[0]['score'] ?? 0);
        if ($topScore < 22 || $scored === []) {
            $scored = $this->mergeSemanticScores($scored, $pool, $question);
        }

        $eligible = [];
        foreach ($scored as $row) {
            if ((int) ($row['score'] ?? 0) >= self::MIN_SCORE) {
                $eligible[] = $row;
            }
            if (count($eligible) >= 5) {
                break;
            }
        }

        if ($eligible === []) {
            if ($category !== '' && $allowCrossCategory) {
                $cross = $this->crossCategoryMatch($faqs, $question, $category);
                if ($cross !== null) {
                    return $cross;
                }
            }
            $items = array_map(fn ($row) => $this->publicFaq($row['faq'], $row['score']), array_slice($scored, 0, 3));
            if ($category === '') {
                return $this->needCategoryResult($faqs, $items);
            }
            return [
                'matched' => false,
                'message' => self::noMatchMessage(),
                'items' => $items,
                'suggestions' => $this->suggestions($faqs, $category),
            ];
        }

        $picked = $eligible[0];
        $trusted = (int) ($picked['score'] ?? 0) >= 22;
        if (OpenAiFaqVerifier::configured()) {
            $candidates = [];
            foreach ($eligible as $row) {
                $candidates[] = $this->publicFaq($row['faq'], $row['score'], $row['hits'] ?? []);
            }
            $verdict = OpenAiFaqVerifier::verify($question, $category, $candidates);
            if ($verdict === null || OpenAiFaqVerifier::isParseFailure($verdict)) {
                if ($verdict !== null) {
                    error_log('SCiGENIE OpenAI parse fallback: ' . mb_substr((string) ($verdict['reason'] ?? ''), 0, 120));
                } else {
                    error_log('SCiGENIE OpenAI unavailable, using matcher result');
                }
            } elseif (OpenAiFaqVerifier::accept($verdict, $candidates)) {
                $trusted = true;
                $chosenId = (string) ($verdict['chosen_id'] ?? '');
                foreach ($eligible as $row) {
                    if ((string) ($row['faq']['id'] ?? '') === $chosenId) {
                        $picked = $row;
                        break;
                    }
                }
            } elseif ($this->shouldUseMatcherDespiteReject($verdict, $picked, $question)) {
                $trusted = true;
                error_log('SCiGENIE OpenAI reject but matcher trusted, using matcher');
            } else {
                $why = $verdict['reason'] ?? 'rejected';
                error_log('SCiGENIE OpenAI reject: ' . mb_substr((string) $why, 0, 200));
                if ($category !== '' && $allowCrossCategory) {
                    $cross = $this->crossCategoryMatch($faqs, $question, $category);
                    if ($cross !== null) {
                        return $cross;
                    }
                }
                if ($category === '') {
                    return $this->needCategoryResult($faqs, array_slice($candidates, 0, 3));
                }
                return [
                    'matched' => false,
                    'message' => self::noMatchMessage(),
                    'items' => array_slice($candidates, 0, 3),
                    'suggestions' => $this->suggestions($faqs, $category),
                ];
            }
        }

        if ($category === '' && !$trusted) {
            $items = [];
            foreach (array_slice($eligible, 0, 3) as $row) {
                $items[] = $this->publicFaq($row['faq'], $row['score'], $row['hits'] ?? []);
            }
            return $this->needCategoryResult($faqs, $items);
        }

        // Locked category may still produce a false local hit — prefer a clearly better answer elsewhere.
        if ($category !== '' && $allowCrossCategory) {
            $cross = $this->crossCategoryMatch($faqs, $question, $category);
            if ($cross !== null) {
                $crossScore = (int) ($cross['item']['score'] ?? 0);
                $localScore = (int) ($picked['score'] ?? 0);
                $margin = $trusted ? 8 : 0;
                if ($crossScore >= $localScore + $margin) {
                    return $cross;
                }
            }
        }

        $related = [];
        foreach ($eligible as $item) {
            if ((string) ($item['faq']['id'] ?? '') === (string) ($picked['faq']['id'] ?? '')) {
                continue;
            }
            if ((int) ($item['score'] ?? 0) >= self::MIN_SCORE * 0.7) {
                $related[] = $this->publicFaq($item['faq'], $item['score']);
            }
            if (count($related) >= 3) {
                break;
            }
        }

        $itemCat = trim((string) ($picked['faq']['category'] ?? ''));
        return [
            'matched' => true,
            'confidence' => $this->confidence((int) ($picked['score'] ?? 0)),
            'item' => $this->publicFaq($picked['faq'], $picked['score'], $picked['hits'] ?? []),
            'related' => $related,
            'suggestions' => [],
            'other_category' => $category !== '' && $itemCat !== '' && $itemCat !== $category,
        ];
    }

    /**
     * Retry matching across all FAQs when the locked category has no good answer.
     *
     * @param list<array<string, mixed>> $faqs
     * @return array<string, mixed>|null
     */
    private function crossCategoryMatch(array $faqs, string $question, string $lockedCategory): ?array
    {
        $cross = $this->ask($faqs, $question, '', false);
        if (empty($cross['matched']) || empty($cross['item']['answer'])) {
            return null;
        }
        $foundCat = trim((string) ($cross['item']['category'] ?? ''));
        if ($foundCat === '' || $foundCat === $lockedCategory) {
            return null;
        }
        $cross['other_category'] = true;
        return $cross;
    }

    public static function noMatchMessage(): string
    {
        return (string) config(
            'no_match_message',
            "ขออภัยค่ะ ข้อมูลส่วนนี้ไม่มีในระบบที่ตรวจสอบได้ในขณะนี้\nแนะนำให้ติดต่อเจ้าหน้าที่คณะวิทยาศาสตร์โดยตรง ได้ที่:\n📞 เบอร์โทร: 043-009-700\n• งานบริการการศึกษา 084-600-1149\n• งานบริการวิชาการและวิจัย ต่อ 44523\n• งานสารบรรณ ต่อ 44260\n• ฝ่ายประชาสัมพันธ์ ต่อ 42958\n📧 อีเมล: sc.inbox@kku.ac.th"
        );
    }

    public static function needCategoryMessage(): string
    {
        return "ขออภัยค่ะ ยังไม่มั่นใจว่าตรงกับคำถามนี้\nกรุณาเลือกหมวดคำถามก่อน เพื่อให้ระบบตอบได้ตรงขึ้นค่ะ";
    }

    /**
     * @param list<array<string, mixed>> $faqs
     * @param list<array<string, mixed>> $items
     * @return array<string, mixed>
     */
    private function needCategoryResult(array $faqs, array $items = []): array
    {
        return [
            'matched' => false,
            'need_category' => true,
            'message' => self::needCategoryMessage(),
            'items' => $items,
            'suggestions' => $this->suggestions($faqs, ''),
        ];
    }

    public function suggestions(array $faqs, string $category = '', int $limit = 6): array
    {
        $pool = $faqs;
        if ($category !== '') {
            $pool = array_values(array_filter($faqs, static fn ($row) => ($row['category'] ?? '') === $category));
        }
        $out = [];
        foreach ($pool as $row) {
            if (mb_strpos((string) $row['question'], 'สวัสดี') !== false) {
                continue;
            }
            $out[] = [
                'id' => $row['id'],
                'category' => $row['category'],
                'question' => $row['question'],
            ];
            if (count($out) >= $limit) {
                break;
            }
        }
        return $out;
    }

    private function scoreAll(array $faqs, string $question): array
    {
        $normQ = $this->normalize($question);
        $scored = [];
        foreach ($faqs as $faq) {
            $result = $this->scoreOne($faq, $question, $normQ);
            if ($result['score'] > 0) {
                $scored[] = $result;
            }
        }
        usort($scored, static fn ($a, $b) => $b['score'] <=> $a['score']);
        return $scored;
    }

    /**
     * @param list<array{faq: array<string, mixed>, score: int, hits: list<string>}> $scored
     * @param list<array<string, mixed>> $pool
     * @return list<array{faq: array<string, mixed>, score: int, hits: list<string>}>
     */
    private function mergeSemanticScores(array $scored, array $pool, string $question): array
    {
        if (!FaqEmbedder::configured() || $pool === []) {
            return $scored;
        }

        $hits = FaqEmbedder::search($question, $pool, 5);
        if ($hits === []) {
            return $scored;
        }

        $scale = (float) config('semantic_bonus_scale', 45);
        $byId = [];
        foreach ($scored as $row) {
            $id = (string) ($row['faq']['id'] ?? '');
            if ($id !== '') {
                $byId[$id] = $row;
            }
        }

        foreach ($hits as $hit) {
            $faq = $hit['faq'];
            $id = (string) ($faq['id'] ?? '');
            if ($id === '') {
                continue;
            }
            $bonus = (int) round(((float) $hit['similarity']) * $scale);
            if ($bonus < 1) {
                continue;
            }
            if (isset($byId[$id])) {
                $byId[$id]['score'] = (int) ($byId[$id]['score'] ?? 0) + $bonus;
                $hitsList = $byId[$id]['hits'] ?? [];
                $hitsList[] = 'semantic:' . number_format((float) $hit['similarity'], 2);
                $byId[$id]['hits'] = array_values(array_unique($hitsList));
            } else {
                $byId[$id] = [
                    'faq' => $faq,
                    'score' => $bonus,
                    'hits' => ['semantic:' . number_format((float) $hit['similarity'], 2)],
                ];
            }
        }

        $merged = array_values($byId);
        usort($merged, static fn ($a, $b) => $b['score'] <=> $a['score']);
        return $merged;
    }

    private function scoreOne(array $faq, string $rawQ, string $normQ): array
    {
        $score = 0;
        $hits = [];
        $keywords = $this->expandKeywords($this->splitKeywords($faq['keyword'] ?? ''));
        $back = $this->expandKeywords($this->splitKeywords($faq['keyword_back'] ?? ''));
        $title = (string) $faq['question'];
        $normTitle = $this->normalize($title);

        foreach ($keywords as $kw) {
            $n = $this->normalize($kw);
            if ($n === '' || mb_strlen($n) < 2) {
                continue;
            }
            if (mb_strpos($normQ, $n) !== false) {
                $weight = 6 + mb_strlen($n);
                $score += $weight;
                $hits[] = $kw;
            } elseif (mb_strlen($n) >= 5 && $this->similar($normQ, $n) >= 78) {
                $score += 8;
                $hits[] = $kw;
            }
        }

        foreach ($back as $kw) {
            $n = $this->normalize($kw);
            if ($n === '' || mb_strlen($n) < 2) {
                continue;
            }
            if (mb_strpos($normQ, $n) !== false) {
                $score += 4 + min(8, mb_strlen($n));
                $hits[] = $kw;
            }
        }

        if ($normTitle !== '' && mb_strpos($normQ, $normTitle) !== false) {
            $score += 40;
            $hits[] = 'ตรงหัวข้อคำถาม';
        } elseif ($normTitle !== '' && mb_strpos($normTitle, $normQ) !== false && mb_strlen($normQ) >= 6) {
            $score += 28;
            $hits[] = 'ใกล้เคียงหัวข้อ';
        } else {
            $sim = $this->similar($normQ, $normTitle);
            if ($sim >= 55) {
                $score += (int) round($sim * 0.18);
            }
        }

        if (count(array_unique($hits)) >= 2) {
            $score = (int) round($score * 1.25);
        }
        if (count(array_unique($hits)) >= 3) {
            $score = (int) round($score * 1.15);
        }

        $score += $this->scoreAnswerMatch($faq, $normQ);

        return [
            'faq' => $faq,
            'score' => $score,
            'hits' => array_values(array_unique($hits)),
        ];
    }

    private function scoreAnswerMatch(array $faq, string $normQ): int
    {
        $answer = html_entity_decode(strip_tags((string) ($faq['answer'] ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $answerNorm = $this->normalize($answer);
        if ($answerNorm === '' || $normQ === '') {
            return 0;
        }
        $score = 0;
        $hits = 0;
        foreach ($this->expandQuestionTerms($normQ) as $term) {
            if (mb_strlen($term) < 4) {
                continue;
            }
            if (mb_strpos($answerNorm, $term) !== false) {
                $score += 14 + min(12, mb_strlen($term));
                $hits++;
            }
        }
        if ($hits >= 2) {
            $score = (int) round($score * 1.2);
        }
        return $score;
    }

    private function answerCoversQuestion(array $faq, string $question): bool
    {
        return $this->scoreAnswerMatch($faq, $this->normalize($question)) >= 14;
    }

    /**
     * @param array{chosen_id?: mixed, confidence?: string, reason?: string}|null $verdict
     * @param array{faq: array<string, mixed>, score?: int} $picked
     */
    private function shouldUseMatcherDespiteReject(?array $verdict, array $picked, string $question): bool
    {
        if ($verdict === null || OpenAiFaqVerifier::isParseFailure($verdict)) {
            return false;
        }

        $chosenId = $verdict['chosen_id'] ?? null;
        if ($chosenId !== null && $chosenId !== '') {
            return false;
        }

        $score = (int) ($picked['score'] ?? 0);
        if ($score < 20) {
            return false;
        }

        if ($score >= 50) {
            return true;
        }

        if ($score >= 35 && $this->answerCoversQuestion($picked['faq'], $question)) {
            return true;
        }

        if ($this->isTopicKeywordQuery($question) && $score >= 25) {
            return true;
        }

        return OpenAiFaqVerifier::explicitReject($verdict)
            && $this->answerCoversQuestion($picked['faq'], $question);
    }

    private function isTopicKeywordQuery(string $question): bool
    {
        $norm = $this->normalize($question);
        if ($norm === '' || mb_strlen($norm) > 14) {
            return false;
        }

        foreach ($this->expandQuestionTerms($norm) as $term) {
            if ($term === '') {
                continue;
            }
            foreach (self::SYNONYMS as $key => $list) {
                $nk = $this->normalize($key);
                if ($nk !== '' && ($term === $nk || mb_strpos($term, $nk) !== false || mb_strpos($nk, $term) !== false)) {
                    return true;
                }
                foreach ($list as $syn) {
                    $ns = $this->normalize($syn);
                    if ($ns !== '' && ($term === $ns || mb_strpos($term, $ns) !== false || mb_strpos($ns, $term) !== false)) {
                        return true;
                    }
                }
            }
        }

        return mb_strlen($norm) <= 8;
    }

    private function expandQuestionTerms(string $normQ): array
    {
        $terms = [$normQ];
        foreach (self::SYNONYMS as $key => $list) {
            $nk = $this->normalize($key);
            $matched = ($nk !== '' && mb_strpos($normQ, $nk) !== false);
            foreach ($list as $syn) {
                $ns = $this->normalize($syn);
                if ($ns !== '' && mb_strpos($normQ, $ns) !== false) {
                    $matched = true;
                    break;
                }
            }
            if (!$matched) {
                continue;
            }
            if ($nk !== '') {
                $terms[] = $nk;
            }
            foreach ($list as $syn) {
                $ns = $this->normalize($syn);
                if ($ns !== '') {
                    $terms[] = $ns;
                }
            }
        }
        return array_values(array_unique(array_filter($terms)));
    }

    private function splitKeywords(string $text): array
    {
        $parts = preg_split('/[,;|\/]+/u', $text) ?: [];
        $out = [];
        foreach ($parts as $p) {
            $p = trim($p, " \t\n\r\0\x0B\"'");
            if ($p !== '') {
                $out[] = $p;
            }
        }
        return $out;
    }

    private function expandKeywords(array $keywords): array
    {
        $out = $keywords;
        foreach ($keywords as $kw) {
            $n = $this->normalize($kw);
            foreach (self::SYNONYMS as $key => $list) {
                $nk = $this->normalize($key);
                $synNorm = array_map([$this, 'normalize'], $list);
                $hit = $n === $nk
                    || in_array($n, $synNorm, true)
                    || ($nk !== '' && mb_strpos($n, $nk) !== false)
                    || ($n !== '' && mb_strpos($nk, $n) !== false && mb_strlen($n) >= 4);
                if ($hit) {
                    $out[] = $key;
                    foreach ($list as $syn) {
                        $out[] = $syn;
                    }
                }
            }
        }
        return array_values(array_unique($out));
    }

    private function normalize(string $text): string
    {
        $text = mb_strtolower($text);
        $text = str_replace(
            ['สวัสดีค่ะ', 'สวัสดีครับ', 'ครับ', 'ค่ะ', 'คะ', 'นะคะ', 'นะครับ', 'ไหมคะ', 'ไหมครับ', 'ไหม', 'มั้ย', 'ได้มะ', 'มะ', 'หรือไม่', 'ได้หรือไม่'],
            '',
            $text
        );
        $text = preg_replace('/[\"\'“”‘’()\[\]{}?？!！:：.,，、]/u', '', $text) ?? $text;
        $text = preg_replace('/\s+/u', '', $text) ?? $text;
        return $text;
    }

    private function similar(string $a, string $b): float
    {
        if ($a === '' || $b === '') {
            return 0;
        }
        similar_text($a, $b, $pct);
        return (float) $pct;
    }

    private function confidence(int $score): string
    {
        if ($score >= 40) {
            return 'high';
        }
        if ($score >= 22) {
            return 'medium';
        }
        return 'low';
    }

    private function publicFaq(array $faq, int $score = 0, array $hits = []): array
    {
        return [
            'id' => $faq['id'] ?? null,
            'category' => $faq['category'] ?? '',
            'question' => $faq['question'] ?? '',
            'answer' => $faq['answer'] ?? '',
            'score' => $score,
            'matched_keywords' => $hits,
            'source' => $faq['source'] ?? 'sheet',
        ];
    }
}
