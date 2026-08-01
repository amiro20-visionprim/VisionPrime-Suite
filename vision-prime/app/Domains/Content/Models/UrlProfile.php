<?php

declare(strict_types=1);

namespace App\Domains\Content\Models;

use App\Domains\Workspace\Models\Site;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UrlProfile extends Model
{
    protected $fillable = ['site_id', 'public_id', 'external_content_id', 'canonical_url', 'slug', 'content_type', 'post_status', 'metadata', 'current_hash', 'last_synced_at'];

    protected function casts(): array
    {
        return ['metadata' => 'array', 'last_synced_at' => 'immutable_datetime'];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function snapshots(): HasMany
    {
        return $this->hasMany(ContentSnapshot::class);
    }
}
