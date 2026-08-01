<?php

declare(strict_types=1);

namespace Tests\Feature\Seo;

use App\Domains\Organization\Models\Organization;
use App\Domains\Seo\Actions\MapKeywordsToUrls;
use App\Domains\Workspace\Models\Client;
use App\Domains\Workspace\Models\Project;
use App\Domains\Workspace\Models\Site;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class KeywordMappingTest extends TestCase
{
    use RefreshDatabase;

    public function test_query_is_mapped_to_url_profile_and_intent(): void
    {
        $o = Organization::create(['public_id' => (string) Str::ulid(), 'name' => 'O', 'slug' => 'o', 'status' => 'active']);
        $c = Client::create(['organization_id' => $o->id, 'public_id' => (string) Str::ulid(), 'name' => 'C', 'status' => 'active']);
        $p = Project::create(['organization_id' => $o->id, 'client_id' => $c->id, 'public_id' => (string) Str::ulid(), 'name' => 'P', 'status' => 'active']);
        $s = Site::create(['organization_id' => $o->id, 'project_id' => $p->id, 'public_id' => (string) Str::ulid(), 'name' => 'S', 'canonical_url' => 'https://e.ir', 'status' => 'active']);
        $profile = \DB::table('url_profiles')->insertGetId(['site_id' => $s->id, 'public_id' => (string) Str::ulid(), 'canonical_url' => 'https://e.ir/buy', 'content_type' => 'page', 'post_status' => 'publish', 'created_at' => now(), 'updated_at' => now()]);
        $a = \DB::table('gsc_accounts')->insertGetId(['organization_id' => $o->id, 'google_subject' => 'x', 'email' => 'x@y', 'token_ciphertext' => 'x', 'status' => 'connected', 'created_at' => now(), 'updated_at' => now()]);
        $property = \DB::table('gsc_properties')->insertGetId(['site_id' => $s->id, 'gsc_account_id' => $a, 'property_uri' => 'https://e.ir', 'property_type' => 'url', 'created_at' => now(), 'updated_at' => now()]);
        \DB::table('gsc_query_page_metrics')->insert(['gsc_property_id' => $property, 'date' => '2026-01-01', 'query' => 'خرید محصول', 'page_url' => 'https://e.ir/buy', 'clicks' => 1, 'impressions' => 10, 'ctr' => .1, 'position' => 2]);
        app(MapKeywordsToUrls::class)->handle($s);
        $this->assertDatabaseHas('keyword_insights', ['site_id' => $s->id, 'query_normalized' => 'خرید محصول', 'mapped_url_profile_id' => $profile]);
        $this->assertDatabaseHas('intent_classifications', ['intent' => 'transactional']);
    }
}
