<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domains\Platform\Services\PlatformSettingsService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * چالش MFA برای سوپرادمین — کاملاً اختیاری:
 *  - اگر کاربر خودش MFA فعال کرده باشد، در نشست‌های جدید تا تأیید کد،
 *    فقط به صفحهٔ چالش دسترسی دارد.
 *  - اگر تنظیم پلتفرم mfa_required روشن باشد، مدیران ارشد باید MFA فعال
 *    داشته باشند (به صفحهٔ تنظیمات MFA هدایت می‌شوند)؛ پیش‌فرض: خاموش.
 * (فقط روی روت‌های پلتفرم اعمال می‌شود؛ روت خودِ MFA مستثناست.)
 */
class EnsureMfaVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        // روت‌های خودِ MFA (چالش، تأیید، تنظیمات) همیشه در دسترس‌اند.
        if ($request->routeIs('platform.mfa.*')) {
            return $next($request);
        }

        // کاربری که MFA فعال دارد ولی در این نشست هنوز تأیید نکرده → چالش.
        if ((bool) $user->mfa_enabled && ! $request->session()->get('mfa_verified')) {
            return redirect()->route('platform.mfa.challenge');
        }

        // الزام پلتفرمی: اگر روشن باشد، مدیران ارشد بدون MFA باید اول فعالش کنند.
        if ($user->isSuperAdmin()
            && app(PlatformSettingsService::class)->bool('mfa_required', false)
            && ! (bool) $user->mfa_enabled) {
            return redirect()->route('platform.mfa.settings');
        }

        return $next($request);
    }
}
