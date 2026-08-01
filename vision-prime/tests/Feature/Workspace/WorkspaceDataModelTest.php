<?php

declare(strict_types=1);

namespace Tests\Feature\Workspace;

use App\Domains\Organization\Models\Organization;
use App\Domains\Workspace\Models\Client;
use App\Domains\Workspace\Models\ClientUserAssignment;
use App\Domains\Workspace\Models\Project;
use App\Domains\Workspace\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class WorkspaceDataModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_workspace_models_preserve_organization_and_client_relationships(): void
    {
        $organization = Organization::query()->create([
            'public_id' => (string) Str::ulid(),
            'name' => 'آژانس آزمون',
            'slug' => 'test-agency',
            'status' => 'active',
        ]);
        $client = Client::query()->create([
            'organization_id' => $organization->getKey(),
            'public_id' => (string) Str::ulid(),
            'name' => 'مشتری نمونه',
            'status' => 'active',
        ]);
        $project = Project::query()->create([
            'organization_id' => $organization->getKey(),
            'client_id' => $client->getKey(),
            'public_id' => (string) Str::ulid(),
            'name' => 'پروژه رشد',
            'status' => 'active',
        ]);
        $site = Site::query()->create([
            'organization_id' => $organization->getKey(),
            'project_id' => $project->getKey(),
            'public_id' => (string) Str::ulid(),
            'name' => 'سایت نمونه',
            'canonical_url' => 'https://example.ir',
            'status' => 'active',
        ]);
        $clientUser = User::factory()->create();
        ClientUserAssignment::query()->create([
            'client_id' => $client->getKey(),
            'user_id' => $clientUser->getKey(),
            'portal_role' => 'viewer',
        ]);

        $this->assertTrue($organization->clients()->whereKey($client)->exists());
        $this->assertTrue($client->projects()->whereKey($project)->exists());
        $this->assertTrue($project->sites()->whereKey($site)->exists());
        $this->assertTrue($clientUser->assignedClients()->whereKey($client)->exists());
        $site->refresh();
        $this->assertSame('Asia/Tehran', $site->timezone);
        $this->assertSame(3, $site->business_importance);
    }
}
