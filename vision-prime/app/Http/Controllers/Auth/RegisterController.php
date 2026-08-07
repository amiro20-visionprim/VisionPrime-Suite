<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Domains\Audit\Actions\RecordAuditLog;
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
        $user = User::query()->create([
            'name' => $request->string('name')->trim()->toString(),
            'email' => $request->string('email')->trim()->lower()->toString(),
            'password' => $request->string('password')->toString(),
        ]);

        Auth::login($user);
        $request->session()->regenerate();

        $recordAuditLog->handle(
            action: 'auth.registered',
            subject: $user,
            metadata: ['name' => $user->name],
        );

        return redirect()->route('app.onboarding');
    }
}
