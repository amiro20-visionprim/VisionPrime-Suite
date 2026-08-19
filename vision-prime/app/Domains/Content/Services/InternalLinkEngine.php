<?php

declare(strict_types=1);

namespace App\Domains\Content\Services;

use Illuminate\Support\Facades\DB;

/**
 * موتور لینک‌سازی داخلی — پیشنهاد لینک‌های مرتبط بر اساس شباهت موضوعی.
 *
 * برای هر محتوای جدید:
 * ۱) کلیدواژه‌های هدف و عنوان را استخراج می‌کند
 * ۲) صفحات موجود سایت را بر اساس topic similarity رتبه‌بندی می‌کند
 * ۳) anchor text مناسب پیشنهاد می‌دهد
 * ۴) لینک‌های داخلی را در HTML محتوا قرار می‌دهد
 */
class InternalLinkEngine
{
    /**
     * حداکثر تعداد لینک پیشنهادی برای هر محتوا.
     */
    private const MAX_SUGGESTIONS = 8;

    /**
     * وزن‌های شباهت — ترکیبی از عنوان، کلیدواژه و نوع محتوا.
     */
    private const WEIGHTS = [
        'title_similarity' => 0.4,
        'keyword_overlap' => 0.35,
        'content_type_bonus' => 0.15,
        'recency_bonus' => 0.1,
    ];

    /**
     * پیشنهاد لینک‌های داخلی برای یک URL profile جدید.
     *
     * @param  int     $siteId
     * @param  string  $title        عنوان محتوای جدید
     * @param  string  $keyword      کلیدواژه هدف
     * @param  string  $contentType  نوع محتوا (article, product, ...)
     * @param  string  $subtype      زیرنوع (tutorial, review, ...)
     * @return array<int, array{url: string, title: string, anchor: string, relevance_score: float, source_url: string}>
     */
    public function suggest(int $siteId, string $title, string $keyword, string $contentType, string $subtype): array
    {
        $profiles = DB::table('url_profiles')
            ->where('site_id', $siteId)
            ->where('canonical_url', '!=', '')
            ->pluck('canonical_url', 'id');

        if ($profiles->isEmpty()) {
            return [];
        }

        $titleNormalized = ContentProfiler::normalizeFa($title);
        $keywordNormalized = ContentProfiler::normalizeFa($keyword);
        $keywordWords = array_filter(explode(' ', $keywordNormalized));

        $scored = [];

        foreach ($profiles as $profileId => $url) {
            $existingTitle = (string) DB::table('url_profiles')
                ->where('id', $profileId)
                ->value('metadata');
            $meta = json_decode($existingTitle, true) ?? [];
            $existingTitleText = (string) ($meta['title'] ?? '');
            $existingType = (string) DB::table('url_profiles')->where('id', $profileId)->value('content_type');
            $existingSlug = (string) DB::table('url_profiles')->where('id', $profileId)->value('slug');
            $modifiedAt = (string) DB::table('url_profiles')->where('id', $profileId)->value('updated_at');

            $existingTitleNorm = ContentProfiler::normalizeFa($existingTitleText);

            // ۱) شباهت عنوان
            $titleSim = $this->cosineSimilarity($titleNormalized, $existingTitleNorm);

            // ۲) overlap کلیدواژه
            $existingWords = array_filter(explode(' ', $existingTitleNorm));
            $overlap = count(array_intersect($keywordWords, $existingWords));
            $keywordScore = $keywordWords !== [] ? $overlap / count($keywordWords) : 0;

            // ۳) جایزه نوع محتوا (مقاله به مقاله، محصول به محصول)
            $typeBonus = ($existingType === $contentType) ? 1.0 : 0.3;

            // ۴) جایزه تازگی (صفحات جدیدتر اولویت بیشتر)
            $recency = $this->recencyScore($modifiedAt);

            // امتیاز نهایی
            $score = (
                self::WEIGHTS['title_similarity'] * $titleSim
                + self::WEIGHTS['keyword_overlap'] * $keywordScore
                + self::WEIGHTS['content_type_bonus'] * $typeBonus
                + self::WEIGHTS['recency_bonus'] * $recency
            );

            // حذف خود-matching و امتیاز خیلی پایین
            if ($score > 0.15) {
                $scored[] = [
                    'url' => $url,
                    'title' => $existingTitleText,
                    'anchor' => $this->generateAnchor($existingTitleText, $keywordWords),
                    'relevance_score' => round($score, 4),
                    'source_url' => $url,
                    'slug' => $existingSlug,
                ];
            }
        }

        // مرتب‌سازی بر اساس امتیاز و محدود کردن
        usort($scored, fn(array $a, array $b): int => $b['relevance_score'] <=> $a['relevance_score']);

        return array_slice($scored, 0, self::MAX_SUGGESTIONS);
    }

