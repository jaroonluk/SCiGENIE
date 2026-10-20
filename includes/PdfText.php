<?php

declare(strict_types=1);

/**
 * Reads text from PDF files that use FlateDecode streams and ToUnicode maps.
 * Glyphs on the same baseline stay in content-stream order so Thai vowels keep their place.
 */
class PdfText
{
    public static function extract(string $bytes): string
    {
        if ($bytes === '' || !str_starts_with($bytes, '%PDF')) {
            return '';
        }
        try {
            $objects = self::objects($bytes);
            self::expandObjectStreams($objects);
            $cmaps = self::cmaps($objects);
            $fontMaps = self::fontMaps($objects);
            $lines = [];
            foreach ($objects as $object) {
                $dict = $object['dict'];
                if (!preg_match('/\/Type\s*\/Page(?!s)/', $dict)) {
                    continue;
                }
                $fonts = self::fontResources($dict);
                foreach (self::contentIds($dict) as $id) {
                    if (!isset($objects[$id]) || $objects[$id]['raw'] === null) {
                        continue;
                    }
                    $decoded = self::inflate($objects[$id]);
                    if ($decoded === '') {
                        continue;
                    }
                    $piece = self::decodeStream($decoded, $fonts, $fontMaps, $cmaps);
                    if ($piece !== '') {
                        $lines[] = $piece;
                    }
                }
            }
            $text = self::normalizeMarks(implode("\n", $lines));
            $text = preg_replace("/[ \t]+/u", ' ', $text) ?? $text;
            $text = preg_replace("/\n{3,}/u", "\n\n", $text) ?? $text;
            $text = trim($text);
            if (mb_strlen($text) > 40000) {
                $text = mb_substr($text, 0, 40000);
            }
            return $text;
        } catch (Throwable $e) {
            error_log('SCiGENIE pdf: ' . mb_substr($e->getMessage(), 0, 200));
            return '';
        }
    }

    /**
     * @return array<int, array{dict: string, raw: ?string}>
     */
    private static function objects(string $blob): array
    {
        $objects = [];
        $i = 0;
        $n = strlen($blob);
        while ($i < $n) {
            if (!preg_match('/(\d+)\s+(\d+)\s+obj\b/', $blob, $match, 0, $i)) {
                break;
            }
            $num = (int) $match[1];
            $body = strpos($blob, $match[0], $i);
            if ($body === false) {
                break;
            }
            $body += strlen($match[0]);
            $window = substr($blob, $body, 8000);
            $streamAt = false;
            $endObjAt = false;
            if (preg_match('/(?<![A-Za-z])stream\r?\n/', $window, $streamMatch, PREG_OFFSET_CAPTURE)) {
                $streamAt = (int) $streamMatch[0][1];
            }
            if (preg_match('/\bendobj\b/', $window, $endMatch, PREG_OFFSET_CAPTURE)) {
                $endObjAt = (int) $endMatch[0][1];
            }
            if ($streamAt !== false && ($endObjAt === false || $streamAt < $endObjAt)) {
                $dict = substr($blob, $body, $streamAt);
                $dataAt = $body + $streamAt + strlen($streamMatch[0][0]);
                if (preg_match('/\/Length\s+(\d+)\b/', $dict, $lengthMatch)) {
                    $length = (int) $lengthMatch[1];
                    $raw = substr($blob, $dataAt, $length);
                    $i = $dataAt + $length;
                    $end = strpos($blob, 'endobj', $i);
                    $i = $end === false ? $i : $end + 6;
                } else {
                    $endStream = strpos($blob, 'endstream', $dataAt);
                    if ($endStream === false) {
                        break;
                    }
                    $raw = substr($blob, $dataAt, $endStream - $dataAt);
                    if (str_ends_with($raw, "\r\n")) {
                        $raw = substr($raw, 0, -2);
                    } elseif (str_ends_with($raw, "\n")) {
                        $raw = substr($raw, 0, -1);
                    }
                    $end = strpos($blob, 'endobj', $endStream);
                    $i = $end === false ? $endStream + 9 : $end + 6;
                }
                $objects[$num] = ['dict' => $dict, 'raw' => $raw];
            } else {
                $end = strpos($blob, 'endobj', $body);
                if ($end === false) {
                    break;
                }
                $objects[$num] = ['dict' => substr($blob, $body, $end - $body), 'raw' => null];
                $i = $end + 6;
            }
        }
        return $objects;
    }

