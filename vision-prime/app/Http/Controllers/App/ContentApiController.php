<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Domains\Ai\Services\AiGateway;
use App\Domains\Content\Services\ContentProfiler;
use App\Domains\Content\Services\ContentQualityGuard;
use App\Domains\Content\Services\InternalLinkEngine;
use App\Domains\Content\Services\SchemaGenerator;
use App\Domains\Content\Models\ContentGuardrail;
use App\Domains\Content\Models\ContentDraft;
use App\Domains\Content\Services\StandardsKB;
use App\Domains\Organization\Contracts\CurrentOrganization;
use App\Domains\Workspace\Models\Site;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * REST API endpoints for intelligent content creation.
 *
 * - /api/content/research  → GSC-based topic suggestions
 * - /api/content/score     → Real-time SEO scoring
 * - /api/content/links     → Internal link suggestions
 * - /api/content/schema    → Schema.org preview
 * - /api/content/generate  → AI content generation
 */
class ContentApiController extends Controller
{
    public function __construct(
        private readonly ContentProfiler $profiler,
        private readonly StandardsKB $standards,
        private readonly ContentQualityGuard $qualityGuard,
        private readonly InternalLinkEngine $linkEngine,
        private readonly SchemaGenerator $schemaGen,
        private readonly AiGateway $gateway,
    ) {}

    /**
     * فقط سوپر ادمین اجازه تولید محتوا با AI و مدیریت Provider رو داره.
     */
    private function authorizeSuperAdmin(): void
    {
        if (! (request()->user()?->isSuperAdmin())) {
            abort(403, 'فقط مدیر سیستم اجازه استفاده از هوش مصنوعی را دارد.');
        }
    }

    /**
     * Research topics from GSC data — opportunities, keyword gaps, high-potential topics.
     *
     * GET /api/content/research?site_id=1
     */
    public function research(Request $request, CurrentOrganization $org): JsonResponse
    {
        $siteId = (int) $request->query('site_id', 0);

        if ($siteId === 0) {
            return response()->json(['error' => 'site_id الزامی است'], 422);
        }

        $site = Site::query()->where('organization_id', $org->id())->findOrFail($siteId);

        // 1. Get keyword insights with opportunities
        $insights = DB::table('keyword_insights')
            ->where('site_id', $siteId)
            ->where('status', 'active')
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        // 2. Get open opportunities
        $opportunities = DB::table('opportunities')
            ->where('site_id', $siteId)
            ->where('status', 'open')
            ->orderByDesc('score')
            ->limit(20)
            ->get();

        // 3. Get money page audits needing improvement
        $audits = DB::table('money_page_audits')
            ->join('url_profiles', 'url_profiles.id', '=', 'money_page_audits.url_profile_id')
            ->where('url_profiles.site_id', $siteId)
            ->where('money_page_audits.score', '<', 80)
            ->orderBy('money_page_audits.score')
            ->limit(10)
            ->get();

        $topics = [];

        // From opportunities
        foreach ($opportunities as $opp) {
            $insight = $insights->first(fn($i) => $i->id === $opp->keyword_insight_id);
            $query = $insight->query_normalized ?? '';
            if ($query === '') continue;

            $profiled = $this->profiler->profile([
                'title' => $query,
                'target_query' => $query,
                'content_type' => 'article',
                'subtype' => '',
            ]);

            $topics[] = [
                'type' => 'opportunity',
                'keyword' => $query,
                'score' => (int) $opp->score,
                'explanation' => $opp->explanation ?? '',
                'suggested_type' => $profiled['content_type'],
                'suggested_subtype' => $profiled['subtype'],
                'intent' => $profiled['intent'],
                'metrics' => [
                    'clicks' => (int) ($insight->latest_metrics->clicks ?? 0),
                    'impressions' => (int) ($insight->latest_metrics->impressions ?? 0),
                    'position' => (float) ($insight->latest_metrics->position ?? 0),
                ],
            ];
        }

        // From audits (pages needing improvement)
        foreach ($audits as $audit) {
            $meta = json_decode($audit->metadata ?? '{}', true) ?? [];
            $topics[] = [
                'type' => 'audit_improvement',
                'keyword' => $meta['title'] ?? $audit->canonical_url ?? '',
                'url' => $audit->canonical_url ?? '',
                'score' => (int) $audit->score,
                'explanation' => "صفحه نیاز به بهبود دارد (امتیاز: {$audit->score})",
                'suggested_type' => $audit->content_type ?? 'article',
                'url_profile_id' => (int) $audit->url_profile_id,
            ];
        }

        // From keyword insights (top queries not yet covered)
        foreach ($insights->take(10) as $insight) {
            $query = $insight->query_normalized ?? '';
            if ($query === '') continue;

            $exists = DB::table('url_profiles')
                ->where('site_id', $siteId)
                ->where('canonical_url', '!=', '')
                ->first();

            $topics[] = [
                'type' => 'keyword_gap',
                'keyword' => $query,
                'score' => 50,
                'explanation' => "کوئری پرجستجو بدون محتوای اختصاصی",
                'suggested_type' => 'article',
                'intent' => DB::table('intent_classifications')
                    ->where('keyword_insight_id', $insight->id)
                    ->value('intent') ?? 'informational',
                'metrics' => [
                    'clicks' => (int) ($insight->latest_metrics->clicks ?? 0),
                    'impressions' => (int) ($insight->latest_metrics->impressions ?? 0),
                    'position' => (float) ($insight->latest_metrics->position ?? 0),
                ],
            ];
        }

        // Sort by score
        usort($topics, fn($a, $b) => $b['score'] <=> $a['score']);

        return response()->json([
            'topics' => array_slice($topics, 0, 30),
            'site' => ['id' => $site->id, 'name' => $site->name],
        ]);
    }

