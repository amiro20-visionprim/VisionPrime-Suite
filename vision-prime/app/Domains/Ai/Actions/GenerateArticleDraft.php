<?php

declare(strict_types=1);

namespace App\Domains\Ai\Actions;

use App\Domains\Ai\Services\AiClient;
use App\Domains\Audit\Actions\RecordAuditLog;
use App\Domains\Content\Services\ContentProfiler;
use App\Domains\Content\Models\ContentGuardrail;
use App\Domains\Content\Services\StandardsKB;
use App\Domains\Workspace\Models\Site;
use Illuminate\Support\Facades\DB;

/**
 * Generates a full article draft for a page using the configured AI provider
 * (or the offline rule-based fallback), guided by:
 *   - ContentProfiler: «فهم چی و چرا» — content type, subtype and intent from the
 *     target query / page title;
 *   - StandardsKB: effective standard (word range, structure, required elements,
 *     tone) resolved from seed → learned → manual, with the hard-coded safety floor;
 *   - live GSC context: target query, page metrics and data freshness.
 * The draft is stored as an ai_generation and a review item is opened so it enters
 * the human review queue («بررسی و تأییدها»).
 */
class GenerateArticleDraft
{
    public function __construct(
        private readonly AiClient $client,
        private readonly ContentProfiler $profiler,
        private readonly StandardsKB $standards,
        private readonly CreateAiGeneration $createGeneration,
        private readonly CreateReviewItem $createReviewItem,
        private readonly RecordAuditLog $audit,
    ) {}

    /**
     * @param  string|null  $title  optional user-provided title; otherwise derived from the target query
     * @param  string|null  $subtype  optional manual subtype hint (validated by ContentProfiler)
     * @return int The ai_generation id.
     */
    public function handle(Site $site, int $urlProfileId, ?string $title = null, ?string $subtype = null): int
    {
        $profile = DB::table('url_profiles')
            ->where('site_id', $site->id)
            ->where('id', $urlProfileId)
            ->firstOrFail();

        $gsc = $this->gscContext($site, $profile);

        // «فهم چی» — type/subtype/intent از روی کوئری هدف و عنوان
        $resolvedTitle = trim((string) ($title ?? '')) !== ''
            ? (string) $title
            : (trim((string) $gsc['target_query']) !== ''
                ? $gsc['target_query']
                : $this->titleFromUrl((string) $profile->canonical_url));
        $profiled = $this->profiler->profile([
            'title' => $resolvedTitle,
            'target_query' => $gsc['target_query'],
            'content_type' => $this->mapContentType((string) $profile->content_type),
            'subtype' => $subtype ?? '',
        ]);

        // «استاندارد مؤثر» — seed → learned → manual + safety floor
        $standard = $this->standards->standardFor($profiled, (int) $site->id);

        // «گارد rails مؤثر» — سایت-specifc → سازمان-wide → defaults
        $guardrail = ContentGuardrail::resolve(
            (int) $site->organization_id,
            (int) $site->id,
            $profiled['content_type'] ?? 'article',
            $profiled['subtype'] ?? 'general',
        );
        $guardrails = $guardrail->toPromptArray();

        $context = [
            'title' => $profiled['title'],
            'target_query' => $gsc['target_query'],
            'site_name' => (string) $site->name,
            'url' => (string) $profile->canonical_url,
            'standard' => $standard,
            'metrics' => $gsc['metrics'],
            'freshness' => $gsc['freshness'],
            'page_status' => (string) $profile->post_status,
            'guardrails' => $guardrails,
        ];

        $result = $this->client->generateArticleDraft($site->organization, $context);

        $isProduct = ($profiled['content_type'] ?? '') === 'product';
        $featuredImage = $this->featuredImageSuggestion($profiled, $standard);

        $output = [
            'kind' => $isProduct ? 'product' : 'article',
            'text' => $result['content'],
            'model' => $result['model'],
            'source' => $result['source'],
            'standard' => $standard,
            'profile' => [
                'content_type' => $profiled['content_type'],
                'subtype' => $profiled['subtype'],
                'intent' => $profiled['intent'],
                'title' => $profiled['title'],
            ],
            'featured_image' => $featuredImage,
            'schema' => $isProduct
                ? $this->productSchemaMarkup($profiled, $standard, $result['content'], $site, $profile)
                : $this->schemaMarkup($profiled, $standard, $result['content'], $site, $profile),
        ];

        $generationId = $this->createGeneration->handle(
            $site,
            null,
            [
                'kind' => 'article',
                'url' => $context['url'],
                'target_query' => $gsc['target_query'],
                'metrics' => $gsc['metrics'],
                'freshness' => $gsc['freshness'],
                'standard' => $standard,
                'profile' => $profiled,
            ],
            $output,
            $result['usage'],
        );

        $this->createReviewItem->handle($site, 'ai_generation', $generationId);

        $this->audit->handle(
            action: 'ai.article_draft_generated',
            subject: $site,
            after: [
                'generation_id' => $generationId,
                'url_profile_id' => $urlProfileId,
                'subtype' => $profiled['subtype'],
                'intent' => $profiled['intent'],
                'source' => $result['source'],
            ],
        );

        return (int) $generationId;
    }

