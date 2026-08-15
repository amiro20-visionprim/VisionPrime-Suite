<?php

declare(strict_types=1);

namespace App\Domains\Content\Services;

/**
 * پاک‌سازی HTML پیش‌نویس مقاله برای نمایش امن در UI.
 *
 * AI می‌تواند تگ/ویژگی ناخواسته تولید کند (script، onclick، iframe...). این سرویس با
 * whitelist سخت تگ‌ها و ویژگی‌ها، خروجی را برای v-html ایمن می‌کند و هم‌زمان
 * «پیش‌نمایش ساختار» (heading ها، تعداد کلمه، عناصر الزامی حاضر) را استخراج می‌کند
 * تا بازبین پیش از تأیید، کیفیت ساختاری را ببیند.
 */
class ArticleHtmlSanitizer
{
    private const ALLOWED_TAGS = [
        'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
        'p', 'br', 'strong', 'em', 'b', 'i', 'u', 'mark',
        'ul', 'ol', 'li',
        'table', 'thead', 'tbody', 'tr', 'th', 'td', 'caption',
        'blockquote', 'code', 'pre', 'a',
    ];

    /** ویژگی‌های مجاز برای تگ‌های خاص — همهٔ ویژگی‌های دیگر حذف می‌شوند. */
    private const ALLOWED_ATTRS = [
        'a' => ['href'],
        'td' => ['colspan', 'rowspan'],
        'th' => ['colspan', 'rowspan'],
    ];

    /** تگ‌هایی که باید با همهٔ محتوا حذف شوند (هرگز نباید رندر شوند). */
    private const DROP_TAGS = ['script', 'style', 'iframe', 'object', 'embed', 'form', 'input', 'button', 'svg', 'math', 'template'];

    /**
     * @return array{safe_html: string, structure: array{headings: array<int, array{level: int, text: string}>, word_count: int, elements: array<string, bool>}}
     */
    public function sanitize(string $html, array $standard = []): array
    {
        // ۱) حذف کامل تگ‌های خطرناک همراه با محتوا
        $html = (string) preg_replace('#<(script|style|iframe|object|embed|form|input|button|svg|math|template)\\b[^>]*>.*?</\\1>#isu', '', $html);
        $html = (string) preg_replace('#</?(script|style|iframe|object|embed|form|input|button|svg|math|template)\\b[^>]*>#iu', '', $html);

        // ۲) حذف event handler ها و javascript: در ویژگی‌ها
        $html = (string) preg_replace('/\\son\\w+\\s*=\\s*("[^"]*"|\'[^\']*\'|[^\\s>]+)/iu', '', $html);
        $html = (string) preg_replace('~(href|src)\\s*=\\s*(["\'])\\s*javascript:[^>]*?\\2~iu', '$1=$2#$2', $html);

        // ۳) عبور از whitelist تگ‌ها
        $html = $this->stripUnknownTags($html);

        // ۴) استخراج ساختار از HTML پاک‌شده
        $structure = $this->structure($html, $standard);

        return ['safe_html' => $html, 'structure' => $structure];
    }

    /**
     * whitelist تگ‌ها و ویژگی‌ها — تگ/ویژگی ناشناخته حذف می‌شود (محتوا می‌ماند).
     */
    private function stripUnknownTags(string $html): string
    {
        return (string) preg_replace_callback(
            '#<(/?)  ([a-zA-Z][a-zA-Z0-9-]*)([^>]*)>#x',
            function (array $m): string {
                [, $closing, $tag, $attrs] = $m;
                $tag = mb_strtolower($tag, 'UTF-8');

                if ($closing !== '') {
                    return in_array($tag, self::ALLOWED_TAGS, true) ? "</{$tag}>" : '';
                }

                if (! in_array($tag, self::ALLOWED_TAGS, true)) {
                    return '';
                }

                $allowed = self::ALLOWED_ATTRS[$tag] ?? [];

                return '<'.$tag.$this->filterAttrs($attrs, $allowed).'>';
            },
            $html,
        );
    }

