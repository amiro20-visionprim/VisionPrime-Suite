<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Domains\Automation\Actions\EmergencyStop;
use App\Domains\Automation\Actions\ResumeAutomation;
use App\Domains\Workspace\Models\Site;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * داشبورد خودکارسازی سایت (D-013 2-4) — مسیر /app/sites/{site}/automation.
 */
class AutomationPolicyController extends Controller
{
    public function show(Site $site): Response
    {
        Gate::authorize('view', $site);

        $policy = $this->policy($site);

        return Inertia::render('App/Sites/Automation', [
            'site' => ['id' => $site->id, 'name' => $site->name],
            'policy' => $this->policyPayload($site, $policy),
            'profiles' => $this->profiles($site->organization_id),
            'routes' => $this->routes($site),
            'executions' => $this->executions($site),
        ]);
    }

    public function update(Request $request, Site $site): RedirectResponse
    {
        Gate::authorize('update', $site);

        $data = $request->validate([
            'active_profile_id' => ['nullable', 'integer', 'exists:automation_profiles,id'],
            'auto_publish_scope' => ['nullable', 'in:none,meta,article,product,all'],
            'overrides.automation_level' => ['nullable', 'integer', 'between:0,4'],
            'overrides.ai_policy' => ['nullable', 'in:disabled,draft_only,approved_templates,bounded_auto'],
            'overrides.confidence_threshold' => ['nullable', 'integer', 'between:50,100'],
            'overrides.high_risk_threshold' => ['nullable', 'integer', 'between:50,100'],
            'overrides.risk_tier_max' => ['nullable', 'in:R0,R1,R2,R3'],
            'overrides.enabled_content_types' => ['nullable', 'array'],
            'overrides.enabled_content_types.*' => ['in:meta,article,product'],
            'overrides.daily_command_limit' => ['nullable', 'integer', 'min:0', 'max:500'],
            'overrides.daily_mutation_limit' => ['nullable', 'integer', 'min:0', 'max:500'],
            'overrides.auto_rollback' => ['nullable', 'boolean'],
            'overrides.notification_policy' => ['nullable', 'array'],
            'overrides.notification_policy.enabled' => ['nullable', 'boolean'],
            'overrides.notification_policy.channels' => ['nullable', 'array'],
            'overrides.notification_policy.channels.*' => ['in:database,mail,telegram,whatsapp'],
            'overrides.notification_policy.webhooks' => ['nullable', 'array'],
            'overrides.notification_policy.webhooks.telegram' => ['nullable', 'url', 'max:2048'],
            'overrides.notification_policy.webhooks.whatsapp' => ['nullable', 'url', 'max:2048'],
        ]);

        if (($data['active_profile_id'] ?? null) !== null && ! $this->profileAccessible((int) $data['active_profile_id'], $site->organization_id)) {
            abort(422, 'پروفایل انتخاب‌شده معتبر نیست.');
        }

        $policy = $this->policy($site);

        DB::table('site_automation_policies')->where('id', $policy->id)->update([
            'active_profile_id' => $data['active_profile_id'] ?? $policy->active_profile_id,
            'auto_publish_scope' => $data['auto_publish_scope'] ?? $policy->auto_publish_scope ?? 'none',
            'overrides_json' => isset($data['overrides']) && $data['overrides'] !== []
                ? json_encode($data['overrides'], JSON_UNESCAPED_UNICODE)
                : null,
            'updated_by' => $request->user()?->id,
            'updated_at' => now(),
        ]);

        return back()->with('status', 'سیاست خودکارسازی به‌روزرسانی شد.');
    }

