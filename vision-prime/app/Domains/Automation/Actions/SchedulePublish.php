<?php

declare(strict_types=1);

namespace App\Domains\Automation\Actions;

use App\Domains\Audit\Actions\RecordAuditLog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * تقویم محتوایی — زمان‌بندی انتشار پیش‌نویس مقاله/محصول.
 *
 * یک کامند publish_new_article که هنوز اجرا نشده (pending_approval یا scheduled) را
 * برای تاریخ/ساعت مشخصی زمان‌بندی می‌کند:
 *  - schedule: status → scheduled + scheduled_for = موعد
 *  - cancel:   status → pending_approval + scheduled_for = null (از تقویم خارج می‌شود)
 *
 * Job آزادسازی (ReleaseScheduledCommands) کامندهای موعدرسیده را در لحظهٔ موعد
 * از AutoPublish عبور می‌دهد — یعنی تصمیم نهایی (انتشار خودکار یا انتظار تأیید انسانی)
 * همیشه در همان لحظهٔ انتشار بر اساس Policy جاری گرفته می‌شود (اصل D-013).
 */
class SchedulePublish
{
    public const SCHEDULABLE_TYPES = ['publish_new_article'];

    public function __construct(private readonly RecordAuditLog $audit) {}

    /**
     * انتشار فوری — موعد را به همین لحظه می‌رساند و بلافاصله کامند را از AutoPublish
     * عبور می‌دهد (همان مسیر آزادسازی موعدرسیده؛ تصمیم با Policy جاری).
     *
     * @return array{status: string, command_id: int, decision: string, reason?: string}
     */
    public function publishNow(int $commandId, AutoPublish $autoPublish): array
    {
        $command = $this->commandOrFail($commandId);

        DB::table('commands')->where('id', $command->id)->update([
            'status' => 'pending_approval',
            'scheduled_for' => now(),
            'updated_at' => now(),
        ]);

        $result = $autoPublish->handle($command->id);
        $this->audit->handle(
            action: 'command.publish_now',
            after: ['command_id' => $command->id, 'decision' => $result['decision'], 'reason' => $result['reason'] ?? null],
        );

        return ['status' => 'released', 'command_id' => $command->id, 'decision' => $result['decision'], 'reason' => $result['reason'] ?? null];
    }

    /** @return array{status: string, command_id: int, scheduled_for: string|null} */
    public function schedule(int $commandId, string $scheduledFor): array
    {
        $command = $this->commandOrFail($commandId);

        $scheduledAt = $this->parseScheduledFor($scheduledFor);

        DB::table('commands')->where('id', $command->id)->update([
            'status' => 'scheduled',
            'scheduled_for' => $scheduledAt->toDateTimeString(),
            'updated_at' => now(),
        ]);

        $this->audit->handle(
            action: 'command.publish_scheduled',
            after: ['command_id' => $command->id, 'scheduled_for' => $scheduledAt->toDateTimeString()],
        );

        return ['status' => 'scheduled', 'command_id' => $command->id, 'scheduled_for' => $scheduledAt->toDateTimeString()];
    }

    /** @return array{status: string, command_id: int, scheduled_for: null} */
    public function cancel(int $commandId): array
    {
        $command = $this->commandOrFail($commandId);

        DB::table('commands')->where('id', $command->id)->update([
            'status' => 'pending_approval',
            'scheduled_for' => null,
            'updated_at' => now(),
        ]);

        $this->audit->handle(
            action: 'command.publish_schedule_cancelled',
            after: ['command_id' => $command->id],
        );

        return ['status' => 'pending_approval', 'command_id' => $command->id, 'scheduled_for' => null];
    }

    private function commandOrFail(int $commandId): object
    {
        $command = DB::table('commands')->where('id', $commandId)->first();
        abort_unless($command !== null, 404);

        if (! in_array($command->type, self::SCHEDULABLE_TYPES, true)) {
            abort(422, 'فقط پیش‌نویس مقاله/محصول (publish_new_article) قابل زمان‌بندی است.');
        }

        if (! in_array($command->status, ['pending_approval', 'scheduled'], true)) {
            abort(422, 'فقط کامندهای در انتظار تأیید یا زمان‌بندی‌شده قابل تغییر زمان هستند.');
        }

        return $command;
    }

    private function parseScheduledFor(string $value): Carbon
    {
        $parsed = Carbon::parse($value);

        if ($parsed->lt(now()->subMinute())) {
            abort(422, 'زمان انتشار باید در آینده باشد.');
        }

        return $parsed;
    }
}
