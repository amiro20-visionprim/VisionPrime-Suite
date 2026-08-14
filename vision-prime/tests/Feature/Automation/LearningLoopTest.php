<?php

declare(strict_types=1);

namespace Tests\Feature\Automation;

use App\Domains\Automation\Jobs\LearningLoop;
use App\Domains\Organization\Models\Organization;
use App\Domains\Workspace\Models\Client;
use App\Domains\Workspace\Models\Project;
use App\Domains\Workspace\Models\Site;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class LearningLoopTest extends TestCase
{
    use RefreshDatabase;

    public function test_learning_loop_aggregates_success_rate_per_type(): void
    {
        $o = Organization::create(['public_id' => (string) Str::ulid(), 'name' => 'O', 'slug' => 'o', 'status' => 'active']);
        $c = Client::create(['organization_id' => $o->id, 'public_id' => (string) Str::ulid(), 'name' => 'C', 'status' => 'active']);
        $p = Project::create(['organization_id' => $o->id, 'client_id' => $c->id, 'public_id' => (string) Str::ulid(), 'name' => 'P', 'status' => 'active']);
        $site = Site::create(['organization_id' => $o->id, 'project_id' => $p->id, 'public_id' => (string) Str::ulid(), 'name' => 'S', 'canonical_url' => 'https://e.ir', 'status' => 'active']);
        foreach (['executed', 'executed', 'rolled_back', 'executed'] as $i => $status) {
            \DB::table('commands')->insert([
                'site_id' => $site->id, 'source_type' => 'test', 'type' => 'update_meta_title', 'risk_tier' => 'R1',
                'payload' => json_encode([]), 'idempotency_key' => (string) Str::uuid(), 'status' => $status,
                'confidence_score' => 85, 'decision_source' => 'policy',
                'expires_at' => now()->addHour(), 'created_at' => now()->subDays($i), 'updated_at' => now(),
            ]);
        }

        (new LearningLoop(siteId: $site->id))->handle();

        $this->assertDatabaseHas('automation_learning_history', [
            'site_id' => $site->id, 'command_type' => 'update_meta_title', 'total' => 4, 'successful' => 3,
        ]);
    }

    public function test_learning_loop_is_idempotent_and_upserts(): void
    {
        $o = Organization::create(['public_id' => (string) Str::ulid(), 'name' => 'O', 'slug' => 'o', 'status' => 'active']);
        $c = Client::create(['organization_id' => $o->id, 'public_id' => (string) Str::ulid(), 'name' => 'C', 'status' => 'active']);
        $p = Project::create(['organization_id' => $o->id, 'client_id' => $c->id, 'public_id' => (string) Str::ulid(), 'name' => 'P', 'status' => 'active']);
        $site = Site::create(['organization_id' => $o->id, 'project_id' => $p->id, 'public_id' => (string) Str::ulid(), 'name' => 'S', 'canonical_url' => 'https://e.ir', 'status' => 'active']);
        \DB::table('commands')->insert([
            'site_id' => $site->id, 'source_type' => 'test', 'type' => 'update_meta_description', 'risk_tier' => 'R1',
            'payload' => json_encode([]), 'idempotency_key' => (string) Str::uuid(), 'status' => 'executed',
            'expires_at' => now()->addHour(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        (new LearningLoop(siteId: $site->id))->handle();
        (new LearningLoop(siteId: $site->id))->handle();

        $this->assertSame(
            1,
            \DB::table('automation_learning_history')->where('site_id', $site->id)->where('command_type', 'update_meta_description')->count(),
        );
        $this->assertDatabaseHas('automation_learning_history', [
            'site_id' => $site->id, 'command_type' => 'update_meta_description', 'total' => 1, 'successful' => 1,
        ]);
    }
}
