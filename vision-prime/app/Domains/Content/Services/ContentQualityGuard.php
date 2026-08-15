<?php

declare(strict_types=1);

namespace App\Domains\Content\Services;

/**
 * لایهٔ ۲ — گیت کیفیت محتوا.
 *
 * قبل از هر انتشار خودکار، خروجی (متن تولیدشده) را با استاندارد مؤثر StandardsKB
 * برای همان (نوع × زیرنوع × قصد) مقایسه می‌کند. هر گیت شکست‌خورده = رد (fail-closed)؛
 * یعنی محتوا هرگز بدون پاس شدن همهٔ گیت‌ها خودکار منتشر نمی‌شود.
 *
 * گیت‌ها: طول، ساختار (heading)، عناصر الزامی، پوشش کلیدواژه، ناخالصی (placeholder/لینک مرده).
 */
class ContentQualityGuard
{
    /** الگوهای ناخالصی که هرگز نباید در خروجی خودکار باشند */
    private const PLACEHOLDER_PATTERNS = [
        '/\blorem ipsum\b/i',
        '/\{\{[^}]+\}\}/',
        '/\[(placeholder|insert|put|add)[^\]]*\]/i',
        '/(کپی|paste|جایگزین)[\s\p{L}]{0,10}(متن|اینجا)/u',
        '/\bTODO\b/',
        '/\bxxx\b/i',
    ];

    /**
     * @param  array<string, mixed>  $profile  خروجی ContentProfiler
     * @param  array<string, mixed>  $content  ['title' => string, 'body' => string|null, 'keyword' => string|null, 'headings' => array<int,string>|null]
     * @return array{passed: bool, score: int, failures: array<int, string>, standard: array<string, mixed>}
     */
    public function evaluate(array $profile, array $content, ?int $siteId = null, ?StandardsKB $kb = null): array
    {
        $kb ??= app(StandardsKB::class);
        $standard = $kb->standardFor($profile, $siteId);

        $failures = [];
        $passed = 0;
        $total = 0;

        $body = (string) ($content['body'] ?? '');
        $plain = $body !== '' ? trim((string) preg_replace('/<[^>]+>/', ' ', $body)) : '';
        $words = $this->wordCount($plain);
        $title = (string) ($content['title'] ?? '');
        $keyword = mb_strtolower((string) ($content['keyword'] ?? ''), 'UTF-8');
        $headings = (array) ($content['headings'] ?? []);

        // ۱) طول — برای meta بر حسب کاراکتر (استاندارد صنعت)، بقیه بر حسب کلمه
        $isMeta = ($profile['content_type'] ?? '') === 'meta';
        $length = $isMeta ? mb_strlen($plain, 'UTF-8') : $words;
        $unit = $isMeta ? 'chars' : 'words';
        $total++;
        if ($length >= (int) $standard['word_min']) {
            $passed++;
        } else {
            $failures[] = "{$unit}:{$length} below min:{$standard['word_min']}";
        }
        if ($standard['word_max'] !== null) {
            $total++;
            if ($length <= (int) $standard['word_max']) {
                $passed++;
            } else {
                $failures[] = "{$unit}:{$length} above max:{$standard['word_max']}";
            }
        }

        // ۲) ساختار — حداقل heading
        $total++;
        if (count($headings) >= (int) $standard['min_headings']) {
            $passed++;
        } else {
            $failures[] = 'headings:'.count($headings).' below min:'.$standard['min_headings'];
        }

        // ۳) عناصر الزامی
        $elements = (array) ($standard['required_elements'] ?? []);
        $hasElement = $this->hasElements($body, $elements);
        foreach ($elements as $element) {
            $total++;
            if (in_array($element, $hasElement, true)) {
                $passed++;
            } else {
                $failures[] = "missing_element:{$element}";
            }
        }

        // ۴) پوشش کلیدواژه در عنوان/مقدمه (اگر راهنما الزامی کند)
        $keywordGuidance = is_array($standard['keyword_guidance'] ?? null) ? $standard['keyword_guidance'] : [];
        $titleRequired = (bool) ($keywordGuidance['title_required'] ?? false);
        $introRequired = (bool) ($keywordGuidance['intro_required'] ?? false);
        $densityMax = (float) ($keywordGuidance['density_max'] ?? 3);
        $intro = mb_substr($plain, 0, 300, 'UTF-8');

        if ($keyword !== '') {
            if ($titleRequired) {
                $total++;
                if ($this->contains($title, $keyword)) {
                    $passed++;
                } else {
                    $failures[] = "keyword_not_in_title:{$keyword}";
                }
            }
            if ($introRequired) {
                $total++;
                if ($this->contains($intro, $keyword)) {
                    $passed++;
                } else {
                    $failures[] = "keyword_not_in_intro:{$keyword}";
                }
            }
            // تراکم
            $total++;
            $density = $words > 0 ? mb_substr_count(mb_strtolower($plain, 'UTF-8'), $keyword) / $words * 100 : 0;
            if ($density <= $densityMax) {
                $passed++;
            } else {
                $failures[] = 'keyword_density:'.round($density, 2).' above max:'.$densityMax;
            }
        }

        // ۵) ناخالصی
        $total++;
        if (! $this->hasPlaceholders($body.$title)) {
            $passed++;
        } else {
            $failures[] = 'placeholder_content_detected';
        }

        // ۶) عنوان نباید خالی باشد (برای article/product/landing)
        if (in_array($profile['content_type'] ?? '', ['article', 'product', 'landing'], true)) {
            $total++;
            if (trim($title) !== '') {
                $passed++;
            } else {
                $failures[] = 'empty_title';
            }
        }

        $score = $total > 0 ? (int) round($passed / $total * 100) : 0;

        return [
            'passed' => $failures === [],
            'score' => $score,
            'failures' => $failures,
            'standard' => $standard,
        ];
    }

