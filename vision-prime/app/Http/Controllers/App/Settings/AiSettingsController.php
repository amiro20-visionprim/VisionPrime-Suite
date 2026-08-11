<?php

declare(strict_types=1);

namespace App\Http\Controllers\App\Settings;

use App\Domains\Ai\Actions\SaveAiProviderSetting;
use App\Domains\Ai\Services\AiClient;
use App\Domains\Organization\Contracts\CurrentOrganization;
use App\Domains\Organization\Models\Organization;
use App\Domains\Workspace\Services\OrganizationPermission;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AiSettingsController extends Controller
{
    public function __construct(
        private readonly OrganizationPermission $permission,
        private readonly SaveAiProviderSetting $save,
    ) {}

    public function store(Request $request, CurrentOrganization $currentOrganization): RedirectResponse
    {
        $organization = $currentOrganization->get();
        $this->authorizeManage($organization);

        $data = $request->validate([
            'provider' => ['required', 'in:'.implode(',', AiClient::PROVIDERS)],
            'api_key' => ['required', 'string', 'max:500'],
            'model' => ['nullable', 'string', 'max:100'],
        ]);

        $this->save->handle($organization, $data['provider'], [
            'api_key' => $data['api_key'],
            'model' => $data['model'] ?? '',
        ]);

        return back()->with('status', 'سرویس هوش مصنوعی پیکربندی شد.');
    }

    public function destroy(string $provider, CurrentOrganization $currentOrganization): RedirectResponse
    {
        $organization = $currentOrganization->get();
        $this->authorizeManage($organization);

        if (! in_array($provider, AiClient::PROVIDERS, true)) {
            abort(422, 'سرویس نامعتبر است.');
        }

        DB::table('ai_provider_settings')
            ->where('organization_id', $organization->getKey())
            ->where('provider', $provider)
            ->delete();

        return back()->with('status', 'پیکربندی سرویس حذف شد.');
    }

    private function authorizeManage(Organization $organization): void
    {
        if (! $this->permission->allows(request()->user(), $organization, 'member.manage.organization')) {
            abort(403, 'شما دسترسی پیکربندی هوش مصنوعی را ندارید.');
        }
    }
}