    /**
     * Context جستجو: کوئری هدف (از keyword_insights)، متریک‌های GSC و تازگی داده
     * (آخرین import موفق برای همان property).
     *
     * @param  object  $profile  url_profiles row
     * @return array{target_query: string, metrics: array{clicks: int, impressions: int, ctr: float, position: float}, freshness: array<string, string|null>|null}
     */
    private function gscContext(Site $site, object $profile): array
    {
        $metadata = json_decode($profile->metadata ?? '{}', true) ?? [];
        $gsc = $metadata['gsc'] ?? [];

        $insight = DB::table('keyword_insights')
            ->where('site_id', $site->id)
            ->where('mapped_url_profile_id', $profile->id)
            ->orderByDesc('id')
            ->first();

        $insightMetrics = $insight === null ? [] : (json_decode($insight->latest_metrics ?? '{}', true) ?? []);
        $targetQuery = (string) ($insightMetrics['query'] ?? $insight->query_normalized ?? '');

        // تازگی داده: آخرین import موفقِ همین property
        $freshness = null;
        $property = DB::table('gsc_properties')
            ->where('site_id', $site->id)
            ->where('status', 'selected')
            ->latest('id')
            ->first();

        if ($property !== null) {
            $run = DB::table('gsc_import_runs')
                ->where('gsc_property_id', $property->id)
                ->where('status', 'completed')
                ->orderByDesc('date_end')
                ->first();

            if ($run !== null) {
                $freshness = [
                    'date_start' => (string) $run->date_start,
                    'date_end' => (string) $run->date_end,
                    'finished_at' => (string) $run->finished_at,
                ];
            }
        }

        // Top queries for this URL
        $topQueries = DB::table('keyword_insights')
            ->where('site_id', $site->id)
            ->where('mapped_url_profile_id', $profile->id)
            ->where('status', 'active')
            ->orderByDesc(DB::raw("(latest_metrics->>'impressions')::int"))
            ->limit(10)
            ->pluck('query_normalized')
            ->filter()
            ->values()
            ->toArray();

        // Related pages (same site, similar topic)
        $relatedPages = DB::table('url_profiles')
            ->where('site_id', $site->id)
            ->where('id', '!=', $profile->id)
            ->where('canonical_url', '!=', '')
            ->limit(5)
            ->pluck('metadata')
            ->map(fn($m) => json_decode($m ?? '{}', true)['title'] ?? '')
            ->filter()
            ->values()
            ->toArray();

        // Opportunities for improvement
        $opportunities = DB::table('opportunities')
            ->where('site_id', $site->id)
            ->where('status', 'open')
            ->orderByDesc('score')
            ->limit(5)
            ->pluck('explanation')
            ->filter()
            ->values()
            ->toArray();

        return [
            'target_query' => $targetQuery,
            'metrics' => [
                'clicks' => (int) ($gsc['clicks'] ?? 0),
                'impressions' => (int) ($gsc['impressions'] ?? 0),
                'ctr' => (float) ($gsc['ctr'] ?? 0),
                'position' => (float) ($gsc['position'] ?? 0),
            ],
            'freshness' => $freshness,
            'top_queries' => $topQueries,
            'related_pages' => $relatedPages,
            'opportunities' => $opportunities,
        ];
    }

