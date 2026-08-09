<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Domains\Automation\Actions\ExecuteCommand;
use App\Domains\Organization\Contracts\CurrentOrganization;
use App\Domains\Workspace\Models\Site;
use App\Domains\Workspace\Services\OrganizationPermission;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

class CommandDispatchController extends Controller
{
    public function __construct(
        private readonly OrganizationPermission $permission,
        private readonly ExecuteCommand $execute,
    ) {}

    public function store(int $command, CurrentOrganization $org): RedirectResponse
    {
        $item = \DB::table('commands')->where('id', $command)->firstOrFail();
        $site = Site::query()->where('id', $item->site_id)->where('organization_id', $org->id())->firstOrFail();

        if (! $this->permission->allows(request()->user(), $org->get(), 'command.execute.assigned')) {
            abort(403, 'شما مجوز اجرای تغییرات را ندارید.');
        }

        try {
            $this->execute->handle($item->id);
        } catch (\Throwable $e) {
            return back()->with('error', 'اجرای تغییر ناموفق بود: '.$e->getMessage());
        }

        return back()->with('status', 'تغییر اجرا شد و نتیجه ثبت گردید.');
    }
}
