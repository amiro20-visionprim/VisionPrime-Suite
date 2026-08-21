<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Domains\Ai\Services\AiGateway;
use App\Domains\Content\Services\ContentProfiler;
use App\Domains\Content\Services\ContentQualityGuard;
use App\Domains\Content\Services\InternalLinkEngine;
use App\Domains\Content\Services\SchemaGenerator;
use App\Domains\Content\Models\ContentGuardrail;
use App\Domains\Content\Models\PromptTemplate;
use App\Domains\Content\Models\ContentDraft;
use App\Domains\Content\Services\StandardsKB;
use App\Domains\Content\Services\SERPAnalyzer;
use App\Domains\Content\Services\SEOExpertAnalyzer;
use App\Domains\Content\Services\WordPressPublisher;
use App\Domain\Content\Services\ImageSuggestionService;
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

        // Load template if specified
        $template = null;
        if (!empty($data['template_id'])) {
            $template = PromptTemplate::find((int) $data['template_id']);
        }

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
            'metrics' => $this->fetchGscMetrics($site->id, $data['keyword'] ?? ''),
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
                'slug' => $this->validateSlug(str()->slug($data['title'] ?? $data['keyword']), $data['keyword'] ?? ''),
                'content' => $result['content'],
                'meta_title' => $metaTitle,
                'meta_description' => $metaDesc,
                'schemas' => $schemas,
                'quality_score' => $evaluation['score'] ?? 0,
                'subtype' => $profiled['subtype'] ?? 'article',
                'model_used' => $result['model'],
                'status' => 'draft',
                'audit_log' => $evaluation['audit_log'] ?? [],
            ]);

            // Auto expert analysis
            try {
                $analyzer = app(SEOExpertAnalyzer::class);
                $expertResult = $analyzer->analyze([
                    'body' => $result['content'],
                    'title' => $data['title'] ?? $data['keyword'],
                    'keyword' => $data['keyword'],
                    'audit_log' => $evaluation['audit_log'] ?? [],
                ]);
                ContentDraft::where('title', $data['title'] ?? $data['keyword'])
                    ->latest()->first()?->update(['expert_analysis' => $expertResult]);
                $evaluation['expert_analysis'] = $expertResult;
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Expert analysis failed');
            }

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
     * Check for duplicate content before generation.
     *
     * POST /api/content/check-duplicate
     */
    public function checkDuplicate(Request $request, CurrentOrganization $org): JsonResponse
    {
        $data = $request->validate([
            'title' => 'required|string|max:500',
            'site_id' => 'required|integer',
        ]);

        $title = $data['title'];
        $siteId = (int) $data['site_id'];

        // Search for similar titles in content_drafts
        $normalizedName = ContentProfiler::normalizeFa($title);
        $words = array_filter(explode(' ', $normalizedName), fn(string $w): bool => mb_strlen($w) > 2);

        $existingDrafts = ContentDraft::query()
            ->where('site_id', $siteId)
            ->where(function ($q) use ($words, $title) {
                // Exact match
                $q->where('title', $title);
                // Or similar (any word match)
                foreach ($words as $word) {
                    $q->orWhere('title', 'LIKE', '%' . $word . '%');
                }
            })
            ->orderByDesc('created_at')
            ->limit(5)
            ->get(['id', 'title', 'status', 'quality_score', 'created_at']);

        $similar = [];
        foreach ($existingDrafts as $draft) {
            $draftNormalized = ContentProfiler::normalizeFa($draft->title);
            $overlap = count(array_intersect($words, array_filter(explode(' ', $draftNormalized))));
            $similarity = count($words) > 0 ? round($overlap / count($words) * 100) : 0;

            if ($similarity >= 40) {
                $similar[] = [
                    'id' => $draft->id,
                    'title' => $draft->title,
                    'status' => $draft->status,
                    'quality_score' => $draft->quality_score,
                    'similarity' => $similarity,
                    'created_at' => $draft->created_at,
                ];
            }
        }

        usort($similar, fn(array $a, array $b): int => $b['similarity'] <=> $a['similarity']);

        return response()->json([
            'has_duplicate' => count($similar) > 0,
            'similar_count' => count($similar),
            'similar_drafts' => $similar,
        ]);
    }

    /**
     * Regenerate a specific section of content.
     *
     * POST /api/content/regenerate-section
     */
    public function regenerateSection(Request $request, CurrentOrganization $org): JsonResponse
    {
        $this->authorizeSuperAdmin();

        $data = $request->validate([
            'heading' => 'required|string|max:200',
            'context' => 'required|string',
            'full_content' => 'required|string',
            'keyword' => 'nullable|string|max:100',
            'instruction' => 'nullable|string|max:500',
        ]);

        $system = 'تو یک متخصص سئو و تولید محتوای فارسی هستی. فقط بخش درخواستی را بازنویسی کن.
'
            . 'خروجی فقط HTML معتبر آن بخش باشد (بدون h1 اولیه).
'
            . 'ساختار و لحن بقیه محتوا را حفظ کن.';

        $instruction = $data['instruction'] ?? '';
        $user = "بخش «{$data['heading']}» را بازنویسی کن.

"
            . "موضوع کلی مقاله: {$data['context']}
"
            . ($data['keyword'] ? "کلمه کلیدی: {$data['keyword']}
" : '')
            . ($instruction !== '' ? "دستور ویژه: {$instruction}
" : '')
            . "
فقط خروجی HTML این بخش را برگردان:";

        try {
            $result = $this->gateway->generate($system, $user, 'section');
            return response()->json([
                'content' => $result['content'],
                'model' => $result['model'],
                'source' => $result['source'],
            ]);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'بازنویسی بخش ناموفق بود: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Get GSC context for a title — find related queries with real metrics.
     *
     * GET /api/content/gsc-context?site_id=1&title=...
     */
    public function gscContext(Request $request, CurrentOrganization $org): JsonResponse
    {
        $siteId = (int) $request->query('site_id', 0);
        $title = (string) $request->query('title', '');

        if ($siteId === 0 || $title === '') {
            return response()->json(['error' => 'site_id و title الزامی است'], 422);
        }

        $site = Site::query()->where('organization_id', $org->id())->findOrFail($siteId);

        // Search keyword_insights for queries matching the title
        $keywords = ['clicks', 'impressions', 'ctr', 'position'];
        $insights = DB::table('keyword_insights')
            ->where('site_id', $siteId)
            ->where('status', 'active')
            ->where(function ($q) use ($title) {
                // Split title into words and match any
                $words = array_filter(explode(' ', trim($title)), fn($w) => mb_strlen($w) > 2);
                foreach ($words as $word) {
                    $q->orWhere('query_normalized', 'LIKE', '%' . $word . '%');
                }
            })
            ->orderByDesc('latest_metrics->clicks')
            ->limit(20)
            ->get();

        $queries = [];
        $totalClicks = 0;
        $totalImpressions = 0;

        foreach ($insights as $insight) {
            $metrics = json_decode($insight->latest_metrics, true) ?? [];
            $clicks = (int) ($metrics['clicks'] ?? 0);
            $impressions = (int) ($metrics['impressions'] ?? 0);
            $ctr = (float) ($metrics['ctr'] ?? 0);
            $position = (float) ($metrics['position'] ?? 0);

            // Calculate CTR gap: what CTR should be based on position
            $expectedCtr = ($position <= 3) ? 0.30 : (($position <= 10) ? 0.10 : 0.03);
            $ctrGap = max(0, $expectedCtr - $ctr);

            $queries[] = [
                'query' => $insight->query_normalized,
                'clicks' => $clicks,
                'impressions' => $impressions,
                'ctr' => round($ctr * 100, 2),
                'position' => round($position, 1),
                'ctr_gap' => round($ctrGap * 100, 2),
                'opportunity_score' => (int) ($impressions * $ctrGap),
            ];

            $totalClicks += $clicks;
            $totalImpressions += $impressions;
        }

        // Sort by opportunity score
        usort($queries, fn($a, $b) => $b['opportunity_score'] <=> $a['opportunity_score']);

        return response()->json([
            'queries' => $queries,
            'summary' => [
                'total_queries' => count($queries),
                'total_clicks' => $totalClicks,
                'total_impressions' => $totalImpressions,
                'avg_ctr' => $totalImpressions > 0 ? round($totalClicks / $totalImpressions * 100, 2) : 0,
                'has_data' => count($queries) > 0,
            ],
            'site' => ['id' => $site->id, 'name' => $site->name],
        ]);
    }

    /**
     * Get AI provider status.
     *
     * GET /api/content/providers
     */

    /**
     * Generate outline for article.
     *
     * POST /api/content/outline
     */
    public function outline(Request $request, CurrentOrganization $org): JsonResponse
    {
        $this->authorizeSuperAdmin();

        $data = $request->validate([
            'site_id' => 'required|integer|exists:sites,id',
            'title'   => 'required|string|max:500',
            'subtype' => 'nullable|string|max:100',
            'template_id' => 'nullable|integer|exists:prompt_templates,id',
        ]);

        $siteId = (int) $data['site_id'];
        $title = $data['title'];
        $subtype = $data['subtype'] ?? 'how_to_guide';
        $site = Site::query()->where('organization_id', $org->id())->findOrFail($siteId);

        // Fetch GSC data for context
        $gscData = $this->fetchGscMetrics($siteId, $title);

        // Load prompt template if selected
        $template = null;
        if (!empty($data["template_id"])) {
            $template = PromptTemplate::find((int) $data["template_id"]);
        }

        // Get guardrails for context
        $guardrails = ContentGuardrail::resolve($org->id(), $siteId, 'article', $subtype);
        $guardrailConfig = $guardrails->toArray();

        // Generate outline via AI
        if ($template) {
            $system = $template->system_prompt;
            $user = $template->render($title) . "
" . "Subtype: " . $subtype . ". Site: " . $site->name;
            if ($gscData) {
                $user .= "
" . "GSC Context: " . json_encode($gscData, JSON_UNESCAPED_UNICODE);
            }
        } else {
            [$system, $user] = $this->gateway->generateOutline($title, $subtype, $site->name, $gscData);
        }

        // Use the AI gateway to generate
        $result = $this->gateway->generate($system, $user, 'outline');

        // Parse JSON from response (handle markdown fences, extra text, etc.)
        $content = $result['content'] ?? '[]';
        $outline = [];

        // Step 1: Try direct decode
        $trimmed = trim($content);
        $outline = json_decode($trimmed, true);

        // Step 2: Strip markdown code fences
        if (!is_array($outline)) {
            $cleaned = preg_replace('/^```(?:json)?\s*/im', '', $trimmed);
            $cleaned = preg_replace('/```\s*$/m', '', $cleaned);
            $outline = json_decode(trim($cleaned), true);
        }

        // Step 3: Extract JSON array from mixed text
        if (!is_array($outline)) {
            if (preg_match('/\[{.*?}\]/s', $content, $matches)) {
                $outline = json_decode($matches[0], true);
            }
        }

        // Step 4: Try non-greedy match for array
        if (!is_array($outline)) {
            if (preg_match('/\[.*\]/s', $content, $matches)) {
                $outline = json_decode($matches[0], true);
            }
        }

        if (!is_array($outline)) {
            $outline = [];
            \Illuminate\Support\Facades\Log::warning('Outline parsing failed', [
                'raw_content' => mb_substr($content, 0, 500),
                'model' => $result['model'] ?? 'unknown',
                'source' => $result['source'] ?? 'unknown',
            ]);
        }

        // Validate and normalize each item
        $normalized = [];
        foreach ($outline as $item) {
            if (!is_array($item) || empty($item['heading'])) {
                continue;
            }
            $normalized[] = [
                'heading' => (string) $item['heading'],
                'level' => in_array(($item['level'] ?? 2), [2, 3]) ? (int) $item['level'] : 2,
                'note' => (string) ($item['note'] ?? ''),
            ];
        }

        return response()->json([
            'outline' => $normalized,
            'model' => $result['model'] ?? 'unknown',
            'source' => $result['source'] ?? 'unknown',
            'gsc_queries_count' => count($gscData['related_queries'] ?? []),
            'guardrails_applied' => !empty($guardrailConfig),
        ]);
    }


    /**
     * SERP Intelligence — analyze competitor content for a keyword.
     *
     * POST /api/content/serp-analysis
     */
    public function serpAnalysis(Request $request, CurrentOrganization $org): JsonResponse
    {
        $this->authorizeSuperAdmin();

        $data = $request->validate([
            'keyword'  => 'required|string|max:500',
            'subtype'  => 'nullable|string|max:100',
            'outline'  => 'nullable|array',
        ]);

        $keyword = $data['keyword'];
        $subtype = $data['subtype'] ?? 'how_to_guide';
        $outline = $data['outline'] ?? [];

        $analysis = app(\App\Domains\Content\Services\SERPAnalyzer::class)->analyze($keyword, $subtype, $outline);

        return response()->json($analysis);
    }

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

    /**
     * Validate and sanitize slug.
     * Rules: max 75 chars, lowercase, contains keyword, no special chars.
     */
    private function validateSlug(string $slug, string $keyword = ''): string
    {
        // Transliterate Persian/Arabic to ASCII-safe
        $slug = mb_strtolower($slug, 'UTF-8');
        // Remove special chars, keep alphanumeric and hyphens
        $slug = preg_replace('/[^a-z0-9\s-]/u', '', $slug);
        // Replace spaces with hyphens
        $slug = preg_replace('/[\s]+/', '-', $slug);
        // Remove consecutive hyphens
        $slug = preg_replace('/-+/', '-', $slug);
        // Trim hyphens from start/end
        $slug = trim($slug, '-');
        // Enforce max length
        if (mb_strlen($slug, 'UTF-8') > 75) {
            $slug = mb_substr($slug, 0, 75, 'UTF-8');
            $slug = rtrim($slug, '-');
        }
        // Ensure slug is not empty
        if ($slug === '') {
            $slug = str()->slug($keyword ?: 'article');
        }
        return $slug;
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

    /**
     * Fetch real GSC metrics for a keyword from keyword_insights.
     */
    private function fetchGscMetrics(int $siteId, string $keyword): array
    {
        if ($keyword === '') {
            return ['clicks' => 0, 'impressions' => 0, 'ctr' => 0, 'position' => 0, 'related_queries' => []];
        }

        $insights = DB::table('keyword_insights')
            ->where('site_id', $siteId)
            ->where('status', 'active')
            ->where(function ($q) use ($keyword) {
                $words = array_filter(explode(' ', trim($keyword)), fn($w) => mb_strlen($w) > 2);
                foreach ($words as $word) {
                    $q->orWhere('query_normalized', 'LIKE', '%' . $word . '%');
                }
            })
            ->orderByDesc('latest_metrics->clicks')
            ->limit(10)
            ->get();

        $totalClicks = 0;
        $totalImpressions = 0;
        $relatedQueries = [];

        foreach ($insights as $insight) {
            $m = json_decode($insight->latest_metrics, true) ?? [];
            $clicks = (int) ($m['clicks'] ?? 0);
            $impressions = (int) ($m['impressions'] ?? 0);
            $totalClicks += $clicks;
            $totalImpressions += $impressions;
            $relatedQueries[] = [
                'query' => $insight->query_normalized,
                'clicks' => $clicks,
                'impressions' => $impressions,
                'ctr' => round((float) ($m['ctr'] ?? 0) * 100, 2),
                'position' => round((float) ($m['position'] ?? 0), 1),
            ];
        }

        return [
            'clicks' => $totalClicks,
            'impressions' => $totalImpressions,
            'ctr' => $totalImpressions > 0 ? round($totalClicks / $totalImpressions * 100, 2) : 0,
            'position' => count($relatedQueries) > 0 ? round(array_sum(array_column($relatedQueries, 'position')) / count($relatedQueries), 1) : 0,
            'related_queries' => $relatedQueries,
        ];
    }


    public function applySuggestions(Request $request, CurrentOrganization $org): JsonResponse
    {
        $this->authorizeSuperAdmin();
        $data = $request->validate([
            'content' => 'required|string',
            'suggestions' => 'required|array',
            'title' => 'required|string|max:200',
            'keyword' => 'required|string|max:200',
        ]);

        $system = "تو یک متخصص سئو و ویرایش محتوا هستی. محتوا را بر اساس پیشنهادات بهبود بده. فقط HTML بهبود یافته را برگردان.";
        $user = "پیشنهادات:\n";
        foreach ($data['suggestions'] as $s) {
            $user .= "- {$s}\n";
        }
        $user .= "\nمحتوای فعلی:\n{$data['content']}\n\nفقط HTML بهبود یافته را برگردان:";

        try {
            $result = $this->gateway->generate($system, $user, 'apply_suggestions');
            return response()->json(['content' => $result['content'], 'model' => $result['model']]);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'اعمال پیشنهادات ناموفق: ' . $e->getMessage()], 500);
        }
    }

    public function saveUserTemplate(Request $request): JsonResponse
    {
        $this->authorizeSuperAdmin();
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'system_prompt' => 'required|string',
            'user_prompt_template' => 'required|string',
            'tone' => 'nullable|string|max:50',
            'content_type' => 'nullable|string|max:50',
        ]);

        $template = PromptTemplate::create([
            'title' => $data['title'],
            'slug' => \Str::slug($data['title']),
            'content_type' => $data['content_type'] ?? 'article',
            'tone' => $data['tone'] ?? 'informative',
            'system_prompt' => $data['system_prompt'],
            'user_prompt_template' => $data['user_prompt_template'],
            'is_user_created' => true,
            'created_by_user_id' => $request->user()->id,
            'is_active' => true,
            'is_featured' => false,
        ]);

        return response()->json($template, 201);
    }

    /**
     * Publish article to WordPress.
     * POST /api/content/publish
     */
    public function publishToWordPress(Request $request, CurrentOrganization $org): JsonResponse
    {
        $this->authorizeSuperAdmin();
        $data = $request->validate([
            'draft_id' => 'required|integer|exists:content_drafts,id',
            'status' => 'nullable|string|in:publish,draft,pending',
            'wp_url' => 'required|string|max:500',
            'wp_username' => 'required|string|max:200',
            'wp_app_password' => 'required|string|max:200',
        ]);

        $draft = ContentDraft::findOrFail($data['draft_id']);
        $publisher = app(WordPressPublisher::class);

        $result = $publisher->publish(
            [
                'wp_url' => $data['wp_url'],
                'wp_username' => $data['wp_username'],
                'wp_app_password' => $data['wp_app_password'],
            ],
            [
                'title' => $draft->title,
                'content' => $draft->content,
                'meta_title' => $draft->meta_title,
                'meta_description' => $draft->meta_description,
                'slug' => $draft->slug,
                'status' => $data['status'] ?? 'draft',
            ]
        );

        if ($result['success']) {
            $draft->update([
                'status' => 'published',
                'audit_log' => array_merge($draft->audit_log ?? [], [
                    'wp_post_id' => $result['post_id'],
                    'wp_post_url' => $result['post_url'],
                    'published_at' => now()->toISOString(),
                ]),
            ]);
        }

        return response()->json($result);
    }

    /**
     * Test WordPress connection.
     * POST /api/content/test-wp
     */
    public function testWordPress(Request $request): JsonResponse
    {
        $this->authorizeSuperAdmin();
        $data = $request->validate([
            'wp_url' => 'required|string|max:500',
            'wp_username' => 'required|string|max:200',
            'wp_app_password' => 'required|string|max:200',
        ]);

        $publisher = app(WordPressPublisher::class);
        return response()->json($publisher->testConnection(
            $data['wp_url'], $data['wp_username'], $data['wp_app_password']
        ));
    }

    /**
     * List content drafts with filters.
     * GET /api/content/drafts
     */
    public function listDrafts(Request $request, CurrentOrganization $org): JsonResponse
    {
        $query = ContentDraft::query()
            ->whereHas('site', fn($q) => $q->where('organization_id', $org->id()));

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        if ($siteId = $request->query('site_id')) {
            $query->where('site_id', (int) $siteId);
        }
        if ($search = $request->query('search')) {
            $query->where('title', 'LIKE', '%' . $search . '%');
        }

        $drafts = $query->orderByDesc('created_at')
            ->limit(50)
            ->get(['id', 'title', 'slug', 'status', 'quality_score', 'model_used', 'subtype', 'created_at', 'updated_at']);

        return response()->json(['drafts' => $drafts]);
    }

    /**
     * Get single draft with full content.
     * GET /api/content/drafts/{id}
     */
    public function getDraft(int $id, CurrentOrganization $org): JsonResponse
    {
        $draft = ContentDraft::whereHas('site', fn($q) => $q->where('organization_id', $org->id()))
            ->findOrFail($id);

        return response()->json($draft);
    }

    /**
     * Delete a draft.
     * DELETE /api/content/drafts/{id}
     */
    public function deleteDraft(int $id, CurrentOrganization $org): JsonResponse
    {
        $this->authorizeSuperAdmin();
        ContentDraft::whereHas('site', fn($q) => $q->where('organization_id', $org->id()))
            ->findOrFail($id)
            ->delete();

        return response()->json(['ok' => true]);
    }
}
