<?php

declare(strict_types=1);

namespace App\Http\Controllers\Platform;

use App\Domains\Audit\Actions\RecordAuditLog;
use App\Domains\Organization\Models\Organization;
use App\Domains\Platform\Models\Subscription;
use App\Domains\Workspace\Models\Site;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PlatformOrganizationController extends Controller
{
    public function __construct(private readonly RecordAuditLog $audit) {}

    public function index(): Response
    {
        $organizations = Organization::query()
            ->withCount('clients')
            ->withCount('memberships')
            ->get()
            ->map(function (Organization $org): array {
                $subscription = Subscription::query()
                    ->where('organization_id', $org->getKey())
                    ->whereIn('status', [Subscription::STATUS_ACTIVE, Subscription::STATUS_TRIALING, Subscription::STATUS_PAST_DUE])
                    ->latest()
                    ->first();

                return [
                    'id' => $org->getKey(),
                    'name' => $org->name,
                    'slug' => $org->slug,
                    'status' => $org->status,
                    'created_at' => (string) $org->created_at,
                    'clients_count' => (int) $org->clients_count,
                    'members_count' => (int) $org->memberships_count,
                    'sites_count' => Site::query()->where('organization_id', $org->getKey())->count(),
                    'plan_name' => $subscription?->plan?->name ?? 'بدون پلن',
                    'subscription_status' => $subscription?->status ?? null,
                ];
            })->all();

        return Inertia::render('Platform/Organizations', [
            'organizations' => $organizations,
        ]);
    }

    public function show(Organization $organization): Response
    {
        $subscription = Subscription::query()
            ->where('organization_id', $organization->getKey())
            ->latest()
            ->first();

        $aiProviders = DB::table('ai_provider_settings')
            ->where('organization_id', $organization->getKey())
            ->where('status', 'active')
            ->get()
            ->map(function ($row): array {
                $config = json_decode(Crypt::decryptString($row->encrypted_config), true) ?? [];
                $key = (string) ($config['api_key'] ?? '');

                return [
                    'provider' => (string) $row->provider,
                    'model' => (string) ($config['model'] ?? ''),
                    'has_key' => $key !== '',
                    'key_last4' => $key !== '' ? mb_substr($key, -4) : null,
                ];
            })->all();

        $orgSiteIds = DB::table('sites')->where('organization_id', $organization->getKey())->pluck('id');

        $tokensMonth = DB::table('ai_generations')
            ->whereIn('site_id', $orgSiteIds)
            ->where('created_at', '>=', now()->startOfMonth())
            ->get()
            ->sum(fn ($row): int => (int) (json_decode((string) $row->usage, true)['output_tokens'] ?? 0));

        return Inertia::render('Platform/Organizations/Show', [
            'organization' => [
                'id' => $organization->getKey(),
                'name' => $organization->name,
                'slug' => $organization->slug,
                'status' => $organization->status,
                'created_at' => (string) $organization->created_at,
            ],
            'subscription' => $subscription === null ? null : [
                'id' => $subscription->getKey(),
                'plan_name' => $subscription->plan?->name ?? '—',
                'status' => $subscription->status,
                'current_period_end' => (string) $subscription->current_period_end,
                'auto_renew' => $subscription->auto_renew,
            ],
            'aiProviders' => $aiProviders,
            'tokensMonth' => (int) $tokensMonth,
            'sites' => Site::query()
                ->where('organization_id', $organization->getKey())
                ->get(['id', 'name', 'canonical_url', 'status'])
                ->map(fn (Site $site): array => [
                    'id' => $site->getKey(),
                    'name' => $site->name,
                    'url' => (string) $site->canonical_url,
                    'status' => $site->status,
                ])->all(),
            'members' => $organization->users()
                ->withPivot(['role_id', 'status'])
                ->get()
                ->map(fn ($user): array => [
                    'id' => $user->getKey(),
                    'name' => $user->name,
                    'email' => $user->email,
                    'status' => $user->pivot->status,
                ])->all(),
        ]);
    }

    public function suspend(Request $request, Organization $organization): RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $organization->update(['status' => 'suspended']);

        // اشتراک فعال هم معلق شود تا کل اتوماسیون متوقف باشد
        Subscription::query()
            ->where('organization_id', $organization->getKey())
            ->whereIn('status', [Subscription::STATUS_ACTIVE, Subscription::STATUS_TRIALING])
            ->update(['status' => Subscription::STATUS_SUSPENDED, 'updated_at' => now()]);

        $this->audit->handle(
            action: 'platform.organization.suspended',
            subject: $organization,
            before: ['status' => 'active'],
            after: ['status' => 'suspended', 'reason' => $data['reason']],
            organization: null,
            source: 'platform',
        );

        return back()->with('status', 'سازمان تعلیق شد: '.$data['reason']);
    }

    public function activate(Organization $organization): RedirectResponse
    {
        $organization->update(['status' => 'active']);

        $this->audit->handle(
            action: 'platform.organization.activated',
            subject: $organization,
            before: ['status' => 'suspended'],
            after: ['status' => 'active'],
            organization: null,
            source: 'platform',
        );

        return back()->with('status', 'سازمان فعال شد.');
    }
}
