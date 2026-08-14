<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lead extends Model
{
    public const STATUS_NEW = 'new';

    public const STATUS_CONTACTED = 'contacted';

    public const STATUS_QUALIFIED = 'qualified';

    public const STATUS_UNQUALIFIED = 'unqualified';

    /** @var array<string, string> */
    public const STATUS_LABELS = [
        self::STATUS_NEW => 'جدید',
        self::STATUS_CONTACTED => 'تماس گرفته‌شده',
        self::STATUS_QUALIFIED => 'واجد شرایط',
        self::STATUS_UNQUALIFIED => 'رد شده',
    ];

    protected $fillable = [
        'name',
        'email',
        'company',
        'website',
        'message',
        'source',
        'status',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_term',
        'utm_content',
        'landing_page',
        'referrer',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    /** @return HasMany<LeadNote, $this> */
    public function notes(): HasMany
    {
        return $this->hasMany(LeadNote::class)->latest();
    }
}
