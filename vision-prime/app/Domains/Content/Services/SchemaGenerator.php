<?php

declare(strict_types=1);

namespace App\Domains\Content\Services;

/**
 * تولیدکنندهٔ اسکیمای Schema.org — تمام انواع محتوا.
 *
 * پشتیبانی:
 * - Article / NewsArticle / BlogPosting
 * - Product (با price/availability واقعی از ووکامرس)
 * - FAQPage (استخراج از محتوا)
 * - HowTo (مراحل)
 * - Review
 * - BreadcrumbList
 * - Organization
 * - ItemList (listicle)
 * - WebPage
 */
class SchemaGenerator
{
    /**
     * تولید اسکیمای کامل بر اساس نوع محتوا و محتوای HTML.
     *
     * @param  array{content_type: string, subtype: string, intent: string}  $profile
     * @param  string  $html        محتوای HTML
     * @param  string  $title       عنوان
     * @param  string  $url         آدرس صفحه
     * @param  string  $siteName    نام سایت
     * @param  string  $description توضیح کوتاه
     * @param  array   $standard    خروجی StandardsKB
     * @param  array   $wooInfo     اطلاعات ووکامرس (اختیاری)
     * @return array<int, array<string, mixed>>
     */
    public function generate(
        array $profile,
        string $html,
        string $title,
        string $url,
        string $siteName,
        string $description,
        array $standard = [],
        array $wooInfo = [],
    ): array {
        $schemas = [];
        $contentType = $profile['content_type'] ?? 'article';
        $subtype = $profile['subtype'] ?? 'article';
        $schemaType = $standard['schema_type'] ?? 'Article';

        // ۱) اسکیمای اصلی (Article/Product/...)
        $schemas[] = match (true) {
            $contentType === 'product' => $this->productSchema($title, $html, $url, $siteName, $description, $wooInfo),
            $schemaType === 'HowTo' => $this->howToSchema($title, $html, $url, $siteName, $description),
            $schemaType === 'Review' => $this->reviewSchema($title, $html, $url, $siteName, $description),
            $schemaType === 'ItemList' => $this->itemListSchema($title, $html, $url, $siteName, $description),
            $schemaType === 'NewsArticle' => $this->articleSchema($title, $html, $url, $siteName, $description, 'NewsArticle'),
            $schemaType === 'BlogPosting' => $this->articleSchema($title, $html, $url, $siteName, $description, 'BlogPosting'),
            default => $this->articleSchema($title, $html, $url, $siteName, $description, 'Article'),
        };

        // ۲) FAQPage — اگر FAQ در محتوا باشد
        if (in_array('faq', $standard['required_elements'] ?? [], true)) {
            $faq = $this->faqSchema($html);
            if ($faq !== null) {
                $schemas[] = $faq;
            }
        }

        // ۳) BreadcrumbList — همیشه
        $breadcrumb = $this->breadcrumbSchema($url, $title);
        if ($breadcrumb !== null) {
            $schemas[] = $breadcrumb;
        }

        return array_filter($schemas, fn($s): bool => $s !== null);
    }

    /**
     * Schema.org Article / NewsArticle / BlogPosting
     */
    private function articleSchema(string $title, string $html, string $url, string $siteName, string $description, string $type = 'Article'): array
    {
        $date = now()->toDateString();
        $desc = mb_substr(strip_tags($description !== '' ? $description : $html), 0, 300, 'UTF-8');

        return [
            '@context' => 'https://schema.org',
            '@type' => $type,
            'headline' => mb_substr($title, 0, 110, 'UTF-8'),
            'description' => $desc,
            'image' => [
                'url' => null,
                'alt' => $title,
            ],
            'author' => ['@type' => 'Organization', 'name' => $siteName],
            'publisher' => [
                '@type' => 'Organization',
                'name' => $siteName,
                'logo' => ['@type' => 'ImageObject', 'url' => null],
            ],
            'datePublished' => $date,
            'dateModified' => $date,
            'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => $url],
            'wordCount' => $this->wordCount(strip_tags($html)),
        ];
    }

    /**
     * Schema.org Product — با price/availability از ووکامرس
     */
    private function productSchema(string $title, string $html, string $url, string $siteName, string $description, array $wooInfo = []): array
    {
        $desc = mb_substr(strip_tags($description !== '' ? $description : $html), 0, 500, 'UTF-8');

        $product = [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $title,
            'description' => $desc,
            'image' => ['url' => null, 'alt' => $title],
            'brand' => ['@type' => 'Brand', 'name' => $siteName],
            'url' => $url,
            'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => $url],
        ];

        // اضافه کردن قیمت و موجودی از ووکامرس
        if (! empty($wooInfo['price']) && $wooInfo['price'] !== null) {
            $product['offers'] = [
                '@type' => 'Offer',
                'price' => $wooInfo['price'],
                'priceCurrency' => $wooInfo['currency'] ?? 'IRR',
                'availability' => ! empty($wooInfo['in_stock'])
                    ? 'https://schema.org/InStock'
                    : 'https://schema.org/OutOfStock',
                'url' => $url,
            ];

            if (! empty($wooInfo['regular_price']) && ! empty($wooInfo['sale_price'])) {
                $product['offers']['priceValidUntil'] = now()->addDays(30)->toDateString();
            }
        }

        return $product;
    }

