<?php

declare(strict_types=1);

namespace App\Domains\Platform\Models;

use App\Domains\Organization\Models\Organization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Subscription extends Model
{
    use SoftDeletes;

    public const STATUS_TRIALING = 'trialing';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_PAST_DUE = 'past_due';

    public const STATUS_CANCELED = 'canceled';

    public const STATUS_SUSPENDED = 'suspended';

    protected $fillable = [
        'organization_id', 'plan_id', 'status', 'trial_ends_at', 'starts_at',
        'current_period_end', 'auto_renew', 'cancel_at_period_end',
    ];

    protected function casts(): array
    {
        return [
            'trial_ends_at' => 'datetime',
            'starts_at' => 'datetime',
            'current_period_end' => 'datetime',
            'auto_renew' => 'bool',
            'cancel_at_period_end' => 'bool',
        ];
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return BelongsTo<Plan, $this> */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function isActive(): bool
    {
        return in_array($this->status, [self::STATUS_ACTIVE, self::STATUS_TRIALING], true);
    }

    public function isExpired(): bool
    {
        return $this->current_period_end !== null
            && $this->current_period_end->isPast()
            && ! $this->isActive();
    }

    public function remainingDays(): int
    {
        if ($this->current_period_end === null) {
            return 0;
        }

        return max(0, (int) now()->diffInDays($this->current_period_end, false));
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_TRIALING => 'دورهٔ آزمایشی',
            self::STATUS_ACTIVE => 'فعال',
            self::STATUS_PAST_DUE => 'پرداخت معوق',
            self::STATUS_CANCELED => 'لغو شده',
            self::STATUS_SUSPENDED => 'معلق',
            default => $this->status,
        };
    }
}
