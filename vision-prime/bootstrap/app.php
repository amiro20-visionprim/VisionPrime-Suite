<?php

declare(strict_types=1);

use App\Http\Middleware\AssignRequestId;
use App\Http\Middleware\BlockSensitiveWhileImpersonating;
use App\Http\Middleware\EnsureClientPortalAccess;
use App\Http\Middleware\EnsureCurrentOrganization;
use App\Http\Middleware\EnsureMfaVerified;
use App\Http\Middleware\EnsurePlatformAccess;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            AssignRequestId::class,
            HandleInertiaRequests::class,
        ]);

        // The WordPress connector calls these routes server-to-server without a session;
        // they authenticate via HMAC signatures, so they must not require a CSRF token.
        $middleware->validateCsrfTokens(except: [
            'connector/*',
        ]);

        $middleware->redirectGuestsTo('/login');
        $middleware->redirectUsersTo('/app/dashboard');
        $middleware->alias([
            'current.organization' => EnsureCurrentOrganization::class,
            'client.portal' => EnsureClientPortalAccess::class,
            'platform.only' => EnsurePlatformAccess::class,
            'impersonation.readonly' => BlockSensitiveWhileImpersonating::class,
            'platform.mfa' => EnsureMfaVerified::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
