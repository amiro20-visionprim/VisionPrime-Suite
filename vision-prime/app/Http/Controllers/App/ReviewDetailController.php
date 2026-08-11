<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Domains\Organization\Contracts\CurrentOrganization;
use App\Domains\Workspace\Models\Site;
use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class ReviewDetailController extends Controller
{
    public function show(int $review, CurrentOrganization $org): Response
    {
        $siteIds = Site::query()->where('organization_id', $org->id())->pluck('id');
        $item = \DB::table('review_items')->whereIn('site_id', $siteIds)->where('id', $review)->firstOrFail();
        $decisions = \DB::table('review_decisions')->where('review_item_id', $item->id)->get();

        return Inertia::render('App/Reviews/Show', [
            'item' => $item,
            'decisions' => $decisions,
            'subject' => $this->subject($item),
        ]);
    }

    private function subject(object $item): array|null
    {
        return match ($item->subject_type) {
            'money_page_audit' => $this->moneyPageAudit((int) $item->subject_id),
            'ai_generation' => $this->aiGeneration((int) $item->subject_id),
            'command' => $this->command((int) $item->subject_id),
            'url_profile' => $this->urlProfile((int) $item->subject_id),
            default => null,
        };
    }

    private function moneyPageAudit(int $id): array
    {
        $audit = \DB::table('money_page_audits')->where('id', $id)->first();
        if ($audit === null) {
            return ['kind' => 'money_page_audit', 'audit' => null, 'issues' => [], 'url' => null];
        }
        $issues = \DB::table('money_page_issues')->where('money_page_audit_id', $audit->id)->get();
        $profile = \DB::table('url_profiles')->where('id', $audit->url_profile_id)->first();

        return [
            'kind' => 'money_page_audit',
            'audit' => ['id' => $audit->id, 'score' => $audit->score, 'summary' => json_decode($audit->summary ?? '{}', true), 'auditedAt' => $audit->audited_at],
            'issues' => $issues->map(fn ($i) => ['key' => $i->key, 'severity' => $i->severity, 'explanation' => $i->explanation])->values(),
            'url' => $profile?->canonical_url,
            'urlProfileId' => $profile?->id,
            'drafts' => $profile === null ? [] : $this->profileDrafts((int) $profile->id, (string) $profile->canonical_url),
        ];
    }

    private function profileDrafts(int $urlProfileId, string $canonicalUrl): array
    {
        $siteId = \DB::table('url_profiles')->where('id', $urlProfileId)->value('site_id');

        return \DB::table('ai_generations')
            ->join('ai_generation_versions', 'ai_generation_versions.id', '=', 'ai_generations.current_version_id')
            ->where('ai_generations.site_id', $siteId)
            ->orderByDesc('ai_generations.id')
            ->limit(50)
            ->get([
                'ai_generations.id',
                'ai_generations.input_redacted',
                'ai_generations.output_status',
                'ai_generations.created_at',
                'ai_generation_versions.output',
            ])
            ->filter(fn (object $row): bool => (($json = json_decode($row->input_redacted, true)) !== null) && ($json['url'] ?? null) === $canonicalUrl)
            ->map(function (object $row): array {
                $output = json_decode($row->output, true) ?? [];

                return [
                    'id' => (int) $row->id,
                    'kind' => $output['kind'] ?? '',
                    'text' => (string) ($output['text'] ?? ''),
                    'model' => (string) ($output['model'] ?? ''),
                    'source' => (string) ($output['source'] ?? ''),
                    'status' => $row->output_status,
                    'createdAt' => $row->created_at,
                ];
            })
            ->values()
            ->all();
    }

    private function aiGeneration(int $id): array
    {
        $generation = \DB::table('ai_generations')->where('id', $id)->first();
        if ($generation === null) {
            return ['kind' => 'ai_generation', 'generation' => null];
        }

        $version = \DB::table('ai_generation_versions')->where('id', $generation->current_version_id)->first();
        $output = $version === null ? [] : (json_decode($version->output, true) ?? []);

        return [
            'kind' => 'ai_generation',
            'generation' => [
                'id' => $generation->id,
                'input' => $generation->input_redacted,
                'text' => (string) ($output['text'] ?? ''),
                'model' => (string) ($output['model'] ?? ''),
                'source' => (string) ($output['source'] ?? ''),
                'status' => $generation->output_status,
                'usage' => json_decode($generation->usage ?? '{}', true),
                'createdAt' => $generation->created_at,
            ],
        ];
    }

    private function command(int $id): array
    {
        $command = \DB::table('commands')->where('id', $id)->first();

        return ['kind' => 'command', 'command' => $command === null ? null : ['id' => $command->id, 'type' => $command->type, 'riskTier' => $command->risk_tier, 'payload' => json_decode($command->payload ?? '{}', true), 'status' => $command->status, 'expiresAt' => $command->expires_at]];
    }

    private function urlProfile(int $id): array
    {
        $profile = \DB::table('url_profiles')->where('id', $id)->first();

        return ['kind' => 'url_profile', 'profile' => $profile === null ? null : ['canonicalUrl' => $profile->canonical_url, 'slug' => $profile->slug, 'contentType' => $profile->content_type]];
    }
}
