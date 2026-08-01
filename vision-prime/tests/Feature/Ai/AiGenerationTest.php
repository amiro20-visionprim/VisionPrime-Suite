<?php

declare(strict_types=1);

namespace Tests\Feature\Ai;

use App\Domains\Ai\Actions\CreateAiGeneration;
use App\Domains\Organization\Models\Organization;
use App\Domains\Workspace\Models\Client;
use App\Domains\Workspace\Models\Project;
use App\Domains\Workspace\Models\Site;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AiGenerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_generation_starts_needing_review_with_version(): void
    {
        $o = Organization::create(['public_id' => (string) Str::ulid(), 'name' => 'O', 'slug' => 'o', 'status' => 'active']);
        $c = Client::create(['organization_id' => $o->id, 'public_id' => (string) Str::ulid(), 'name' => 'C', 'status' => 'active']);
        $p = Project::create(['organization_id' => $o->id, 'client_id' => $c->id, 'public_id' => (string) Str::ulid(), 'name' => 'P', 'status' => 'active']);
        $s = Site::create(['organization_id' => $o->id, 'project_id' => $p->id, 'public_id' => (string) Str::ulid(), 'name' => 'S', 'canonical_url' => 'https://e.ir', 'status' => 'active']);
        $id = app(CreateAiGeneration::class)->handle($s, null, ['query' => 'خرید'], ['title' => 'عنوان پیشنهادی'], ['input_tokens' => 10]);
        $this->assertDatabaseHas('ai_generations', ['id' => $id, 'output_status' => 'needs_review']);
        $this->assertDatabaseHas('ai_generation_versions', ['generation_id' => $id, 'version' => 1, 'status' => 'needs_review']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'ai.generation_created']);
    }
}
