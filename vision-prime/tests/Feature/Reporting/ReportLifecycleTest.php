<?php

declare(strict_types=1);

namespace Tests\Feature\Reporting;

use App\Domains\Automation\Actions\ExecuteCommand;
use App\Domains\Identity\Models\Role;
use App\Domains\Organization\Models\Membership;
use App\Domains\Organization\Models\Organization;
use App\Domains\Workspace\Models\Client;
use App\Domains\Workspace\Models\Project;
use App\Domains\Workspace\Models\Site;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReportLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function setUpWorkspace(): array
    {
        $organization = Organization::query()->create(['public_id' => (string) Str::ulid(), 'name' => 'R', 'slug' => 'r-'.Str::random(5), 'status' => 'active']);
        $admin = User::factory()->create();
        $clientUser = User::factory()->create();
        Membership::query()->create(['organization_id' => $organization->id, 'user_id' => $admin->id, 'role_id' => Role::query()->where('key', 'agency-admin')->valueOrFail('id'), 'status' => 'active']);
        Membership::query()->create(['organization_id' => $organization->id, 'user_id' => $clientUser->id, 'role_id' => Role::query()->where('key', 'client-approver')->valueOrFail('id'), 'status' => 'active']);
        $client = Client::query()->create(['organization_id' => $organization->id, 'public_id' => (string) Str::ulid(), 'name' => 'C', 'status' => 'active']);
        $project = Project::query()->create(['organization_id' => $organization->id, 'client_id' => $client->id, 'public_id' => (string) Str::ulid(), 'name' => 'P', 'status' => 'active']);
        $site = Site::query()->create(['organization_id' => $organization->id, 'project_id' => $project->id, 'public_id' => (string) Str::ulid(), 'name' => 'S', 'canonical_url' => 'https://e.ir', 'status' => 'active']);
        \DB::table('client_user_assignments')->insert(['client_id' => $client->id, 'user_id' => $clientUser->id, 'created_at' => now(), 'updated_at' => now()]);

        return [$organization, $admin, $clientUser, $client, $site];
    }

    public function test_agency_can_create_and_publish_report(): void
    {
        [$organization, $admin, , , $site] = $this->setUpWorkspace();

        $this->actingAs($admin)->withSession(['current_organization_id' => $organization->id])
            ->post('/app/reports', [
                'site_id' => $site->id,
                'type' => 'executive_seo_summary',
                'period_start' => '2026-07-01',
                'period_end' => '2026-07-31',
            ])
            ->assertRedirect();

        $report = \DB::table('reports')->where('site_id', $site->id)->first();
        $this->assertNotNull($report);
        $this->assertSame('draft', $report->status);

        $this->actingAs($admin)->withSession(['current_organization_id' => $organization->id])
            ->post("/app/reports/{$report->id}/publish")
            ->assertRedirect();

        $this->assertDatabaseHas('reports', ['id' => $report->id, 'status' => 'published']);
        $this->assertNotNull(\DB::table('reports')->where('id', $report->id)->value('published_at'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'report.published']);
    }

    public function test_client_sees_only_published_reports(): void
    {
        [$organization, , $clientUser, , $site] = $this->setUpWorkspace();

        \DB::table('reports')->insert([
            ['site_id' => $site->id, 'type' => 'executive_seo_summary', 'period_start' => '2026-07-01', 'period_end' => '2026-07-31', 'status' => 'draft', 'content' => json_encode(['opportunities' => 2, 'high_risks' => 1, 'recommendations' => 3, 'impact_events' => 0]), 'published_at' => null, 'created_at' => now(), 'updated_at' => now()],
            ['site_id' => $site->id, 'type' => 'growth_report', 'period_start' => '2026-07-01', 'period_end' => '2026-07-31', 'status' => 'published', 'content' => json_encode(['opportunities' => 4, 'high_risks' => 0, 'recommendations' => 5, 'impact_events' => 2]), 'published_at' => now(), 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->actingAs($clientUser)->withSession(['current_client_id' => Client::first()->id])
            ->get('/client/reports')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Client/Reports')
                ->has('reports', 1)
                ->where('reports.0.type', 'growth_report'));
    }

    public function test_cannot_publish_report_of_another_organization(): void
    {
        [$organization, $admin] = $this->setUpWorkspace();

        $foreignOrg = Organization::query()->create(['public_id' => (string) Str::ulid(), 'name' => 'F', 'slug' => 'f-'.Str::random(5), 'status' => 'active']);
        $foreignClient = Client::query()->create(['organization_id' => $foreignOrg->id, 'public_id' => (string) Str::ulid(), 'name' => 'FC', 'status' => 'active']);
        $foreignProject = Project::query()->create(['organization_id' => $foreignOrg->id, 'client_id' => $foreignClient->id, 'public_id' => (string) Str::ulid(), 'name' => 'FP', 'status' => 'active']);
        $foreignSite = Site::query()->create(['organization_id' => $foreignOrg->id, 'project_id' => $foreignProject->id, 'public_id' => (string) Str::ulid(), 'name' => 'FS', 'canonical_url' => 'https://f.ir', 'status' => 'active']);
        $reportId = \DB::table('reports')->insertGetId(['site_id' => $foreignSite->id, 'type' => 'x', 'period_start' => '2026-07-01', 'period_end' => '2026-07-31', 'status' => 'draft', 'created_at' => now(), 'updated_at' => now()]);

        $this->actingAs($admin)->withSession(['current_organization_id' => $organization->id])
            ->post("/app/reports/{$reportId}/publish")
            ->assertNotFound();

        $this->assertDatabaseHas('reports', ['id' => $reportId, 'status' => 'draft']);
    }

    public function test_command_execution_records_impact_event(): void
    {
        Http::fake([
            'wp.test/*' => Http::response(['status' => 'ack', 'result' => ['post_id' => 4, 'previous' => 'old', 'new' => 'new title']], 200),
        ]);

        [$organization, $admin, , , $site] = $this->setUpWorkspace();

        \DB::table('site_connections')->insert([
            'site_id' => $site->id,
            'status' => 'connected',
            'platform_url' => 'https://wp.test',
            'secret_ciphertext' => Crypt::encryptString('secret'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $commandId = \DB::table('commands')->insertGetId([
            'site_id' => $site->id,
            'source_type' => 'recommendation',
            'type' => 'update_meta_title',
            'risk_tier' => 'R2',
            'payload' => json_encode(['url' => 'https://wp.test/seo-services/', 'title' => 'new title']),
            'idempotency_key' => (string) Str::uuid(),
            'status' => 'approved',
            'expires_at' => now()->addHour(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $result = app(ExecuteCommand::class)->handle($commandId);

        $this->assertSame(200, $result['http_status']);
        $this->assertDatabaseHas('commands', ['id' => $commandId, 'status' => 'executed']);
        $this->assertDatabaseHas('impact_events', [
            'site_id' => $site->id,
            'source_type' => 'command',
            'source_id' => $commandId,
        ]);
        $event = \DB::table('impact_events')->where('source_id', $commandId)->first();
        $baseline = json_decode($event->baseline, true);
        $this->assertSame('update_meta_title', $baseline['command_type']);
        $this->assertSame('https://wp.test/seo-services/', $baseline['target_url']);
    }
}
