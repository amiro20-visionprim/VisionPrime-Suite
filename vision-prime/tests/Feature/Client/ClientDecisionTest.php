<?php

declare(strict_types=1);

namespace Tests\Feature\Client;

use App\Domains\Identity\Models\Role;
use App\Domains\Organization\Models\Membership;
use App\Domains\Organization\Models\Organization;
use App\Domains\Workspace\Models\Client;
use App\Domains\Workspace\Models\ClientUserAssignment;
use App\Domains\Workspace\Models\Project;
use App\Domains\Workspace\Models\Site;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ClientDecisionTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Organization $organization;

    private Client $client;

    private Site $site;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->organization = Organization::query()->create([
            'public_id' => (string) Str::ulid(),
            'name' => 'سازمان آزمون',
            'slug' => 'organization-'.Str::lower(Str::random(8)),
            'status' => 'active',
        ]);

        $this->user = User::factory()->create();
        Membership::query()->create([
            'organization_id' => $this->organization->getKey(),
            'user_id' => $this->user->getKey(),
            'role_id' => Role::query()->where('key', 'client-viewer')->valueOrFail('id'),
            'status' => 'active',
        ]);

        $this->client = Client::query()->create([
            'organization_id' => $this->organization->getKey(),
            'public_id' => (string) Str::ulid(),
            'name' => 'مشتری آزمون',
            'status' => 'active',
        ]);

        ClientUserAssignment::query()->create([
            'client_id' => $this->client->getKey(),
            'user_id' => $this->user->getKey(),
            'portal_role' => 'viewer',
        ]);

        $project = Project::query()->create([
            'organization_id' => $this->organization->getKey(),
            'client_id' => $this->client->getKey(),
            'public_id' => (string) Str::ulid(),
            'name' => 'پروژه آزمون',
            'status' => 'active',
        ]);

        $this->site = Site::query()->create([
            'organization_id' => $this->organization->getKey(),
            'project_id' => $project->getKey(),
            'public_id' => (string) Str::ulid(),
            'name' => 'سایت آزمون',
            'canonical_url' => 'https://test.example.ir',
            'status' => 'active',
        ]);
    }

    public function test_client_can_approve_pending_command(): void
    {
        $commandId = $this->createPendingCommand($this->site->getKey());

        $this->actingAs($this->user)
            ->post("/client/decisions/commands/{$commandId}", ['decision' => 'approved'])
            ->assertRedirect();

        $this->assertSame('approved', \DB::table('commands')->where('id', $commandId)->value('status'));
        $this->assertDatabaseHas('command_approvals', [
            'command_id' => $commandId,
            'reviewer_id' => $this->user->getKey(),
            'decision' => 'approved',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $this->organization->getKey(),
            'actor_id' => $this->user->getKey(),
            'action' => 'command.approval_decided',
        ]);
    }

    public function test_client_can_reject_pending_command(): void
    {
        $commandId = $this->createPendingCommand($this->site->getKey());

        $this->actingAs($this->user)
            ->post("/client/decisions/commands/{$commandId}", ['decision' => 'rejected'])
            ->assertRedirect();

        $this->assertSame('cancelled', \DB::table('commands')->where('id', $commandId)->value('status'));
    }

    public function test_client_cannot_decide_command_of_another_client(): void
    {
        $otherClient = Client::query()->create([
            'organization_id' => $this->organization->getKey(),
            'public_id' => (string) Str::ulid(),
            'name' => 'مشتری دیگر',
            'status' => 'active',
        ]);
        $otherProject = Project::query()->create([
            'organization_id' => $this->organization->getKey(),
            'client_id' => $otherClient->getKey(),
            'public_id' => (string) Str::ulid(),
            'name' => 'پروژه دیگر',
            'status' => 'active',
        ]);
        $otherSite = Site::query()->create([
            'organization_id' => $this->organization->getKey(),
            'project_id' => $otherProject->getKey(),
            'public_id' => (string) Str::ulid(),
            'name' => 'سایت دیگر',
            'canonical_url' => 'https://other.example.ir',
            'status' => 'active',
        ]);
        $commandId = $this->createPendingCommand($otherSite->getKey());

        $this->actingAs($this->user)
            ->post("/client/decisions/commands/{$commandId}", ['decision' => 'approved'])
            ->assertNotFound();

        $this->assertSame('pending_approval', \DB::table('commands')->where('id', $commandId)->value('status'));
    }

    public function test_client_cannot_decide_already_decided_command(): void
    {
        $commandId = $this->createPendingCommand($this->site->getKey());
        \DB::table('commands')->where('id', $commandId)->update(['status' => 'approved']);

        $this->actingAs($this->user)
            ->post("/client/decisions/commands/{$commandId}", ['decision' => 'approved'])
            ->assertNotFound();
    }

    public function test_command_decision_requires_valid_value(): void
    {
        $commandId = $this->createPendingCommand($this->site->getKey());

        $this->actingAs($this->user)
            ->post("/client/decisions/commands/{$commandId}", ['decision' => 'maybe'])
            ->assertSessionHasErrors('decision');

        $this->assertSame('pending_approval', \DB::table('commands')->where('id', $commandId)->value('status'));
    }

    public function test_client_can_approve_pending_review(): void
    {
        $reviewId = $this->createPendingReview($this->site->getKey());

        $this->actingAs($this->user)
            ->post("/client/decisions/reviews/{$reviewId}", ['decision' => 'approved'])
            ->assertRedirect();

        $this->assertSame('approved', \DB::table('review_items')->where('id', $reviewId)->value('status'));
        $this->assertDatabaseHas('review_decisions', [
            'review_item_id' => $reviewId,
            'decided_by' => $this->user->getKey(),
            'decision' => 'approved',
        ]);
    }

    public function test_client_can_request_changes_on_review(): void
    {
        $reviewId = $this->createPendingReview($this->site->getKey());

        $this->actingAs($this->user)
            ->post("/client/decisions/reviews/{$reviewId}", ['decision' => 'changes_requested'])
            ->assertRedirect();

        $this->assertSame('changes_requested', \DB::table('review_items')->where('id', $reviewId)->value('status'));
    }

    public function test_client_cannot_decide_review_of_another_client(): void
    {
        $otherClient = Client::query()->create([
            'organization_id' => $this->organization->getKey(),
            'public_id' => (string) Str::ulid(),
            'name' => 'مشتری دیگر',
            'status' => 'active',
        ]);
        $otherProject = Project::query()->create([
            'organization_id' => $this->organization->getKey(),
            'client_id' => $otherClient->getKey(),
            'public_id' => (string) Str::ulid(),
            'name' => 'پروژه دیگر',
            'status' => 'active',
        ]);
        $otherSite = Site::query()->create([
            'organization_id' => $this->organization->getKey(),
            'project_id' => $otherProject->getKey(),
            'public_id' => (string) Str::ulid(),
            'name' => 'سایت دیگر',
            'canonical_url' => 'https://other.example.ir',
            'status' => 'active',
        ]);
        $reviewId = $this->createPendingReview($otherSite->getKey());

        $this->actingAs($this->user)
            ->post("/client/decisions/reviews/{$reviewId}", ['decision' => 'approved'])
            ->assertNotFound();
    }

    private function createPendingCommand(int $siteId): int
    {
        return \DB::table('commands')->insertGetId([
            'site_id' => $siteId,
            'source_type' => 'recommendation',
            'source_id' => null,
            'type' => 'update_meta_title',
            'risk_tier' => 'R2',
            'payload' => json_encode(['title' => 'عنوان جدید']),
            'idempotency_key' => (string) Str::uuid(),
            'status' => 'pending_approval',
            'expires_at' => now()->addDays(7),
            'policy_version' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createPendingReview(int $siteId): int
    {
        return \DB::table('review_items')->insertGetId([
            'site_id' => $siteId,
            'subject_type' => 'money_page_audit',
            'subject_id' => 1,
            'status' => 'pending_review',
            'due_at' => now()->addDays(3),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
