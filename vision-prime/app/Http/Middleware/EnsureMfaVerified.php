<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * چالش MFA برای سوپرادمین — اگر کاربر MFA فعال داشته باشد و در این نشست
 * هنوز تأیید نکرده باشد، به صفحهٔ کد تأیید هدایت می‌شود.
 * (فقط روی روت‌های پلتفرم اعمال می‌شود؛ روت خودِ تأیید مستثناست.)
 */
class EnsureMfaVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && (bool) $user->mfa_enabled && ! $request->session()->get('mfa_verified')) {
            if ($request->routeIs('platform.mfa.challenge', 'platform.mfa.verify')) {
                return $next($request);
            }

            return redirect()->route('platform.mfa.challenge');
        }

        return $next($request);
    }
}
