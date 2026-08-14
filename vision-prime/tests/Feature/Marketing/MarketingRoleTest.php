<?php

declare(strict_types=1);

namespace Tests\Feature\Marketing;

use App\Domains\Identity\Models\Role;
use App\Domains\Organization\Models\Membership;
use App\Domains\Organization\Models\Organization;
use App\Models\Lead;
use App\Models\LeadNote;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class MarketingRoleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_marketing_manager_can_view_marketing_dashboard(): void
    {
        $organization = $this->organization();
        $user = User::factory()->create();
        $this->membership($user, $organization, 'marketing-manager');

        Lead::query()->create($this->leadAttributes(['email' => 'lead@test.ir']));

        $this->actingAs($user)
            ->get('/app/marketing')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('App/Marketing/Index')
                ->has('leads', 1)
                ->where('stats.total', 1)
                ->where('canManage', true));
    }

    public function test_user_without_marketing_permission_is_forbidden(): void
    {
        $organization = $this->organization();
        $user = User::factory()->create();
        $this->membership($user, $organization, 'client-approver');

        $this->actingAs($user)
            ->get('/app/marketing')
            ->assertForbidden();
    }

    public function test_marketing_dashboard_filters_by_status_and_campaign(): void
    {
        $organization = $this->organization();
        $user = User::factory()->create();
        $this->membership($user, $organization, 'marketing-manager');

        Lead::query()->create($this->leadAttributes(['email' => 'a@test.ir', 'status' => 'new', 'utm_campaign' => 'camp_x']));
        Lead::query()->create($this->leadAttributes(['email' => 'b@test.ir', 'status' => 'qualified', 'utm_campaign' => 'camp_y']));

        $this->actingAs($user)
            ->get('/app/marketing?status=qualified')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('leads', 1)
                ->where('leads.0.email', 'b@test.ir'));

        $this->actingAs($user)
            ->get('/app/marketing?campaign=camp_x')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('leads', 1)->where('leads.0.email', 'a@test.ir'));
    }

    public function test_marketing_manager_can_update_lead_status(): void
    {
        $organization = $this->organization();
        $user = User::factory()->create();
        $this->membership($user, $organization, 'marketing-manager');

        $lead = Lead::query()->create($this->leadAttributes());

        $this->actingAs($user)
            ->from('/app/marketing')
            ->put("/app/marketing/leads/{$lead->getKey()}/status", ['status' => 'qualified'])
            ->assertRedirect('/app/marketing');

        $this->assertSame('qualified', $lead->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'marketing.lead_status_changed']);
    }

    public function test_marketing_manager_can_add_note_with_user_attribution(): void
    {
        $organization = $this->organization();
        $user = User::factory()->create();
        $this->membership($user, $organization, 'marketing-manager');

        $lead = Lead::query()->create($this->leadAttributes());

        $this->actingAs($user)
            ->from("/app/marketing/leads/{$lead->getKey()}")
            ->post("/app/marketing/leads/{$lead->getKey()}/notes", ['body' => 'پیشنهاد: جلسهٔ دمو با تمرکز روی وایتدلیبل.'])
            ->assertRedirect();

        $note = LeadNote::query()->first();
        $this->assertNotNull($note);
        $this->assertSame($lead->getKey(), $note->lead_id);
        $this->assertSame($user->getKey(), $note->user_id);
        $this->assertStringContainsString('وایتدلیبل', (string) $note->body);
    }

    public function test_marketing_dashboard_reports_conversion_funnel_per_campaign(): void
    {
        $organization = $this->organization();
        $user = User::factory()->create();
        $this->membership($user, $organization, 'marketing-manager');

        // کمپین A: ۲ لید — یکی جدید، یکی واجد شرایط
        Lead::query()->create($this->leadAttributes(['email' => 'a1@test.ir', 'utm_campaign' => 'camp_a', 'status' => 'new']));
        Lead::query()->create($this->leadAttributes(['email' => 'a2@test.ir', 'utm_campaign' => 'camp_a', 'status' => 'qualified']));
        // کمپین B: ۱ لید — تماس‌گرفته‌شده
        Lead::query()->create($this->leadAttributes(['email' => 'b1@test.ir', 'utm_campaign' => 'camp_b', 'status' => 'contacted']));

        $this->actingAs($user)
            ->get('/app/marketing')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('stats.funnel.total', 3)
                ->where('stats.funnel.contacted', 2)
                ->where('stats.funnel.qualified', 1)
                ->where('stats.funnel.leadToContactedRate', 66.7)
                ->where('stats.funnel.contactedToQualifiedRate', 50)
                ->where('stats.funnel.qualifiedRate', 33.3)
                ->where('stats.campaignFunnel.0.campaign', 'camp_a')
                ->where('stats.campaignFunnel.0.total', 2)
                ->where('stats.campaignFunnel.0.contacted', 1)
                ->where('stats.campaignFunnel.0.qualified', 1)
                ->where('stats.campaignFunnel.0.leadToContactedRate', 50)
                ->where('stats.campaignFunnel.0.contactedToQualifiedRate', 100)
                ->where('stats.campaignFunnel.1.campaign', 'camp_b')
                ->where('stats.campaignFunnel.1.contacted', 1)
                ->where('stats.campaignFunnel.1.leadToContactedRate', 100)
                ->where('stats.campaignFunnel.1.contactedToQualifiedRate', 0));
    }

    public function test_filtered_funnel_reacts_to_status_filter(): void
    {
        $organization = $this->organization();
        $user = User::factory()->create();
        $this->membership($user, $organization, 'marketing-manager');

        Lead::query()->create($this->leadAttributes(['email' => 'a@test.ir', 'status' => 'new']));
        Lead::query()->create($this->leadAttributes(['email' => 'b@test.ir', 'status' => 'qualified']));

        $this->actingAs($user)
            ->get('/app/marketing?status=qualified')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('stats.filteredFunnel.total', 1)
                ->where('stats.filteredFunnel.contacted', 1)
                ->where('stats.filteredFunnel.qualified', 1)
                ->where('stats.filteredFunnel.qualifiedRate', 100));
    }

    public function test_lead_detail_requires_marketing_permission(): void
    {
        $organization = $this->organization();
        $user = User::factory()->create();
        $this->membership($user, $organization, 'client-approver');

        $lead = Lead::query()->create($this->leadAttributes());

        $this->actingAs($user)
            ->get("/app/marketing/leads/{$lead->getKey()}")
            ->assertForbidden();
    }

    /** @param  array<string, mixed>  $overrides */
    private function leadAttributes(array $overrides = []): array
    {
        return array_merge([
            'name' => 'لید تست',
            'email' => 'lead@test.ir',
            'source' => 'demo',
            'status' => 'new',
            'utm_source' => 'google',
            'utm_medium' => 'cpc',
            'utm_campaign' => 'launch_1405',
            'metadata' => ['device' => 'desktop', 'contact' => '09121234567'],
        ], $overrides);
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
