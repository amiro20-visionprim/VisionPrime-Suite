<?php

declare(strict_types=1);

namespace App\Domains\Ai\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Auto-detect available models, token usage, rate limits, and status
 * for each AI provider.
 *
 * Supports:
 *   - OpenAI-compatible /v1/models endpoint
 *   - Anthropic model listing
 *   - Google Gemini model listing
 *   - Custom providers (GapGPT, etc.)
 */
final class ModelDetector
{
    /**
     * Detect all available models for a provider.
     *
     * @return array{
     *     models: array<int, array{id: string, name: string, status: string, context_window: int|null, max_output: int|null}>,
     *     usage: array{total_tokens: int|null, total_requests: int|null, limit: int|null}|null,
     *     error: string|null,
     * }
     */
    public function detect(string $provider, string $apiKey, string $model = ''): array
    {
        return match ($provider) {
            'anthropic' => $this->detectAnthropic($apiKey),
            'google' => $this->detectGoogle($apiKey),
            default => $this->detectOpenAiCompatible($provider, $apiKey),
        };
    }

    /**
     * Get usage/quota info for a provider.
     *
     * @return array{total_tokens: int|null, total_requests: int|null, limit: int|null, reset_at: string|null}
     */
    public function getUsage(string $provider, string $apiKey): array
    {
        return match ($provider) {
            'openai' => $this->getOpenAiUsage($apiKey),
            'openrouter' => $this->getOpenRouterUsage($apiKey),
            'deepseek' => $this->getDeepSeekUsage($apiKey),
            default => ['total_tokens' => null, 'total_requests' => null, 'limit' => null, 'reset_at' => null],
        };
    }

    // ──── OpenAI-compatible detection ────

    private function detectOpenAiCompatible(string $provider, string $apiKey): array
    {
        $endpoint = match ($provider) {
            'deepseek' => 'https://api.deepseek.com/v1/models',
            'openrouter' => 'https://openrouter.ai/api/v1/models',
            'groq' => 'https://api.groq.com/openai/v1/models',
            'together' => 'https://api.together.xyz/v1/models',
            'fireworks' => 'https://api.fireworks.ai/inference/v1/models',
            'deepinfra' => 'https://api.deepinfra.com/v1/openai/models',
            'novita' => 'https://api.novita.ai/v3/openai/models',
            'gapgpt' => 'https://api.gapgpt.app/v1/models',
            default => null,
        };

        if ($endpoint === null) {
            return ['models' => [], 'usage' => null, 'error' => 'Provider detection not supported'];
        }

        try {
            $response = Http::timeout(30)
                ->withHeaders(['Authorization' => 'Bearer ' . $apiKey])
                ->get($endpoint);

            if (! $response->successful()) {
                return ['models' => [], 'usage' => null, 'error' => 'HTTP ' . $response->status()];
            }

            $data = $response->json();
            $models = [];

            foreach ($data['data'] ?? [] as $m) {
                $id = (string) ($m['id'] ?? '');
                if ($id === '') {
                    continue;
                }

                $models[] = [
                    'id' => $id,
                    'name' => (string) ($m['name'] ?? $id),
                    'status' => 'active',
                    'context_window' => isset($m['context_length']) ? (int) $m['context_length'] : null,
                    'max_output' => isset($m['max_output_tokens']) ? (int) $m['max_output_tokens'] : null,
                ];
            }

            return ['models' => $models, 'usage' => null, 'error' => null];
        } catch (\Throwable $e) {
            Log::warning("ModelDetector: failed for {$provider}", ['error' => $e->getMessage()]);
            return ['models' => [], 'usage' => null, 'error' => $e->getMessage()];
        }
    }

    // ──── Anthropic detection ────

