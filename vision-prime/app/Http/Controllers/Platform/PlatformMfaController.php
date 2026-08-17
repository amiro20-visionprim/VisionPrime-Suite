<?php

declare(strict_types=1);

namespace App\Http\Controllers\Platform;

use App\Domains\Audit\Actions\RecordAuditLog;
use App\Domains\Platform\Services\Totp;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PlatformMfaController
{
    public function __construct(
        private readonly Totp $totp,
        private readonly RecordAuditLog $audit,
    ) {}

    /** صفحهٔ تنظیمات MFA در پنل پلتفرم. */
    public function index(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Platform/Mfa', [
            'enabled' => (bool) $user->mfa_enabled,
            'enabledAt' => $user->mfa_enabled_at,
            'setupSecret' => (string) $user->mfa_secret,
            'setupUri' => $user->mfa_secret !== null
                ? $this->totp->otpauthUri((string) $user->mfa_secret, (string) $user->email)
                : null,
        ]);
    }

    /** شروع فعال‌سازی: ساخت سکرت (اگر هنوز ساخته نشده). */
    public function setup(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->mfa_secret === null) {
            $user->forceFill(['mfa_secret' => $this->totp->generateSecret()])->save();
        }

        return back();
    }

    /** تأیید کد و فعال‌سازی نهایی. */
    public function enable(Request $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validate(['code' => ['required', 'string', 'max:6']]);

        if ($user->mfa_secret === null || ! $this->totp->verify((string) $user->mfa_secret, $data['code'])) {
            return back()->with('error', 'کد تأیید صحیح نیست. از اپ احراز هویت خود کد فعلی را وارد کنید.');
        }

        $backupCodes = $this->totp->backupCodes(10);

        $user->forceFill([
            'mfa_enabled' => true,
            'mfa_backup_codes' => $backupCodes,
            'mfa_enabled_at' => now(),
        ])->save();

        $request->session()->put('mfa_verified', true);

        $this->audit->handle(action: 'platform.mfa.enabled', subject: $user);

        // کدهای پشتیبان فقط یک‌بار (در همین ریدایرکت) به UI فرستاده می‌شوند تا
        // مالک آن‌ها را ذخیره کند؛ بعد از رفرش دیگر در دسترس نیستند.
        session()->flash('mfa_backup_codes', $backupCodes);

        return back()->with('success', 'احراز هویت دومرحله‌ای فعال شد. کدهای پشتیبان را حتماً ذخیره کنید.');
    }

    /** صفحهٔ چالش بعد از لاگین (بدون لایوت پلتفرم). */
    public function challenge(): Response
    {
        return Inertia::render('Platform/MfaChallenge');
    }

    /** تأیید کد در چالش لاگین. */
    public function verify(Request $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validate(['code' => ['required', 'string', 'max:6']]);

        if ($user === null || ! $user->mfa_enabled) {
            return redirect()->route('platform.dashboard');
        }

        if ($this->totp->verify((string) $user->mfa_secret, $data['code'])) {
            $request->session()->put('mfa_verified', true);
            $this->audit->handle(action: 'platform.mfa.verified', subject: $user);

            return redirect()->route('platform.dashboard');
        }

        // کد پشتیبان یکبارمصرف
        $backupCodes = $user->mfa_backup_codes ?? [];
        if (in_array($data['code'], $backupCodes, true)) {
            $remaining = array_values(array_diff($backupCodes, [$data['code']]));
            $user->forceFill(['mfa_backup_codes' => $remaining])->save();
            $request->session()->put('mfa_verified', true);
            $this->audit->handle(action: 'platform.mfa.backup_code_used', subject: $user);

            return redirect()->route('platform.dashboard');
        }

        return back()->withErrors(['code' => 'کد تأیید صحیح نیست.'])->onlyInput('code');
    }

    /** غیرفعال‌سازی با کد فعلی. */
    public function disable(Request $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validate(['code' => ['required', 'string', 'max:6']]);

        if (! $this->totp->verify((string) $user->mfa_secret, $data['code'])) {
            return back()->with('error', 'کد تأیید صحیح نیست — برای غیرفعال‌سازی کد فعلی اپ لازم است.');
        }

        $user->forceFill([
            'mfa_secret' => null,
            'mfa_enabled' => false,
            'mfa_backup_codes' => null,
            'mfa_enabled_at' => null,
        ])->save();

        $request->session()->forget('mfa_verified');

        $this->audit->handle(action: 'platform.mfa.disabled', subject: $user);

        return back()->with('success', 'احراز هویت دومرحله‌ای غیرفعال شد.');
    }
}
