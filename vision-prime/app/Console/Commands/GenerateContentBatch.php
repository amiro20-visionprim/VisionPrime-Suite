<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Ai\Services\AiClient;
use App\Domains\Content\Services\ContentProfiler;
use App\Domains\Content\Services\ContentQualityGuard;
use App\Domains\Content\Services\InternalLinkEngine;
use App\Domains\Content\Services\SchemaGenerator;
use App\Domains\Content\Services\StandardsKB;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * تولید دسته‌ای محتوا — مقاله و محصول.
 *
 * Usage:
 *   php artisan content:generate-batch --site=1 --type=article --limit=5
 *   php artisan content:generate-batch --site=1 --type=product --limit=3
 *   php artisan content:generate-batch --site=1 --type=all --limit=10
 */
class GenerateContentBatch extends Command
{
    protected $signature = 'content:generate-batch
        {--site= : ID سایت (اجباری)}
        {--type=article : نوع محتوا: article, product, all}
        {--limit=5 : حداکثر تعداد محتوا}
        {--keyword= : کلیدواژه هدف (اختیاری)}
        {--subtype= : زیرنوع (اختیاری)}
        {--dry-run= : فقط نمایش اطلاعات بدون تولید}
    ';

    protected $description = 'تولید دسته‌ای مقاله و محصول با رعایت تمام استانداردهای SEO';

    private StandardsKB $standards;
    private ContentProfiler $profiler;
    private ContentQualityGuard $guard;
    private InternalLinkEngine $linkEngine;
    private SchemaGenerator $schemaGen;
    private AiClient $aiClient;

    public function __construct(
        StandardsKB $standards,
        ContentProfiler $profiler,
        ContentQualityGuard $guard,
        InternalLinkEngine $linkEngine,
        SchemaGenerator $schemaGen,
        AiClient $aiClient,
    ) {
        parent::__construct();
        $this->standards = $standards;
        $this->profiler = $profiler;
        $this->guard = $guard;
        $this->linkEngine = $linkEngine;
        $this->schemaGen = $schemaGen;
        $this->aiClient = $aiClient;
    }

