<?php

declare(strict_types=1);

namespace App\Domains\Content\Models;

use App\Domains\Workspace\Models\Site;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SyncRun extends Model
{
    protected $fillable = ['site_id', 'type', 'status', 'cursor', 'summary', 'error', 'started_at', 'finished_at'];

    protected function casts(): array
    {
        return ['summary' => 'array', 'error' => 'array', 'started_at' => 'immutable_datetime', 'finished_at' => 'immutable_datetime'];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SyncRunItem::class);
    }
}