    private function detectAnthropic(string $apiKey): array
    {
        // Anthropic doesn't have a /models endpoint; return known models
        $knownModels = [
            ['id' => 'claude-3-5-haiku-latest', 'name' => 'Claude 3.5 Haiku', 'status' => 'active', 'context_window' => 200000, 'max_output' => 8192],
            ['id' => 'claude-3-5-sonnet-latest', 'name' => 'Claude 3.5 Sonnet', 'status' => 'active', 'context_window' => 200000, 'max_output' => 8192],
            ['id' => 'claude-3-opus-latest', 'name' => 'Claude 3 Opus', 'status' => 'active', 'context_window' => 200000, 'max_output' => 4096],
            ['id' => 'claude-3-haiku-20240307', 'name' => 'Claude 3 Haiku', 'status' => 'active', 'context_window' => 200000, 'max_output' => 4096],
        ];

        // Test with a minimal request to verify key works
        try {
            $response = Http::timeout(15)
                ->withHeaders([
                    'x-api-key' => $apiKey,
                    'anthropic-version' => '2023-06-01',
                ])
                ->post('https://api.anthropic.com/v1/messages', [
                    'model' => 'claude-3-5-haiku-latest',
                    'max_tokens' => 10,
                    'messages' => [['role' => 'user', 'content' => 'hi']],
                ]);

            $status = $response->successful() ? 'active' : 'error';

            return ['models' => array_map(fn ($m) => [...$m, 'status' => $status], $knownModels), 'usage' => null, 'error' => null];
        } catch (\Throwable $e) {
            return ['models' => array_map(fn ($m) => [...$m, 'status' => 'inactive'], $knownModels), 'usage' => null, 'error' => $e->getMessage()];
        }
    }

    // ──── Google Gemini detection ────

    private function detectGoogle(string $apiKey): array
    {
        try {
            $response = Http::timeout(30)
                ->get("https://generativelanguage.googleapis.com/v1beta/models?key={$apiKey}");

            if (! $response->successful()) {
                return ['models' => [], 'usage' => null, 'error' => 'HTTP ' . $response->status()];
            }

            $models = [];
            foreach ($response->json('models', []) as $m) {
                $name = (string) ($m['name'] ?? '');
                $models[] = [
                    'id' => str_replace('models/', '', $name),
                    'name' => (string) ($m['displayName'] ?? $name),
                    'status' => 'active',
                    'context_window' => (int) ($m['inputTokenLimit'] ?? 0) ?: null,
                    'max_output' => (int) ($m['outputTokenLimit'] ?? 0) ?: null,
                ];
            }

            return ['models' => $models, 'usage' => null, 'error' => null];
        } catch (\Throwable $e) {
            return ['models' => [], 'usage' => null, 'error' => $e->getMessage()];
        }
    }

    // ──── Usage endpoints ────

    private function getOpenAiUsage(string $apiKey): array
    {
        try {
            $response = Http::timeout(15)
                ->withHeaders(['Authorization' => 'Bearer ' . $apiKey])
                ->get('https://api.openai.com/v1/usage');

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'total_tokens' => $data['total_usage'] ?? null,
                    'total_requests' => null,
                    'limit' => null,
                    'reset_at' => null,
                ];
            }
        } catch (\Throwable $e) {
            // Silently fail
        }

        return ['total_tokens' => null, 'total_requests' => null, 'limit' => null, 'reset_at' => null];
    }

    private function getOpenRouterUsage(string $apiKey): array
    {
        try {
            $response = Http::timeout(15)
                ->withHeaders(['Authorization' => 'Bearer ' . $apiKey])
                ->get('https://openrouter.ai/api/v1/auth/key');

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'total_tokens' => $data['data']['usage'] ?? null,
                    'total_requests' => null,
                    'limit' => $data['data']['limit'] ?? null,
                    'reset_at' => $data['data']['limit_reset'] ?? null,
                ];
            }
        } catch (\Throwable $e) {
            // Silently fail
        }

        return ['total_tokens' => null, 'total_requests' => null, 'limit' => null, 'reset_at' => null];
    }

    private function getDeepSeekUsage(string $apiKey): array
    {
        try {
            $response = Http::timeout(15)
                ->withHeaders(['Authorization' => 'Bearer ' . $apiKey])
                ->get('https://api.deepseek.com/user/balance');

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'total_tokens' => null,
                    'total_requests' => null,
                    'limit' => (float) ($data['balance_infos'][0]['total_balance'] ?? 0) * 1000,
                    'reset_at' => null,
                ];
            }
        } catch (\Throwable $e) {
            // Silently fail
        }

        return ['total_tokens' => null, 'total_requests' => null, 'limit' => null, 'reset_at' => null];
    }
}
