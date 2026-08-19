<?php

declare(strict_types=1);

namespace App\Domains\Content\Services;

use Illuminate\Support\Facades\DB;

/**
 * پایگاه استانداردهای پویا (StandardsKB) — نسخهٔ جامع.
 *
 * استاندارد مؤثر برای هر (نوع × زیرنوع × قصد) از مدل سهلایه حل می‌شود:
 *   L1 seed صنعت (versioned) → L2 یادگیری از عملکرد (site × subtype) → L3 تنظیم دستی
 * و همیشه «کف امنیت مطلق» (safety floor) هاردکد اعمال می‌شود.
 *
 * این نسخه شامل تمام فیلدهای مورد نیاز برای کسب بالاترین نمره در RankMath/Yoast:
 * - طول محتوا، تراکم کلیدواژه، ساختار عنوان‌ها
 * - Meta title/description range
 * - عناصر الزامی (جدول، FAQ، CTA، لیست، اسکیما، لینک داخلی)
 * - قوانین لینک‌سازی داخلی
 * - نوع اسکیمای Schema.org
 * - راهنمای کلیدواژه (title_required, intro_required, density_max)
 */
class StandardsKB
{
    /** کف امنیت مطلق — غیرقابل override (هاردکد) */
    public const SAFETY_FLOOR = [
        'article' => [
            'word_min' => 300,
            'min_headings' => 2,
            'min_title_length' => 30,
            'max_title_length' => 60,
            'min_meta_desc_length' => 120,
            'max_meta_desc_length' => 160,
        ],
        'product' => [
            'word_min' => 80,
            'min_headings' => 1,
            'min_title_length' => 30,
            'max_title_length' => 60,
            'min_meta_desc_length' => 120,
            'max_meta_desc_length' => 160,
        ],
        'meta' => [
            'word_min' => 20,
            'min_headings' => 0,
            'min_title_length' => 30,
            'max_title_length' => 60,
            'min_meta_desc_length' => 120,
            'max_meta_desc_length' => 160,
        ],
        'landing' => [
            'word_min' => 400,
            'min_headings' => 2,
            'min_title_length' => 30,
            'max_title_length' => 60,
            'min_meta_desc_length' => 130,
            'max_meta_desc_length' => 160,
        ],
    ];