    /** @param  array<int, string>  $allowed */
    private function filterAttrs(string $rawAttrs, array $allowed): string
    {
        if ($rawAttrs === '' || $allowed === []) {
            return '';
        }

        $out = '';
        if (preg_match_all('/([a-zA-Z][a-zA-Z0-9-]*)\\s*=\\s*("[^"]*"|\'[^\']*\'|[^\\s>]+)/', $rawAttrs, $m, PREG_SET_ORDER)) {
            foreach ($m as $attr) {
                $name = mb_strtolower($attr[1], 'UTF-8');
                if (! in_array($name, $allowed, true)) {
                    continue;
                }
                $value = trim($attr[2], "\"'");
                if ($name === 'href' && ! preg_match('~^(https?://|mailto:|/|\\#|\\?)[a-z0-9_\\#?&=./%:@-]*$~iu', $value)) {
                    continue;
                }
                $out .= ' '.$name.'="'.htmlspecialchars($value, ENT_QUOTES, 'UTF-8').'"';
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $standard  استاندارد مؤثر (required_elements و...)
     * @return array{headings: array<int, array{level: int, text: string}>, word_count: int, elements: array<string, bool>}
     */
    private function structure(string $html, array $standard): array
    {
        $headings = [];
        if (preg_match_all('#<h([1-6])[^>]*>(.*?)</h\\1>#isu', $html, $m, PREG_SET_ORDER)) {
            foreach ($m as $match) {
                $text = trim((string) preg_replace('/<[^>]+>/', '', (string) $match[2]));
                if ($text !== '') {
                    $headings[] = ['level' => (int) $match[1], 'text' => mb_substr($text, 0, 160, 'UTF-8')];
                }
            }
        }

        $plain = trim((string) preg_replace('/<[^>]+>/', ' ', $html));
        $wordCount = $this->wordCount($plain);

        // عناصر الزامی استاندارد → حضور/غیاب در سند
        $required = (array) ($standard['required_elements'] ?? []);
        $elements = [];
        foreach ($required as $key) {
            $elements[(string) $key] = $this->elementPresent((string) $key, $html);
        }

        return ['headings' => $headings, 'word_count' => $wordCount, 'elements' => $elements];
    }

    private function elementPresent(string $key, string $html): bool
    {
        return match ($key) {
            'h2_structure' => preg_match('/<h2[ >]/i', $html) === 1,
            'table' => preg_match('/<table[ >]/i', $html) === 1,
            'faq' => preg_match('/سؤالات متداول|پرسش‌های متداول|سؤال/i', $html) === 1,
            'cta' => preg_match('/گام بعدی|همین حالا|تماس بگیرید|مشاوره/i', $html) === 1,
            'pros_cons' => preg_match('/مزایا|معایب|مزیت|نکات مثبت/i', $html) === 1,
            'steps' => preg_match('/مرحله|گام|قدم/i', $html) === 1,
            'list' => preg_match('/<ul[ >]|<ol[ >]/i', $html) === 1,
            'table_of_contents' => preg_match('/فهرست|نمایه|فهرست مطالب/i', $html) === 1,
            'internal_links' => preg_match('/<a[ >]/i', $html) === 1,
            'keyword' => preg_match('/<a[ >]/i', $html) === 1 || trim((string) preg_replace('/<[^>]+>/', ' ', $html)) !== '',
            'specs' => preg_match('/مشخصات|ویژگی‌های فنی|<table[ >]/i', $html) === 1,
            'rating' => preg_match('/امتیاز|رتبه|ستاره/i', $html) === 1,
            'social_proof' => preg_match('/توصیه|نظر مشتری|رضایت/i', $html) === 1,
            'intro_required' => true, // توسط گیت کیفیت بررسی می‌شود
            'title_required' => preg_match('/<h1[ >]/i', $html) === 1,
            default => false,
        };
    }

    private function wordCount(string $plain): int
    {
        if (trim($plain) === '') {
            return 0;
        }
        $words = preg_split('/[\s'.chr(0xE2).chr(0x80).chr(0x8C).']+/u', $plain) ?: [];

        return count(array_filter($words, fn (string $w): bool => trim($w) !== ''));
    }
}
