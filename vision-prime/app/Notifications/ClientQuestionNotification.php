<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * «سؤال از تیم» در تأییدهای پنل مشتری (تصمیم ۹ بارش فکری).
 *
 * وقتی مشتری به‌جای ردکردن، سؤالی دربارهٔ یک پیشنهاد می‌پرسد، این اعلان به
 * اعضای فعال سازمانِ آژانس می‌رود تا پاسخ دهند.
 */
class ClientQuestionNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $siteId,
        public readonly string $subjectType, // command | review
        public readonly int $subjectId,
        public readonly string $question,
        public readonly string $askedByName,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        return [
            'site_id' => $this->siteId,
            'subject_type' => $this->subjectType,
            'subject_id' => $this->subjectId,
            'kind' => 'client_question',
            'title' => 'سؤال جدید از مشتری',
            'asked_by' => $this->askedByName,
            'question' => $this->question,
            'message' => 'مشتری ('.$this->askedByName.') دربارهٔ یک پیشنهاد سؤال پرسیده است: '.$this->question,
            'created_at' => now()->toIso8601String(),
        ];
    }
}
