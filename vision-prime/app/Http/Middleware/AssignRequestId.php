<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\RequestContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class AssignRequestId
{
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = $request->header('X-Request-ID');
        $requestId = is_string($requestId) && preg_match('/^[A-Za-z0-9_-]{8,64}$/', $requestId) === 1
            ? $requestId
            : (string) Str::ulid();

        app(RequestContext::class)->setRequestId($requestId);
        $response = $next($request);
        $response->headers->set('X-Request-ID', $requestId);

        return $response;
    }
}
