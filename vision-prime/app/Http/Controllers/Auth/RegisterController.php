<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Domains\Audit\Actions\RecordAuditLog;
use App\Domains\Identity\Services\OtpService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class RegisterController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/Register');
    }

    public function store(RegisterRequest $request, RecordAuditLog $recordAuditLog): RedirectResponse
    {
        $phone = OtpService::normalizePhone((string) $request->string('phone'));

        if (! app(OtpService::class)->verify($phone, (string) $request->string('otp_code'), 'register')) {
            return back()->withErrors([
                'otp_code' => 'کد تأیید شماره تماس صحیح نیست یا منقضی شده است.',
            ])->onlyInput('name', 'email', 'phone');
        }

        $user = User::query()->create([
            'name' => $request->string('name')->trim()->toString(),
            'email' => $request->string('email')->trim()->lower()->toString(),
            'password' => $request->string('password')->toString(),
            'phone' => $phone,
            'phone_verified_at' => now(),
        ]);

        Auth::login($user);
        $request->session()->regenerate();

        $recordAuditLog->handle(
            action: 'auth.registered',
            subject: $user,
            metadata: ['name' => $user->name, 'phone_verified' => true],
        );

        return redirect()->route('app.onboarding');
    }
}
