<?php

declare(strict_types=1);

namespace App\Domains\Content\Services;

/**
 * «فهم چی» — قبل از هر تولید، مشخص می‌کند چه نوع محتوایی (article/product/meta/landing)،
 * با کدام زیرنوع (tutorial/comparison/review/...) و با چه قصدی (informational/commercial/
 * transactional/navigational) قرار است ساخته شود. تشخیص از روی عنوان/کوئری هدف و ساختار
 * داده‌شده انجام می‌شود تا StandardsKB استانداردِ دقیقاً همان قالب را اعمال کند.
 */
class ContentProfiler
{
    /** واژه‌های زیرنوع → کلید زیرنوع */
    private const SUBTYPE_KEYWORDS = [
        'tutorial' => ['آموزش', 'نحوه', 'چطور', 'راهنمای گام', 'آموزشی', 'tutorial', 'how to', 'learn', 'آموزشگاه'],
        'how_to' => ['چگونه', 'چطوری', 'how to', 'steps', 'مراحل', 'گام به گام', 'روش'],
        'comparison' => ['مقایسه', 'بهتر است', 'فرق', 'تفاوت', 'کدام بهتر', 'vs', 'versus', 'مقایسه‌ای', 'alternative'],
        'review' => ['بررسی', 'نقد', 'راهنمای خرید', 'تجربه', 'بهترین برند', 'review', 'معرفی و بررسی'],
        'listicle' => ['۱۰ تا', '۵ تا', 'بهترین', 'برترین', 'لیست', 'بهترین‌های', 'top', 'best', 'ترفندهای'],
        'pillar' => ['راهنمای جامع', 'کامل', 'پایگاه دانش', 'دانشنامه', 'ultimate guide', 'راهنمای کامل'],
        'guide' => ['راهنما', 'guide', 'راهنمای خرید', 'مشاوره', 'دفترچه'],
        'news' => ['خبر', 'اخبار', 'آخرین', 'news', 'گزارش'],
        'faq' => ['سوال', 'سوالات', 'پرسش', 'faq', 'پاسخ', 'سوالات متداول'],
    ];

    private const INTENT_KEYWORDS = [
        'transactional' => ['خرید', 'قیمت', 'فروش', 'سفارش', 'buy', 'price', 'purchase', 'فروشگاه', 'پیشنهاد'],
        'commercial' => ['بهترین', 'مقایسه', 'بررسی', 'کدام', 'top', 'best', 'review', 'comparison', 'راهنمای خرید'],
        'navigational' => ['وبسایت', 'سایت رسمی', 'ورود', 'login', 'dashboard', 'پنل', 'اپلیکیشن'],
    ];

    /** زیرنوع‌های معتبر برای هر نوع محتوا (انتخاب دستی کاربر در UI) */
    public const SUBTYPES = [
        'article' => ['tutorial', 'how_to', 'comparison', 'review', 'listicle', 'pillar', 'guide', 'news', 'faq'],
        'product' => ['short_desc', 'long_desc', 'comparison', 'technical'],
        'landing' => ['sales'],
    ];

    /**
     * @return array<string, string> label فارسی برای هر زیرنوع
     */
    public static function subtypeLabels(): array
    {
        return [
            'tutorial' => 'آموزشی',
            'how_to' => 'چگونگی / راهنما',
            'comparison' => 'مقایسه‌ای',
            'review' => 'بررسی / نقد',
            'listicle' => 'لیستی (بهترین‌ها)',
            'pillar' => 'راهنمای جامع (پیلار)',
            'guide' => 'راهنمای خرید / مشاوره',
            'news' => 'خبری',
            'faq' => 'سؤالات متداول',
            'short_desc' => 'توضیح کوتاه محصول',
            'long_desc' => 'توضیح بلند محصول',
            'technical' => 'مشخصات فنی',
            'sales' => 'فروش / لندینگ',
        ];
    }

    /** @param  array<string, mixed>  $context  title، target_query، content_type و subtype پیشنهادی
     * @return array{content_type: string, subtype: string, intent: string, title: string}
     */
    public function profile(array $context): array
    {
        $title = trim((string) ($context['title'] ?? ''));
        $query = trim((string) ($context['target_query'] ?? ''));
        $hintType = (string) ($context['content_type'] ?? '');
        $hintSubtype = (string) ($context['subtype'] ?? '');

        $text = mb_strtolower($title.' '.$query, 'UTF-8');

        // ۱) نوع محتوا: hint از بالا (اگر مشخص باشد)؛ وگرنه حدس از واژه‌ها
        $contentType = $this->detectContentType($hintType, $text);

        // ۲) زیرنوع: اولویت با انتخاب دستی کاربر (اگر برای همین نوع معتبر باشد)
        $subtype = $this->validSubtype($contentType, $hintSubtype)
            ? $hintSubtype
            : $this->detectSubtype($text, $contentType);

        // ۳) قصد
        $intent = $this->detectIntent($text);

        return [
            'content_type' => $contentType,
            'subtype' => $subtype,
            'intent' => $intent,
            'title' => $title !== '' ? $title : $query,
        ];
    }

    private function validSubtype(string $contentType, string $subtype): bool
    {
        return in_array($subtype, self::SUBTYPES[$contentType] ?? [], true);
    }

    private function detectContentType(string $hint, string $text): string
    {
        if (in_array($hint, ['article', 'product', 'meta', 'landing'], true)) {
            return $hint;
        }

        foreach (['خرید', 'قیمت', 'محصول', 'product', 'فروشگاه', 'خرید آنلاین'] as $kw) {
            if (str_contains($text, $kw)) {
                return 'product';
            }
        }

        return 'article';
    }

    private function detectSubtype(string $text, string $contentType): string
    {
        if ($contentType === 'product') {
            return match (true) {
                str_contains($text, 'مقایسه') || str_contains($text, 'فرق') => 'comparison',
                str_contains($text, 'مشخصات') || str_contains($text, 'تکنیکال') || str_contains($text, 'spec') => 'technical',
                mb_strlen($text, 'UTF-8') < 40 => 'short_desc',
                default => 'long_desc',
            };
        }

        if ($contentType === 'meta') {
            return 'title'; // تشخیص description در لایهٔ بالاتر (kind)
        }

        $best = ['score' => 0, 'subtype' => 'article'];
        foreach (self::SUBTYPE_KEYWORDS as $subtype => $keywords) {
            $score = 0;
            foreach ($keywords as $kw) {
                if (str_contains($text, $kw)) {
                    $score++;
                }
            }
            if ($score > $best['score']) {
                $best = ['score' => $score, 'subtype' => $subtype];
            }
        }

        return $best['subtype'] === 'article' ? 'article' : $best['subtype'];
    }

    private function detectIntent(string $text): string
    {
        foreach (self::INTENT_KEYWORDS as $intent => $keywords) {
            foreach ($keywords as $kw) {
                if (str_contains($text, $kw)) {
                    return $intent;
                }
            }
        }

        return 'informational';
    }
}
