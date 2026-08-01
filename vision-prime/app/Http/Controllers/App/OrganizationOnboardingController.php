<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Domains\Organization\Actions\CreateOrganization;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organization\CreateOrganizationRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class OrganizationOnboardingController extends Controller
{
    public function create(): Response|RedirectResponse
    {
        if ($this->hasOrganization()) {
            return redirect()->route('app.dashboard');
        }

        return Inertia::render('App/OrganizationOnboarding');
    }

    public function store(CreateOrganizationRequest $request, CreateOrganization $createOrganization): RedirectResponse
    {
        $organization = $createOrganization->handle($request->user(), $request->string('name')->trim()->toString());
        $request->session()->put('current_organization_id', $organization->getKey());

        return redirect()->route('app.dashboard');
    }

    private function hasOrganization(): bool
    {
        return auth()->user()?->memberships()->where('status', 'active')->exists() ?? false;
    }
}
