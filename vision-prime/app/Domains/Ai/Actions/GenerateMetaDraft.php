<?php

declare(strict_types=1);

namespace App\Domains\Ai\Actions;

use App\Domains\Ai\Services\AiClient;
use App\Domains\Audit\Actions\RecordAuditLog;
use App\Domains\Workspace\Models\Site;
use Illuminate\Support\Facades\DB;

/**
 * Generates a meta title/description draft for a page using the configured
 * AI provider (or the offline rule-based fallback), stores it as an
 * ai_generation and opens a review item so the draft enters the review
 * workflow ("بررسی و تأییدها").
 */
class GenerateMetaDraft
{
    public const KINDS = ['meta_title', 'meta_description'];

    public function __construct(
        private readonly AiClient $client,
        private readonly CreateAiGeneration $createGeneration,
        private readonly CreateReviewItem $createReviewItem,
        private readonly RecordAuditLog $audit,
    ) {}

    /** @return int The ai_generation id. */
    public function handle(Site $site, string $kind, int $urlProfileId): int
    {
        if (! in_array($kind, self::KINDS, true)) {
            throw new \InvalidArgumentException('نوع پیشنویس پشتیبانی نمی‌شود.');
        }

        $profile = DB::table('url_profiles')
            ->where('site_id', $site->id)
            ->where('id', $urlProfileId)
            ->firstOrFail();

        $context = $this->context($site, $profile);

        $result = $this->client->generateMetaDraft($site->organization, $context);

        $output = [
            'kind' => $kind,
            'text' => $result['content'],
            'model' => $result['model'],
            'source' => $result['source'],
        ];

        $generationId = $this->createGeneration->handle(
            $site,
            null,
            [
                'kind' => $kind,
                'url' => $context['url'],
                'top_query' => $context['top_query'],
                'metrics' => $context['metrics'],
            ],
            $output,
            $result['usage'],
        );

        $this->createReviewItem->handle($site, 'ai_generation', $generationId);

        $this->audit->handle(
            action: 'ai.draft_generated',
            subject: $site,
            after: ['generation_id' => $generationId, 'kind' => $kind, 'url_profile_id' => $urlProfileId, 'source' => $result['source']],
        );

        return (int) $generationId;
    }

    /**
     * @param  object  $profile  url_profiles row
     * @return array<string, mixed>
     */
    private function context(Site $site, object $profile): array
    {
        $metadata = json_decode($profile->metadata ?? '{}', true) ?? [];
        $gsc = $metadata['gsc'] ?? [];

        $snapshot = DB::table('content_snapshots')
            ->where('url_profile_id', $profile->id)
            ->latest('captured_at')
            ->value('content');

        $insight = DB::table('keyword_insights')
            ->where('mapped_url_profile_id', $profile->id)
            ->orderByDesc('id')
            ->first();

        $insightMetrics = $insight === null ? [] : (json_decode($insight->latest_metrics ?? '{}', true) ?? []);
        $topQuery = (string) ($insightMetrics['query'] ?? $insight->query_normalized ?? '');

        return [
            'kind' => 'meta_title',
            'url' => (string) $profile->canonical_url,
            'site_name' => (string) $site->name,
            'top_query' => $topQuery,
            'metrics' => [
                'clicks' => (int) ($gsc['clicks'] ?? 0),
                'impressions' => (int) ($gsc['impressions'] ?? 0),
                'ctr' => (float) ($gsc['ctr'] ?? 0),
                'position' => (float) ($gsc['position'] ?? 0),
            ],
            'existing_meta' => (string) ($metadata['meta_title'] ?? $metadata['meta_description'] ?? ''),
            'content_snippet' => (string) $snapshot,
        ];
    }
}
