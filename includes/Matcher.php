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
        'เกียรตินิยม' => ['เกียรตินิยมอันดับ', 'honors'],
        'สหกิจ' => ['สหกิจศึกษา', 'coop', 'ฝึกงาน'],
        'สวัสดิการ' => ['รักษาพยาบาล', 'บัตรทอง', 'สุขภาพ'],
        'ทุน' => ['ทุนการศึกษา', 'ทุนอุดหนุน'],
        'portfolio' => ['พอร์ต', 'แฟ้มสะสมงาน', 'tcas1'],
        'พสวท' => ['พสวท.', 'dpst'],
        'สควค' => ['สควค.', 'สควค'],
        'วมว' => ['วมว.', 'scius', 'วมว-มข', 'วมว.มข'],
    ];

    public function ask(array $faqs, string $question, string $category = ''): array
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
        $best = $scored[0] ?? null;

        if ($best && $best['score'] >= self::MIN_SCORE) {
            $related = [];
            foreach (array_slice($scored, 1, 3) as $item) {
                if ($item['score'] >= self::MIN_SCORE * 0.7) {
                    $related[] = $this->publicFaq($item['faq'], $item['score']);
                }
            }
            return [
                'matched' => true,
                'confidence' => $this->confidence($best['score']),
                'item' => $this->publicFaq($best['faq'], $best['score'], $best['hits']),
                'related' => $related,
                'suggestions' => [],
            ];
        }

        if ($category !== '') {
            $global = $this->scoreAll($faqs, $question);
            $gBest = $global[0] ?? null;
            if ($gBest && $gBest['score'] >= self::MIN_SCORE) {
                return [
                    'matched' => true,
                    'confidence' => $this->confidence($gBest['score']),
                    'other_category' => true,
                    'item' => $this->publicFaq($gBest['faq'], $gBest['score'], $gBest['hits']),
                    'related' => [],
                    'suggestions' => $this->suggestions($faqs, $category, 4),
                ];
            }
        }

        return [
            'matched' => false,
            'message' => 'ยังไม่พบคำตอบที่ตรงกับคำถามนี้ในหมวดที่เลือก ลองใช้คีย์เวิร์ดอื่น หรือเลือกคำถามแนะนำด้านล่าง',
            'items' => array_map(fn ($row) => $this->publicFaq($row['faq'], $row['score']), array_slice($scored, 0, 3)),
            'suggestions' => $this->suggestions($faqs, $category),
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

        return [
            'faq' => $faq,
            'score' => $score,
            'hits' => array_values(array_unique($hits)),
        ];
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
            ['สวัสดีค่ะ', 'สวัสดีครับ', 'ครับ', 'ค่ะ', 'คะ', 'นะคะ', 'นะครับ', 'ไหมคะ', 'ไหมครับ', 'ไหม', 'มั้ย', 'หรือไม่', 'ได้หรือไม่'],
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