    /**
     * @param  array<int, string>  $elements
     * @return array<int, string>
     */
    private function hasElements(string $body, array $elements): array
    {
        $found = [];
        $lower = mb_strtolower($body, 'UTF-8');
        foreach ($elements as $element) {
            $found[] = match ($element) {
                'h2_structure' => preg_match('/<h2[\s>]/i', $body) === 1 ? 'h2_structure' : '',
                'faq' => str_contains($lower, 'سوال') || str_contains($lower, 'پرسش') || str_contains($lower, 'faq') ? 'faq' : '',
                'table' => preg_match('/<table[\s>]/i', $body) === 1 ? 'table' : '',
                'cta' => (str_contains($lower, 'خرید') || str_contains($lower, 'ثبت سفارش') || str_contains($lower, 'تماس بگیرید') || preg_match('/<a[\s>][^>]*href=/i', $body) === 1) ? 'cta' : '',
                'pros_cons' => (str_contains($lower, 'مزایا') && str_contains($lower, 'معایب')) ? 'pros_cons' : '',
                'rating' => preg_match('/<[^>]+aria-label="[^"]*(star|rating)[^"]*"/i', $body) === 1 || preg_match('/\b\d\/\d\b|\b\d(\.\d)? از \d\b/u', $body) === 1 ? 'rating' : '',
                'list' => preg_match('/<(ul|ol)[\s>]/i', $body) === 1 ? 'list' : '',
                'steps' => preg_match('/<(ol)[\s>]/i', $body) === 1 || str_contains($lower, 'مرحله') ? 'steps' : '',
                'table_of_contents' => (str_contains($lower, 'فهرست') && str_contains($lower, 'مطالب')) || str_contains($lower, 'فهرست مطالب') ? 'table_of_contents' : '',
                'internal_links' => preg_match('/<a[\s>][^>]*href=/i', $body) === 1 ? 'internal_links' : '',
                'social_proof' => (str_contains($lower, 'رضایت') || str_contains($lower, 'مشتری') || str_contains($lower, 'تعداد فروش')) ? 'social_proof' : '',
                'specs' => (str_contains($lower, 'مشخصات') || str_contains($lower, 'ویژگی')) ? 'specs' : '',
                'keyword' => $body !== '' ? 'keyword' : '',
                default => '',
            };
        }

        return array_values(array_filter($found, fn (string $f): bool => $f !== ''));
    }

    /** شمارش کلمات فارسی/انگلیسی (str_word_count فارسی را نمی‌شمارد). */
    private function wordCount(string $text): int
    {
        if (trim($text) === '') {
            return 0;
        }
        $words = preg_split('/[\s'.chr(0xE2).chr(0x80).chr(0x8C).']+/u', trim($text)) ?: [];

        return count(array_filter($words, fn (string $w): bool => trim($w) !== ''));
    }

    private function contains(string $haystack, string $needle): bool
    {
        return $needle !== '' && mb_strpos(mb_strtolower($haystack, 'UTF-8'), $needle) !== false;
    }

    private function hasPlaceholders(string $text): bool
    {
        foreach (self::PLACEHOLDER_PATTERNS as $pattern) {
            if (preg_match($pattern, $text) === 1) {
                return true;
            }
        }

        return false;
    }
}
