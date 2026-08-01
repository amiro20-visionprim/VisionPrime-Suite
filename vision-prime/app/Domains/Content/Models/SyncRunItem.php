<?php

declare(strict_types=1);

namespace App\Domains\Content\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SyncRunItem extends Model
{
    protected $fillable = ['sync_run_id', 'external_id', 'url', 'status', 'action', 'error'];

    protected function casts(): array
    {
        return ['error' => 'array'];
    }

    public function syncRun(): BelongsTo
    {
        return $this->belongsTo(SyncRun::class);
    }
}