    /**
     * @param array<int, array{dict: string, raw: ?string}> $objects
     */
    private static function expandObjectStreams(array &$objects): void
    {
        foreach ($objects as $object) {
            if ($object['raw'] === null || !str_contains($object['dict'], '/ObjStm')) {
                continue;
            }
            $blob = self::inflate($object);
            if ($blob === '' || !preg_match('/\/N\s+(\d+)/', $object['dict'], $countMatch) || !preg_match('/\/First\s+(\d+)/', $object['dict'], $firstMatch)) {
                continue;
            }
            $count = (int) $countMatch[1];
            $first = (int) $firstMatch[1];
            $header = preg_split('/\s+/', trim(substr($blob, 0, $first))) ?: [];
            $pairs = [];
            $limit = min(count($header), $count * 2);
            for ($i = 0; $i + 1 < $limit; $i += 2) {
                $pairs[] = [(int) $header[$i], (int) $header[$i + 1]];
            }
            $pairCount = count($pairs);
            foreach ($pairs as $index => $pair) {
                $start = $first + $pair[1];
                $end = $index + 1 < $pairCount ? $first + $pairs[$index + 1][1] : strlen($blob);
                if ($end < $start) {
                    continue;
                }
                $objects[$pair[0]] = ['dict' => substr($blob, $start, $end - $start), 'raw' => null];
            }
        }
    }

    /**
     * @param array<int, array{dict: string, raw: ?string}> $objects
     * @return array<int, array<int, string>>
     */
    private static function cmaps(array $objects): array
    {
        $maps = [];
        foreach ($objects as $id => $object) {
            if ($object['raw'] === null) {
                continue;
            }
            $text = self::inflate($object);
            if ($text === '' || (!str_contains($text, 'beginbfchar') && !str_contains($text, 'beginbfrange'))) {
                continue;
            }
            $maps[$id] = self::parseCmap($text);
        }
        return $maps;
    }

    /**
     * @return array<int, string>
     */
    private static function parseCmap(string $text): array
    {
        $map = [];
        if (preg_match_all('/beginbfchar(.*?)endbfchar/s', $text, $blocks)) {
            foreach ($blocks[1] as $block) {
                if (!preg_match_all('/<([0-9A-Fa-f]+)>\s*<([0-9A-Fa-f]+)>/', $block, $pairs, PREG_SET_ORDER)) {
                    continue;
                }
                foreach ($pairs as $pair) {
                    if (strlen($pair[1]) > 4) {
                        continue;
                    }
                    $map[hexdec($pair[1])] = self::cmapDest($pair[2]);
                }
            }
        }
        if (preg_match_all('/beginbfrange(.*?)endbfrange/s', $text, $blocks)) {
            foreach ($blocks[1] as $block) {
                if (preg_match_all('/<([0-9A-Fa-f]+)>\s*<([0-9A-Fa-f]+)>\s*<([0-9A-Fa-f]+)>/', $block, $ranges, PREG_SET_ORDER)) {
                    foreach ($ranges as $range) {
                        $start = hexdec($range[1]);
                        $end = hexdec($range[2]);
                        $dest = self::cmapDest($range[3]);
                        if ($dest === '' || $end < $start || ($end - $start) > 400) {
                            continue;
                        }
                        $code = mb_ord($dest);
                        for ($i = 0; $start + $i <= $end; $i++) {
                            $map[$start + $i] = mb_chr($code + $i);
                        }
                    }
                }
                if (preg_match_all('/<([0-9A-Fa-f]+)>\s*<([0-9A-Fa-f]+)>\s*\[([^\]]+)\]/', $block, $lists, PREG_SET_ORDER)) {
                    foreach ($lists as $list) {
                        $start = hexdec($list[1]);
                        preg_match_all('/<([0-9A-Fa-f]+)>/', $list[3], $chars);
                        foreach ($chars[1] as $offset => $hex) {
                            $map[$start + $offset] = self::cmapDest($hex);
                        }
                    }
                }
            }
        }
        return $map;
    }

