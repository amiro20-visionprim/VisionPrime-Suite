<?php

declare(strict_types=1);

namespace App\Domains\Platform\Models;

use App\Domains\Organization\Models\Organization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_PAID = 'paid';

    public const STATUS_FAILED = 'failed';

    public const STATUS_REFUNDED = 'refunded';

    protected $fillable = [
        'organization_id', 'subscription_id', 'amount', 'currency', 'method',
        'status', 'reference', 'gateway_transaction_id', 'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'int',
            'paid_at' => 'datetime',
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

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'در انتظار',
            self::STATUS_PAID => 'پرداخت شده',
            self::STATUS_FAILED => 'ناموفق',
            self::STATUS_REFUNDED => 'بازگشت داده شده',
            default => $this->status,
        };
    }
}
