<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seed اولیهٔ «دانش روز صنعت» برای StandardsKB — داده، نه کد.
 * هر ردیف version=1 دارد؛ آپدیت‌های بعدی (seed/learned/manual/serp) نسخهٔ جدید می‌سازند
 * تا سیستم «آپدیت استاندارد سئو» را ردیابی کند.
 */
class ContentStandardsSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            // ---------- مقالات ----------
            ['article', 'tutorial', 'informational', 600, 2000, 3, ['h2_structure', 'cta'], 'informative', ['density_max' => 2, 'title_required' => true, 'intro_required' => true]],
            ['article', 'how_to', 'informational', 500, 1800, 3, ['h2_structure', 'steps', 'cta'], 'informative', ['density_max' => 2, 'title_required' => true, 'intro_required' => true]],
            ['article', 'comparison', 'commercial', 900, 2500, 4, ['h2_structure', 'table', 'pros_cons', 'cta'], 'neutral', ['density_max' => 2.5, 'title_required' => true, 'intro_required' => true]],
            ['article', 'review', 'commercial', 700, 2200, 3, ['h2_structure', 'pros_cons', 'rating', 'cta'], 'persuasive', ['density_max' => 2.5, 'title_required' => true, 'intro_required' => true]],
            ['article', 'listicle', 'informational', 500, 1600, 3, ['h2_structure', 'list', 'cta'], 'informative', ['density_max' => 2, 'title_required' => true, 'intro_required' => true]],
            ['article', 'pillar', 'informational', 1500, 5000, 6, ['h2_structure', 'table_of_contents', 'faq', 'internal_links', 'cta'], 'informative', ['density_max' => 1.5, 'title_required' => true, 'intro_required' => true]],
            ['article', 'guide', 'informational', 1000, 3500, 5, ['h2_structure', 'steps', 'faq', 'cta'], 'informative', ['density_max' => 1.5, 'title_required' => true, 'intro_required' => true]],
            ['article', 'news', 'informational', 300, 1000, 2, ['h2_structure'], 'neutral', ['density_max' => 2, 'title_required' => true, 'intro_required' => true]],
            ['article', 'faq', 'informational', 400, 1500, 1, ['faq', 'h2_structure'], 'informative', ['density_max' => 2, 'title_required' => true, 'intro_required' => true]],
            // ---------- محصولات (ووکامرس) ----------
            ['product', 'short_desc', 'commercial', 60, 160, 0, ['cta'], 'persuasive', ['density_max' => 3, 'title_required' => false, 'intro_required' => true]],
            ['product', 'long_desc', 'commercial', 200, 600, 2, ['h2_structure', 'pros_cons', 'cta'], 'persuasive', ['density_max' => 2, 'title_required' => false, 'intro_required' => true]],
            ['product', 'comparison', 'transactional', 400, 1200, 3, ['table', 'pros_cons', 'cta'], 'neutral', ['density_max' => 2, 'title_required' => false, 'intro_required' => true]],
            ['product', 'technical', 'commercial', 300, 900, 2, ['specs', 'h2_structure'], 'technical', ['density_max' => 2, 'title_required' => false, 'intro_required' => true]],
            // ---------- متا ----------
            ['meta', 'title', 'any', 30, 60, 0, ['keyword'], 'neutral', ['density_max' => 20, 'title_required' => true, 'intro_required' => false]],
            ['meta', 'description', 'any', 70, 155, 0, ['keyword', 'cta'], 'persuasive', ['density_max' => 5, 'title_required' => false, 'intro_required' => false]],
            // ---------- لندینگ ----------
            ['landing', 'sales', 'transactional', 500, 1500, 3, ['h2_structure', 'cta', 'social_proof'], 'persuasive', ['density_max' => 2, 'title_required' => true, 'intro_required' => true]],
        ];

        $now = now()->toDateTimeString();
        foreach ($rows as [$contentType, $subtype, $intent, $wordMin, $wordMax, $minHeadings, $elements, $tone, $keyword]) {
            DB::table('content_standards')->insert([
                'content_type' => $contentType,
                'subtype' => $subtype,
                'intent' => $intent,
                'word_min' => $wordMin,
                'word_max' => $wordMax,
                'min_headings' => $minHeadings,
                'required_elements' => json_encode($elements, JSON_UNESCAPED_UNICODE),
                'tone' => $tone,
                'keyword_guidance' => json_encode($keyword, JSON_UNESCAPED_UNICODE),
                'version' => 1,
                'updated_by' => null,
                'source' => 'seed',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}
