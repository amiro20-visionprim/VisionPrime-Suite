<?php

declare(strict_types=1);

namespace App\Domains\Workspace\Models;

use App\Domains\Organization\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Client extends Model
{
    use SoftDeletes;

    protected $fillable = ['organization_id', 'public_id', 'name', 'status', 'contact', 'settings'];

    protected function casts(): array
    {
        return ['contact' => 'array', 'settings' => 'array'];
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return HasMany<Project, $this> */
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    /** @return HasMany<ClientUserAssignment, $this> */
    public function userAssignments(): HasMany
    {
        return $this->hasMany(ClientUserAssignment::class);
    }

    /** @return BelongsToMany<User, $this> */
    public function portalUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'client_user_assignments')
            ->withPivot('portal_role')
            ->withTimestamps();
    }
}