    /**
     * پیش‌فرض‌های استاندارد SEO بر اساس زیرنوع محتوا.
     * این مقادیر زمانی اعمال می‌شوند که ردیف seed در دیتابیس وجود نداشته باشد.
     */
    private const SUBTYPE_DEFAULTS = [
        // ─── مقالات ───
        'article' => [
            'word_min' => 600, 'word_max' => 3000, 'min_headings' => 3,
            'required_elements' => ['h2_structure', 'faq', 'cta', 'internal_links', 'table_of_contents'],
            'tone' => 'informative',
            'keyword_guidance' => ['title_required' => true, 'intro_required' => true, 'density_min' => 0.8, 'density_max' => 2.5],
            'schema_type' => 'Article',
            'internal_link_rules' => ['min_links' => 2, 'max_links' => 5, 'anchor_relevant' => true],
        ],
        'tutorial' => [
            'word_min' => 800, 'word_max' => 4000, 'min_headings' => 4,
            'required_elements' => ['h2_structure', 'steps', 'faq', 'cta', 'internal_links', 'table_of_contents'],
            'tone' => 'educational',
            'keyword_guidance' => ['title_required' => true, 'intro_required' => true, 'density_min' => 1.0, 'density_max' => 2.5],
            'schema_type' => 'HowTo',
            'internal_link_rules' => ['min_links' => 3, 'max_links' => 6, 'anchor_relevant' => true],
        ],
        'how_to' => [
            'word_min' => 800, 'word_max' => 4000, 'min_headings' => 4,
            'required_elements' => ['h2_structure', 'steps', 'list', 'faq', 'cta', 'internal_links'],
            'tone' => 'educational',
            'keyword_guidance' => ['title_required' => true, 'intro_required' => true, 'density_min' => 1.0, 'density_max' => 2.5],
            'schema_type' => 'HowTo',
            'internal_link_rules' => ['min_links' => 3, 'max_links' => 6, 'anchor_relevant' => true],
        ],
        'comparison' => [
            'word_min' => 800, 'word_max' => 3000, 'min_headings' => 3,
            'required_elements' => ['h2_structure', 'table', 'pros_cons', 'cta', 'faq', 'internal_links'],
            'tone' => 'analytical',
            'keyword_guidance' => ['title_required' => true, 'intro_required' => true, 'density_min' => 0.8, 'density_max' => 2.0],
            'schema_type' => 'Article',
            'internal_link_rules' => ['min_links' => 3, 'max_links' => 5, 'anchor_relevant' => true],
        ],
        'review' => [
            'word_min' => 1000, 'word_max' => 3500, 'min_headings' => 4,
            'required_elements' => ['h2_structure', 'rating', 'pros_cons', 'table', 'faq', 'cta', 'internal_links'],
            'tone' => 'analytical',
            'keyword_guidance' => ['title_required' => true, 'intro_required' => true, 'density_min' => 1.0, 'density_max' => 2.5],
            'schema_type' => 'Review',
            'internal_link_rules' => ['min_links' => 3, 'max_links' => 6, 'anchor_relevant' => true],
        ],
        'listicle' => [
            'word_min' => 800, 'word_max' => 3000, 'min_headings' => 3,
            'required_elements' => ['h2_structure', 'list', 'table', 'cta', 'internal_links'],
            'tone' => 'engaging',
            'keyword_guidance' => ['title_required' => true, 'intro_required' => true, 'density_min' => 0.8, 'density_max' => 2.0],
            'schema_type' => 'ItemList',
            'internal_link_rules' => ['min_links' => 3, 'max_links' => 5, 'anchor_relevant' => true],
        ],
        'pillar' => [
            'word_min' => 1500, 'word_max' => 6000, 'min_headings' => 6,
            'required_elements' => ['h2_structure', 'table_of_contents', 'faq', 'table', 'list', 'cta', 'internal_links'],
            'tone' => 'authoritative',
            'keyword_guidance' => ['title_required' => true, 'intro_required' => true, 'density_min' => 1.0, 'density_max' => 2.0],
            'schema_type' => 'Article',
            'internal_link_rules' => ['min_links' => 5, 'max_links' => 10, 'anchor_relevant' => true],
        ],
        'guide' => [
            'word_min' => 1200, 'word_max' => 5000, 'min_headings' => 5,
            'required_elements' => ['h2_structure', 'table_of_contents', 'steps', 'faq', 'cta', 'internal_links'],
            'tone' => 'authoritative',
            'keyword_guidance' => ['title_required' => true, 'intro_required' => true, 'density_min' => 1.0, 'density_max' => 2.5],
            'schema_type' => 'Article',
            'internal_link_rules' => ['min_links' => 4, 'max_links' => 8, 'anchor_relevant' => true],
        ],
        'news' => [
            'word_min' => 300, 'word_max' => 1000, 'min_headings' => 2,
            'required_elements' => ['h2_structure', 'cta', 'internal_links'],
            'tone' => 'journalistic',
            'keyword_guidance' => ['title_required' => true, 'intro_required' => true, 'density_min' => 0.5, 'density_max' => 2.0],
            'schema_type' => 'NewsArticle',
            'internal_link_rules' => ['min_links' => 2, 'max_links' => 4, 'anchor_relevant' => true],
        ],
        'faq' => [
            'word_min' => 500, 'word_max' => 2000, 'min_headings' => 3,
            'required_elements' => ['h2_structure', 'faq', 'list', 'internal_links'],
            'tone' => 'informative',
            'keyword_guidance' => ['title_required' => true, 'intro_required' => true, 'density_min' => 1.0, 'density_max' => 3.0],
            'schema_type' => 'FAQPage',
            'internal_link_rules' => ['min_links' => 2, 'max_links' => 5, 'anchor_relevant' => true],
        ],
        // ─── محصولات ───
        'short_desc' => [
            'word_min' => 80, 'word_max' => 200, 'min_headings' => 0,
            'required_elements' => ['cta'],
            'tone' => 'persuasive',
            'keyword_guidance' => ['title_required' => true, 'intro_required' => false, 'density_min' => 1.0, 'density_max' => 3.0],
            'schema_type' => 'Product',
            'internal_link_rules' => ['min_links' => 1, 'max_links' => 2, 'anchor_relevant' => true],
        ],
        'long_desc' => [
            'word_min' => 200, 'word_max' => 800, 'min_headings' => 2,
            'required_elements' => ['h2_structure', 'list', 'specs', 'cta', 'internal_links'],
            'tone' => 'persuasive',
            'keyword_guidance' => ['title_required' => true, 'intro_required' => true, 'density_min' => 1.0, 'density_max' => 2.5],
            'schema_type' => 'Product',
            'internal_link_rules' => ['min_links' => 2, 'max_links' => 4, 'anchor_relevant' => true],
        ],
        'technical' => [
            'word_min' => 300, 'word_max' => 1200, 'min_headings' => 3,
            'required_elements' => ['h2_structure', 'specs', 'table', 'list', 'internal_links'],
            'tone' => 'technical',
            'keyword_guidance' => ['title_required' => true, 'intro_required' => true, 'density_min' => 1.0, 'density_max' => 2.0],
            'schema_type' => 'Product',
            'internal_link_rules' => ['min_links' => 2, 'max_links' => 4, 'anchor_relevant' => true],
        ],
        // ─── لندینگ ───
        'sales' => [
            'word_min' => 400, 'word_max' => 1500, 'min_headings' => 3,
            'required_elements' => ['h2_structure', 'list', 'table', 'cta', 'faq', 'social_proof', 'internal_links'],
            'tone' => 'persuasive',
            'keyword_guidance' => ['title_required' => true, 'intro_required' => true, 'density_min' => 1.0, 'density_max' => 2.5],
            'schema_type' => 'WebPage',
            'internal_link_rules' => ['min_links' => 2, 'max_links' => 5, 'anchor_relevant' => true],
        ],
    ];

