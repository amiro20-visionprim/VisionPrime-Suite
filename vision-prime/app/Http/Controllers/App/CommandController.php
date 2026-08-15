<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Domains\Organization\Contracts\CurrentOrganization;
use App\Domains\Reporting\Actions\BuildPublishImpactReport;
use App\Domains\Workspace\Models\Site;
use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class CommandController extends Controller
{
    public function index(CurrentOrganization $org): Response
    {
        $siteIds = Site::query()->where('organization_id', $org->id())->pluck('id');
        $commands = \DB::table('commands')->whereIn('site_id', $siteIds)->latest('id')->paginate(50);

        $sites = \DB::table('sites')->whereIn('id', $siteIds)->get()->keyBy('id');
        $connections = \DB::table('site_connections')->whereIn('site_id', $siteIds)->get()->keyBy('site_id');

        // غنی‌سازی هر کامند با جزئیات auto_publish: نام سایت، آدرس وردپرس، snapshot گیت‌ها،
        // confidence factors و لینک پست منتشرشده (برای publish_new_article).
        $commands->getCollection()->transform(function (object $command) use ($sites, $connections): array {
            $site = $sites->get($command->site_id);
            $connection = $connections->get($command->site_id);
            $platformUrl = rtrim((string) ($connection->platform_url ?? ''), '/');

            $approval = \DB::table('command_approvals')
                ->where('command_id', $command->id)
                ->where('reviewer_type', 'system')
                ->orderByDesc('id')
                ->first();
            $postId = $this->publishedPostId((int) $command->id, (string) ($command->status ?? ''));
            $impact = $command->type === 'publish_new_article'
                ? app(BuildPublishImpactReport::class)->handle($command)
                : null;

            return array_merge((array) $command, [
                'site_name' => $site?->name,
                'platform_url' => $platformUrl,
                'content_type' => $command->content_type ?? null,
                'confidence_factors' => $this->jsonField($command->confidence_factors),
                'decision_source' => $command->decision_source ?? null,
                'published_at' => $command->published_at ?? null,
                'gate_snapshot' => $approval !== null ? $this->jsonField($approval->policy_snapshot) : null,
                'auto_approved' => $approval !== null,
                'post_id' => $postId,
                'post_url' => $postId !== null && $platformUrl !== '' ? $platformUrl.'/?p='.$postId : null,
                'impact' => $impact,
            ]);
        });

        return Inertia::render('App/Commands/Index', ['commands' => $commands]);
    }

    /**
     * شناسهٔ پست منتشرشدهٔ وردپرس برای کامندهای publish_new_article:
     * از snapshot بازگشت (post:<id>) یا از پاسخ آخرین اجرای موفق (result.post_id).
     */
    private function publishedPostId(int $commandId, string $status): ?int
    {
        if ($status !== 'executed' && $status !== 'rolled_back') {
            return null;
        }

        $snapshot = \DB::table('rollback_snapshots')
            ->where('command_id', $commandId)
            ->orderByDesc('id')
            ->first();
        if ($snapshot !== null && str_starts_with((string) $snapshot->target_ref, 'post:')) {
            return (int) substr((string) $snapshot->target_ref, 5);
        }

        $log = \DB::table('command_execution_logs')
            ->where('command_id', $commandId)
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

    public function show(int $command, CurrentOrganization $org): Response
    {
        $siteIds = Site::query()->where('organization_id', $org->id())->pluck('id');
        $item = \DB::table('commands')->whereIn('site_id', $siteIds)->where('id', $command)->firstOrFail();
        $approvals = \DB::table('command_approvals')->where('command_id', $item->id)->get();
        $logs = \DB::table('command_execution_logs')->where('command_id', $item->id)->get();
        $snapshots = \DB::table('rollback_snapshots')->where('command_id', $item->id)->get(['id', 'target_ref', 'status', 'expires_at']);
        $impact = $item->type === 'publish_new_article'
            ? app(BuildPublishImpactReport::class)->handle($item)
            : null;

        return Inertia::render('App/Commands/Show', ['command' => $item, 'approvals' => $approvals, 'logs' => $logs, 'snapshots' => $snapshots, 'impact' => $impact]);
    }
}
