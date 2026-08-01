<?php

declare(strict_types=1);

namespace App\Domains\Workspace\Models;

use App\Domains\Organization\Models\Organization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Site extends Model
{
    use SoftDeletes;

    protected $fillable = ['organization_id', 'project_id', 'public_id', 'name', 'canonical_url', 'locale', 'timezone', 'business_importance', 'status', 'settings'];

    protected function casts(): array
    {
        return ['business_importance' => 'integer', 'settings' => 'array'];
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
