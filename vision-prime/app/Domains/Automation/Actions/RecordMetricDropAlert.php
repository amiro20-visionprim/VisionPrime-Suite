<?php

declare(strict_types=1);

namespace App\Domains\Automation\Actions;

use App\Models\User;
use App\Notifications\AutomationAlert;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * هشدار افت معیار (D-013 — R1).
 *
 * برای R1 فقط هشدار می‌دهد (بدون rollback خودکار):
 *  ۱) رویداد ممیزی `automation.alert.metric_drop` در connector_events (با dedupe ۲۴ ساعته)
 *  ۲) اعلان به اعضای فعال سازمانِ سایت، اگر alert_level پروفایل != none باشد و
 *     notification_policy آن را غیرفعال نکرده باشد.
 */
class RecordMetricDropAlert
{
    /** @param  object  $command  ردیف commands
     * @return bool آیا هشدار ثبت شد (dedupe شده بود یا نه) */
    public function handle(object $command, float $ratio): bool
    {
        $already = DB::table('connector_events')
            ->where('site_id', $command->site_id)
            ->where('type', 'automation.alert.metric_drop')
            ->where('occurred_at', '>=', now()->subDay())
            ->whereRaw('json_extract(payload_redacted, "$.command_id") = ?', [(int) $command->id])
            ->exists();

        if ($already) {
            return false;
        }

        DB::table('connector_events')->insert([
            'site_id' => $command->site_id,
            'type' => 'automation.alert.metric_drop',
            'payload_redacted' => json_encode([
                'command_id' => (int) $command->id,
                'command_type' => $command->type,
                'metric' => 'clicks',
                'ratio' => round($ratio, 3),
            ], JSON_UNESCAPED_UNICODE),
            'occurred_at' => now(),
        ]);

        $this->notifyUsers($command, $ratio);

        return true;
    }

    private function notifyUsers(object $command, float $ratio): void
    {
        $site = DB::table('sites')->where('id', $command->site_id)->first();
        $policy = DB::table('site_automation_policies')->where('site_id', $command->site_id)->first();
        if ($site === null || $policy === null) {
            return;
        }

        $profile = $policy->active_profile_id
            ? DB::table('automation_profiles')->where('id', $policy->active_profile_id)->first()
            : null;
        $alertLevel = (string) ($profile->alert_level ?? 'warn');
        if ($alertLevel === 'none') {
            return;
        }

        // مدل سهلایه: notification_policy پروفایل ← overrides_json سایت (override برنده است)
        $notificationPolicy = $profile?->notification_policy
            ? (json_decode((string) $profile->notification_policy, true) ?: [])
            : [];
        $overrides = json_decode((string) ($policy->overrides_json ?? '{}'), true) ?: [];
        if (isset($overrides['notification_policy']) && is_array($overrides['notification_policy'])) {
            $notificationPolicy = array_replace($notificationPolicy, $overrides['notification_policy']);
        }
        if (($notificationPolicy['enabled'] ?? true) === false) {
            return;
        }

        $channels = $notificationPolicy['channels'] ?? ['database'];
        $message = 'افت '.(int) round((1 - $ratio) * 100).'٪ در بازدید/CTR صفحهٔ تغییر «'.$command->type.'» نسبت به baseline.';

        // کانال‌های خارجی (تلگرام/واتساپ) از طریق webhook — best-effort، غیرمسدود
        if (in_array('telegram', $channels, true) && ! empty($notificationPolicy['webhooks']['telegram'] ?? null)) {
            $this->postWebhook((string) $notificationPolicy['webhooks']['telegram'], $message);
        }
        if (in_array('whatsapp', $channels, true) && ! empty($notificationPolicy['webhooks']['whatsapp'] ?? null)) {
            $this->postWebhook((string) $notificationPolicy['webhooks']['whatsapp'], $message);
        }

        $userIds = DB::table('memberships')
            ->where('organization_id', $site->organization_id)
            ->where('status', 'active')
            ->pluck('user_id')
            ->all();

        $users = User::query()->whereIn('id', $userIds)->get();

        foreach ($users as $user) {
            $user->notify(new AutomationAlert((int) $command->site_id, (int) $command->id, [
                'command_type' => $command->type,
                'ratio' => round($ratio, 3),
                'message' => $message,
                'channels' => $channels,
            ]));
        }
    }

    private function postWebhook(string $url, string $message): void
    {
        try {
            Http::timeout(10)->post($url, ['text' => $message, 'message' => $message]);
        } catch (\Throwable) {
            // best-effort: شکست کانال خارجی نباید زنجیرهٔ هشدار را بشکند
        }
    }
}
