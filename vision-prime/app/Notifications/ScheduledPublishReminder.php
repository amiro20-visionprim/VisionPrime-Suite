<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * یادآوری موعد انتشار (تقویم محتوایی).
 *
 * یک روز قبل از موعد کامند زمان‌بندی‌شده به اعضای فعال سازمان اعلان می‌دهد تا
 * پیش‌نویس را بررسی/تأیید کنند و در صورت نیاز موعد را تغییر دهند.
 */
class ScheduledPublishReminder extends Notification implements ShouldQueue
{
    use Queueable;

    /** @param  array<string, mixed>  $context  شامل message، title، scheduled_for و channels[] */
    public function __construct(
        public readonly int $siteId,
        public readonly int $commandId,
        public readonly array $context,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        $channels = $this->context['channels'] ?? ['database'];
        $via = [];
        if (in_array('database', $channels, true)) {
            $via[] = 'database';
        }
        if (in_array('mail', $channels, true)) {
            $via[] = 'mail';
        }

        return $via;
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        return [
            'site_id' => $this->siteId,
            'command_id' => $this->commandId,
            'command_type' => 'publish_new_article',
            'kind' => 'scheduled_publish_reminder',
            'title' => $this->context['title'] ?? null,
            'scheduled_for' => $this->context['scheduled_for'] ?? null,
            'message' => $this->context['message'] ?? 'یک پیش‌نویس زمان‌بندی‌شده فردا منتشر می‌شود.',
            'created_at' => now()->toIso8601String(),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('یادآوری: انتشار زمان‌بندی‌شده فردا — سوئیت')
            ->line($this->context['message'] ?? 'یک پیش‌نویس زمان‌بندی‌شده فردا منتشر می‌شود.')
            ->line('عنوان: '.($this->context['title'] ?? '—'))
            ->line('موعد: '.($this->context['scheduled_for'] ?? '—'))
            ->line('این پیام به‌صورت خودکار از سامانهٔ سوئیت ارسال شده است.');
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }
}
