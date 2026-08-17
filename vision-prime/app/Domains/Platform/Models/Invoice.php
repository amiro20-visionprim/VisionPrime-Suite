<?php

declare(strict_types=1);

namespace App\Domains\Platform\Models;

use App\Domains\Organization\Models\Organization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invoice extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_ISSUED = 'issued';

    public const STATUS_PAID = 'paid';

    public const STATUS_OVERDUE = 'overdue';

    public const STATUS_CANCELED = 'canceled';

    protected $fillable = [
        'organization_id', 'subscription_id', 'payment_id', 'number', 'amount',
        'tax', 'total', 'status', 'issued_at', 'due_at', 'sms_reminder_sent_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'int',
            'tax' => 'int',
            'total' => 'int',
            'issued_at' => 'datetime',
            'due_at' => 'datetime',
            'sms_reminder_sent_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return BelongsTo<Subscription, $this> */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    /** @return BelongsTo<Payment, $this> */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_DRAFT => 'پیش‌نویس',
            self::STATUS_ISSUED => 'صادر شده',
            self::STATUS_PAID => 'پرداخت شده',
            self::STATUS_OVERDUE => 'معوق',
            self::STATUS_CANCELED => 'لغو شده',
            default => $this->status,
        };
    }
}
