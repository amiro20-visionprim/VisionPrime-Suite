<?php

declare(strict_types=1);

namespace Tests\Feature\Seo;

use App\Domains\Organization\Models\Organization;
use App\Domains\Seo\Actions\ScoreRevenueOpportunities;
use App\Domains\Workspace\Models\Client;
use App\Domains\Workspace\Models\Project;
use App\Domains\Workspace\Models\Site;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class OpportunityScoringTest extends TestCase
{
    use RefreshDatabase;

    public function test_revenue_opportunity_is_scored_with_explainable_factors(): void
    {
        $o = Organization::create(['public_id' => (string) Str::ulid(), 'name' => 'O', 'slug' => 'o', 'status' => 'active']);
        $c = Client::create(['organization_id' => $o->id, 'public_id' => (string) Str::ulid(), 'name' => 'C', 'status' => 'active']);
        $p = Project::create(['organization_id' => $o->id, 'client_id' => $c->id, 'public_id' => (string) Str::ulid(), 'name' => 'P', 'status' => 'active']);
        $s = Site::create(['organization_id' => $o->id, 'project_id' => $p->id, 'public_id' => (string) Str::ulid(), 'name' => 'S', 'canonical_url' => 'https://e.ir', 'status' => 'active']);
        $id = \DB::table('keyword_insights')->insertGetId(['site_id' => $s->id, 'query_normalized' => 'خرید محصول', 'latest_metrics' => json_encode(['clicks' => 10, 'impressions' => 3000, 'ctr' => .02, 'position' => 8]), 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        app(ScoreRevenueOpportunities::class)->handle($s);
        $op = \DB::table('opportunities')->where('keyword_insight_id', $id)->where('type', 'revenue_opportunity')->first();
        $this->assertGreaterThan(0, $op->score);
        $this->assertSame(3, \DB::table('opportunity_factors')->where('opportunity_id', $op->id)->count());
    }
}
