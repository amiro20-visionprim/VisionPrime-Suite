<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domains\Organization\Contracts\CurrentOrganization;
use App\Domains\Organization\Models\Membership;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCurrentOrganization
{
    public function handle(Request $request, Closure $next): Response|RedirectResponse
    {
        $user = $request->user();

        if ($user === null) {
            return redirect()->route('login');
        }

        $membershipQuery = Membership::query()
            ->with('organization')
            ->where('user_id', $user->getKey())
            ->where('status', 'active');

        $selectedOrganizationId = $request->session()->get('current_organization_id');
        $membership = $selectedOrganizationId === null
            ? $membershipQuery->orderBy('id')->first()
            : (clone $membershipQuery)->where('organization_id', $selectedOrganizationId)->first();

        if ($membership === null) {
            $request->session()->forget('current_organization_id');

            return redirect()->route('app.onboarding');
        }

        $request->session()->put('current_organization_id', $membership->organization_id);
        app(CurrentOrganization::class)->set($membership->organization);

        return $next($request);
    }
}
