<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class OtpRegisterController extends Controller
{
    public function request(Request $request): JsonResponse
    {
        return response()->json(['message' => 'OTP registration is not available yet.'], 501);
    }

    public function verify(Request $request): JsonResponse
    {
        return response()->json(['message' => 'OTP registration is not available yet.'], 501);
    }
}
