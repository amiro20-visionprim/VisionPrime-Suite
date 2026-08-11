<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Domains\Automation\Actions\ApproveCommand;
use App\Domains\Organization\Contracts\CurrentOrganization;
use App\Domains\Workspace\Models\Site;
use App\Domains\Workspace\Services\OrganizationPermission;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CommandDecisionController extends Controller
{
    public function __construct(private readonly OrganizationPermission $permission) {}

    public function store(Request $request, int $command, CurrentOrganization $org, ApproveCommand $approve): RedirectResponse
    {
        $data = $request->validate([
            'decision' => ['required', 'string', 'in:approved,rejected'],
        ]);

        $item = DB::table('commands')->where('id', $command)->firstOrFail();
        $site = Site::query()->where('id', $item->site_id)->where('organization_id', $org->id())->firstOrFail();

        abort_unless($item->status === 'pending_approval', 422, 'فقط تغییرات در انتظار تأیید قابل تصمیم‌گیری هستند.');

        if (! $this->permission->allows($request->user(), $org->get(), 'command.approve.assigned')) {
            abort(403, 'شما مجوز تأیید تغییرات را ندارید.');
        }

        $approve->handle($command, $request->user(), $data['decision']);

        return back()->with('status', $data['decision'] === 'approved' ? 'تغییر تأیید شد.' : 'تغییر رد شد.');
    }
}