    /**
     * پیشنهاد تصویر شاخص بر اساس استاندارد مؤثر (ابعاد، متن جایگزین، توضیح).
     * تصویر واقعی در فاز فعلی تولید نمی‌شود؛ بازبین می‌تواند بر اساس این پیشنهاد
     * تصویر را از گالری سایت یا ابزار تولید تصویر تهیه کند.
     *
     * @param  array<string, mixed>  $profiled
     * @param  array<string, mixed>  $standard
     * @return array{alt: string, suggested_width: int, suggested_height: int, aspect: string, rationale: string}
     */
    private function featuredImageSuggestion(array $profiled, array $standard): array
    {
        $title = (string) ($profiled['title'] ?? '');
        $subtype = (string) ($profiled['subtype'] ?? 'article');
        $subtypeLabel = ContentProfiler::subtypeLabels()[$subtype] ?? $subtype;

        // استاندارد مؤثر ابعاد پیشنهادی را تعیین می‌کند (مقاله‌های پیلار/راهنما بزرگ‌تر)
        [$width, $height] = match (true) {
            ($profiled['content_type'] ?? '') === 'product' => [1200, 1200], // مربعی — استاندارد گالری ووکامرس
            in_array($subtype, ['pillar', 'guide'], true) => [1600, 900],
            default => [1200, 630],
        };

        $rationale = sprintf(
            'تصویر شاخص با نسبت %d:%d و متن جایگزین «%s» — متناسب با زیرنوع «%s» (استاندارد مؤثر: بین %d و %s کلمه). تصویر باید مرتبط با «%s» باشد و هیچ متنی داخل آن نباشد.',
            $width,
            $height,
            $title !== '' ? $title : 'تصویر شاخص مقاله',
            $subtypeLabel,
            (int) ($standard['word_min'] ?? 0),
            ($standard['word_max'] ?? '∞') === null ? '∞' : (string) $standard['word_max'],
            $title !== '' ? $title : 'موضوع مقاله',
        );

        return [
            'alt' => $title !== '' ? $title : 'تصویر شاخص مقاله',
            'suggested_width' => $width,
            'suggested_height' => $height,
            'aspect' => $width.':'.$height,
            'rationale' => $rationale,
        ];
    }

    /**
     * اسکیمای Product برای پیش‌نویس محصول — نام، توضیح، تصویر و برند.
     * قیمت/موجودی دادهٔ واقعی است و هرگز جعل نمی‌شود؛ این اسکیما فقط ساختار
     * پیشنهادی است که بازبین پیش از انتشار تکمیل می‌کند.
     *
     * @param  array<string, mixed>  $profiled
     * @param  array<string, mixed>  $standard
     * @return array<int, array<string, mixed>>
     */
    private function productSchemaMarkup(array $profiled, array $standard, string $content, Site $site, object $profile): array
    {
        $title = (string) ($profiled['title'] ?? '');
        $url = (string) $profile->canonical_url;
        $description = mb_substr(trim((string) preg_replace('/<[^>]+>/', ' ', $content)), 0, 300, 'UTF-8');
        $featured = $this->featuredImageSuggestion($profiled, $standard);

        $product = [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $title !== '' ? $title : 'محصول',
            'description' => $description !== '' ? $description : $title,
            'image' => [
                'url' => null,
                'suggested' => [
                    'width' => $featured['suggested_width'],
                    'height' => $featured['suggested_height'],
                ],
                'alt' => $featured['alt'],
            ],
            'brand' => ['@type' => 'Brand', 'name' => (string) $site->name],
            'url' => $url,
            'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => $url],
        ];

        return [$product];
    }

