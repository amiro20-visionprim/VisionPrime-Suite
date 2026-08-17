<?php

declare(strict_types=1);

namespace App\Domains\Platform\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Plan extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'key', 'name', 'description', 'price_monthly', 'price_yearly', 'currency',
        'limits', 'features', 'is_active', 'sort',
    ];

    protected function casts(): array
    {
        return [
            'price_monthly' => 'int',
            'price_yearly' => 'int',
            'limits' => 'array',
            'features' => 'array',
            'is_active' => 'bool',
        ];
    }

    public function isActive(): bool
    {
        return $this->is_active;
    }

    /** @return array<string, mixed> */
    public function limits(): array
    {
        return is_array($this->limits) ? $this->limits : [];
    }

    /** @return array<string, mixed> */
    public function features(): array
    {
        return is_array($this->features) ? $this->features : [];
    }

    /** @return HasMany<Subscription, $this> */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }
}
