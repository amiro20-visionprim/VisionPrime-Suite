<?php

declare(strict_types=1);

namespace App\Http\Controllers\App\Settings;

use App\Domains\Organization\Contracts\CurrentOrganization;
use App\Domains\Organization\Models\Organization;
use App\Domains\Workspace\Services\OrganizationPermission;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class IntegrationsSettingsController extends Controller
{
    public function __construct(
        private readonly OrganizationPermission $organizationPermission,
    ) {}

    public function index(Request $request, CurrentOrganization $currentOrganization): Response
    {
        $organization = $currentOrganization->get();
        $this->authorizeView($request->user(), $organization);

        $organizationId = $organization->getKey();

        $gscAccounts = DB::table('gsc_accounts')
            ->where('organization_id', $organizationId)
            ->get(['email', 'status', 'token_expires_at']);

        $gscProperties = DB::table('gsc_properties')
            ->join('gsc_accounts', 'gsc_properties.gsc_account_id', '=', 'gsc_accounts.id')
            ->where('gsc_accounts.organization_id', $organizationId)
            ->count();

        $siteRows = DB::table('sites')
            ->leftJoin('site_connections', 'site_connections.site_id', '=', 'sites.id')
            ->where('sites.organization_id', $organizationId)
            ->whereNull('sites.deleted_at')
            ->get([
                'sites.id',
                'sites.name',
                'site_connections.status',
                'site_connections.last_seen_at',
            ]);

        $pairedSites = $siteRows->filter(fn (object $site): bool => $site->status === 'paired');

        $aiProviders = DB::table('ai_provider_settings')
            ->where('organization_id', $organizationId)
            ->where('status', 'active')
            ->get(['provider', 'encrypted_config', 'updated_at'])
            ->map(function (object $setting): array {
                $config = json_decode(\Illuminate\Support\Facades\Crypt::decryptString($setting->encrypted_config), true) ?? [];

                return [
                    'provider' => $setting->provider,
                    'model' => $config['model'] ?? '',
                    'updatedAt' => $setting->updated_at,
                ];
            });

        return Inertia::render('App/Settings/Integrations', [
            'gsc' => [
                'connected' => $gscAccounts->count(),
                'accounts' => $gscAccounts->map(fn (object $account): array => [
                    'email' => $account->email,
                    'status' => $account->status,
                    'expiresAt' => $account->token_expires_at,
                ])->values(),
                'propertiesCount' => $gscProperties,
            ],
            'wordpress' => [
                'totalSites' => $siteRows->count(),
                'pairedSites' => $pairedSites->count(),
                'sites' => $siteRows->map(fn (object $site): array => [
                    'id' => (int) $site->id,
                    'name' => $site->name,
                    'status' => $site->status ?? 'unpaired',
                    'lastSeenAt' => $site->last_seen_at,
                ])->values(),
            ],
            'ai' => [
                'providers' => $aiProviders->values(),
                'isConfigured' => $aiProviders->isNotEmpty(),
            ],
        ]);
    }

    private function authorizeView(?User $user, Organization $organization): void
    {
        $viewable = ['gsc.view.assigned', 'connector.view.assigned'];
        $hasAccess = $user !== null
            && collect($viewable)->contains(fn (string $permission): bool => $this->organizationPermission->allows($user, $organization, $permission));

        if (! $hasAccess) {
            abort(403, 'شما دسترسی مشاهدهٔ یکپارچه‌سازی‌ها را ندارید.');
        }
    }
}
