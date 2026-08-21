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
     * @param  array<string, mixed>  $content  ['title' => string, 'body' => string|null, 'keyword' => string|null, 'headings' => array<int,string>|null, 'meta_title' => string|null, 'meta_description' => string|null]
     * @return array{passed: bool, score: int, failures: array<int, string>, warnings: array<int, string>, standard: array<string, mixed>, rankmath_score: int}
     */
    public function evaluate(array $profile, array $content, ?int $siteId = null, ?StandardsKB $kb = null): array
    {
        $kb ??= app(StandardsKB::class);
        $standard = $kb->standardFor($profile, $siteId);

        $failures = [];
        $warnings = [];
        $passed = 0;
        $total = 0;

        $body = (string) ($content['body'] ?? '');
        $plain = $body !== '' ? trim((string) preg_replace('/<[^>]+>/', ' ', $body)) : '';
        $words = $this->wordCount($plain);
        $title = (string) ($content['title'] ?? '');
        $keyword = mb_strtolower((string) ($content['keyword'] ?? ''), 'UTF-8');
        $headings = (array) ($content['headings'] ?? []);
        $metaTitle = (string) ($content['meta_title'] ?? '');
        $metaDescription = (string) ($content['meta_description'] ?? '');
        $contentType = (string) ($profile['content_type'] ?? 'article');
        $isMeta = $contentType === 'meta';

        // ─── ۱) طول محتوا ───
        $length = $isMeta ? mb_strlen($plain, 'UTF-8') : $words;
        $unit = $isMeta ? 'chars' : 'words';
        $total++;
        if ($length >= (int) $standard['word_min']) {
            $passed++;
        } else {
            $failures[] = "{$unit}:{$length} below min:{$standard['word_min']}";
        }
        if (isset($standard['word_max']) && $standard['word_max'] !== null) {
            $total++;
            if ($length <= (int) $standard['word_max']) {
                $passed++;
            } else {
                $failures[] = "{$unit}:{$length} above max:{$standard['word_max']}";
            }
        }

        // ─── ۲) ساختار heading ───
        $total++;
        if (count($headings) >= (int) $standard['min_headings']) {
            $passed++;
        } else {
            $failures[] = 'headings:'.count($headings).' below min:'.$standard['min_headings'];
        }

        // ─── ۳) سلسله‌مراتب heading (h1 → h2 → h3) ───
        if ($body !== '') {
            $total++;
            $headingLevels = [];
            preg_match_all('/<h([1-6])[^>]*>/i', $body, $levelMatches);
            foreach ($levelMatches[1] as $level) {
                $headingLevels[] = (int) $level;
            }
            $hierarchyValid = true;
            for ($i = 1; $i < count($headingLevels); $i++) {
                if ($headingLevels[$i] > $headingLevels[$i - 1] + 1) {
                    $hierarchyValid = false;
                    break;
                }
            }
            if ($hierarchyValid && $headingLevels !== []) {
                $passed++;
            } else {
                $failures[] = 'heading_hierarchy_violated';
            }
        }

        // ─── ۴) عناصر الزامی ───
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

        // ─── ۵) کلیدواژه — عنوان ───
        $keywordGuidance = is_array($standard['keyword_guidance'] ?? null) ? $standard['keyword_guidance'] : [];
        $titleRequired = (bool) ($keywordGuidance['title_required'] ?? false);
        $introRequired = (bool) ($keywordGuidance['intro_required'] ?? false);
        $densityMax = (float) ($keywordGuidance['density_max'] ?? 3.0);
        $densityMin = (float) ($keywordGuidance['density_min'] ?? 0.5);
        $intro = mb_substr($plain, 0, 500, 'UTF-8');

        if ($keyword !== '') {
            // عنوان حتماً کلیدواژه داشته باشد
            if ($titleRequired) {
                $total++;
                if ($this->contains($title, $keyword)) {
                    $passed++;
                } else {
                    $failures[] = "keyword_not_in_title:{$keyword}";
                }
            }
            // مقدمه کلیدواژه داشته باشد
            if ($introRequired) {
                $total++;
                if ($this->contains($intro, $keyword)) {
                    $passed++;
                } else {
                    $failures[] = "keyword_not_in_intro:{$keyword}";
                }
            }
            // تراکم — بالای حداقل
            if ($densityMin > 0) {
                $total++;
                $density = $words > 0 ? mb_substr_count(mb_strtolower($plain, 'UTF-8'), $keyword) / $words * 100 : 0;
                if ($density >= $densityMin) {
                    $passed++;
                } else {
                    $warnings[] = 'keyword_density:'.round($density, 2).' below recommended_min:'.$densityMin;
                    $passed++; // warning, not failure
                }
            }
            // تراکم — زیر حداکثر
            $total++;
            $density = $words > 0 ? mb_substr_count(mb_strtolower($plain, 'UTF-8'), $keyword) / $words * 100 : 0;
            if ($density <= $densityMax) {
                $passed++;
            } else {
                $failures[] = 'keyword_density:'.round($density, 2).' above max:'.$densityMax;
            }
        }

        // ─── ۶) ناخالصی ───
        $total++;
        if (! $this->hasPlaceholders($body.$title)) {
            $passed++;
        } else {
            $failures[] = 'placeholder_content_detected';
        }

        // ─── ۷) عنوان خالی نباشد ───
        if (in_array($contentType, ['article', 'product', 'landing'], true)) {
            $total++;
            if (trim($title) !== '') {
                $passed++;
            } else {
                $failures[] = 'empty_title';
            }
        }

        // ─── ۸) Meta Title (طول) ───
        if ($metaTitle !== '' && ! $isMeta) {
            $minTitleLen = (int) ($standard['min_title_length'] ?? 30);
            $maxTitleLen = (int) ($standard['max_title_length'] ?? 60);
            $titleLen = mb_strlen($metaTitle, 'UTF-8');
            $total++;
            if ($titleLen >= $minTitleLen && $titleLen <= $maxTitleLen) {
                $passed++;
            } elseif ($titleLen < $minTitleLen) {
                $failures[] = "meta_title_too_short:{$titleLen} < {$minTitleLen}";
            } else {
                $warnings[] = "meta_title_too_long:{$titleLen} > {$maxTitleLen}";
                $passed++; // warning
            }
            // کلیدواژه در meta title
            if ($keyword !== '') {
                $total++;
                if ($this->contains($metaTitle, $keyword)) {
                    $passed++;
                } else {
                    $warnings[] = 'keyword_not_in_meta_title';
                    $passed++; // warning
                }
            }
        }

        // ─── ۹) Meta Description (طول) ───
        if ($metaDescription !== '' && ! $isMeta) {
            $minDescLen = (int) ($standard['min_meta_desc_length'] ?? 120);
            $maxDescLen = (int) ($standard['max_meta_desc_length'] ?? 160);
            $descLen = mb_strlen($metaDescription, 'UTF-8');
            $total++;
            if ($descLen >= $minDescLen && $descLen <= $maxDescLen) {
                $passed++;
            } elseif ($descLen < $minDescLen) {
                $failures[] = "meta_desc_too_short:{$descLen} < {$minDescLen}";
            } else {
                $warnings[] = "meta_desc_too_long:{$descLen} > {$maxDescLen}";
                $passed++; // warning
            }
            // کلیدواژه در meta description
            if ($keyword !== '') {
                $total++;
                if ($this->contains($metaDescription, $keyword)) {
                    $passed++;
                } else {
                    $warnings[] = 'keyword_not_in_meta_description';
                    $passed++; // warning
                }
            }
        }

        // ─── ۱۰) لینک‌های داخلی ───
        $linkRules = $standard['internal_link_rules'] ?? [];
        if ($linkRules !== []) {
            $minLinks = (int) ($linkRules['min_links'] ?? 1);
            $maxLinks = (int) ($linkRules['max_links'] ?? 10);
            $internalLinkCount = preg_match_all('/<a[\s>][^>]*href=["\'][^"\']*["\']/i', $body);
            $total++;
            if ($internalLinkCount >= $minLinks) {
                $passed++;
            } else {
                $failures[] = "internal_links:{$internalLinkCount} below min:{$minLinks}";
            }
            if ($internalLinkCount > $maxLinks) {
                $warnings[] = "internal_links:{$internalLinkCount} above suggested_max:{$maxLinks}";
            }
        }

        $score = $total > 0 ? (int) round($passed / $total * 100) : 0;

        // ─── ۱۱) Readability Score ───
        $readability = null;
        if ($body !== '') {
            $readabilityService = app(ReadabilityService::class);
            $readability = $readabilityService->analyze($body);
            $total++;
            if ($readability['score'] >= 40) {
                $passed++;
            } else {
                $warnings[] = 'readability_low:' . $readability['score'] . '/100';
                $passed++; // warning only
            }
        }

        // ─── ۱۲) لینک‌های خارجی ───
        $externalLinks = [];
        preg_match_all('/<a[\s>][^>]*href=["\']([^"\']*)["\'][^>]*>(.*?)<\/a>/is', $body, $extMatches, PREG_SET_ORDER);
        $externalCount = 0;
        $trustedDomains = ['google.com', 'wikipedia.org', 'github.com', '.gov', '.edu', '.org'];
        foreach ($extMatches as $m) {
            $href = $m[1];
            $anchor = strip_tags($m[2]);
            if (preg_match('/^https?:\/\//i', $href)) {
                $externalCount++;
                $isTrusted = false;
                foreach ($trustedDomains as $domain) {
                    if (str_contains($href, $domain)) { $isTrusted = true; break; }
                }
                $externalLinks[] = ['url' => $href, 'anchor' => $anchor, 'trusted' => $isTrusted];
            }
        }
        $total++;
        if ($externalCount >= 1 && $externalCount <= 5) {
            $passed++;
        } elseif ($externalCount === 0) {
            $warnings[] = 'no_external_links';
            $passed++; // warning only
        } else {
            $warnings[] = "external_links:{$externalCount} above suggested_max:5";
            $passed++;
        }

        // ─── ۱۳) E-E-A-T Signals ───
        $eeatScore = 0;
        $eeatSignals = [];
        $lower = mb_strtolower($body, 'UTF-8');
        // 1) تجربه واقعی
        if (preg_match('/(تجربه|تجریه|ما در|من در|case study|مطالعه موردی)/u', $lower)) {
            $eeatScore += 0.20; $eeatSignals[] = 'experience';
        }
        // 2) منابع معتبر
        if (preg_match('/<a[^>]*href=["\'][^"\']*(\.gov|\.edu|\.org|wikipedia)[^"\']*["\']/i', $body)) {
            $eeatScore += 0.15; $eeatSignals[] = 'authoritative_sources';
        }
        // 3) آمار دقیق
        if (preg_match('/\d+[\s]*(٪|%|درصد|هزار|میلیون|بیش از|کمتر از)/u', $lower)) {
            $eeatScore += 0.15; $eeatSignals[] = 'statistics';
        }
        // 4) اصطلاحات تخصصی
        if (preg_match('/(سئو|SEO|Core Web Vitals|Schema|LCP|FID|CLS|INP|crawl|ranking|SERP|Bounce Rate)/i', $body)) {
            $eeatScore += 0.15; $eeatSignals[] = 'technical_terms';
        }
        // 5) ذکر نویسنده
        if (preg_match('/(نویسنده|author|تاریخ انتشار|published|آخرین به‌روزرسانی)/u', $lower)) {
            $eeatScore += 0.10; $eeatSignals[] = 'author_mention';
        }
        // 6) نکات عملی
        if (preg_match('/(مرحله|قدم|گام|steps|todo|چک‌لیست|checklist|نحوه|how to)/u', $lower)) {
            $eeatScore += 0.15; $eeatSignals[] = 'actionable_steps';
        }
        // 7) مقایسه واقعی
        if (preg_match('/(مقایسه| pros | cons | مزایا| معایب| outweigh| better| worse| vs)/u', $lower)) {
            $eeatScore += 0.10; $eeatSignals[] = 'comparison';
        }
        $total++;
        if ($eeatScore >= 0.60) {
            $passed++;
        } else {
            $warnings[] = 'eeat_low:' . round($eeatScore * 100, 0) . '%';
            $passed++; // warning only
        }

        // ─── ۱۴) AI Overviews Compatibility ───
        $hasDirectAnswer = false;
        if (preg_match('/^(سوال|پرسش|FAQ|question)[:\s]/u', $lower) ||
            str_contains($lower, 'faq') || str_contains($lower, 'سوالات متداول')) {
            $hasDirectAnswer = true;
        }
        $total++;
        if ($hasDirectAnswer) {
            $passed++;
        } else {
            $warnings[] = 'no_direct_answer_section';
            $passed++; // warning only
        }

        // ─── ۱۵) Freshness (ذکر سال/تاریخ) ───
        $hasFreshness = false;
        $currentYear = (int) date('Y');
        $persianYear = (int) date('Y') - 621; // approximate
        if (preg_match('/(' . $currentYear . '|' . ($currentYear - 1) . ')/u', $body) ||
            preg_match('/(سال ۱۴\d\d|۱۴\d\d)/u', $body)) {
            $hasFreshness = true;
        }
        $total++;
        if ($hasFreshness) {
            $passed++;
        } else {
            $warnings[] = 'no_freshness_signal';
            $passed++; // warning only
        }

        // RankMath-style score (0-100)
        $rankmathScore = max(0, min(100, $score));

        return [
            'passed' => $failures === [],
            'score' => $score,
            'failures' => $failures,
            'warnings' => $warnings,
            'standard' => $standard,
            'rankmath_score' => $rankmathScore,
            'readability' => $readability,
            'external_links' => $externalLinks,
            'eeat' => ['score' => (int) round($eeatScore * 100), 'signals' => $eeatSignals],
            'ai_overviews' => $hasDirectAnswer,
            'freshness' => $hasFreshness,
            'audit_log' => [
                'word_count' => $words,
                'heading_count' => count($headings),
                'external_link_count' => $externalCount,
                'eeat_score' => (int) round($eeatScore * 100),
                'readability_score' => $readability['score'] ?? 0,
                'freshness' => $hasFreshness,
                'ai_overviews' => $hasDirectAnswer,
                'timestamp' => now()->toISOString(),
            ],
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
