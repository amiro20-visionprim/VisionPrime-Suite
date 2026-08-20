<?php

declare(strict_types=1);

namespace App\Domains\Ai\Services;

/**
 * Registry of all supported AI providers — internal (Iranian) and external.
 *
 * Each provider defines:
 *   - endpoint, auth header format, default model
 *   - whether it supports free models
 *   - rate limit hints
 *   - category (internal / external)
 */
final class ProviderRegistry
{
    public const CATEGORIES = [
        'internal' => 'سرویس‌های داخلی',
        'external' => 'سرویس‌های بین‌المللی',
    ];

    /**
     * Full list of providers.  Key = provider slug used in DB and UI.
     *
     * @return array<string, array{
     *     name: string,
     *     category: string,
     *     endpoint: string,
     *     auth_style: string,
     *     default_model: string,
     *     free_models: string[],
     *     models: string[],
     *     supports_streaming: bool,
     *     notes: string,
     * }>
     */
    public static function all(): array
    {
        return [
            // ──── سرویس‌های داخلی ────
            'deepseek' => [
                'name' => 'DeepSeek',
                'category' => 'internal',
                'endpoint' => 'https://api.deepseek.com/v1/chat/completions',
                'auth_style' => 'bearer',
                'default_model' => 'deepseek-chat',
                'free_models' => [],
                'models' => ['deepseek-chat', 'deepseek-reasoner'],
                'supports_streaming' => true,
                'notes' => 'ارزان و سریع — بهترین گزینه برای محتوای فارسی',
            ],
            'samani' => [
                'name' => 'سمانی (Samani)',
                'category' => 'internal',
                'endpoint' => 'https://api.samani.ir/v1/chat/completions',
                'auth_style' => 'bearer',
                'default_model' => 'samani-chat',
                'free_models' => [],
                'models' => ['samani-chat', 'samani-pro'],
                'supports_streaming' => true,
                'notes' => 'پلتفرم هوش مصنوعی ایرانی — پشتیبانی کامل فارسی',
            ],
            'parstech' => [
                'name' => 'پارستک (ParsTech)',
                'category' => 'internal',
                'endpoint' => 'https://api.parstech.ai/v1/chat/completions',
                'auth_style' => 'bearer',
                'default_model' => 'parschat-7b',
                'free_models' => [],
                'models' => ['parschat-7b', 'parschat-13b'],
                'supports_streaming' => true,
                'notes' => 'مدل‌های فارسی‌زبان اختصاصی',
            ],
            'ayez' => [
                'name' => 'آیز (Ayez)',
                'category' => 'internal',
                'endpoint' => 'https://api.ayeiz.com/v1/chat/completions',
                'auth_style' => 'bearer',
                'default_model' => 'ayeiz-chat',
                'free_models' => [],
                'models' => ['ayeiz-chat'],
                'supports_streaming' => false,
                'notes' => 'سرویس هوش مصنوعی فارسی',
            ],
            'fal' => [
                'name' => 'فال (Fal.ai)',
                'category' => 'internal',
                'endpoint' => 'https://api.fal.ai/v1/chat/completions',
                'auth_style' => 'bearer',
                'default_model' => 'fal-chat',
                'free_models' => [],
                'models' => ['fal-chat'],
                'supports_streaming' => true,
                'notes' => 'پلتفرم AI ایرانی',
            ],
            'gapgpt' => [
                'name' => 'GapGPT (گپ جی پی تی)',
                'category' => 'internal',
                'endpoint' => 'https://api.gapgpt.app/v1/chat/completions',
                'auth_style' => 'bearer',
                'default_model' => 'gapgpt-chat',
                'free_models' => [],
                'models' => ['gapgpt-chat'],
                'supports_streaming' => true,
                'notes' => 'سرویس هوش مصنوعی فارسی — gapgpt.app',
                'models_endpoint' => 'https://api.gapgpt.app/v1/models',
            ],

            // ──── سرویس‌های بین‌المللی ────
            'openai' => [
                'name' => 'OpenAI',
                'category' => 'external',
                'endpoint' => 'https://api.openai.com/v1/chat/completions',
                'auth_style' => 'bearer',
                'default_model' => 'gpt-4o-mini',
                'free_models' => [],
                'models' => ['gpt-4o-mini', 'gpt-4o', 'gpt-4-turbo', 'gpt-3.5-turbo'],
                'supports_streaming' => true,
                'notes' => 'محبوب‌ترین سرویس AI جهان',
            ],
            'anthropic' => [
                'name' => 'Anthropic (Claude)',
                'category' => 'external',
                'endpoint' => 'https://api.anthropic.com/v1/messages',
                'auth_style' => 'x-api-key',
                'default_model' => 'claude-3-5-haiku-latest',
                'free_models' => [],
                'models' => ['claude-3-5-haiku-latest', 'claude-3-5-sonnet-latest', 'claude-3-opus-latest'],
                'supports_streaming' => true,
                'notes' => 'Claude — بهترین کیفیت محتوا',
            ],
            'openrouter' => [
                'name' => 'OpenRouter',
                'category' => 'external',
                'endpoint' => 'https://openrouter.ai/api/v1/chat/completions',
                'auth_style' => 'bearer',
                'default_model' => 'auto',
                'free_models' => [
                    'nvidia/nemotron-3-ultra-550b-a55b:free',
                    'nvidia/nemotron-3-super-120b-a12b:free',
                    'z-ai/glm-5.2:free',
                    'google/gemma-4-31b-it:free',
                    'google/gemma-4-26b-a4b-it:free',
                    'dots-studio/dots-3-note-preview:free',
                    'nvidia/nemotron-3.5-lightning:free',
                    'nvidia/nemotron-3-nano-omni-30b-a3b-reasoning:free',
                    'poolside/laguna-s-2.1:free',
                    'cohere/north-mini-code:free',
                    'meta-llama/llama-3.3-8b-instruct:free',
                    'qwen/qwen3-235b-a22b:free',
                    'deepseek/deepseek-r1-0528:free',
                    'mistralai/mistral-small-3.2-24b-instruct:free',
                ],
                'models' => ['auto', 'openai/gpt-4o-mini', 'anthropic/claude-3.5-haiku', 'google/gemini-flash-1.5'],
                'supports_streaming' => true,
                'notes' => '۱۴+ مدل رایگان — بهترین برای failover',
            ],
            'google' => [
                'name' => 'Google AI (Gemini)',
                'category' => 'external',
                'endpoint' => 'https://generativelanguage.googleapis.com/v1beta/models',
                'auth_style' => 'query_key',
                'default_model' => 'gemini-1.5-flash',
                'free_models' => ['gemini-1.5-flash', 'gemini-1.5-pro'],
                'models' => ['gemini-1.5-flash', 'gemini-1.5-pro', 'gemini-2.0-flash'],
                'supports_streaming' => true,
                'notes' => 'Google Gemini — رایگان با محدودیت',
            ],
            'groq' => [
                'name' => 'Groq',
                'category' => 'external',
                'endpoint' => 'https://api.groq.com/openai/v1/chat/completions',
                'auth_style' => 'bearer',
                'default_model' => 'llama-3.3-70b-versatile',
                'free_models' => ['llama-3.3-70b-versatile', 'mixtral-8x7b-32768', 'gemma2-9b-it'],
                'models' => ['llama-3.3-70b-versatile', 'mixtral-8x7b-32768', 'gemma2-9b-it', 'llama-3.1-8b-instant'],
                'supports_streaming' => true,
                'notes' => 'سریع‌ترین inference — رایگان با محدودیت',
            ],
            'together' => [
                'name' => 'Together AI',
                'category' => 'external',
                'endpoint' => 'https://api.together.xyz/v1/chat/completions',
                'auth_style' => 'bearer',
                'default_model' => 'meta-llama/Meta-Llama-3.1-70B-Instruct-Turbo',
                'free_models' => [],
                'models' => ['meta-llama/Meta-Llama-3.1-70B-Instruct-Turbo', 'mistralai/Mixtral-8x7B-Instruct-v0.1'],
                'supports_streaming' => true,
                'notes' => 'مدل‌های متن‌باز با کیفیت بالا',
            ],
            'fireworks' => [
                'name' => 'Fireworks AI',
                'category' => 'external',
                'endpoint' => 'https://api.fireworks.ai/inference/v1/chat/completions',
                'auth_style' => 'bearer',
                'default_model' => 'accounts/fireworks/models/llama-v3p1-70b-instruct',
                'free_models' => [],
                'models' => ['accounts/fireworks/models/llama-v3p1-70b-instruct'],
                'supports_streaming' => true,
                'notes' => 'Inference سریع و ارزان',
            ],
            'mistral' => [
                'name' => 'Mistral AI',
                'category' => 'external',
                'endpoint' => 'https://api.mistral.ai/v1/chat/completions',
                'auth_style' => 'bearer',
                'default_model' => 'mistral-small-latest',
                'free_models' => [],
                'models' => ['mistral-small-latest', 'mistral-medium-latest', 'mistral-large-latest'],
                'supports_streaming' => true,
                'notes' => 'مدل‌های اروپایی — خوب برای SEO',
            ],
            'cohere' => [
                'name' => 'Cohere',
                'category' => 'external',
                'endpoint' => 'https://api.cohere.com/v2/chat',
                'auth_style' => 'bearer',
                'default_model' => 'command-r',
                'free_models' => [],
                'models' => ['command-r', 'command-r-plus'],
                'supports_streaming' => true,
                'notes' => 'عالی برای جستجو و تولید محتوا',
            ],
            'bedrock' => [
                'name' => 'AWS Bedrock',
                'category' => 'external',
                'endpoint' => 'https://bedrock-runtime.{region}.amazonaws.com/model/{model}/invoke',
                'auth_style' => 'aws_sigv4',
                'default_model' => 'anthropic.claude-3-haiku-20240307-v1:0',
                'free_models' => [],
                'models' => ['anthropic.claude-3-haiku-20240307-v1:0', 'meta.llama3-70b-instruct-v1:0'],
                'supports_streaming' => false,
                'notes' => 'AWS — برای سازمان‌های بزرگ',
            ],
            'azure' => [
                'name' => 'Azure OpenAI',
                'category' => 'external',
                'endpoint' => 'https://{resource}.openai.azure.com/openai/deployments/{deployment}/chat/completions',
                'auth_style' => 'api_key_header',
                'default_model' => 'gpt-4o-mini',
                'free_models' => [],
                'models' => ['gpt-4o-mini', 'gpt-4o', 'gpt-4-turbo'],
                'supports_streaming' => true,
                'notes' => 'Azure — برای سازمان‌های Microsoft',
            ],
            'deepinfra' => [
                'name' => 'DeepInfra',
                'category' => 'external',
                'endpoint' => 'https://api.deepinfra.com/v1/openai/chat/completions',
                'auth_style' => 'bearer',
                'default_model' => 'meta-llama/Meta-Llama-3.1-70B-Instruct',
                'free_models' => ['meta-llama/Meta-Llama-3.1-8B-Instruct'],
                'models' => ['meta-llama/Meta-Llama-3.1-70B-Instruct', 'meta-llama/Meta-Llama-3.1-8B-Instruct'],
                'supports_streaming' => true,
                'notes' => 'ارزان و سریع — مدل‌های متن‌باز',
            ],
            'novita' => [
                'name' => 'Novita AI',
                'category' => 'external',
                'endpoint' => 'https://api.novita.ai/v3/openai/chat/completions',
                'auth_style' => 'bearer',
                'default_model' => 'meta-llama/llama-3.1-70b-instruct',
                'free_models' => [],
                'models' => ['meta-llama/llama-3.1-70b-instruct'],
                'supports_streaming' => true,
                'notes' => 'Inference ارزان',
            ],
        ];
    }

    /**
     * Get providers filtered by category.
     *
     * @return array<string, array>
     */
    public static function byCategory(string $category): array
    {
        return array_filter(self::all(), fn (array $p): bool => $p['category'] === $category);
    }

    /**
     * Get only free models across all providers.
     *
     * @return array<int, array{provider: string, model: string, name: string}>
     */
    public static function freeModels(): array
    {
        $result = [];
        foreach (self::all() as $slug => $provider) {
            foreach ($provider['free_models'] as $model) {
                $result[] = [
                    'provider' => $slug,
                    'model' => $model,
                    'name' => $provider['name'],
                ];
            }
        }

        return $result;
    }

    /**
     * Get a specific provider by slug.
     */
    public static function get(string $slug): ?array
    {
        return self::all()[$slug] ?? null;
    }

    /**
     * Provider slugs that work with OpenAI-compatible API format.
     */
    public static function openaiCompatible(): array
    {
        return ['deepseek', 'openrouter', 'groq', 'together', 'fireworks', 'deepinfra', 'novita', 'samani', 'parstech', 'ayez', 'fal', 'gapgpt'];
    }
}