    private static function cmapDest(string $hex): string
    {
        if (strlen($hex) % 2 === 1) {
            return '';
        }
        $bytes = hex2bin($hex);
        if ($bytes === false || $bytes === '') {
            return '';
        }
        if (strlen($bytes) >= 2 && strlen($bytes) % 2 === 0) {
            $text = mb_convert_encoding($bytes, 'UTF-8', 'UTF-16BE');
            return is_string($text) ? $text : '';
        }
        $ord = ord($bytes);
        return ($ord >= 32 && $ord < 127) ? $bytes : '';
    }

    /**
     * @param array<int, array{dict: string, raw: ?string}> $objects
     * @return array<int, int>
     */
    private static function fontMaps(array $objects): array
    {
        $maps = [];
        foreach ($objects as $id => $object) {
            if (preg_match('/\/ToUnicode\s+(\d+)\s+0\s+R/', $object['dict'], $match)) {
                $maps[$id] = (int) $match[1];
            }
        }
        return $maps;
    }

    /**
     * @return array<string, int>
     */
    private static function fontResources(string $dict): array
    {
        $fonts = [];
        if (!preg_match('/\/Font\s*<<(.*?)>>/s', $dict, $match)) {
            return $fonts;
        }
        if (preg_match_all('/\/([A-Za-z0-9_.]+)\s+(\d+)\s+0\s+R/', $match[1], $pairs, PREG_SET_ORDER)) {
            foreach ($pairs as $pair) {
                $fonts[$pair[1]] = (int) $pair[2];
            }
        }
        return $fonts;
    }

    /**
     * @return list<int>
     */
    private static function contentIds(string $dict): array
    {
        if (preg_match('/\/Contents\s*\[([^\]]+)\]/', $dict, $match)) {
            preg_match_all('/(\d+)\s+0\s+R/', $match[1], $ids);
            return array_map('intval', $ids[1]);
        }
        if (preg_match('/\/Contents\s+(\d+)\s+0\s+R/', $dict, $match)) {
            return [(int) $match[1]];
        }
        return [];
    }

    /**
     * @param array{dict: string, raw: ?string} $object
     */
    private static function inflate(array $object): string
    {
        $raw = $object['raw'];
        if ($raw === null || $raw === '') {
            return '';
        }
        if (!str_contains($object['dict'], 'FlateDecode')) {
            return $raw;
        }
        $text = @gzuncompress($raw);
        if (is_string($text) && $text !== '') {
            return $text;
        }
        $text = @gzuncompress(trim($raw, "\r\n"));
        return is_string($text) ? $text : '';
    }

