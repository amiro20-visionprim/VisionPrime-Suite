<?php

declare(strict_types=1);

namespace App\Http\Controllers\Platform;

use App\Domains\Audit\Actions\RecordAuditLog;
use App\Domains\Organization\Models\Organization;
use App\Domains\Workspace\Models\Client;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * ورود به‌جای کاربر (impersonation) برای پشتیبانی — فقط super-admin،
 * با audit کامل در شروع/پایان و بنر قرمز در UI. هنگام impersonation
 * اکشن‌های حساس (تغییر پلن/پرداخت/حذف) غیرفعال می‌شوند (در کنترلرها چک می‌شود).
 */
class PlatformImpersonationController extends Controller
{
    public const SESSION_KEY = 'platform_impersonating';

    public function __construct(private readonly RecordAuditLog $audit) {}

    public function start(Request $request, Organization $organization, User $user): RedirectResponse
    {
        // فقط اعضای فعال همان سازمان
        $membership = DB::table('memberships')
            ->where('organization_id', $organization->getKey())
            ->where('user_id', $user->getKey())
            ->where('status', 'active')
            ->first();

        if ($membership === null) {
            abort(422, 'کاربر عضو فعال این سازمان نیست.');
        }

        // ذخیرهٔ هویت اصلی قبل از impersonation
        $request->session()->put(self::SESSION_KEY, Auth::id());

        $this->audit->handle(
            action: 'platform.impersonation.started',
            after: ['target_user_id' => $user->getKey(), 'target_org_id' => $organization->getKey(), 'target_name' => $user->name],
            organization: null,
            source: 'platform',
        );

        Auth::login($user);
        $request->session()->regenerate();

        // وارد پنل مشتری/آژانس همان org شو
        $client = Client::query()
            ->where('organization_id', $organization->getKey())
            ->first();
        $redirect = $client !== null ? '/client/dashboard' : '/app/dashboard';

        return redirect($redirect)->with('status', 'در حال مشاهده به‌جای '.$user->name);
    }

    public function stop(Request $request): RedirectResponse
    {
        $originalId = (int) $request->session()->pull(self::SESSION_KEY, 0);
        $impersonatedUser = $request->user();

        $this->audit->handle(
            action: 'platform.impersonation.stopped',
            after: ['impersonated_user_id' => $impersonatedUser?->getKey(), 'impersonated_name' => $impersonatedUser?->name],
            organization: null,
            source: 'platform',
        );

        if ($originalId > 0) {
            $original = User::find($originalId);
            if ($original !== null) {
                Auth::login($original);
                $request->session()->regenerate();
            }
        }

        return redirect('/platform/dashboard')->with('status', 'خروج از حالت مشاهده به‌جای کاربر.');
    }

    /** آیا درخواست فعلی در حالت impersonation است؟ */
    public static function active(Request $request): bool
    {
        return $request->session()->has(self::SESSION_KEY);
    }
}
