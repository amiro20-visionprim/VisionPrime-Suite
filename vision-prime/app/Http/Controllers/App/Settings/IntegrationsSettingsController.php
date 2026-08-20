<?php

declare(strict_types=1);

namespace App\Http\Controllers\App\Settings;

use App\Domains\Ai\Services\ProviderRegistry;
use App\Domains\Organization\Contracts\CurrentOrganization;
use App\Domains\Organization\Models\Organization;
use App\Domains\Workspace\Services\OrganizationPermission;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Contracts\Encryption\DecryptException;
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
                try {
                    $config = json_decode(Crypt::decryptString($setting->encrypted_config), true) ?? [];
                    $model = $config['model'] ?? '';
                } catch (DecryptException $e) {
                    $config = [];
                    $model = '';
                }

                return [
                    'provider' => $setting->provider,
                    'model' => $model,
                    'updatedAt' => $setting->updated_at,
                ];
            });

        $isSuperAdmin = $request->user()?->isSuperAdmin() ?? false;

        $aiData = [
            'providers' => $aiProviders->values(),
            'isConfigured' => $aiProviders->isNotEmpty(),
        ];

        // فقط سوپر ادمین اطلاعات کلیدها و مدل‌ها رو می‌بینه
        if (! $isSuperAdmin) {
            $aiData['providers'] = $aiProviders->map(fn (object $p): array => [
                'provider' => $p->provider,
                'model' => '',
                'updatedAt' => $p->updatedAt,
            ])->values();
            $aiData['masked'] = true;
        }

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
            'ai' => $aiData,
            'allProviders' => ProviderRegistry::all(),
            'freeModelsCount' => count(ProviderRegistry::freeModels()),
            'isSuperAdmin' => $isSuperAdmin,
        ]);
    }

    private function authorizeView(?User $user, Organization $organization): void
    {
        // همه کاربران مجاز می‌تونن صفحه یکپارچه‌سازی رو ببینن
        // اما بخش AI فقط برای سوپر ادمین نمایش داده میشه
        if ($user === null) {
            abort(401);
        }
    }
}