    /**
     * قرار دادن لینک‌های داخلی در HTML محتوا.
     * برای هر anchor پیشنهادی، اولین occurrence مناسب در محتوا را پیدا کرده و لینک می‌کند.
     *
     * @param  string  $html       HTML محتوا
     * @param  array   $suggestions خروجی suggest()
     * @param  string  $baseUrl     آدرس ریشه سایت (مثلاً https://example.com)
     * @return string  HTML با لینک‌های داخلی اضافه‌شده
     */
    public function injectLinks(string $html, array $suggestions, string $baseUrl = ''): string
    {
        if ($suggestions === [] || trim($html) === '') {
            return $html;
        }

        foreach ($suggestions as $suggestion) {
            $anchor = $suggestion['anchor'];
            if ($anchor === '') {
                continue;
            }

            $url = $suggestion['url'];
            if ($baseUrl !== '' && ! str_starts_with($url, 'http')) {
                $url = rtrim($baseUrl, '/') . '/' . ltrim($url, '/');
            }

            $anchorNormalized = ContentProfiler::normalizeFa($anchor);
            $htmlNormalized = ContentProfiler::normalizeFa($html);

            // پیدا کردن anchor در محتوا (ساده — اولین match)
            if (str_contains($htmlNormalized, $anchorNormalized)) {
                // anchor واقعی در HTML (با حفظ case اصلی)
                if (preg_match('/' . preg_quote($anchor, '/') . '/iu', $html, $matches, PREG_OFFSET_CAPTURE)) {
                    $match = $matches[0][0];
                    $offset = $matches[0][1];
                    $link = '<a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" title="' . htmlspecialchars($suggestion['title'], ENT_QUOTES, 'UTF-8') . '">' . $match . '</a>';
                    $html = substr_replace($html, $link, $offset, mb_strlen($match, 'UTF-8'));
                }
            }
        }

        return $html;
    }

    /**
     * تولید anchor text مناسب از عنوان صفحه مقصد و کلیدواژه‌های هدف.
     */
    private function generateAnchor(string $targetTitle, array $keywordWords): string
    {
        $normalized = ContentProfiler::normalizeFa($targetTitle);
        $words = array_filter(explode(' ', $normalized));

        // اگر کلمه‌ای از کلیدواژه در عنوان هست، همان را anchor قرار بده
        $overlapping = array_intersect($keywordWords, $words);
        if ($overlapping !== []) {
            return implode(' ', array_slice($overlapping, 0, 3));
        }

        // وگرنه ۳ کلمه اول عنوان
        return implode(' ', array_slice($words, 0, 3));
    }

    /**
     * محاسبه شباهت کاسینوی بین دو رشته (بر اساس کلمات مشترک).
     */
    private function cosineSimilarity(string $a, string $b): float
    {
        $wordsA = array_filter(explode(' ', $a));
        $wordsB = array_filter(explode(' ', $b));

        if ($wordsA === [] || $wordsB === []) {
            return 0.0;
        }

        $countA = array_count_values($wordsA);
        $countB = array_count_values($wordsB);

        $dot = 0;
        foreach ($countA as $word => $count) {
            if (isset($countB[$word])) {
                $dot += $count * $countB[$word];
            }
        }

        $magA = sqrt(array_sum(array_map(fn(int $c): int => $c * $c, $countA)));
        $magB = sqrt(array_sum(array_map(fn(int $c): int => $c * $c, $countB)));

        if ($magA == 0 || $magB == 0) {
            return 0.0;
        }

        return $dot / ($magA * $magB);
    }

    /**
     * امتیاز تازگی — صفحات جدیدتر امتیاز بالاتر.
     */
    private function recencyScore(?string $updatedAt): float
    {
        if ($updatedAt === null) {
            return 0.5;
        }

        $diff = now()->diffInDays(now()->parse($updatedAt), false);

        // صفحات زیر ۷ روز: 1.0 | زیر ۳۰ روز: 0.7 | زیر ۹۰ روز: 0.4 | بیشتر: 0.2
        return match (true) {
            $diff <= 7 => 1.0,
            $diff <= 30 => 0.7,
            $diff <= 90 => 0.4,
            default => 0.2,
        };
    }
}
