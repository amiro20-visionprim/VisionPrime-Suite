<?php

declare(strict_types=1);

namespace Tests\Feature\Automation;

use App\Domains\Automation\Actions\ConvertRecommendationToCommand;
use App\Domains\Automation\Services\CommandConfidenceAssessor;
use App\Domains\Organization\Models\Organization;
use App\Domains\Seo\Models\Recommendation;
use App\Domains\Workspace\Models\Client;
use App\Domains\Workspace\Models\Project;
use App\Domains\Workspace\Models\Site;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Tests\TestCase;

class CommandConfidenceAssessorTest extends TestCase
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
    }

    public function test_assessor_combines_fresh_gsc_and_opportunity_confidence(): void
    {
        $accountId = \DB::table('gsc_accounts')->insertGetId(['organization_id' => $this->site->organization_id, 'google_subject' => 'sub', 'email' => 'a@b.ir', 'token_ciphertext' => Crypt::encryptString('t'), 'status' => 'connected', 'created_at' => now(), 'updated_at' => now()]);
        $propertyId = \DB::table('gsc_properties')->insertGetId(['site_id' => $this->site->id, 'gsc_account_id' => $accountId, 'property_uri' => 'sc-domain:e.ir', 'property_type' => 'sc-domain', 'status' => 'selected', 'created_at' => now(), 'updated_at' => now()]);
        \DB::table('gsc_import_runs')->insert(['gsc_property_id' => $propertyId, 'date_start' => now()->subDays(6)->toDateString(), 'date_end' => now()->toDateString(), 'status' => 'completed', 'finished_at' => now()->subHours(2), 'created_at' => now(), 'updated_at' => now()]);
        $opportunityId = \DB::table('opportunities')->insertGetId(['site_id' => $this->site->id, 'type' => 'ctr_gap', 'score' => 88, 'confidence' => 0.9, 'status' => 'open', 'explanation' => 'شکاف CTR', 'created_at' => now(), 'updated_at' => now()]);
        $recommendation = Recommendation::create(['site_id' => $this->site->id, 'source_type' => 'opportunity', 'source_id' => $opportunityId, 'title' => 't', 'body' => 'b', 'priority' => 'high', 'status' => 'active']);

        $result = app(CommandConfidenceAssessor::class)->assess($recommendation, 'update_meta_title');

        $this->assertGreaterThanOrEqual(80, $result['score']);
        $this->assertSame(1.0, $result['factors']['gsc_freshness']);
        $this->assertGreaterThanOrEqual(0.9, $result['factors']['signal_strength']);
        $this->assertNull($result['factors']['history']);
    }

    public function test_conversion_persists_confidence_on_command(): void
    {
        $opportunityId = \DB::table('opportunities')->insertGetId(['site_id' => $this->site->id, 'type' => 'ctr_gap', 'score' => 80, 'confidence' => 0.85, 'status' => 'open', 'explanation' => 'شکاف CTR', 'created_at' => now(), 'updated_at' => now()]);
        $recommendation = Recommendation::create(['site_id' => $this->site->id, 'source_type' => 'opportunity', 'source_id' => $opportunityId, 'title' => 't', 'body' => 'b', 'priority' => 'medium', 'status' => 'active']);

        $commandId = app(ConvertRecommendationToCommand::class)->handle($recommendation, 'update_meta_title', 'https://e.ir/x', 'عنوان جدید');

        $command = \DB::table('commands')->where('id', $commandId)->first();
        $this->assertNotNull($command->confidence_score);
        $this->assertGreaterThanOrEqual(0, (int) $command->confidence_score);
        $factors = json_decode((string) $command->confidence_factors, true);
        $this->assertIsArray($factors);
        $this->assertArrayHasKey('data_quality', $factors);
        $this->assertArrayHasKey('signal_strength', $factors);
    }
}