    /**
     * @param array<string, int> $fonts
     * @param array<int, int> $fontMaps
     * @param array<int, array<int, string>> $cmaps
     */
    private static function decodeStream(string $decoded, array $fonts, array $fontMaps, array $cmaps): string
    {
        $pattern = '/(?P<tm>-?\d+(?:\.\d+)?\s+-?\d+(?:\.\d+)?\s+-?\d+(?:\.\d+)?\s+-?\d+(?:\.\d+)?\s+-?\d+(?:\.\d+)?\s+-?\d+(?:\.\d+)?)\s+Tm'
            . '|(?P<td>-?\d+(?:\.\d+)?\s+-?\d+(?:\.\d+)?)\s+Td'
            . '|\/(?P<font>[A-Za-z0-9_.]+)\s+-?\d+(?:\.\d+)?\s+Tf'
            . '|<(?P<hex>[0-9A-Fa-f\s]+)>\s*Tj'
            . '|\((?P<lit>(?:\\\\.|[^\\\\)])*)\)\s*Tj'
            . '|\[(?P<tj>[^\]]*)\]\s*TJ/';
        if (!preg_match_all($pattern, $decoded, $tokens, PREG_SET_ORDER)) {
            return '';
        }
        $cmap = [];
        $y = 0.0;
        $currentY = null;
        $buffer = '';
        $lines = [];
        $flush = static function () use (&$buffer, &$lines): void {
            if ($buffer !== '') {
                $lines[] = $buffer;
                $buffer = '';
            }
        };
        foreach ($tokens as $token) {
            if (($token['font'] ?? '') !== '') {
                $fontId = $fonts[$token['font']] ?? null;
                $cmapId = $fontId !== null ? ($fontMaps[$fontId] ?? null) : null;
                $cmap = $cmapId !== null ? ($cmaps[$cmapId] ?? []) : [];
                continue;
            }
            if (($token['tm'] ?? '') !== '') {
                $parts = preg_split('/\s+/', trim($token['tm'])) ?: [];
                $y = (float) ($parts[5] ?? 0);
                continue;
            }
            if (($token['td'] ?? '') !== '') {
                $parts = preg_split('/\s+/', trim($token['td'])) ?: [];
                $y += (float) ($parts[1] ?? 0);
                continue;
            }
            $chunk = '';
            if (($token['hex'] ?? '') !== '') {
                $chunk = self::decodeHex($token['hex'], $cmap);
            } elseif (array_key_exists('lit', $token) && $token['lit'] !== '') {
                $chunk = stripcslashes($token['lit']);
            } elseif (($token['tj'] ?? '') !== '') {
                if (preg_match_all('/<([0-9A-Fa-f\s]+)>|\((?:\\\\.|[^\\\\)])*\)/', $token['tj'], $parts)) {
                    foreach ($parts[0] as $part) {
                        if (str_starts_with($part, '<')) {
                            $chunk .= self::decodeHex(trim($part, '<>'), $cmap);
                        } else {
                            $chunk .= stripcslashes(substr($part, 1, -1));
                        }
                    }
                }
            }
            if ($chunk === '') {
                continue;
            }
            if ($currentY === null || abs($y - $currentY) > 1.8) {
                $flush();
                $currentY = $y;
            }
            $buffer .= $chunk;
        }
        $flush();
        return trim(implode("\n", $lines));
    }

    /**
     * @param array<int, string> $cmap
     */
    private static function decodeHex(string $hex, array $cmap): string
    {
        $hex = preg_replace('/\s+/', '', $hex) ?? $hex;
        if ($hex === '' || strlen($hex) % 2 === 1) {
            return '';
        }
        $raw = hex2bin($hex);
        if ($raw === false) {
            return '';
        }
        $hits = 0;
        $length = strlen($raw);
        if ($length % 2 === 0) {
            for ($i = 0; $i < $length; $i += 2) {
                $cid = (ord($raw[$i]) << 8) | ord($raw[$i + 1]);
                if (isset($cmap[$cid])) {
                    $hits++;
                }
            }
        }
        $out = '';
        if ($hits > 0) {
            for ($i = 0; $i < $length; $i += 2) {
                $cid = (ord($raw[$i]) << 8) | ord($raw[$i + 1]);
                $out .= $cmap[$cid] ?? '';
            }
            return $out;
        }
        for ($i = 0; $i < $length; $i++) {
            $byte = ord($raw[$i]);
            if (isset($cmap[$byte])) {
                $out .= $cmap[$byte];
            } elseif ($byte >= 32 && $byte < 127) {
                $out .= chr($byte);
            }
        }
        return $out;
    }

    private static function normalizeMarks(string $text): string
    {
        static $map = [
            "\u{F700}" => "\u{0E31}",
            "\u{F701}" => "\u{0E34}",
            "\u{F702}" => "\u{0E35}",
            "\u{F703}" => "\u{0E36}",
            "\u{F704}" => "\u{0E37}",
            "\u{F705}" => "\u{0E48}",
            "\u{F706}" => "\u{0E49}",
            "\u{F707}" => "\u{0E4A}",
            "\u{F708}" => "\u{0E4B}",
            "\u{F709}" => "\u{0E4C}",
            "\u{F70A}" => "\u{0E48}",
            "\u{F70B}" => "\u{0E49}",
            "\u{F70C}" => "\u{0E4A}",
            "\u{F70D}" => "\u{0E4B}",
            "\u{F70E}" => "\u{0E4C}",
            "\u{F70F}" => "\u{0E47}",
            "\u{F710}" => "\u{0E31}",
            "\u{F711}" => "\u{0E4D}",
            "\u{F712}" => "\u{0E47}",
        ];
        return strtr($text, $map);
    }
}
