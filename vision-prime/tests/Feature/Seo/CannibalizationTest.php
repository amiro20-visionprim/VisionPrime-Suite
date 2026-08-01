<?php

declare(strict_types=1);

namespace Tests\Feature\Seo;

use App\Domains\Organization\Models\Organization;
use App\Domains\Seo\Actions\DetectCannibalization;
use App\Domains\Workspace\Models\Client;
use App\Domains\Workspace\Models\Project;
use App\Domains\Workspace\Models\Site;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CannibalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_same_query_competing_on_multiple_urls_creates_opportunity(): void
    {
        $o = Organization::create(['public_id' => (string) Str::ulid(), 'name' => 'O', 'slug' => 'o', 'status' => 'active']);
        $c = Client::create(['organization_id' => $o->id, 'public_id' => (string) Str::ulid(), 'name' => 'C', 'status' => 'active']);
        $p = Project::create(['organization_id' => $o->id, 'client_id' => $c->id, 'public_id' => (string) Str::ulid(), 'name' => 'P', 'status' => 'active']);
        $s = Site::create(['organization_id' => $o->id, 'project_id' => $p->id, 'public_id' => (string) Str::ulid(), 'name' => 'S', 'canonical_url' => 'https://e.ir', 'status' => 'active']);
        $a = \DB::table('gsc_accounts')->insertGetId(['organization_id' => $o->id, 'google_subject' => 'x', 'email' => 'x@y', 'token_ciphertext' => 'x', 'status' => 'connected', 'created_at' => now(), 'updated_at' => now()]);
        $property = \DB::table('gsc_properties')->insertGetId(['site_id' => $s->id, 'gsc_account_id' => $a, 'property_uri' => 'https://e.ir', 'property_type' => 'url', 'created_at' => now(), 'updated_at' => now()]);
        foreach (['https://e.ir/a', 'https://e.ir/b'] as $url) {
            \DB::table('gsc_query_page_metrics')->insert(['gsc_property_id' => $property, 'date' => '2026-01-01', 'query' => 'بهترین محصول', 'page_url' => $url, 'clicks' => 1, 'impressions' => 20, 'ctr' => .05, 'position' => 5]);
        }app(DetectCannibalization::class)->handle($s);
        $this->assertDatabaseHas('opportunities', ['site_id' => $s->id, 'type' => 'cannibalization', 'status' => 'open']);
    }
}
