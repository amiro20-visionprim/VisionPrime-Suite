<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domains\Audit\Actions\RecordAuditLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * پنل پلتفرم (اتاق فرماندهی) — فقط کاربرانی که در یک عضویت فعال، نقش
 * super-admin دارند. علاوه بر کنترل دسترسی، هر ورود به پلتفرم در audit ثبت
 * می‌شود (action=platform.access).
 */
class EnsurePlatformAccess
{
    public function __construct(private readonly RecordAuditLog $audit) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // در حالت impersonation، فقط روت خروج (stop) مجاز است تا کاربر بتواند
        // به هویت اصلی بازگردد؛ بقیهٔ روت‌های پلتفرم مسدود می‌مانند.
        if ($request->session()->has('platform_impersonating') && $request->routeIs('platform.impersonation.stop')) {
            return $next($request);
        }

        if ($request->session()->has('platform_impersonating')) {
            abort(403, 'در حالت مشاهده به‌جای کاربر، دسترسی به پنل پلتفرم غیرفعال است.');
        }

        $isSuperAdmin = $user !== null && $user->memberships()
            ->where('status', 'active')
            ->with('role')
            ->get()
            ->contains(fn ($membership): bool => $membership->role?->key === 'super-admin');

        if (! $isSuperAdmin) {
            abort(403, 'دسترسی به پنل پلتفرم فقط برای مدیر ارشد است.');
        }

        // ثبت ورود به پلتفرم (بدون CurrentOrganization — پلتفرم بالای orgهاست)
        if (! $request->session()->get('platform_access_audited')) {
            $this->audit->handle(
                action: 'platform.access',
                metadata: ['path' => $request->path()],
                organization: null,
                source: 'platform',
            );
            $request->session()->put('platform_access_audited', true);
        }

        return $next($request);
    }
}
