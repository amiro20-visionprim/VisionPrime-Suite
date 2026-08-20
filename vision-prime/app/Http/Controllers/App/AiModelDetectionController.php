<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Domains\Ai\Services\ModelDetector;
use App\Domains\Ai\Services\ProviderRegistry;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiModelDetectionController extends Controller
{
    /**
     * Detect available models for a provider.
     * POST /api/content/detect-models
     */
    public function detectModels(Request $request, ModelDetector $detector): JsonResponse
    {
        $request->validate([
            'provider' => ['required', 'string'],
            'api_key' => ['required', 'string'],
            'model' => ['nullable', 'string'],
        ]);

        $provider = $request->string('provider')->toString();
        $apiKey = $request->string('api_key')->toString();
        $model = $request->string('model')->toString();

        $result = $detector->detect($provider, $apiKey, $model);

        return response()->json([
            'success' => $result['error'] === null,
            'models' => $result['models'],
            'usage' => $result['usage'],
            'error' => $result['error'],
            'provider_info' => ProviderRegistry::get($provider),
        ]);
    }

    /**
     * Get usage/quota info for a provider.
     * POST /api/content/provider-usage
     */
    public function getUsage(Request $request, ModelDetector $detector): JsonResponse
    {
        $request->validate([
            'provider' => ['required', 'string'],
            'api_key' => ['required', 'string'],
        ]);

        $provider = $request->string('provider')->toString();
        $apiKey = $request->string('api_key')->toString();

        $usage = $detector->getUsage($provider, $apiKey);

        return response()->json([
            'success' => true,
            'usage' => $usage,
        ]);
    }

    /**
     * List all supported providers with their info.
     * GET /api/content/providers
     */
    public function providers(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'providers' => ProviderRegistry::all(),
            'free_models' => ProviderRegistry::freeModels(),
            'categories' => ProviderRegistry::CATEGORIES,
        ]);
    }
}
