<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Domains\Content\Models\ContentGuardrail;
use App\Domains\Organization\Contracts\CurrentOrganization;
use App\Domains\Workspace\Models\Site;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * API for the Content Command Center — manages guardrails, prompts,
 * and customization per site/subtype.
 */
class ContentGuardrailController extends Controller
{
    /**
     * List all guardrails for the current organization.
     *
     * GET /api/content/guardrails?site_id=1
     */
    public function index(Request $request, CurrentOrganization $org): JsonResponse
    {
        $siteId = $request->query('site_id') ? (int) $request->query('site_id') : null;

        $query = ContentGuardrail::where('organization_id', $org->id());

        if ($siteId !== null) {
            $query->where(function ($q) use ($siteId) {
                $q->where('site_id', $siteId)
                    ->orWhereNull('site_id');
            });
        }

        $guardrails = $query->orderBy('content_type')
            ->orderBy('subtype')
            ->orderByDesc('site_id') // site-specific first
            ->get();

        return response()->json(['guardrails' => $guardrails]);
    }

    /**
     * Resolve effective guardrails for a specific site/content_type/subtype.
     *
     * GET /api/content/guardrails/resolve?site_id=1&content_type=article&subtype=how_to
     */
    public function resolve(Request $request, CurrentOrganization $org): JsonResponse
    {
        $data = $request->validate([
            'site_id' => 'required|integer',
            'content_type' => 'required|string|in:article,product',
            'subtype' => 'required|string|max:50',
        ]);

        $guardrail = ContentGuardrail::resolve(
            (int) $org->id(),
            (int) $data['site_id'],
            $data['content_type'],
            $data['subtype'],
        );

        return response()->json([
            'guardrail' => $guardrail,
            'is_default' => $guardrail->id === null,
        ]);
    }

    /**
     * Create or update a guardrail.
     *
     * POST /api/content/guardrails
     */
    public function store(Request $request, CurrentOrganization $org): JsonResponse
    {
        $data = $request->validate([
            'site_id' => 'nullable|integer|exists:sites,id',
            'content_type' => 'required|string|in:article,product',
            'subtype' => 'required|string|max:50',
            'max_characters' => 'nullable|integer|min:500|max:50000',
            'min_words' => 'nullable|integer|min:50|max:10000',
            'max_words' => 'nullable|integer|min:100|max:50000',
            'allowed_tone' => 'nullable|string|max:100',
            'allowed_tags' => 'nullable|array',
            'require_cta' => 'nullable|boolean',
            'require_faq' => 'nullable|boolean',
            'require_internal_links' => 'nullable|boolean',
            'min_internal_links' => 'nullable|integer|min:0|max:20',
            'require_brand_mention' => 'nullable|boolean',
            'forbidden_words' => 'nullable|array',
            'system_prompt' => 'nullable|string|max:10000',
            'user_prompt_template' => 'nullable|string|max:10000',
            'is_active' => 'nullable|boolean',
        ]);

        $guardrail = ContentGuardrail::updateOrCreate(
            [
                'organization_id' => $org->id(),
                'site_id' => $data['site_id'] ?? null,
                'content_type' => $data['content_type'],
                'subtype' => $data['subtype'],
            ],
            array_filter($data, fn($v) => $v !== null),
        );

        return response()->json(['guardrail' => $guardrail]);
    }

    /**
     * Delete a guardrail.
     *
     * DELETE /api/content/guardrails/{guardrail}
     */
    public function destroy(ContentGuardrail $guardrail, CurrentOrganization $org): JsonResponse
    {
        if ($guardrail->organization_id !== $org->id()) {
            abort(403);
        }

        $guardrail->delete();

        return response()->json(['ok' => true]);
    }

    /**
     * Bulk create default guardrails for all subtypes of a content type.
     *
     * POST /api/content/guardrails/seed
     */
    public function seed(Request $request, CurrentOrganization $org): JsonResponse
    {
        $data = $request->validate([
            'content_type' => 'required|string|in:article,product',
            'site_id' => 'nullable|integer|exists:sites,id',
        ]);

        $subtypes = match ($data['content_type']) {
            'article' => ['general', 'how_to', 'review', 'comparison', 'list', 'news', 'guide', 'pillar'],
            'product' => ['general', 'simple', 'variable', 'grouped', 'digital'],
        };

        $created = 0;
        foreach ($subtypes as $subtype) {
            $existing = ContentGuardrail::where('organization_id', $org->id())
                ->where('site_id', $data['site_id'] ?? null)
                ->where('content_type', $data['content_type'])
                ->where('subtype', $subtype)
                ->first();

            if ($existing === null) {
                ContentGuardrail::create([
                    'organization_id' => $org->id(),
                    'site_id' => $data['site_id'] ?? null,
                    'content_type' => $data['content_type'],
                    'subtype' => $subtype,
                ]);
                $created++;
            }
        }

        return response()->json(['created' => $created, 'total_subtypes' => count($subtypes)]);
    }
}
