<?php

declare(strict_types=1);

namespace App\Domains\Automation\Jobs;

use App\Domains\Organization\Models\Organization;
use App\Domains\Platform\Services\SmsManager;
use App\Models\User;
use App\Notifications\ScheduledPublishReminder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

/**
 * تقویم محتوایی — یادآوری موعد انتشار (روزانه، یک روز قبل از موعد).
 *
 * کامندهای publish_new_article با status=scheduled که موعدشان در ۲۴ ساعت آینده است را
 * پیدا می‌کند و به اعضای فعال سازمانِ سایت اعلان database (و mail در صورت فعال‌بودن
 * notification_policy) می‌دهد تا پیش‌نویس را بررسی/تأیید کنند.
 */
class RemindScheduledPublishes implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;

    public function handle(): void
    {
        $rows = DB::table('commands')
            ->where('type', 'publish_new_article')
            ->where('status', 'scheduled')
            ->where('scheduled_for', '>', now())
            ->where('scheduled_for', '<=', now()->addDay())
            ->whereNull('reminder_sent_at')
            ->orderBy('scheduled_for')
            ->get();

        foreach ($rows as $command) {
            $site = DB::table('sites')->where('id', $command->site_id)->first();
            if ($site === null) {
                continue;
            }

            $payload = json_decode((string) ($command->payload ?? '{}'), true) ?? [];
            $title = (string) ($payload['title'] ?? $payload['content_type'] ?? 'پیش‌نویس');

            $userIds = DB::table('memberships')
                ->where('organization_id', $site->organization_id)
                ->where('status', 'active')
                ->pluck('user_id')
                ->all();
            if ($userIds === []) {
                continue;
            }

            $message = 'پیش‌نویس «'.$title.'» فردا ('.$command->scheduled_for.') به‌صورت خودکار منتشر می‌شود — در صورت نیاز بررسی/تأیید کنید.';

            foreach (User::query()->whereIn('id', $userIds)->get() as $user) {
                $user->notify(new ScheduledPublishReminder((int) $command->site_id, (int) $command->id, [
                    'title' => $title,
                    'scheduled_for' => $command->scheduled_for,
                    'message' => $message,
                    'channels' => ['database'],
                ]));
            }

            DB::table('commands')->where('id', $command->id)->update([
                'reminder_sent_at' => now(),
                'updated_at' => now(),
            ]);

            // F-02 (گسترش): یادآوری موعد انتشار از طریق پیامک به شمارهٔ سازمان
            $org = Organization::query()->find((int) $site->organization_id);
            $phone = $org?->settings['phone'] ?? null;
            if (is_string($phone) && $phone !== '') {
                app(SmsManager::class)->send(
                    $phone,
                    sprintf(
                        'یادآوری: پیش‌نویس «%s» فردا (%s) به‌صورت خودکار منتشر می‌شود.',
                        $title,
                        (string) $command->scheduled_for,
                    ),
                );
            }
        }
    }
}
