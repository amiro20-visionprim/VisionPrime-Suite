<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AutomationAlert extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $context  شامل message، command_type، ratio و channels[] (از notification_policy)
     */
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
            'command_type' => $this->context['command_type'] ?? null,
            'metric' => $this->context['metric'] ?? 'clicks',
            'ratio' => $this->context['ratio'] ?? null,
            'message' => $this->context['message'] ?? 'افت معیار زیر baseline شناسایی شد.',
            'created_at' => now()->toIso8601String(),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('هشدار اتوماسیون سوئیت')
            ->line($this->context['message'] ?? 'افت معیار زیر baseline شناسایی شد.')
            ->line('سایت #'.$this->siteId.' · دستور #'.$this->commandId.' · نوع: '.($this->context['command_type'] ?? '—'))
            ->line('این پیام به‌صورت خودکار از سامانهٔ سوئیت ارسال شده است.');
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }
}