    public function updateRoutes(Request $request, Site $site): RedirectResponse
    {
        Gate::authorize('update', $site);

        $data = $request->validate([
            'routes' => ['nullable', 'array'],
            'routes.*.content_type' => ['required', 'in:meta,article,product'],
            'routes.*.profile_id' => ['required', 'integer', 'exists:automation_profiles,id'],
        ]);

        foreach ($data['routes'] ?? [] as $route) {
            if (! $this->profileAccessible((int) $route['profile_id'], $site->organization_id)) {
                abort(422, 'پروفایل مسیر معتبر نیست.');
            }
        }

        DB::transaction(function () use ($site, $data): void {
            DB::table('site_profile_routes')->where('site_id', $site->id)->delete();
            foreach ($data['routes'] ?? [] as $route) {
                DB::table('site_profile_routes')->insert([
                    'site_id' => $site->id,
                    'profile_id' => $route['profile_id'],
                    'content_type' => $route['content_type'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });

        return back()->with('status', 'مسیریابی پروفایل‌ها به‌روزرسانی شد.');
    }

    public function copyProfile(Request $request, Site $site): RedirectResponse
    {
        Gate::authorize('update', $site);

        $data = $request->validate([
            'profile_id' => ['required', 'integer', 'exists:automation_profiles,id'],
            'name' => ['nullable', 'string', 'max:120'],
        ]);

        $source = DB::table('automation_profiles')->where('id', $data['profile_id'])->firstOrFail();
        abort_unless($this->profileAccessible((int) $source->id, $site->organization_id), 422, 'پروفایل مبدأ معتبر نیست.');
        $name = $data['name'] ?? 'کپی از '.$source->name;
        $slug = $source->slug.'-copy-'.substr((string) Str::ulid(), 0, 6);

        DB::table('automation_profiles')->insert([
            'name' => $name,
            'slug' => $slug,
            'kind' => 'custom',
            'scope' => 'site',
            'organization_id' => $site->organization_id,
            'automation_level' => $source->automation_level,
            'ai_policy' => $source->ai_policy,
            'confidence_threshold' => $source->confidence_threshold,
            'high_risk_threshold' => $source->high_risk_threshold,
            'risk_tier_max' => $source->risk_tier_max,
            'enabled_content_types' => $source->enabled_content_types,
            'daily_command_limit' => $source->daily_command_limit,
            'daily_mutation_limit' => $source->daily_mutation_limit,
            'execution_window' => $source->execution_window,
            'rollback_hours' => $source->rollback_hours,
            'auto_rollback' => $source->auto_rollback,
            'alert_level' => $source->alert_level,
            'reviewer_policy' => $source->reviewer_policy,
            'notification_policy' => $source->notification_policy,
            'version' => $source->version,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('status', 'پروفایل کپی شد؛ می‌توانید آن را در مسیریابی انتخاب کنید.');
    }

    public function trust(Site $site): Response
    {
        Gate::authorize('view', $site);

        return Inertia::render('App/Sites/AutomationTrust', [
            'site' => ['id' => $site->id, 'name' => $site->name],
            'kpis' => $this->kpis($site),
            'learning' => $this->learning($site),
            'reviewSample' => $this->reviewSample($site),
        ]);
    }

    public function emergencyStop(Site $site, EmergencyStop $stop): RedirectResponse
    {
        Gate::authorize('update', $site);
        $stop->handle($site->id);

        return back()->with('status', 'خودکارسازی متوقف شد؛ دستورهای در صف لغو شدند.');
    }

    public function resume(Site $site, ResumeAutomation $resume): RedirectResponse
    {
        Gate::authorize('update', $site);
        $resume->handle($site->id);

        return back()->with('status', 'توقف اضطراری برداشته شد؛ خودکارسازی طبق سیاست از سر گرفته شد.');
    }

    private function policy(Site $site): object
    {
        $policy = DB::table('site_automation_policies')->where('site_id', $site->id)->first();
        if ($policy !== null) {
            return $policy;
        }

        $id = DB::table('site_automation_policies')->insertGetId([
            'site_id' => $site->id,
            'level' => 1,
            'rules' => json_encode(['max_risk_tier' => 'R2'], JSON_UNESCAPED_UNICODE),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('site_automation_policies')->where('id', $id)->first();
    }

    /** @return array<string, mixed> */
    private function policyPayload(Site $site, object $policy): array
    {
        $profile = $policy->active_profile_id
            ? DB::table('automation_profiles')->where('id', $policy->active_profile_id)->first()
            : null;
        $overrides = json_decode((string) ($policy->overrides_json ?? '{}'), true) ?: [];
        $effective = array_merge(
            $profile !== null ? (array) $profile : [],
            $overrides,
            ['level' => $overrides['automation_level'] ?? $profile->automation_level ?? $policy->level ?? 1],
        );

        return [
            'level' => (int) $effective['level'],
            'aiPolicy' => (string) ($effective['ai_policy'] ?? 'draft_only'),
            'confidenceThreshold' => (int) ($effective['confidence_threshold'] ?? 80),
            'highRiskThreshold' => (int) ($effective['high_risk_threshold'] ?? 90),
            'riskTierMax' => (string) ($effective['risk_tier_max'] ?? 'R2'),
            'enabledContentTypes' => $this->jsonList($effective['enabled_content_types'] ?? null),
            'dailyCommandLimit' => (int) ($effective['daily_command_limit'] ?? 5),
            'dailyMutationLimit' => (int) ($effective['daily_mutation_limit'] ?? 2),
            'autoRollback' => (bool) ($effective['auto_rollback'] ?? false),
            'executionWindow' => $this->jsonList($effective['execution_window'] ?? null),
            'activeProfileId' => $policy->active_profile_id,
            'autoPublishScope' => (string) ($policy->auto_publish_scope ?? 'none'),
            'emergencyStoppedAt' => $policy->emergency_stopped_at,
            'overrides' => $overrides,
            'notificationPolicy' => $this->notificationPolicy($profile, $overrides),
            'siteId' => $site->id,
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array{enabled: bool, channels: array<int, string>, webhooks: array<string, string|null>}
     */
    private function notificationPolicy(?object $profile, array $overrides): array
    {
        $base = $profile?->notification_policy
            ? (json_decode((string) $profile->notification_policy, true) ?: [])
            : [];
        $merged = isset($overrides['notification_policy']) && is_array($overrides['notification_policy'])
            ? array_replace($base, $overrides['notification_policy'])
            : $base;

        return [
            'enabled' => (bool) ($merged['enabled'] ?? true),
            'channels' => is_array($merged['channels'] ?? null) ? array_values($merged['channels']) : ['database'],
            'webhooks' => [
                'telegram' => $merged['webhooks']['telegram'] ?? null,
                'whatsapp' => $merged['webhooks']['whatsapp'] ?? null,
            ],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function routes(Site $site): array
    {
        return DB::table('site_profile_routes')
            ->where('site_id', $site->id)
            ->orderBy('id')
            ->get(['content_type', 'profile_id'])
            ->map(fn (object $route): array => ['contentType' => $route->content_type, 'profileId' => (int) $route->profile_id])
            ->all();
    }

    /**
     * پروفایل‌های قابل استفاده: پروفایل‌های سیستمی (بدون سازمان) + پروفایل‌های سفارشی سازمان جاری.
     *
     * @return array<int, array<string, mixed>>
     */
    private function profiles(int $organizationId): array
    {
        return DB::table('automation_profiles')
            ->where(fn ($q) => $q->whereNull('organization_id')->orWhere('organization_id', $organizationId))
            ->orderBy('automation_level')
            ->orderBy('id')
            ->get(['id', 'name', 'slug', 'kind', 'automation_level', 'ai_policy', 'confidence_threshold', 'high_risk_threshold', 'risk_tier_max', 'enabled_content_types', 'daily_command_limit', 'daily_mutation_limit', 'auto_rollback', 'version'])
            ->map(function (object $p): array {
                return [
                    'id' => (int) $p->id,
                    'name' => $p->name,
                    'slug' => $p->slug,
                    'kind' => $p->kind,
                    'automationLevel' => (int) $p->automation_level,
                    'aiPolicy' => $p->ai_policy,
                    'confidenceThreshold' => (int) $p->confidence_threshold,
                    'highRiskThreshold' => (int) $p->high_risk_threshold,
                    'riskTierMax' => $p->risk_tier_max,
                    'enabledContentTypes' => $this->jsonList($p->enabled_content_types),
                    'dailyCommandLimit' => (int) $p->daily_command_limit,
                    'dailyMutationLimit' => (int) $p->daily_mutation_limit,
                    'autoRollback' => (bool) $p->auto_rollback,
                    'version' => (int) $p->version,
                ];
            })
            ->all();
    }

    /**
     * پروفایل فقط وقتی قابل استفاده است که سیستمی (بدون سازمان) یا متعلق به سازمان داده‌شده باشد.
     */
    private function profileAccessible(int $profileId, int $organizationId): bool
    {
        return DB::table('automation_profiles')
            ->where('id', $profileId)
            ->where(fn ($q) => $q->whereNull('organization_id')->orWhere('organization_id', $organizationId))
            ->exists();
    }

    /** @return array<string, int|float> */
    private function kpis(Site $site): array
    {
        $auto = DB::table('commands')->where('site_id', $site->id)->where('decision_source', 'policy');
        $totalAuto = (clone $auto)->count();
        $autoExecuted = (clone $auto)->where('status', 'executed')->count();
        $autoRolledBack = (clone $auto)->where('status', 'rolled_back')->count();
        $autoFailed = (clone $auto)->where('status', 'failed')->count();

        $systemApprovals = DB::table('command_approvals')->join('commands', 'commands.id', '=', 'command_approvals.command_id')
            ->where('commands.site_id', $site->id)
            ->where('command_approvals.reviewer_type', 'system')
            ->where('command_approvals.decision', 'auto_approved')
            ->count();
        $humanApproved = DB::table('command_approvals')->join('commands', 'commands.id', '=', 'command_approvals.command_id')
            ->where('commands.site_id', $site->id)
            ->where('command_approvals.reviewer_type', 'user')
            ->where('command_approvals.decision', 'approved')
            ->count();
        $humanRejected = DB::table('command_approvals')->join('commands', 'commands.id', '=', 'command_approvals.command_id')
            ->where('commands.site_id', $site->id)
            ->where('command_approvals.reviewer_type', 'user')
            ->where('command_approvals.decision', 'rejected')
            ->count();

        $rollbacks = DB::table('commands')->where('site_id', $site->id)->where('status', 'rolled_back')->count();
        $successRate = $totalAuto > 0 ? (int) round($autoExecuted / $totalAuto * 100) : null;

        return [
            'totalAuto' => $totalAuto,
            'autoExecuted' => $autoExecuted,
            'autoRolledBack' => $autoRolledBack,
            'autoFailed' => $autoFailed,
            'successRate' => $successRate,
            'systemApprovals' => $systemApprovals,
            'humanApproved' => $humanApproved,
            'humanRejected' => $humanRejected,
            'rollbacks' => $rollbacks,
            // تخمین: هر انتشار خودکار ~۱۵ دقیقه کار دستی صرفه‌جویی می‌کند
            'estimatedHoursSaved' => round($systemApprovals * 0.25, 1),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function learning(Site $site): array
    {
        return DB::table('automation_learning_history')
            ->where('site_id', $site->id)
            ->orderByDesc('updated_at')
            ->get(['command_type', 'total', 'successful', 'updated_at'])
            ->map(function (object $row): array {
                return [
                    'commandType' => $row->command_type,
                    'total' => (int) $row->total,
                    'successful' => (int) $row->successful,
                    'successRate' => (int) $row->total > 0 ? (int) round($row->successful / $row->total * 100) : 0,
                    'updatedAt' => $row->updated_at,
                ];
            })
            ->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function reviewSample(Site $site): array
    {
        return DB::table('command_approvals')
            ->join('commands', 'commands.id', '=', 'command_approvals.command_id')
            ->where('commands.site_id', $site->id)
            ->where('command_approvals.reviewer_type', 'system')
            ->where('command_approvals.decision', 'auto_approved')
            ->orderByDesc('command_approvals.id')
            ->limit(10)
            ->get(['commands.id', 'commands.type', 'commands.risk_tier', 'commands.confidence_score', 'commands.status', 'commands.payload', 'command_approvals.created_at'])
            ->map(function (object $row): array {
                $payload = json_decode((string) $row->payload, true) ?: [];

                return [
                    'id' => (int) $row->id,
                    'type' => $row->type,
                    'riskTier' => $row->risk_tier,
                    'confidence' => $row->confidence_score,
                    'status' => $row->status,
                    'url' => $payload['url'] ?? null,
                    'approvedAt' => $row->created_at,
                ];
            })
            ->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function executions(Site $site): array
    {
        return DB::table('commands')
            ->where('site_id', $site->id)
            ->whereIn('status', ['executed', 'rolled_back', 'failed', 'dispatched', 'queued', 'cancelled'])
            ->orderByDesc('id')
            ->limit(15)
            ->get(['id', 'type', 'risk_tier', 'confidence_score', 'decision_source', 'status', 'published_at', 'policy_version', 'created_at'])
            ->map(function (object $c): array {
                return [
                    'id' => (int) $c->id,
                    'type' => $c->type,
                    'riskTier' => $c->risk_tier,
                    'confidence' => $c->confidence_score,
                    'decisionSource' => $c->decision_source,
                    'status' => $c->status,
                    'publishedAt' => $c->published_at,
                    'policyVersion' => (int) $c->policy_version,
                    'createdAt' => $c->created_at,
                ];
            })
            ->all();
    }

    /** @return array<int, mixed> */
    private function jsonList(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }
        $decoded = json_decode((string) $value, true);

        return is_array($decoded) ? $decoded : [];
    }
}
