<?php

declare(strict_types=1);

namespace Tests\Feature\Seo;

use App\Domains\Organization\Models\Organization;
use App\Domains\Seo\Actions\CreateRiskRecommendations;
use App\Domains\Seo\Actions\DetectConversionRisks;
use App\Domains\Workspace\Models\Client;
use App\Domains\Workspace\Models\Project;
use App\Domains\Workspace\Models\Site;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ConversionRiskTest extends TestCase
{
    use RefreshDatabase;

    public function test_thin_content_and_weak_cta_create_risks_and_recommendations(): void
    {
        $o = Organization::create(['public_id' => (string) Str::ulid(), 'name' => 'O', 'slug' => 'o', 'status' => 'active']);
        $c = Client::create(['organization_id' => $o->id, 'public_id' => (string) Str::ulid(), 'name' => 'C', 'status' => 'active']);
        $p = Project::create(['organization_id' => $o->id, 'client_id' => $c->id, 'public_id' => (string) Str::ulid(), 'name' => 'P', 'status' => 'active']);
        $s = Site::create(['organization_id' => $o->id, 'project_id' => $p->id, 'public_id' => (string) Str::ulid(), 'name' => 'S', 'canonical_url' => 'https://e.ir', 'status' => 'active']);
        $profile = \DB::table('url_profiles')->insertGetId(['site_id' => $s->id, 'public_id' => (string) Str::ulid(), 'canonical_url' => 'https://e.ir/services', 'content_type' => 'page', 'post_status' => 'publish', 'metadata' => json_encode([]), 'created_at' => now(), 'updated_at' => now()]);
        \DB::table('content_snapshots')->insert(['url_profile_id' => $profile, 'content_hash' => 'h', 'content' => 'short', 'word_count' => 1, 'captured_at' => now()]);
        app(DetectConversionRisks::class)->handle($s);
        app(CreateRiskRecommendations::class)->handle($s);
        $this->assertDatabaseHas('conversion_risks', ['url_profile_id' => $profile, 'key' => 'thin_content', 'severity' => 'high']);
        $this->assertDatabaseHas('conversion_risks', ['url_profile_id' => $profile, 'key' => 'weak_cta']);
        $this->assertDatabaseCount('recommendations', 3);
    }
}
