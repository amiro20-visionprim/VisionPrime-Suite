<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Domains\Identity\Services\OtpService;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class OtpRegisterController extends Controller
{
    public function __construct(private readonly OtpService $otp) {}

    /**
     * درخواست کد تأیید شماره تماس قبل از ثبت‌نام.
     */
    public function request(Request $request): JsonResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'regex:/^0?9[0-9]{9}$/'],
        ]);

        $phone = OtpService::normalizePhone($data['phone']);

        if (User::query()->where('phone', $phone)->exists()) {
            return response()->json([
                'sent' => false,
                'message' => 'این شماره تماس قبلاً ثبت شده است؛ با آن وارد شوید.',
            ], 422);
        }

        $throttleKey = 'otp-register:'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            return response()->json([
                'sent' => false,
                'message' => 'تعداد درخواست‌ها بیش از حد مجاز است؛ چند دقیقه دیگر تلاش کنید.',
            ], 429);
        }
        RateLimiter::hit($throttleKey, 300);

        return response()->json($this->otp->request($phone, 'register'));
    }
}
