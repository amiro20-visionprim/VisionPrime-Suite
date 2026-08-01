<?php

declare(strict_types=1);

namespace App\Domains\Workspace\Actions;

use App\Domains\Audit\Actions\RecordAuditLog;
use App\Domains\Organization\Contracts\CurrentOrganization;
use App\Domains\Workspace\Models\Project;
use App\Domains\Workspace\Models\Site;
use App\Domains\Workspace\Services\CanonicalUrl;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateSite
{
    public function __construct(private readonly CurrentOrganization $org, private readonly CanonicalUrl $url, private readonly RecordAuditLog $audit) {}

    public function handle(Project $project, array $data): Site
    {
        $canonical = $this->url->normalize($data['canonical_url']);
        if (Site::query()->where('organization_id', $this->org->id())->where('canonical_url', $canonical)->exists()) {
            throw ValidationException::withMessages(['canonical_url' => 'این نشانی قبلاً در فضای کاری ثبت شده است.']);
        }$site = Site::query()->create(['organization_id' => $this->org->id(), 'project_id' => $project->id, 'public_id' => (string) Str::ulid(), 'name' => $data['name'], 'canonical_url' => $canonical, 'locale' => $data['locale'], 'timezone' => $data['timezone'], 'business_importance' => $data['business_importance'], 'status' => 'active']);
        $this->audit->handle(action: 'site.created', subject: $site, after: ['name' => $site->name, 'canonical_url' => $site->canonical_url, 'project_id' => $project->id]);

        return $site;
    }
}
