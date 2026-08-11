<?php

declare(strict_types=1);

namespace Tests\Feature\Marketing;

use App\Domains\Identity\Models\Role;
use App\Domains\Marketing\Actions\ScoreLead;
use App\Domains\Marketing\Services\NotifyMarketingTeam;
use App\Domains\Organization\Models\Membership;
use App\Domains\Organization\Models\Organization;
use App\Models\Lead;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class LeadScoringAndNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_high_intent_paid_lead_gets_high_score(): void
    {
        $lead = Lead::query()->create([
            'name' => 'آژانس بزرگ',
            'email' => 'agency@test.ir',
            'company' => 'آژانس بزرگ',
            'website' => 'https://agency.ir',
            'message' => 'ما ۱۵ سایت مشتری داریم و دنبال پلن آژانس با برند اختصاصی و وایتدلیبل هستیم.',
            'source' => 'demo',
            'status' => 'new',
            'utm_source' => 'google',
            'utm_medium' => 'cpc',
            'utm_campaign' => 'agency_launch',
            'landing_page' => '/pricing',
            'metadata' => ['device' => 'desktop'],
        ]);

        app(ScoreLead::class)->handle($lead);

        $this->assertGreaterThanOrEqual(80, $lead->fresh()->score);

        $fresh = $lead->fresh();
        $breakdown = $fresh->metadata['score_breakdown'] ?? [];
        $this->assertArrayHasKey('total', $breakdown);
        $this->assertNotEmpty($breakdown['items']);
        $this->assertLessThanOrEqual(100, $breakdown['total']);
        $this->assertSame($fresh->score, $breakdown['total']);
    }

    public function test_low_intent_support_lead_gets_low_score(): void
    {
        $lead = Lead::query()->create([
            'name' => 'کاربر تست',
            'email' => null,
            'message' => 'سلام',
            'source' => 'support',
            'status' => 'new',
            'metadata' => ['contact' => '0912', 'device' => 'desktop'],
        ]);

        app(ScoreLead::class)->handle($lead);

        $this->assertLessThanOrEqual(25, $lead->fresh()->score);
    }

    public function test_new_lead_notifies_marketing_team_members(): void
    {
        $organization = $this->organization();
        $marketingUser = User::factory()->create();
        $otherUser = User::factory()->create();
        $this->membership($marketingUser, $organization, 'marketing-manager');
        $this->membership($otherUser, $organization, 'client-approver');

        $lead = Lead::query()->create([
            'name' => 'لید اعلان',
            'email' => 'notify@test.ir',
            'source' => 'demo',
            'status' => 'new',
            'metadata' => ['device' => 'desktop'],
        ]);

        app(NotifyMarketingTeam::class)->handle($lead);

        $this->assertSame(1, $marketingUser->notifications()->count());
        $this->assertSame(0, $otherUser->notifications()->count());

        $data = $marketingUser->notifications()->first()->data;
        $this->assertSame($lead->getKey(), $data['lead_id']);
        $this->assertSame('لید اعلان', $data['lead_name']);
    }

    public function test_notification_endpoints_list_and_mark_read(): void
    {
        $organization = $this->organization();
        $user = User::factory()->create();
        $this->membership($user, $organization, 'marketing-manager');

        $lead = Lead::query()->create([
            'name' => 'لید اعلان',
            'email' => 'notify2@test.ir',
            'source' => 'demo',
            'status' => 'new',
            'metadata' => ['device' => 'desktop'],
        ]);

        app(NotifyMarketingTeam::class)->handle($lead);

        $notification = $user->notifications()->first();

        $this->actingAs($user)
            ->getJson('/app/notifications')
            ->assertOk()
            ->assertJsonPath('unreadCount', 1)
            ->assertJsonPath('notifications.0.leadName', 'لید اعلان');

        $this->actingAs($user)
            ->putJson("/app/notifications/{$notification->getKey()}/read")
            ->assertOk()
            ->assertJsonPath('unreadCount', 0);

        $this->actingAs($user)
            ->putJson('/app/notifications/read-all')
            ->assertOk();
    }

    public function test_marketing_dashboard_sorts_by_score(): void
    {
        $organization = $this->organization();
        $user = User::factory()->create();
        $this->membership($user, $organization, 'marketing-manager');

        $low = Lead::query()->create(['name' => 'کم', 'email' => 'low@test.ir', 'source' => 'support', 'status' => 'new', 'metadata' => ['device' => 'desktop']]);
        $high = Lead::query()->create(['name' => 'زیاد', 'email' => 'high@test.ir', 'source' => 'demo', 'status' => 'new', 'company' => 'شرکت', 'website' => 'https://x.ir', 'utm_source' => 'google', 'utm_medium' => 'cpc', 'landing_page' => '/pricing', 'message' => 'پلن آژانس با وایتدلیبل', 'metadata' => ['device' => 'desktop']]);

        app(ScoreLead::class)->handle($low);
        app(ScoreLead::class)->handle($high);

        $this->actingAs($user)
            ->get('/app/marketing?sort=score')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('leads.0.name', 'زیاد')
                ->where('leads.1.name', 'کم'));
    }

    private function organization(): Organization
    {
        return Organization::query()->create([
            'public_id' => (string) Str::ulid(),
            'name' => 'سازمان تست',
            'slug' => 'org-'.Str::random(8),
            'status' => 'active',
        ]);
    }

    private function membership(User $user, Organization $organization, string $roleKey): void
    {
        Membership::query()->create([
            'organization_id' => $organization->getKey(),
            'user_id' => $user->getKey(),
            'role_id' => Role::query()->where('key', $roleKey)->valueOrFail('id'),
            'status' => 'active',
        ]);
    }
}
