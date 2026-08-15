<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Domains\Connector\Actions\FetchWooProductInfo;
use App\Domains\Content\Services\ArticleHtmlSanitizer;
use App\Domains\Organization\Contracts\CurrentOrganization;
use App\Domains\Reporting\Actions\BuildPublishImpactReport;
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

    private function subject(object $item): ?array
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
        $kind = (string) ($output['kind'] ?? '');
        $rawText = (string) ($output['text'] ?? '');
        $standard = (array) ($output['standard'] ?? []);

        $sanitized = in_array($kind, ['article', 'product'], true) && $rawText !== ''
            ? app(ArticleHtmlSanitizer::class)->sanitize($rawText, $standard)
            : ['safe_html' => '', 'structure' => ['headings' => [], 'word_count' => 0, 'elements' => []]];

        $pipeline = $this->generationCommand((int) $generation->id);

        return [
            'kind' => 'ai_generation',
            'generation' => [
                'id' => $generation->id,
                'kind' => $kind,
                'input' => $generation->input_redacted,
                'text' => $rawText,
                'safe_html' => (string) $sanitized['safe_html'],
                'structure' => $sanitized['structure'],
                'standard' => $standard,
                'featured_image' => $output['featured_image'] ?? null,
                'schema' => $output['schema'] ?? [],
                'model' => (string) ($output['model'] ?? ''),
                'source' => (string) ($output['source'] ?? ''),
                'status' => $generation->output_status,
                'usage' => json_decode($generation->usage ?? '{}', true),
                'createdAt' => $generation->created_at,
                // وضعیت بلادرنگ کامند فاز ۲ (پس از تأیید پیش‌نویس)
                'command' => $pipeline,
                // دادهٔ واقعی ووکامرس (فقط برای پیش‌نویس محصول)
                'woo_product' => $kind === 'product' ? $this->wooProduct($generation, $pipeline) : null,
            ],
        ];
    }

    /**
     * قیمت/موجودی واقعی محصول از ووکامرس — از طریق کانکتور (بدون جعل داده).
     * اگر انتشار انجام شده باشد با post_id، وگرنه با اسلاگ صفحهٔ محصول (پیش از انتشار).
     * هر خطایی → null تا صفحهٔ بازبینی نشکند.
     */
    private function wooProduct(object $generation, ?array $pipeline): ?array
    {
        try {
            $site = Site::query()->find($generation->site_id);
            if ($site === null) {
                return null;
            }

            $postId = $pipeline['post_id'] ?? null;
            $slug = null;
            if ($postId === null) {
                $input = json_decode((string) ($generation->input_redacted ?? '{}'), true) ?? [];
                $url = (string) ($input['url'] ?? '');
                $slug = $this->slugFromUrl($url);
            }

            return app(FetchWooProductInfo::class)->handle(
                (int) $site->id,
                $postId !== null ? (int) $postId : null,
                $slug !== '' ? $slug : null,
            );
        } catch (\Throwable $e) {
            // بدون دادهٔ ووکامرس، بازبینی همچنان کار می‌کند
            return null;
        }
    }

    private function slugFromUrl(string $url): string
    {
        $path = trim((string) parse_url($url, PHP_URL_PATH), '/');
        $segments = array_values(array_filter(explode('/', $path), fn (string $s): bool => $s !== ''));

        return $segments !== [] ? (string) end($segments) : '';
    }

    /**
     * کامند publish_new_article ساخته‌شده از این پیش‌نویس (فاز ۲) — وضعیت
     * بلادرنگ (pending_approval / auto_publish / rolled_back) + لینک مقالهٔ منتشرشده.
     */
    private function generationCommand(int $generationId): ?array
    {
        $command = \DB::table('commands')
            ->where('source_type', 'ai_generation')
            ->where('source_id', $generationId)
            ->first();
        if ($command === null) {
            return null;
        }

        $approval = \DB::table('command_approvals')
            ->where('command_id', $command->id)
            ->where('reviewer_type', 'system')
            ->orderByDesc('id')
            ->first();
        $postId = $this->commandPostId($command);
        $connection = \DB::table('site_connections')->where('site_id', $command->site_id)->first();
        $platformUrl = rtrim((string) ($connection->platform_url ?? ''), '/');

        return [
            'id' => (int) $command->id,
            'type' => $command->type,
            'content_type' => $command->content_type ?? null,
            'status' => $command->status,
            'decision_source' => $command->decision_source ?? null,
            'confidence_score' => $command->confidence_score !== null ? (int) $command->confidence_score : null,
            'confidence_factors' => $this->jsonField($command->confidence_factors),
            'gate_snapshot' => $approval !== null ? $this->jsonField($approval->policy_snapshot) : null,
            'auto_approved' => $approval !== null,
            'published_at' => $command->published_at ?? null,
            'post_id' => $postId,
            'post_url' => $postId !== null && $platformUrl !== '' ? $platformUrl.'/?p='.$postId : null,
            'impact' => $command->type === 'publish_new_article'
                ? app(BuildPublishImpactReport::class)->handle($command)
                : null,
        ];
    }

    private function commandPostId(object $command): ?int
    {
        if (! in_array($command->status, ['executed', 'rolled_back'], true)) {
            return null;
        }
        $snapshot = \DB::table('rollback_snapshots')
            ->where('command_id', $command->id)
            ->orderByDesc('id')
            ->first();
        if ($snapshot !== null && str_starts_with((string) $snapshot->target_ref, 'post:')) {
            return (int) substr((string) $snapshot->target_ref, 5);
        }
        $log = \DB::table('command_execution_logs')
            ->where('command_id', $command->id)
            ->where('status', 'executed')
            ->orderByDesc('id')
            ->first();
        if ($log === null) {
            return null;
        }
        $response = $this->jsonField($log->response_redacted);
        $body = is_array($response['body'] ?? null) ? $response['body'] : $response;
        $result = is_array($body['result'] ?? null) ? $body['result'] : $body;

        return isset($result['post_id']) ? (int) $result['post_id'] : null;
    }

    /** @return array<string, mixed> */
    private function jsonField(?string $raw): array
    {
        if ($raw === null || $raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
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