    /**
     * Real-time SEO scoring.
     *
     * POST /api/content/score
     */
    public function score(Request $request, CurrentOrganization $org): JsonResponse
    {
        $data = $request->validate([
            'title' => 'required|string|max:200',
            'body' => 'required|string',
            'keyword' => 'required|string|max:100',
            'subtype' => 'nullable|string',
            'meta_title' => 'nullable|string|max:100',
            'meta_description' => 'nullable|string|max:300',
        ]);

        $profiled = $this->profiler->profile([
            'title' => $data['title'],
            'target_query' => $data['keyword'],
            'content_type' => 'article',
            'subtype' => $data['subtype'] ?? '',
        ]);

        $evaluation = $this->qualityGuard->evaluate($profiled, [
            'title' => $data['title'],
            'body' => $data['body'],
            'keyword' => $data['keyword'],
            'headings' => $this->extractHeadings($data['body']),
            'meta_title' => $data['meta_title'] ?? '',
            'meta_description' => $data['meta_description'] ?? '',
        ], null, $this->standards);

        return response()->json($evaluation);
    }

    /**
     * Internal link suggestions.
     *
     * POST /api/content/links
     */
    public function links(Request $request, CurrentOrganization $org): JsonResponse
    {
        $data = $request->validate([
            'site_id' => 'required|integer',
            'title' => 'required|string|max:200',
            'keyword' => 'required|string|max:100',
            'content_type' => 'nullable|string',
            'subtype' => 'nullable|string',
        ]);

        $suggestions = $this->linkEngine->suggest(
            (int) $data['site_id'],
            $data['title'],
            $data['keyword'],
            $data['content_type'] ?? 'article',
            $data['subtype'] ?? 'article',
        );

        return response()->json(['suggestions' => $suggestions]);
    }

    /**
     *
     * Schema.org preview.
     *
     * POST /api/content/schema
     */
    public function schema(Request $request, CurrentOrganization $org): JsonResponse
    {
        $data = $request->validate([
            'content_type' => 'required|string',
            'subtype' => 'nullable|string',
            'html' => 'required|string',
            'title' => 'required|string|max:200',
            'url' => 'nullable|string|max:500',
            'site_name' => 'nullable|string|max:200',
            'description' => 'nullable|string|max:500',
            'site_id' => 'nullable|integer',
        ]);

        $profiled = [
            'content_type' => $data['content_type'],
            'subtype' => $data['subtype'] ?? 'article',
            'intent' => 'informational',
        ];

        $standard = $this->standards->standardFor($profiled, $data['site_id'] ?? null);

        $schemas = $this->schemaGen->generate(
            $profiled,
            $data['html'],
            $data['title'],
            $data['url'] ?? '',
            $data['site_name'] ?? '',
            $data['description'] ?? '',
            $standard,
        );

        return response()->json(['schemas' => $schemas]);
    }