    /**
     * خروجی جامع استاندارد مؤثر — تمام فیلدهای مورد نیاز RankMath/Yoast.
     *
     * @param  array{content_type: string, subtype: string, intent: string}  $profile
     * @return array{word_min: int, word_max: int|null, min_headings: int, required_elements: array, tone: string|null, keyword_guidance: array, schema_type: string, internal_link_rules: array, meta_title: array, meta_description: array, min_title_length: int, max_title_length: int, min_meta_desc_length: int, max_meta_desc_length: int, version: int, source: string, standard_key: string}
     */
    public function standardFor(array $profile, ?int $siteId = null): array
    {
        $contentType = $profile['content_type'] ?? 'article';
        $subtype = $profile['subtype'] ?? 'article';
        $intent = $profile['intent'] ?? 'informational';

        // انتخاب پیش‌فرض بر اساس زیرنوع یا نوع محتوا
        $defaults = self::SUBTYPE_DEFAULTS[$subtype]
            ?? self::SUBTYPE_DEFAULTS[$contentType]
            ?? self::SUBTYPE_DEFAULTS['article'];

        // L1 — آخرین نسخهٔ seed/global
        $seed = DB::table('content_standards')
            ->where('content_type', $contentType)
            ->where('subtype', $subtype)
            ->where('intent', $intent)
            ->orderByDesc('version')
            ->first();

        $floor = self::SAFETY_FLOOR[$contentType] ?? self::SAFETY_FLOOR['article'];

        $effective = [
            'word_min' => (int) ($seed->word_min ?? $defaults['word_min']),
            'word_max' => $seed?->word_max !== null ? (int) $seed->word_max : ($defaults['word_max'] ?? null),
            'min_headings' => (int) ($seed->min_headings ?? $defaults['min_headings']),
            'required_elements' => $seed !== null ? (array) json_decode((string) $seed->required_elements, true) : ($defaults['required_elements'] ?? []),
            'tone' => $seed?->tone ?? ($defaults['tone'] ?? 'informative'),
            'keyword_guidance' => $seed !== null && isset($seed->keyword_guidance)
                ? (array) json_decode((string) $seed->keyword_guidance, true)
                : ($defaults['keyword_guidance'] ?? []),
            'schema_type' => $seed?->schema_type ?? ($defaults['schema_type'] ?? 'Article'),
            'internal_link_rules' => $seed !== null && isset($seed->internal_link_rules)
                ? (array) json_decode((string) $seed->internal_link_rules, true)
                : ($defaults['internal_link_rules'] ?? []),
            'meta_title' => [
                'min_length' => (int) ($seed?->min_title_length ?? $floor['min_title_length'] ?? 30),
                'max_length' => (int) ($seed?->max_title_length ?? $floor['max_title_length'] ?? 60),
            ],
            'meta_description' => [
                'min_length' => (int) ($seed?->min_meta_desc_length ?? $floor['min_meta_desc_length'] ?? 120),
                'max_length' => (int) ($seed?->max_meta_desc_length ?? $floor['max_meta_desc_length'] ?? 160),
            ],
            'min_title_length' => (int) ($seed?->min_title_length ?? $floor['min_title_length'] ?? 30),
            'max_title_length' => (int) ($seed?->max_title_length ?? $floor['max_title_length'] ?? 60),
            'min_meta_desc_length' => (int) ($seed?->min_meta_desc_length ?? $floor['min_meta_desc_length'] ?? 120),
            'max_meta_desc_length' => (int) ($seed?->max_meta_desc_length ?? $floor['max_meta_desc_length'] ?? 160),
            'version' => (int) ($seed->version ?? 1),
            'source' => $seed?->source ?? 'seed',
        ];

        // L2 — یادگیری از عملکرد سایت (فقط سخت‌گیرتر)
        if ($siteId !== null) {
            $learned = $this->learnedOverride($siteId, $contentType, $subtype);
            if ($learned !== null) {
                $effective['word_min'] = max($effective['word_min'], $learned['word_min']);
                $effective['min_headings'] = max($effective['min_headings'], $learned['min_headings']);
                $effective['version'] = max($effective['version'], $learned['version']);
                $effective['source'] = 'learned';
            }
        }

        // کف امنیت مطلق — همیشه آخر اعمال می‌شود
        $effective['word_min'] = max($effective['word_min'], $floor['word_min']);
        $effective['min_headings'] = max($effective['min_headings'], $floor['min_headings']);
        $effective['min_title_length'] = max($effective['min_title_length'], $floor['min_title_length'] ?? 30);
        $effective['max_title_length'] = max($effective['max_title_length'], $floor['max_title_length'] ?? 60);
        $effective['min_meta_desc_length'] = max($effective['min_meta_desc_length'], $floor['min_meta_desc_length'] ?? 120);
        $effective['max_meta_desc_length'] = max($effective['max_meta_desc_length'], $floor['max_meta_desc_length'] ?? 160);

        // بازنویسی meta ranges برای سازگاری
        $effective['meta_title'] = [
            'min_length' => $effective['min_title_length'],
            'max_length' => $effective['max_title_length'],
        ];
        $effective['meta_description'] = [
            'min_length' => $effective['min_meta_desc_length'],
            'max_length' => $effective['max_meta_desc_length'],
        ];

        $effective['standard_key'] = "{$contentType}×{$subtype}×{$intent}";

        return $effective;
    }

