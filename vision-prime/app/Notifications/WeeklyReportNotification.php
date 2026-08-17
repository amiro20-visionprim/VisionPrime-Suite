<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WeeklyReportNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $report
     */
    public function __construct(public readonly array $report) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        $f = $this->report['financial'];
        $h = $this->report['health'];

        $message = sprintf(
            '📊 گزارش هفتگی سوئیت — درآمد هفته: %s تومان · فاکتور باز: %d · سازمان فعال: %d · تصمیم در انتظار: %d',
            number_format((int) $f['revenue_week']),
            (int) $f['invoices_outstanding'],
            (int) $h['orgs_active'],
            count($this->report['decisions'] ?? []),
        );

        return [
            'message' => $message,
            'report' => $this->report,
            'created_at' => now()->toIso8601String(),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $f = $this->report['financial'];
        $h = $this->report['health'];
        $decisions = $this->report['decisions'] ?? [];

        $mail = (new MailMessage)
            ->subject('گزارش هفتگی سوئیت — '.now()->format('Y-m-d'))
            ->greeting('سلام مدیر ارشد 👋');

        $mail->line('📅 بازه: '.($this->report['week'] ?? ''));

        $mail->line('💰 **خلاصهٔ مالی:**');
        $mail->line('- درآمد این هفته: **'.number_format((int) $f['revenue_week']).'** تومان');
        $mail->line('- درآمد این ماه: **'.number_format((int) $f['revenue_month']).'** تومان');
        $mail->line(sprintf(
            '- فاکتور باز: **%d** (مجموع %s تومان) · معوق: **%d**',
            (int) $f['invoices_outstanding'],
            number_format((int) $f['invoices_outstanding_amount']),
            (int) $f['invoices_overdue'],
        ));

        $mail->line('🛡️ **سلامت اکوسیستم:**');
        $mail->line(sprintf(
            '- **%s** سازمان فعال · **%s** مشتری · **%s** سایت متصل',
            (string) ($h['orgs_active'] ?? 0),
            (string) ($h['clients_total'] ?? 0),
            (string) ($h['sites_connected'] ?? 0),
        ));
        $mail->line(sprintf(
            '- دستور اجراشده این هفته: **%s** · استثنای باز: **%s**',
            (string) ($h['commands_executed_week'] ?? 0),
            (string) ($h['exceptions_open'] ?? 0),
        ));

        if (count($decisions) > 0) {
            $mail->line('🔴 **تصمیم‌هایی که منتظر شماست:**');
            foreach ($decisions as $decision) {
                $mail->line('- '.$this->typeLabel((string) $decision['type']));
            }
            $mail->action('صف تصمیم', url('/platform/dashboard'));
        } else {
            $mail->line('✅ این هفته تصمیم باز وجود ندارد.');
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
            default => $type,
        };
    }
}