    /**
     * Generate content with AI (with failover).
     *
     * POST /api/content/generate
     */
    public function generate(Request $request, CurrentOrganization $org): JsonResponse
    {
        $data = $request->validate([
            'site_id' => 'required|integer',
            'keyword' => 'required|string|max:200',
            'title' => 'nullable|string|max:200',
            'subtype' => 'nullable|string',
            'meta_title' => 'nullable|string|max:100',
            'meta_description' => 'nullable|string|max:300',
        ]);

        $site = Site::query()->where('organization_id', $org->id())->findOrFail($data['site_id']);

        $profiled = $this->profiler->profile([
            'title' => $data['title'] ?? $data['keyword'],
            'target_query' => $data['keyword'],
            'content_type' => 'article',
            'subtype' => $data['subtype'] ?? '',
        ]);

        $standard = $this->standards->standardFor($profiled, (int) $site->id);

        $links = $this->linkEngine->suggest(
            (int) $site->id,
            $data['title'] ?? $data['keyword'],
            $data['keyword'],
            'article',
            $profiled['subtype'],
        );

        // Resolve guardrails for this site/subtype
        $guardrail = ContentGuardrail::resolve(
            (int) $site->organization_id,
            (int) $site->id,
            'article',
            $profiled['subtype'] ?? 'general',
        );

        $context = [
            'title' => $data['title'] ?? $data['keyword'],
            'target_query' => $data['keyword'],
            'site_name' => (string) $site->name,
            'url' => '',
            'standard' => $standard,
            'metrics' => ['clicks' => 0, 'impressions' => 0, 'ctr' => 0, 'position' => 0],
            'freshness' => null,
            'page_status' => 'publish',
            'internal_links' => $links,
            'guardrails' => $guardrail->toPromptArray(),
        ];

        try {
            $result = $this->gateway->generateArticleDraft($site->organization, $context);

            $metaTitle = $data['meta_title'] ?? ($data['keyword'] . ' | ' . $site->name);
            $metaDesc = $data['meta_description'] ?? '';

            $schemas = $this->schemaGen->generate(
                $profiled,
                $result['content'],
                $data['title'] ?? $data['keyword'],
                '',
                (string) $site->name,
                $metaDesc,
                $standard,
            );

            $evaluation = $this->qualityGuard->evaluate($profiled, [
                'title' => $data['title'] ?? $data['keyword'],
                'body' => $result['content'],
                'keyword' => $data['keyword'],
                'headings' => $this->extractHeadings($result['content']),
                'meta_title' => $metaTitle,
                'meta_description' => $metaDesc,
            ], (int) $site->id, $this->standards);


            // Auto-save draft
            ContentDraft::create([
                'site_id' => (int) $site->id,
                'title' => $data['title'] ?? $data['keyword'],
                'slug' => str()->slug($data['title'] ?? $data['keyword']),
                'content' => $result['content'],
                'meta_title' => $metaTitle,
                'meta_description' => $metaDesc,
                'schemas' => $schemas,
                'quality_score' => $evaluation['score'] ?? 0,
                'subtype' => $profiled['subtype'] ?? 'article',
                'model_used' => $result['model'],
                'status' => 'draft',
            ]);

            return response()->json([
                'content' => $result['content'],
                'model' => $result['model'],
                'source' => $result['source'],
                'meta_title' => $metaTitle,
                'meta_description' => $metaDesc,
                'schemas' => $schemas,
                'links' => $links,
                'quality' => $evaluation,
                'standard' => $standard,
                'profile' => $profiled,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'تولید محتوا ناموفق بود: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Get AI provider status.
     *
     * GET /api/content/providers
     */
    public function providers(): JsonResponse
    {
        $this->authorizeSuperAdmin();
        return response()->json(['providers' => $this->gateway->getProviderStatus()]);
    }

    /**
     * Test AI provider connection.
     *
     * POST /api/content/test-provider
     */
    public function testProvider(Request $request): JsonResponse
    {
        $this->authorizeSuperAdmin();
        $data = $request->validate([
            'provider' => 'required|string',
            'api_key' => 'nullable|string',
            'model' => 'nullable|string',
        ]);

        $result = $this->gateway->testConnection(
            $data['provider'],
            $data['api_key'] ?? '',
            $data['model'] ?? '',
        );

        return response()->json($result);
    }

    private function extractHeadings(string $html): array
    {
        $headings = [];
        preg_match_all('/<h([1-6])[^>]*>(.*?)<\/h[1-6]>/is', $html, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $headings[] = strip_tags($match[2]);
        }
        return $headings;
    }
}
