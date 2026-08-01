<?php

declare(strict_types=1);

namespace Tests\Feature\Seo;

use App\Domains\Organization\Models\Organization;
use App\Domains\Seo\Actions\AuditMoneyPages;
use App\Domains\Workspace\Models\Client;
use App\Domains\Workspace\Models\Project;
use App\Domains\Workspace\Models\Site;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class MoneyPageAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_money_page_with_missing_meta_is_audited(): void
    {
        $o = Organization::create(['public_id' => (string) Str::ulid(), 'name' => 'O', 'slug' => 'o', 'status' => 'active']);
        $c = Client::create(['organization_id' => $o->id, 'public_id' => (string) Str::ulid(), 'name' => 'C', 'status' => 'active']);
        $p = Project::create(['organization_id' => $o->id, 'client_id' => $c->id, 'public_id' => (string) Str::ulid(), 'name' => 'P', 'status' => 'active']);
        $s = Site::create(['organization_id' => $o->id, 'project_id' => $p->id, 'public_id' => (string) Str::ulid(), 'name' => 'S', 'canonical_url' => 'https://e.ir', 'status' => 'active']);
        $profile = \DB::table('url_profiles')->insertGetId(['site_id' => $s->id, 'public_id' => (string) Str::ulid(), 'canonical_url' => 'https://e.ir/services', 'content_type' => 'page', 'post_status' => 'publish', 'metadata' => json_encode([]), 'created_at' => now(), 'updated_at' => now()]);
        app(AuditMoneyPages::class)->handle($s);
        $this->assertDatabaseHas('money_page_audits', ['url_profile_id' => $profile, 'score' => 65]);
        $this->assertDatabaseCount('money_page_issues', 2);
    }
}