    /**
     * Schema.org FAQPage — استخراج پرسش/پاسخ از HTML
     */
    private function faqSchema(string $html): ?array
    {
        $entities = [];

        // الگوی ۱: <strong>پرسش:</strong> ... <strong>پاسخ:</strong> ...
        if (preg_match_all('/<strong>پرسش:<\\/strong>\\s*(.*?)\\s*<strong>پاسخ:<\\/strong>\\s*(.*?)(?=<strong>پرسش:|<\\/p>|<h[2-6])/isu', $html, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $q = trim(strip_tags((string) $match[1]));
                $a = trim(strip_tags((string) $match[2]));
                if ($q !== '' && $a !== '') {
                    $entities[] = [
                        '@type' => 'Question',
                        'name' => mb_substr($q, 0, 200, 'UTF-8'),
                        'acceptedAnswer' => [
                            '@type' => 'Answer',
                            'text' => mb_substr($a, 0, 500, 'UTF-8'),
                        ],
                    ];
                }
            }
        }

        // الگوی ۲: <h2>...سوالات متداول</h2> followed by Q/A pairs
        if ($entities === []) {
            if (preg_match_all('/<h[2-6][^>]*>\\s*(?:سؤال|سوال|پرسش)[^<]*<\\/h[2-6]>\\s*<p[^>]*>\\s*<strong[^>]*>(.*?)<\\/strong>\\s*(.*?)\\s*<\\/p>/isu', $html, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $q = trim(strip_tags((string) $match[1]));
                    $a = trim(strip_tags((string) $match[2]));
                    if ($q !== '' && $a !== '') {
                        $entities[] = [
                            '@type' => 'Question',
                            'name' => mb_substr($q, 0, 200, 'UTF-8'),
                            'acceptedAnswer' => [
                                '@type' => 'Answer',
                                'text' => mb_substr($a, 0, 500, 'UTF-8'),
                            ],
                        ];
                    }
                }
            }
        }

        if ($entities === []) {
            return null;
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => array_slice($entities, 0, 10),
        ];
    }

    /**
     * Schema.org HowTo — برای tutorial/how_to
     */
    private function howToSchema(string $title, string $html, string $url, string $siteName, string $description): array
    {
        $steps = [];
        if (preg_match_all('/<li[^>]*>(.*?)<\\/li>/isu', $html, $matches)) {
            foreach ($matches[1] as $idx => $match) {
                $text = trim(strip_tags((string) $match));
                if ($text !== '') {
                    $steps[] = [
                        '@type' => 'HowToStep',
                        'name' => 'مرحله ' . ($idx + 1),
                        'text' => mb_substr($text, 0, 300, 'UTF-8'),
                    ];
                }
            }
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'HowTo',
            'name' => $title,
            'description' => mb_substr(strip_tags($description !== '' ? $description : $html), 0, 300, 'UTF-8'),
            'totalTime' => 'PT30M',
            'step' => array_slice($steps, 0, 15),
            'author' => ['@type' => 'Organization', 'name' => $siteName],
        ];
    }

    /**
     * Schema.org Review — برای review subtype
     */
    private function reviewSchema(string $title, string $html, string $url, string $siteName, string $description): array
    {
        $rating = 4;
        if (preg_match('/(\d(?:\.\d)?)\\s*از\\s*5/u', $html, $m)) {
            $rating = min(5, max(1, (float) $m[1]));
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'Review',
            'name' => $title,
            'reviewBody' => mb_substr(strip_tags($description !== '' ? $description : $html), 0, 500, 'UTF-8'),
            'author' => ['@type' => 'Organization', 'name' => $siteName],
            'reviewRating' => [
                '@type' => 'Rating',
                'ratingValue' => $rating,
                'bestRating' => 5,
            ],
            'itemReviewed' => [
                '@type' => 'Thing',
                'name' => $title,
            ],
        ];
    }

    /**
     * Schema.org ItemList — برای listicle
     */
    private function itemListSchema(string $title, string $html, string $url, string $siteName, string $description): array
    {
        $items = [];
        if (preg_match_all('/<li[^>]*>(.*?)<\\/li>/isu', $html, $matches)) {
            foreach ($matches[1] as $idx => $match) {
                $text = trim(strip_tags((string) $match));
                if ($text !== '') {
                    $items[] = [
                        '@type' => 'ListItem',
                        'position' => $idx + 1,
                        'name' => mb_substr($text, 0, 150, 'UTF-8'),
                    ];
                }
            }
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'ItemList',
            'name' => $title,
            'numberOfItems' => count($items),
            'itemListElement' => array_slice($items, 0, 20),
        ];
    }

    /**
     * Schema.org BreadcrumbList — همیشه اضافه شود
     */
    private function breadcrumbSchema(string $url, string $title): ?array
    {
        $parts = array_filter(explode('/', parse_url($url, PHP_URL_PATH) ?? ''));
        if ($parts === []) {
            return null;
        }

        $items = [];
        $position = 1;
        $path = '';

        foreach ($parts as $part) {
            $path .= '/' . rawurlencode($part);
            $items[] = [
                '@type' => 'ListItem',
                'position' => $position++,
                'name' => str_replace('-', ' ', $part),
                'item' => $url,
            ];
        }

        // آخرین آیتم = صفحه جاری
        if ($items !== []) {
            $items[count($items) - 1]['name'] = $title;
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => $items,
        ];
    }

    /**
     * تبدیل اسکیماها به JSON-LD string
     */
    public function toJsonLd(array $schemas): string
    {
        $scripts = [];
        foreach ($schemas as $schema) {
            $scripts[] = '<script type="application/ld+json">' . json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . '</script>';
        }

        return implode("\n", $scripts);
    }

    private function wordCount(string $text): int
    {
        $words = preg_split('/[\\s\\p{P}]+/u', trim($text)) ?: [];

        return count(array_filter($words, fn(string $w): bool => trim($w) !== ''));
    }
}
