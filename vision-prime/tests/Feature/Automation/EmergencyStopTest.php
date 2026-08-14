<?php

declare(strict_types=1);

namespace Tests\Feature\Automation;

use App\Domains\Automation\Actions\DispatchCommand;
use App\Domains\Automation\Actions\EmergencyStop;
use App\Domains\Automation\Actions\ResumeAutomation;
use App\Domains\Organization\Models\Organization;
use App\Domains\Workspace\Models\Client;
use App\Domains\Workspace\Models\Project;
use App\Domains\Workspace\Models\Site;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class EmergencyStopTest extends TestCase
{
    use RefreshDatabase;

    private Site $site;

    protected function setUp(): void
    {
        parent::setUp();
        $o = Organization::create(['public_id' => (string) Str::ulid(), 'name' => 'O', 'slug' => 'o', 'status' => 'active']);
        $c = Client::create(['organization_id' => $o->id, 'public_id' => (string) Str::ulid(), 'name' => 'C', 'status' => 'active']);
        $p = Project::create(['organization_id' => $o->id, 'client_id' => $c->id, 'public_id' => (string) Str::ulid(), 'name' => 'P', 'status' => 'active']);
        $this->site = Site::create(['organization_id' => $o->id, 'project_id' => $p->id, 'public_id' => (string) Str::ulid(), 'name' => 'S', 'canonical_url' => 'https://e.ir', 'status' => 'active']);
        \DB::table('site_automation_policies')->insert(['site_id' => $this->site->id, 'level' => 2, 'rules' => '{}', 'created_at' => now(), 'updated_at' => now()]);
        \DB::table('site_connections')->insert(['site_id' => $this->site->id, 'status' => 'connected', 'platform_url' => 'https://wp.test', 'secret_ciphertext' => Crypt::encryptString('secret'), 'created_at' => now(), 'updated_at' => now()]);
    }

    private function command(string $status): int
    {
        return (int) \DB::table('commands')->insertGetId([
            'site_id' => $this->site->id, 'source_type' => 'test', 'type' => 'update_meta_title',
            'risk_tier' => 'R1', 'payload' => json_encode(['title' => 'new']),
            'idempotency_key' => (string) Str::uuid(), 'status' => $status,
            'expires_at' => now()->addHour(), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_emergency_stop_cancels_queued_commands_and_keeps_pending_human(): void
    {
        $queued = $this->command('queued');
        $pending = $this->command('pending_approval');

        app(EmergencyStop::class)->handle($this->site->id);

        $this->assertNotNull(\DB::table('site_automation_policies')->where('site_id', $this->site->id)->value('emergency_stopped_at'));
        $this->assertDatabaseHas('commands', ['id' => $queued, 'status' => 'cancelled']);
        $this->assertDatabaseHas('commands', ['id' => $pending, 'status' => 'pending_approval']);
    }

    public function test_resume_clears_emergency_stop(): void
    {
        app(EmergencyStop::class)->handle($this->site->id);

        app(ResumeAutomation::class)->handle($this->site->id);

        $this->assertNull(\DB::table('site_automation_policies')->where('site_id', $this->site->id)->value('emergency_stopped_at'));
    }

    public function test_dispatch_is_blocked_while_emergency_stopped(): void
    {
        $id = $this->command('approved');
        app(EmergencyStop::class)->handle($this->site->id);

        $this->expectException(HttpException::class);
        app(DispatchCommand::class)->handle($id);
    }
}
