<?php

declare(strict_types=1);

namespace App\Domains\Automation\Actions;

use App\Domains\Audit\Actions\RecordAuditLog;
use App\Domains\Automation\Services\PolicyEvaluator;
use Illuminate\Support\Facades\DB;

/**
 * مسیر انتشار خودکار (D-013 فاز ۲).
 *
 * هنگام تبدیل/رسیدن یک command، Policy فعلی را در لحظه ارزیابی می‌کند (بند Acceptance سند ۰۱)
 * و بر اساس تصمیم:
 *  - auto_publish: تأیید سیستمی (reviewer_type=system، reviewer_id=null) + اجرای فوری + published_at
 *  - pending_approval: بدون تغییر (همان مسیر تأیید انسانی موجود)
 *  - delayed: status=queued برای پردازش بعدی (توقف اضطراری این‌ها را cancel می‌کند)
 *  - rejected/blocked: status=cancelled با ثبت دلیل در audit
 *
 * fail-closed: بدون confidence_score هیچ‌وقت auto_publish رخ نمی‌دهد (PolicyEvaluator).
 */
class AutoPublish
{
    public function __construct(
        private readonly ExecuteCommand $execute,
        private readonly RecordAuditLog $audit,
        private readonly PolicyEvaluator $evaluator,
    ) {}

    /** @return array{decision: string, command_id: int, executed?: bool, reason?: string} */
    public function handle(int $commandId): array
    {
        $command = DB::table('commands')->where('id', $commandId)->first();

        if ($command === null) {
            return ['decision' => 'noop', 'command_id' => $commandId, 'reason' => 'command_not_found'];
        }

        // فقط دستورهای در انتظار (انسانی یا صف خودکار) قابل پردازش‌اند؛ idempotent
        if (! in_array($command->status, ['pending_approval', 'queued'], true)) {
            return ['decision' => 'noop', 'command_id' => $commandId, 'reason' => 'not_pending'];
        }

        $policy = DB::table('site_automation_policies')->where('site_id', $command->site_id)->first();
        $profile = $policy?->active_profile_id
            ? DB::table('automation_profiles')->where('id', $policy->active_profile_id)->first()
            : null;

        $decision = $this->evaluator->evaluate([
            'policy' => $policy,
            'profile' => $profile,
            'routes' => $this->routes((int) $command->site_id),
            'command' => [
                'type' => $command->type,
                'risk_tier' => $command->risk_tier,
                'confidence_score' => $command->confidence_score,
                'content_type' => $command->content_type ?? null,
                'policy_version' => $command->policy_version,
            ],
            // سقف روزانه: دستورهای امروز که مصرف‌کنندهٔ بودجه‌اند (اجرا/در حال اجرا)
            'today_counts' => $this->todayCounts((int) $command->site_id),
        ]);

        return match ($decision['decision']) {
            PolicyEvaluator::DECISION_AUTO_PUBLISH => $this->publish($command->id, $decision['snapshot']),
            PolicyEvaluator::DECISION_DELAYED => $this->delay($command->id, $decision['reason'], $decision['snapshot']),
            PolicyEvaluator::DECISION_REJECTED, PolicyEvaluator::DECISION_BLOCKED => $this->reject($command->id, $decision['reason'], $decision['snapshot']),
            default => ['decision' => 'pending_approval', 'command_id' => $command->id, 'reason' => $decision['reason']],
        };
    }

    /** @return array<int, array{content_type: string, profile: array<string, mixed>|null}> */
    private function routes(int $siteId): array
    {
        return DB::table('site_profile_routes')
            ->where('site_id', $siteId)
            ->orderBy('id')
            ->get()
            ->map(function (object $route): array {
                $profile = DB::table('automation_profiles')->where('id', $route->profile_id)->first();

                return [
                    'content_type' => $route->content_type,
                    'profile' => $profile !== null ? (array) $profile : null,
                ];
            })
            ->all();
    }

    /** @return array{daily_commands: int, daily_mutations: int} */
    private function todayCounts(int $siteId): array
    {
        $executedToday = DB::table('commands')
            ->where('site_id', $siteId)
            ->whereDate('created_at', today())
            ->whereIn('status', ['dispatched', 'executed'])
            ->count();
        $mutationsToday = DB::table('commands')
            ->where('site_id', $siteId)
            ->whereDate('created_at', today())
            ->whereIn('status', ['dispatched', 'executed'])
            ->whereIn('risk_tier', ['R1', 'R2', 'R3'])
            ->count();

        return ['daily_commands' => $executedToday, 'daily_mutations' => $mutationsToday];
    }

    /** @param  array<string, mixed>  $snapshot */
    private function publish(int $commandId, array $snapshot): array
    {
        DB::transaction(function () use ($commandId, $snapshot): void {
            DB::table('command_approvals')->insert([
                'command_id' => $commandId,
                'reviewer_id' => null,
                'reviewer_type' => 'system',
                'decision' => 'auto_approved',
                'note' => 'Automatic publication by policy (D-013).',
                'policy_snapshot' => json_encode($snapshot, JSON_UNESCAPED_UNICODE),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('commands')->where('id', $commandId)->update([
                'status' => 'approved',
                'decision_source' => 'policy',
                'updated_at' => now(),
            ]);
        });

        $this->audit->handle(
            action: 'command.auto_approved',
            after: ['command_id' => $commandId, 'snapshot' => $snapshot],
        );

        $this->execute->handle($commandId);

        DB::table('commands')->where('id', $commandId)->update([
            'published_at' => now(),
            'updated_at' => now(),
        ]);
        $this->audit->handle(action: 'command.auto_published', after: ['command_id' => $commandId]);

        return ['decision' => 'auto_publish', 'command_id' => $commandId, 'executed' => true];
    }

    /** @param  array<string, mixed>  $snapshot */
    private function delay(int $commandId, string $reason, array $snapshot): array
    {
        DB::table('commands')->where('id', $commandId)->update([
            'status' => 'queued',
            'updated_at' => now(),
        ]);
        $this->audit->handle(
            action: 'command.policy_delayed',
            after: ['command_id' => $commandId, 'reason' => $reason, 'snapshot' => $snapshot],
        );

        return ['decision' => 'delayed', 'command_id' => $commandId, 'reason' => $reason];
    }

    /** @param  array<string, mixed>  $snapshot */
    private function reject(int $commandId, string $reason, array $snapshot): array
    {
        DB::table('commands')->where('id', $commandId)->update([
            'status' => 'cancelled',
            'updated_at' => now(),
        ]);
        $this->audit->handle(
            action: 'command.policy_rejected',
            after: ['command_id' => $commandId, 'reason' => $reason, 'snapshot' => $snapshot],
        );

        return ['decision' => 'rejected', 'command_id' => $commandId, 'reason' => $reason];
    }
}
