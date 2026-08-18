<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Domains\Audit\Actions\RecordAuditLog;
use App\Domains\Identity\Services\OtpService;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;

class OtpLoginController extends Controller
{
    public function __construct(private readonly OtpService $otp) {}

    /**
     * درخواست کد — SMS یا تماس (از طریق کاوه‌نگار). اگر شماره در سیستم
     * نباشد، همچنان «کد ارسال شد» برمی‌گردد تا وجود شماره لو نرود.
     */
    public function request(Request $request): JsonResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'regex:/^0?9[0-9]{9}$/'],
            'via' => ['sometimes', 'string', 'in:sms,call'],
        ]);

        $phone = OtpService::normalizePhone($data['phone']);
        $throttleKey = 'otp-request:'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            return response()->json([
                'sent' => false,
                'message' => 'تعداد درخواست‌ها بیش از حد مجاز است؛ چند دقیقه دیگر تلاش کنید.',
            ], 429);
        }
        RateLimiter::hit($throttleKey, 300);

        $result = $this->otp->request($phone, 'login');

        return response()->json($result);
    }

    /**
     * تأیید کد و ورود خودکار. اگر شماره‌ای با این کد در سیستم نباشد،
     * خطای عمومی برمی‌گردد (بدون افشای وجود حساب).
     */
    public function verify(Request $request, RecordAuditLog $recordAuditLog): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'regex:/^0?9[0-9]{9}$/'],
            'code' => ['required', 'string', 'digits:6'],
        ]);

        $phone = OtpService::normalizePhone($data['phone']);
        $throttleKey = 'otp-verify:'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 10)) {
            return response()->json([
                'success' => false,
                'message' => 'تلاش‌های ناموفق زیاد بود؛ چند دقیقه دیگر تلاش کنید.',
            ], 429);
        }
        RateLimiter::hit($throttleKey, 300);

        if (! $this->otp->verify($phone, $data['code'], 'login')) {
            return response()->json([
                'success' => false,
                'message' => 'کد واردشده صحیح نیست یا منقضی شده است.',
            ], 422);
        }

        $user = User::query()->where('phone', $phone)->first();

        if ($user === null) {
            return response()->json([
                'success' => false,
                'message' => 'حسابی با این شماره تماس وجود ندارد؛ ابتدا ثبت‌نام کنید.',
            ], 422);
        }

        Auth::login($user);
        $request->session()->regenerate();
        $recordAuditLog->handle(
            action: 'auth.login_otp',
            subject: $user,
            metadata: ['method' => 'otp'],
        );

        return redirect()->intended(route('app.dashboard', absolute: false));
    }
}