    public function handle(): int
    {
        $siteId = (int) $this->option('site');
        $type = $this->option('type');
        $limit = (int) $this->option('limit');
        $targetKeyword = (string) $this->option('keyword');
        $subtype = (string) $this->option('subtype');
        $dryRun = (bool) $this->option('dry-run');

        if ($siteId === 0) {
            $this->error('--site الزامی است');
            return self::FAILURE;
        }

        $site = DB::table('sites')->where('id', $siteId)->first();
        if ($site === null) {
            $this->error("سایت #{$siteId} یافت نشد");
            return self::FAILURE;
        }

        $this->info("🔍 بررسی صفحات سایت: {$site->name}");

        // دریافت URL profiles
        $query = DB::table('url_profiles')
            ->where('site_id', $siteId)
            ->where('canonical_url', '!=', '');

        if ($type !== 'all') {
            $typeMap = [
                'article' => ['page', 'post'],
                'product' => ['product'],
            ];
            $contentTypes = $typeMap[$type] ?? [$type];
            $query->whereIn('content_type', $contentTypes);
        }

        $profiles = $query->get(['id', 'canonical_url', 'content_type', 'slug', 'metadata']);

        if ($profiles->isEmpty()) {
            $this->error('صفحه‌ای یافت نشد. ابتدا Content Sync را اجرا کنید.');
            return self::FAILURE;
        }

        $this->info("📋 {$profiles->count()} صفحه یافت شد");

        // دریافت GSC data برای هر صفحه
        $generated = 0;

        foreach ($profiles->take($limit) as $profile) {
            $this->newLine();
            $this->info("── صفحه: {$profile->canonical_url} ──");

            // دریافت keyword insight
            $insight = DB::table('keyword_insights')
                ->where('site_id', $siteId)
                ->where('mapped_url_profile_id', $profile->id)
                ->orderByDesc('id')
                ->first();

            $keyword = $targetKeyword !== ''
                ? $targetKeyword
                : ($insight->query_normalized ?? '');

            if ($keyword === '') {
                $this->warn('  ⚠️ کلیدواژه‌ای موجود نیست — رد شد');
                continue;
            }

            $meta = json_decode((string) $profile->metadata, true) ?? [];
            $existingTitle = $meta['title'] ?? $profile->slug;

            // Profile کردن
            $profiled = $this->profiler->profile([
                'title' => $existingTitle,
                'target_query' => $keyword,
                'content_type' => $profile->content_type === 'product' ? 'product' : 'article',
                'subtype' => $subtype,
            ]);

            $this->info("  📊 نوع: {$profiled['content_type']} | زیرنوع: {$profiled['subtype']} | قصد: {$profiled['intent']}");

            // دریافت استاندارد مؤثر
            $standard = $this->standards->standardFor($profiled, $siteId);
            $this->info("  📏 کلمات: {$standard['word_min']}-{$standard['word_max']} | عنوان‌ها: {$standard['min_headings']}+ | اسکیما: {$standard['schema_type']}");

            // پیشنهاد لینک داخلی
            $links = $this->linkEngine->suggest($siteId, $existingTitle, $keyword, $profiled['content_type'], $profiled['subtype']);
            $this->info("  🔗 " . count($links) . " لینک داخلی پیشنهاد شد");

            if ($dryRun) {
                $this->info("  🏃 Dry run — تولید نشد");
                foreach ($links as $link) {
                    $this->info("    → {$link['anchor']} → {$link['url']} (score: {$link['relevance_score']})");
                }
                continue;
            }

            // تولید محتوا با AI
            $this->info("  🤖 در حال تولید محتوا...");

            $context = [
                'title' => $existingTitle,
                'target_query' => $keyword,
                'site_name' => (string) $site->name,
                'url' => (string) $profile->canonical_url,
                'standard' => $standard,
                'metrics' => [
                    'clicks' => (int) ($insight->latest_metrics->clicks ?? 0),
                    'impressions' => (int) ($insight->latest_metrics->impressions ?? 0),
                    'ctr' => (float) ($insight->latest_metrics->ctr ?? 0),
                    'position' => (float) ($insight->latest_metrics->position ?? 0),
                ],
                'freshness' => null,
                'page_status' => 'publish',
                'internal_links' => $links,
            ];

            try {
                $result = $this->aiClient->generateArticleDraft($site->organization_id, $context);
                $content = $result['content'];

                // تزریق لینک‌های داخلی
                $baseUrl = parse_url((string) $profile->canonical_url, PHP_URL_SCHEME) . '://' . parse_url((string) $profile->canonical_url, PHP_URL_HOST);
                $content = $this->linkEngine->injectLinks($content, $links, $baseUrl);

                // تولید meta title/description
                $metaTitle = $this->generateMetaTitle($existingTitle, $keyword, (string) $site->name, $standard);
                $metaDesc = $this->generateMetaDescription($existingTitle, $keyword, (string) $site->name, $standard);

                // تولید اسکیما
                $schemas = $this->schemaGen->generate($profiled, $content, $existingTitle, (string) $profile->canonical_url, (string) $site->name, $metaDesc, $standard);
                $schemaJson = $this->schemaGen->toJsonLd($schemas);

                // ارزیابی کیفیت
                $evaluation = $this->guard->evaluate($profiled, [
                    'title' => $existingTitle,
                    'body' => $content,
                    'keyword' => $keyword,
                    'headings' => $this->extractHeadings($content),
                    'meta_title' => $metaTitle,
                    'meta_description' => $metaDesc,
                ], $siteId);

                $this->info("  ✅ تولید شد | امتیاز کیفیت: {$evaluation['score']}/100 | RankMath: {$evaluation['rankmath_score']}/100");

                if ($evaluation['failures'] !== []) {
                    $this->warn("  ⚠️ مشکلات: " . implode(', ', $evaluation['failures']));
                }
                if ($evaluation['warnings'] !== []) {
                    $this->warn("  💡 نکات: " . implode(', ', $evaluation['warnings']));
                }

                // ذخیره در ai_generations
                $generationId = DB::table('ai_generations')->insertGetId([
                    'site_id' => $siteId,
                    'url_profile_id' => $profile->id,
                    'kind' => 'article',
                    'input' => json_encode($context, JSON_UNESCAPED_UNICODE),
                    'text' => $content,
                    'model' => $result['model'],
                    'source' => $result['source'],
                    'usage' => json_encode($result['usage'], JSON_UNESCAPED_UNICODE),
                    'status' => 'generated',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // ذخیره meta و اسکیما در metadata
                DB::table('url_profiles')->where('id', $profile->id)->update([
                    'metadata' => json_encode(array_merge($meta, [
                        'generated_meta_title' => $metaTitle,
                        'generated_meta_description' => $metaDesc,
                        'generated_schema' => $schemas,
                        'quality_score' => $evaluation['score'],
                        'rankmath_score' => $evaluation['rankmath_score'],
                    ]), JSON_UNESCAPED_UNICODE),
                    'updated_at' => now(),
                ]);

                $generated++;

            } catch (\Throwable $e) {
                $this->error("  ❌ خطا: {$e->getMessage()}");
            }
        }

        $this->newLine();
        $this->info("✅ {$generated} محتوا تولید شد");

        return self::SUCCESS;
    }

    private function generateMetaTitle(string $title, string $keyword, string $siteName, array $standard): string
    {
        $maxLen = $standard['max_title_length'] ?? 60;
        $metaTitle = "{$keyword} | {$siteName}";

        if (mb_strlen($metaTitle, 'UTF-8') > $maxLen) {
            $metaTitle = mb_substr($keyword, 0, $maxLen - mb_strlen($siteName, 'UTF-8') - 3, 'UTF-8') . ' | ' . $siteName;
        }

        return trim($metaTitle);
    }

    private function generateMetaDescription(string $title, string $keyword, string $siteName, array $standard): string
    {
        $minLen = $standard['min_meta_desc_length'] ?? 120;
        $maxLen = $standard['max_meta_desc_length'] ?? 160;

        $desc = "{$keyword} را در {$siteName} با بهترین کیفیت و قیمت بررسی کنید. اطلاعات کامل، مقایسه و راهنمای خرید تخصصی با پشتیبانی ۲۴ ساعته.";

        return mb_substr($desc, 0, $maxLen, 'UTF-8');
    }

    private function extractHeadings(string $html): array
    {
        $headings = [];
        preg_match_all('/<h([1-6])[^>]*>(.*?)<\\/h[1-6]>/is', $html, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $headings[] = [
                'level' => (int) $match[1],
                'text' => strip_tags($match[2]),
            ];
        }

        return $headings;
    }
}
