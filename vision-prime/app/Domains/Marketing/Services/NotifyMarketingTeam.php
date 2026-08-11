<?php

declare(strict_types=1);

namespace App\Domains\Marketing\Services;

use App\Models\Lead;
use App\Models\User;
use App\Notifications\NewLeadNotification;
use Illuminate\Support\Facades\Notification;

class NotifyMarketingTeam
{
    public function handle(Lead $lead): void
    {
        $recipients = User::query()
            ->whereHas('memberships', function ($query): void {
                $query->where('status', 'active')
                    ->whereHas('role.permissions', fn ($permission) => $permission->where('key', 'marketing.view.organization'));
            })
            ->get();

        if ($recipients->isEmpty()) {
            return;
        }

        Notification::send($recipients, new NewLeadNotification($lead));
    }
}