    /**
     * بازهٔ آموخته‌شده از عملکرد واقعی سایت برای همان (نوع × زیرنوع).
     *
     * @return array{word_min: int, min_headings: int, version: int}|null
     */
    private function learnedOverride(int $siteId, string $contentType, string $subtype): ?array
    {
        // از جدول یادگیری استانداردها (در صورت وجود) یا از آمار محتوای موفق سایت
        $row = DB::table('site_content_standard_learnings')
            ->where('site_id', $siteId)
            ->where('content_type', $contentType)
            ->where('subtype', $subtype)
            ->first();

        if ($row === null) {
            return null;
        }

        return [
            'word_min' => (int) $row->learned_word_min,
            'min_headings' => (int) $row->learned_min_headings,
            'version' => (int) $row->version,
        ];
    }

    /**
     * ثبت یادگیری جدید از عملکرد واقعی (حلقهٔ یادگیری): فقط سخت‌گیرانه‌تر.
     */
    public function learn(int $siteId, string $contentType, string $subtype, int $wordMin, int $minHeadings): void
    {
        $existing = DB::table('site_content_standard_learnings')
            ->where('site_id', $siteId)
            ->where('content_type', $contentType)
            ->where('subtype', $subtype)
            ->first();

        $next = [
            'learned_word_min' => $existing !== null ? max((int) $existing->learned_word_min, $wordMin) : $wordMin,
            'learned_min_headings' => $existing !== null ? max((int) $existing->learned_min_headings, $minHeadings) : $minHeadings,
            'version' => ($existing->version ?? 1) + 1,
        ];

        if ($existing === null) {
            DB::table('site_content_standard_learnings')->insert([
                'site_id' => $siteId,
                'content_type' => $contentType,
                'subtype' => $subtype,
                'learned_word_min' => $next['learned_word_min'],
                'learned_min_headings' => $next['learned_min_headings'],
                'version' => $next['version'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('site_content_standard_learnings')->where('id', $existing->id)->update([
                'learned_word_min' => $next['learned_word_min'],
                'learned_min_headings' => $next['learned_min_headings'],
                'version' => $next['version'],
                'updated_at' => now(),
            ]);
        }
    }
}
