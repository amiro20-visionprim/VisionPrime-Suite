<?php

declare(strict_types=1);

namespace App\Domains\Content\Models;

use App\Domains\Workspace\Models\Site;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContentDraft extends Model
{
    protected $fillable = [
        'site_id',
        'title',
        'slug',
        'content',
        'meta_title',
        'meta_description',
        'schemas',
        'quality_score',
        'subtype',
        'model_used',
        'status',
    ];

    protected $casts = [
        'schemas' => 'array',
        'quality_score' => 'integer',
    ];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function scopeForSite($query, int $siteId)
    {
        return $query->where('site_id', $siteId);
    }

    public function scopeWithStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopeForOrganization($query, int $organizationId)
    {
        return $query->whereHas('site', fn ($q) => $q->where('organization_id', $organizationId));
    }
}
