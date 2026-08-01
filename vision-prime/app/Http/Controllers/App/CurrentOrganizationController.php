<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Domains\Audit\Actions\RecordAuditLog;
use App\Domains\Organization\Actions\SwitchCurrentOrganization;
use App\Domains\Organization\Models\Organization;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CurrentOrganizationController extends Controller
{
    public function update(Request $request, Organization $organization, SwitchCurrentOrganization $switchCurrentOrganization, RecordAuditLog $recordAuditLog): RedirectResponse
    {
        $switchCurrentOrganization->handle($request->user(), $organization->getKey());
        $recordAuditLog->handle(
            action: 'organization.selected',
            subject: $organization,
            after: ['name' => $organization->name],
            organization: $organization,
        );

        return back();
    }
}
