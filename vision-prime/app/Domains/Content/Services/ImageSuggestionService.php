<?php

declare(strict_types=1);

namespace App\Domains\Content\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * ImageSuggestionService — تولید پیشنهاد تصویر + Alt Text SEO شده
 *
 * برای هر H2 در محتوا:
 * ۱. کلیدواژه جستجو از عنوان استخراج می‌کنه
 * ۲. از Unsplash API تصویر رایگان پیدا می‌کنه
 * ۳. Alt text SEO شده تولید می‌کنه
 */
class ImageSuggestionService
{
    private const UNSPLASH_ACCESS_KEY = ''; // رایگان: 50 درخواست در ساعت

    /**
     * تولید پیشنهاد تصویر برای تمام H2های محتوا.
     *
     * @return array<int, array{heading: string, search_query: string, alt_text: string, image_url: string, photographer: string}>
     */
    public function suggestForContent(string $html, string $mainKeyword, string $siteName = ''): array
    {
        $headings = $this->extractH2Headings($html);
        $suggestions = [];

        foreach ($headings as $heading) {
            $searchQuery = $this->generateSearchQuery($heading, $mainKeyword);
            $altText = $this->generateAltText($heading, $mainKeyword, $siteName);
            $imageUrl = $this->searchUnsplash($searchQuery);

            $suggestions[] = [
                'heading' => $heading,
                'search_query' => $searchQuery,
                'alt_text' => $altText,
                'image_url' => $imageUrl['url'] ?? '',
                'photographer' => $imageUrl['photographer'] ?? '',
                'unsplash_link' => $imageUrl['link'] ?? '',
            ];
        }

        return $suggestions;
    }

    /**
     * استخراج عناوین H2 از HTML.
     */
    private function extractH2Headings(string $html): array
    {
        $headings = [];
        preg_match_all('/<h2[^>]*>(.*?)<\/h2>/is', $html, $matches);
        foreach ($matches[1] as $match) {
            $text = trim(strip_tags($match));
            if ($text !== '' && mb_strlen($text, 'UTF-8') > 3) {
                $headings[] = $text;
            }
        }
        return array_unique($headings);
    }

    /**
     * تولید کلیدواژه جستجو از عنوان.
     * عنوان فارسی رو به انگلیسی ساده تبدیل می‌کنه.
     */
    private function generateSearchQuery(string $heading, string $mainKeyword): string
    {
        // Map common Persian SEO terms to English
        $translations = [
            'سئو' => 'SEO',
            'بهینه‌سازی' => 'optimization',
            'سرعت' => 'speed',
            'موبایل' => 'mobile',
            'امنیت' => 'security',
            'لینک' => 'link',
            'محتوا' => 'content',
            'کلمه کلیدی' => 'keyword',
            'ترافیک' => 'traffic',
            'گوگل' => 'Google',
            'وردپرس' => 'WordPress',
            'وب سایت' => 'website',
            'طراحی' => 'design',
            'تجربه کاربری' => 'UX',
            'داده' => 'data',
            'آنالیز' => 'analytics',
            'نتایج' => 'results',
            'عملکرد' => 'performance',
            'ابزار' => 'tools',
            'آموزش' => 'tutorial',
            'مقایسه' => 'comparison',
            'بررسی' => 'review',
            'معرفی' => 'introduction',
            'مزایا' => 'benefits',
            'معایب' => 'disadvantages',
            'نتیجه‌گیری' => 'conclusion',
            'پرسش' => 'question',
            'پاسخ' => 'answer',
            'راهنما' => 'guide',
            'قدم' => 'step',
            'مرحله' => 'stage',
            'strategy' => 'strategy',
        ];

        $lower = mb_strtolower($heading, 'UTF-8');
        $queryParts = [];

        foreach ($translations as $fa => $en) {
            if (str_contains($lower, mb_strtolower($fa, 'UTF-8'))) {
                $queryParts[] = $en;
            }
        }

        // If no translation found, use the main keyword
        if (empty($queryParts)) {
            $queryParts = explode(' ', $mainKeyword);
            $queryParts = array_slice($queryParts, 0, 2);
        }

        return implode(' ', array_slice($queryParts, 0, 3));
    }

    /**
     * تولید Alt Text SEO شده.
     */
    private function generateAltText(string $heading, string $mainKeyword, string $siteName): string
    {
        // Alt text should be: descriptive + include keyword + under 125 chars
        $alt = $heading;
        if (!str_contains(mb_strtolower($alt, 'UTF-8'), mb_strtolower($mainKeyword, 'UTF-8'))) {
            $alt .= ' - ' . $mainKeyword;
        }
        if ($siteName !== '' && !str_contains($alt, $siteName)) {
            $alt .= ' | ' . $siteName;
        }
        // Truncate to 125 chars
        if (mb_strlen($alt, 'UTF-8') > 125) {
            $alt = mb_substr($alt, 0, 122, 'UTF-8') . '...';
        }
        return $alt;
    }

    /**
     * جستجوی تصویر در Unsplash API.
     */
    private function searchUnsplash(string $query): array
    {
        $apiKey = self::UNSPLASH_ACCESS_KEY;
        if ($apiKey === '') {
            // Return placeholder if no API key
            return [
                'url' => "https://source.unsplash.com/800x450/?" . urlencode($query),
                'photographer' => 'Unsplash',
                'link' => 'https://unsplash.com/s/photos/' . urlencode($query),
            ];
        }

        try {
            $response = Http::timeout(10)
                ->get('https://api.unsplash.com/search/photos', [
                    'query' => $query,
                    'per_page' => 1,
                    'orientation' => 'landscape',
                ]);

            if ($response->successful()) {
                $data = $response->json();
                $photo = $data['results'][0] ?? null;
                if ($photo) {
                    return [
                        'url' => $photo['urls']['regular'] ?? '',
                        'photographer' => $photo['user']['name'] ?? 'Unknown',
                        'link' => $photo['links']['html'] ?? '',
                    ];
                }
            }
        } catch (\Throwable $e) {
            Log::warning('ImageSuggestionService: Unsplash API failed', ['error' => $e->getMessage()]);
        }

        // Fallback to source.unsplash.com
        return [
            'url' => "https://source.unsplash.com/800x450/?" . urlencode($query),
            'photographer' => 'Unsplash',
            'link' => 'https://unsplash.com/s/photos/' . urlencode($query),
        ];
    }
}