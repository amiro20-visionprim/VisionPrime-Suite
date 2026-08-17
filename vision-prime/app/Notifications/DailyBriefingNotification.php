<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DailyBriefingNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $summary
     */
    public function __construct(public readonly array $summary) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        $decisions = (int) count($this->summary['pending_decisions'] ?? []);
        $exceptions = (int) count($this->summary['open_exceptions'] ?? []);

        $message = sprintf(
            '📋 گزارش صبح سوئیت — %s سازمان فعال · %s تصمیم در انتظار · %s استثنای باز',
            (string) ($this->summary['kpis']['orgs_active'] ?? 0),
            $decisions,
            $exceptions,
        );

        return [
            'message' => $message,
            'summary' => $this->summary,
            'created_at' => now()->toIso8601String(),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $kpis = $this->summary['kpis'] ?? [];
        $decisions = $this->summary['pending_decisions'] ?? [];
        $exceptions = $this->summary['open_exceptions'] ?? [];

        $mail = (new MailMessage)
            ->subject('گزارش صبح سوئیت — '.now()->format('Y-m-d'))
            ->greeting('سلام مدیر ارشد 👋');

        $mail->line(sprintf(
            'خلاصهٔ امروز: **%s** سازمان فعال · **%s** مشتری · **%s** سایت متصل · درآمد این ماه: **%s** تومان.',
            (string) ($kpis['orgs_active'] ?? 0),
            (string) ($kpis['clients_total'] ?? 0),
            (string) ($kpis['sites_connected'] ?? 0),
            number_format((int) ($kpis['revenue_month'] ?? 0)),
        ));

        // F-03: خلاصهٔ هوشمند تصمیم‌ها (AI یا اولویت‌بندی خودکار)
        $triageAi = $this->summary['triage_ai'] ?? null;
        if (isset($triageAi['summary']) && $triageAi['summary'] !== '') {
            $mail->line('🧠 **خلاصهٔ هوشمند:** '.$triageAi['summary']);
        }

        if (count($decisions) > 0) {
            $mail->line('🔴 **تصمیم‌هایی که منتظر شماست:**');
            foreach ($decisions as $decision) {
                $mail->line('- '.($this->typeLabel((string) $decision['type'])));
            }
            $mail->action('صف تصمیم', url('/platform/dashboard'));
        }

        if (count($exceptions) > 0) {
            $mail->line('🟡 **استثناهای باز:** '.count($exceptions).' مورد — در پنل پلتفرم ببینید.');
        }

        return $mail;
    }

    private function typeLabel(string $type): string
    {
        return match ($type) {
            'payment.failed' => 'پرداخت ناموفق ثبت شده',
            'review.awaiting' => 'پیش‌نویس در انتظار بررسی',
            'command.awaiting' => 'دستور در انتظار تأیید',
            'subscription.expiring' => 'اشتراک در حال انقضا',
            'subscription.past_due' => 'پرداخت معوق',
            'site.disconnected' => 'سایت بدون اتصال',
            'ai.cost_spike' => 'مصرف AI نزدیک سقف',
            'job.failure' => 'خطای job در صف',
            default => $type,
        };
    }
}
