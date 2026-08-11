<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Lead;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class NewLeadNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Lead $lead) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        return [
            'lead_id' => $this->lead->getKey(),
            'lead_name' => $this->lead->name,
            'source' => $this->lead->source,
            'score' => $this->lead->score,
            'campaign' => $this->lead->utm_campaign,
            'created_at' => $this->lead->created_at?->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }
}