    /**
     * اسکیمای Schema.org بر اساس استاندارد مؤثر:
     *  - Article همیشه (با headline، description، publisher، image)
     *  - FAQPage وقتی استاندارد عنصر «faq» را الزامی کرده باشد (سؤالات از خود محتوا استخراج می‌شوند)
     *
     * @param  array<string, mixed>  $profiled
     * @param  array<string, mixed>  $standard
     * @return array<int, array<string, mixed>>
     */
    private function schemaMarkup(array $profiled, array $standard, string $content, Site $site, object $profile): array
    {
        $title = (string) ($profiled['title'] ?? '');
        $url = (string) $profile->canonical_url;
        $date = now()->toDateString();
        $description = mb_substr(trim((string) preg_replace('/<[^>]+>/', ' ', $content)), 0, 155, 'UTF-8');
        $featured = $this->featuredImageSuggestion($profiled, $standard);

        $article = [
            '@context' => 'https://schema.org',
            '@type' => 'Article',
            'headline' => $title !== '' ? $title : 'مقاله',
            'description' => $description !== '' ? $description : $title,
            'image' => [
                'url' => null,
                'suggested' => [
                    'width' => $featured['suggested_width'],
                    'height' => $featured['suggested_height'],
                ],
                'alt' => $featured['alt'],
            ],
            'author' => ['@type' => 'Organization', 'name' => (string) $site->name],
            'publisher' => ['@type' => 'Organization', 'name' => (string) $site->name],
            'datePublished' => $date,
            'dateModified' => $date,
            'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => $url],
        ];

        $required = (array) ($standard['required_elements'] ?? []);
        if (! in_array('faq', $required, true)) {
            return [$article];
        }

        $faq = $this->faqEntities($content);
        if ($faq === []) {
            return [$article];
        }

        return [
            $article,
            [
                '@context' => 'https://schema.org',
                '@type' => 'FAQPage',
                'mainEntity' => $faq,
            ],
        ];
    }

    /**
     * استخراج پرسش/پاسخ از محتوای مقاله (الگوی «پرسش: … پاسخ: …»).
     *
     * @return array<int, array{@type: string, name: string, acceptedAnswer: array{@type: string, text: string}}>
     */
    private function faqEntities(string $content): array
    {
        $entities = [];
        if (preg_match_all('/<strong>پرسش:<\/strong>\s*(.*?)\s*<strong>پاسخ:<\/strong>\s*(.*?)(?=<strong>پرسش:|<\/p>)/isu', $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $q = trim((string) strip_tags((string) $match[1]));
                $a = trim((string) strip_tags((string) $match[2]));
                if ($q === '' || $a === '') {
                    continue;
                }
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

        return array_slice($entities, 0, 6);
    }

    private function mapContentType(string $profileType): string
    {
        return match ($profileType) {
            'product' => 'product',
            default => 'article',
        };
    }

    /** عنوان پیش‌فرض از مسیر URL وقتی کوئری هدفی ثبت نشده است. */
    private function titleFromUrl(string $url): string
    {
        $path = trim((string) parse_url($url, PHP_URL_PATH), '/');
        $segments = explode('/', $path !== '' ? $path : $url);
        $last = (string) end($segments);
        $words = array_filter(explode('-', rawurldecode($last)), fn (string $w): bool => $w !== '');

        return $words !== [] ? mb_substr(implode(' ', $words), 0, 120, 'UTF-8') : 'این صفحه';
    }
}
