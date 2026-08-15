<?php

declare(strict_types=1);

namespace App\Domains\Content\Services;

use Illuminate\Support\Facades\DB;

/**
 * پایگاه استانداردهای پویا (StandardsKB).
 *
 * استاندارد مؤثر برای هر (نوع × زیرنوع × قصد) از مدل سهلایه حل می‌شود:
 *   L1 seed صنعت (versioned) → L2 یادگیری از عملکرد (site × subtype) → L3 تنظیم دستی (سخت‌گیرتر)
 * و همیشه «کف امنیت مطلق» (safety floor) هاردکد اعمال می‌شود تا حتی با تنظیم دستی،
 * هیچ محتوایی زیر حد منطقی خودکار منتشر نشود.
 *
 * نسخه‌دار بودن ردیف‌ها یعنی هر تغییر استاندارد = ردیف جدید با version+1، و سیستم می‌تواند
 * «آپدیت استاندارد سئو» را ردیابی و در UI نمایش دهد.
 */
class StandardsKB
{
    /** کف امنیت مطلق — غیرقابل override (هاردکد) */
    public const SAFETY_FLOOR = [
        'article' => ['word_min' => 150, 'min_headings' => 1],
        'product' => ['word_min' => 40, 'min_headings' => 0],
        'meta' => ['word_min' => 20, 'min_headings' => 0],
        'landing' => ['word_min' => 200, 'min_headings' => 1],
    ];

    /**
     * @param  array{content_type: string, subtype: string, intent: string}  $profile
     * @return array{word_min: int, word_max: int|null, min_headings: int, required_elements: array<int, string>, tone: string|null, version: int, source: string, standard_key: string}
     */
    public function standardFor(array $profile, ?int $siteId = null): array
    {
        $contentType = $profile['content_type'] ?? 'article';
        $subtype = $profile['subtype'] ?? 'article';
        $intent = $profile['intent'] ?? 'informational';

        // L1 — آخرین نسخهٔ seed/global
        $seed = DB::table('content_standards')
            ->where('content_type', $contentType)
            ->where('subtype', $subtype)
            ->where('intent', $intent)
            ->orderByDesc('version')
            ->first();

        $effective = [
            'word_min' => (int) ($seed->word_min ?? self::SAFETY_FLOOR[$contentType]['word_min'] ?? 0),
            'word_max' => $seed?->word_max !== null ? (int) $seed->word_max : null,
            'min_headings' => (int) ($seed->min_headings ?? self::SAFETY_FLOOR[$contentType]['min_headings'] ?? 0),
            'required_elements' => $seed !== null ? (array) json_decode((string) $seed->required_elements, true) : [],
            'tone' => $seed?->tone,
            'version' => (int) ($seed->version ?? 1),
            'source' => $seed?->source ?? 'seed',
        ];

        // L2 — یادگیری از عملکرد سایت (فقط سخت‌گیرتر از seed؛ هرگز سهل‌گیرتر)
        if ($siteId !== null) {
            $learned = $this->learnedOverride($siteId, $contentType, $subtype);
            if ($learned !== null) {
                $effective['word_min'] = max($effective['word_min'], $learned['word_min']);
                $effective['min_headings'] = max($effective['min_headings'], $learned['min_headings']);
                $effective['version'] = max($effective['version'], $learned['version']);
                $effective['source'] = 'learned';
            }
        }

        // L3 — تنظیم دستی (همین‌جا؛ در عمل از جدول overrides سازمان/سایت خوانده می‌شود)
        // (در فاز فعلی seed + learned کافی است؛ manual از طریق UI در فاز بعدی)

        // کف امنیت مطلق — همیشه آخر اعمال می‌شود
        $floor = self::SAFETY_FLOOR[$contentType] ?? ['word_min' => 0, 'min_headings' => 0];
        $effective['word_min'] = max($effective['word_min'], (int) $floor['word_min']);
        $effective['min_headings'] = max($effective['min_headings'], (int) $floor['min_headings']);

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
