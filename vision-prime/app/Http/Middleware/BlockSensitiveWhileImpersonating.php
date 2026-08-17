<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * هنگام impersonation (مشاهده به‌جای کاربر) اکشن‌های حساس غیرفعال می‌شوند —
 * فقط مشاهده مجاز است. روی روت‌های POST/DELETE/PUT حساس اعمال می‌شود.
 */
class BlockSensitiveWhileImpersonating
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->session()->has('platform_impersonating')) {
            abort(403, 'هنگام مشاهده به‌جای کاربر، تغییرات حساس غیرفعال است.');
        }

        return $next($request);
    }
}
