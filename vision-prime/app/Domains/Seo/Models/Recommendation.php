<?php

declare(strict_types=1);

namespace App\Domains\Seo\Models;

use App\Domains\Workspace\Models\Site;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Recommendation extends Model
{
    protected $table = 'recommendations';

    protected $fillable = [
        'site_id',
        'source_type',
        'source_id',
        'title',
        'body',
        'priority',
        'status',
        'owner_id',
        'due_at',
    ];

    protected function casts(): array
    {
        return [
            'source_id' => 'integer',
            'owner_id' => 'integer',
            'due_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Site, $this> */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }
}
