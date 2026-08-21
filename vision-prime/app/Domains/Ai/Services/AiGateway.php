<?php

declare(strict_types=1);

namespace App\Domains\Ai\Services;

use App\Domains\Organization\Models\Organization;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use App\Domains\Content\Models\ContentGuardrail;
use Illuminate\Support\Facades\Log;

/**
 * AI Content Generation Gateway with automatic failover.
 *
 * Priority chain:
 *   1. User-configured provider (DeepSeek / OpenAI / OpenRouter with custom key)
 *   2. OpenRouter free models (17+ models, auto-rotating)
 *   3. RuleBased offline fallback (never fails)
 *
 * Features:
 *   - Auto-detect rate limits (429, 402, rate_limit_exceeded)
 *   - Auto-switch to next provider/model on limit hit
 *   - Restart generation if limit hit mid-generation (max 3 retries)
 *   - Cache rate limit state in Cache (TTL = reset time)
 *   - Log all switches and errors
 */
class AiGateway
{
    /** Maximum retries when rate-limited mid-generation */
    private const MAX_RETRIES = 3;

    /** Cache prefix for rate limit tracking */
    private const CACHE_PREFIX = 'ai_gateway:limit:';

    /**
     * OpenRouter free models — loaded from ProviderRegistry.
     * These rotate automatically when rate-limited.
     */
    private const FREE_MODELS = [
        'meta-llama/llama-3.3-70b-versatile:free',
        'qwen/qwen3-235b-a22b:free',
        'deepseek/deepseek-r1-0528:free',
        'google/gemma-4-31b-it:free',
        'mistralai/mistral-small-3.2-24b-instruct:free',
        'nvidia/nemotron-3-ultra-550b-a55b:free',
        'meta-llama/llama-3.3-8b-instruct:free',
        'nvidia/nemotron-3.5-lightning:free',
    ];

    public function __construct(
        private readonly RuleBasedDraft $fallback,
    ) {}

    /**
     * Generate article draft with automatic failover.
     *
     * @param  array<string, mixed>  $context
     * @return array{content: string, model: string, source: string, usage: array<string, mixed>}
     */
    public function generateArticleDraft(Organization $org, array $context): array
    {
        [$system, $user] = $this->articlePrompts($context);

        return $this->generateWithFailover($org, 'article', $system, $user);
    }

    /**
     * Generate meta title/description with automatic failover.
     */
    public function generateMetaDraft(Organization $org, array $context): array
    {
        $kind = in_array($context['kind'] ?? '', ['meta_title', 'meta_description'], true)
            ? $context['kind']
            : 'meta_title';

        [$system, $user] = $this->metaPrompts($kind, $context);

        return $this->generateWithFailover($org, $kind, $system, $user);
    }

    /**
     * Core generation with failover chain.
     * Tries user-configured provider first, then free models, then rule-based.
     */
    private function generateWithFailover(Organization $org, string $kind, string $system, string $user): array
    {
        $retryCount = 0;

        while (true) {
            try {
                // 1. Try user-configured provider
                $result = $this->tryUserProvider($org, $system, $user);
                if ($result !== null) {
                    return $result;
                }

                // 2. Try OpenRouter free models
                $result = $this->tryFreeModels($system, $user);
                if ($result !== null) {
                    return $result;
                }

                // 3. Fallback to rule-based
                Log::info('AiGateway: all AI providers exhausted, using RuleBased fallback');
                return $this->fallback->generate($kind, ['kind' => $kind] + $this->contextFromPrompts($system, $user));

            } catch (\RuntimeException $e) {
                $retryCount++;
                if ($retryCount >= self::MAX_RETRIES) {
                    Log::error("AiGateway: max retries ({$retryCount}) reached, using RuleBased fallback", [
                        'error' => $e->getMessage(),
                    ]);
                    return $this->fallback->generate($kind, ['kind' => $kind] + $this->contextFromPrompts($system, $user));
                }

                Log::warning("AiGateway: retry {$retryCount}/" . self::MAX_RETRIES . " after rate limit", [
                    'error' => $e->getMessage(),
                ]);
                // Brief pause before retry
                usleep(500_000); // 0.5 second
            }
        }
    }

    /**
     * Try user-configured provider from ai_provider_settings table.
     */
    private function tryUserProvider(Organization $org, string $system, string $user): ?array
    {
        $settings = DB::table('ai_provider_settings')
            ->where('organization_id', $org->getKey())
            ->where('status', 'active')
            ->first();

        if ($settings === null) {
            return null;
        }

        $cacheKey = self::CACHE_PREFIX . 'user:' . $org->getKey();
        if (Cache::has($cacheKey)) {
            return null; // Rate limited, skip
        }

        $config = json_decode(Crypt::decryptString($settings->encrypted_config), true) ?? [];
        $apiKey = (string) ($config['api_key'] ?? '');

        if ($apiKey === '') {
            return null;
        }

        $model = (string) ($config['model'] ?? match ($settings->provider) {
            'anthropic' => 'claude-3-5-haiku-latest',
            'openrouter' => 'openai/gpt-4o-mini',
            'deepseek' => 'deepseek-chat',

            'groq' => 'llama-3.3-70b-versatile',
            'gapgpt' => 'gpt-4o-mini',
            default => 'gpt-4o-mini',
        });

        try {
            $result = match ($settings->provider) {
                'anthropic' => $this->callAnthropic($apiKey, $model, $system, $user),
                'deepseek' => $this->callOpenAiCompatible('deepseek', $apiKey, $model, $system, $user),

                'groq' => $this->callOpenAiCompatible('groq', $apiKey, $model, $system, $user),
                'gapgpt' => $this->callOpenAiCompatible('gapgpt', $apiKey, $model, $system, $user),
                default => $this->callOpenAiCompatible((string) $settings->provider, $apiKey, $model, $system, $user),
            };

            Log::info('AiGateway: user provider succeeded', [
                'provider' => $settings->provider,
                'model' => $model,
            ]);

            return $result;
        } catch (\RuntimeException $e) {
            if ($this->isRateLimit($e)) {
                Log::warning('AiGateway: user provider rate limited', [
                    'provider' => $settings->provider,
                    'model' => $model,
                ]);
                // Cache the rate limit for 60 seconds
                Cache::put($cacheKey, true, 60);
                throw $e; // Re-throw to trigger retry
            }
            Log::warning('AiGateway: user provider failed', [
                'provider' => $settings->provider,
                'model' => $model,
                'error' => $e->getMessage(),
            ]);
            return null; // Non-rate-limit error, try next provider
        }
    }

    /**
     * Try OpenRouter free models in order, skipping rate-limited ones.
     */
    private function tryFreeModels(string $system, string $user): ?array
    {
        $apiKey = config('services.openrouter.key', '');

        if ($apiKey === '') {
            // Try to get from env or config
            $apiKey = env('OPENROUTER_API_KEY', '');
            if ($apiKey === '') {
                return null;
            }
        }

        foreach (self::FREE_MODELS as $model) {
            $cacheKey = self::CACHE_PREFIX . 'free:' . $model;
            if (Cache::has($cacheKey)) {
                continue; // Rate limited, skip
            }

            try {
                $result = $this->callOpenAiCompatible('openrouter', $apiKey, $model, $system, $user);

                Log::info('AiGateway: free model succeeded', ['model' => $model]);

                return $result;
            } catch (\RuntimeException $e) {
                if ($this->isRateLimit($e)) {
                    Log::warning('AiGateway: free model rate limited', ['model' => $model]);
                    // Cache for 60 seconds
                    Cache::put($cacheKey, true, 60);
           
                    continue; // Try next model
                }
                Log::warning('AiGateway: free model failed', [
                    'model' => $model,
                    'error' => $e->getMessage(),
                ]);
                continue; // Try next model
            }
        }

        return null; // All free models exhausted
    }

    /**
     * Call OpenAI-compatible API (OpenAI, DeepSeek, OpenRouter).
     */
    private function callOpenAiCompatible(string $provider, string $apiKey, string $model, string $system, string $user): array
    {
        $endpoint = match ($provider) {
            'openrouter' => 'https://openrouter.ai/api/v1/chat/completions',
            'deepseek' => 'https://api.deepseek.com/v1/chat/completions',

            'groq' => 'https://api.groq.com/openai/v1/chat/completions',
            'gapgpt' => config('services.gapgpt.endpoint', 'https://api.gapgpt.app/v1/chat/completions'),
            default => 'https://api.openai.com/v1/chat/completions',
        };

        $headers = [
            'Authorization' => 'Bearer ' . $apiKey,
            'Content-Type' => 'application/json',
        ];

        if ($provider === 'openrouter') {
            $headers['HTTP-Referer'] = config('app.url', 'https://visionprime-suite.ir');
            $headers['X-Title'] = 'Vision Prime SEO Suite';
        }

        // Proxy support for OpenRouter (Iran → bypass geo-restrictions)
        $http = Http::timeout(120)->withHeaders($headers);
        if ($provider === 'openrouter') {
            $proxy = config('services.openrouter.proxy', '');
            if ($proxy !== '') {
                $http = $http->withOptions(['proxy' => ['http' => $proxy, 'https' => $proxy]]);
            }
        }

        $response = $http->post($endpoint, [
                'model' => $model,
                'messages' => [
                    ['role' => 'system', 'content' => $system],
                    ['role' => 'user', 'content' => $user],
                ],
                'temperature' => 0.7,
                'max_tokens' => 4096,
            ]);

        if (! $response->successful()) {
            $status = $response->status();
            $body = $response->body();

            if ($status === 429 || $status === 402 || str_contains($body, 'rate_limit') || str_contains($body, 'insufficient_quota')) {
                throw new \RuntimeException("Rate limit hit on {$provider}/{$model}: {$status}");
            }

            throw new \RuntimeException("AI provider {$provider}/{$model} error {$status}: " . mb_substr($body, 0, 200));
        }

        $data = $response->json();
        $content = trim((string) ($data['choices'][0]['message']['content'] ?? ''));

        if ($content === '') {
            throw new \RuntimeException("AI provider {$provider}/{$model} returned empty content");
        }

        return [
            'content' => $content,
            'model' => $model,
            'source' => 'ai',
            'usage' => [
                'input_tokens' => (int) ($data['usage']['prompt_tokens'] ?? 0),
                'output_tokens' => (int) ($data['usage']['completion_tokens'] ?? 0),
            ],
        ];
    }

    /**
     * Call Anthropic Messages API.
     */
    private function callAnthropic(string $apiKey, string $model, string $system, string $user): array
    {
        $response = Http::timeout(120)
            ->acceptJson()
            ->withHeaders([
                'x-api-key' => $apiKey,
                'anthropic-version' => '2023-06-01',
            ])
            ->post('https://api.anthropic.com/v1/messages', [
                'model' => $model,
                'max_tokens' => 4096,
                'system' => $system,
                'messages' => [['role' => 'user', 'content' => $user]],
            ]);

        if (! $response->successful()) {
            $status = $response->status();
            $body = $response->body();

            if ($status === 429 || $status === 402 || str_contains($body, 'rate_limit')) {
                throw new \RuntimeException("Rate limit hit on anthropic/{$model}: {$status}");
            }

            throw new \RuntimeException("Anthropic error {$status}: " . mb_substr($body, 0, 200));
        }

        $blocks = $response->json('content') ?? [];
        $content = '';
        foreach ($blocks as $block) {
            if (($block['type'] ?? '') === 'text') {
                $content .= (string) ($block['text'] ?? '');
            }
        }

        return [
            'content' => trim($content),
            'model' => $model,
            'source' => 'ai',
            'usage' => [
                'input_tokens' => (int) ($response->json('usage.input_tokens') ?? 0),
                'output_tokens' => (int) ($response->json('usage.output_tokens') ?? 0),
            ],
        ];
    }

    /**
     * Check if an exception is a rate limit error.
     */
    private function isRateLimit(\RuntimeException $e): bool
    {
        $message = strtolower($e->getMessage());
        return str_contains($message, 'rate limit')
            || str_contains($message, '429')
            || str_contains($message, '402')
            || str_contains($message, 'insufficient_quota')
            || str_contains($message, 'rate_limit_exceeded');
    }

    /**
     * Get status of all available providers for UI display.
     *
     * @return array<int, array{name: string, model: string, status: string, provider: string}>
     */
    public function getProviderStatus(): array
    {
        $status = [];

        $settings = DB::table('ai_provider_settings')->where('status', 'active')->first();
        if ($settings !== null) {
            $cacheKey = self::CACHE_PREFIX . 'user:' . $settings->organization_id;
            $status[] = [
                'name' => $settings->provider,
                'model' => json_decode($settings->encrypted_config, true)['model'] ?? 'default',
                'status' => Cache::has($cacheKey) ? 'rate_limited' : 'active',
                'provider' => $settings->provider,
            ];
        }

        $apiKey = config('services.openrouter.key', env('OPENROUTER_API_KEY', ''));
        if ($apiKey !== '') {
            foreach (self::FREE_MODELS as $model) {
                $cacheKey = self::CACHE_PREFIX . 'free:' . $model;
                $status[] = [
                    'name' => $model,
                    'model' => $model,
                    'status' => Cache::has($cacheKey) ? 'rate_limited' : 'active',
                    'provider' => 'openrouter_free',
                ];
            }
        }

        $status[] = [
            'name' => 'RuleBased',
            'model' => 'rule-based',
            'status' => 'active',
            'provider' => 'rule_based',
        ];

        return $status;
    }

    /**
     * Test connection to a specific provider.
     */
    public function testConnection(string $provider, string $apiKey = '', string $model = ''): array
    {
        try {
            $system = 'تو یک دستیار ساده هستی. فقط بنویس: "اتصال موفق"';
            $user = 'سلام';

            $result = match ($provider) {
                'deepseek' => $this->callOpenAiCompatible('deepseek', $apiKey, $model ?: 'deepseek-chat', $system, $user),

                'groq' => $this->callOpenAiCompatible('groq', $apiKey, $model ?: 'llama-3.3-70b-versatile', $system, $user),
                'openai' => $this->callOpenAiCompatible('openai', $apiKey, $model ?: 'gpt-4o-mini', $system, $user),
                'openrouter' => $this->callOpenAiCompatible('openrouter', $apiKey, $model ?: 'openai/gpt-4o-mini', $system, $user),
                'anthropic' => $this->callAnthropic($apiKey, $model ?: 'claude-3-5-haiku-latest', $system, $user),
                'gapgpt' => $this->callOpenAiCompatible('gapgpt', $apiKey, $model ?: 'gpt-4o-mini', $system, $user),
                default => throw new \RuntimeException("Unknown provider: {$provider}"),
            };

            return ['success' => true, 'model' => $result['model'], 'content' => mb_substr($result['content'], 0, 100)];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function articlePrompts(array $context): array
    {
        $title = (string) ($context['title'] ?? '');
        $targetQuery = (string) ($context['target_query'] ?? '');
        $siteName = (string) ($context['site_name'] ?? '');
        $standard = (array) ($context['standard'] ?? []);
        $metrics = (array) ($context['metrics'] ?? []);
        $internalLinks = $context['internal_links'] ?? [];
        $guardrails = (array) ($context['guardrails'] ?? []);

        $wordMin = (int) ($guardrails['min_words'] ?? $standard['word_min'] ?? 400);
        $wordMax = (int) ($guardrails['max_words'] ?? $standard['word_max'] ?? 2000);
        $minHeadings = max(2, (int) ($standard['min_headings'] ?? 2));
        $elements = (array) ($standard['required_elements'] ?? []);
        $tone = (string) ($guardrails['allowed_tone'] ?? $standard['tone'] ?? 'informative');
        $schemaType = (string) ($standard['schema_type'] ?? 'Article');

        // Apply guardrail overrides
        $requireCta = (bool) ($guardrails['require_cta'] ?? true);
        $requireFaq = (bool) ($guardrails['require_faq'] ?? false);
        $requireLinks = (bool) ($guardrails['require_internal_links'] ?? true);
        $minLinks = (int) ($guardrails['min_internal_links'] ?? 2);
        $requireBrand = (bool) ($guardrails['require_brand_mention'] ?? true);
        $forbiddenWords = (array) ($guardrails['forbidden_words'] ?? []);
        $maxChars = (int) ($guardrails['max_characters'] ?? 8000);

        $metricsLine = sprintf(
            'کلیک‌ها: %d · نمایش‌ها: %d · نرخ کلیک: %s · میانگین جایگاه: %s',
            (int) ($metrics['clicks'] ?? 0),
            (int) ($metrics['impressions'] ?? 0),
            isset($metrics['ctr']) ? round((float) $metrics['ctr'] * 100, 1).'٪' : '—',
            isset($metrics['position']) ? round((float) $metrics['position'], 1) : '—',
        );

        $linksText = '';
        if (is_array($internalLinks) && $internalLinks !== []) {
            $linkLines = [];
            foreach (array_slice($internalLinks, 0, 5) as $link) {
                $linkLines[] = "- لینک: {$link['url']} (anchor: {$link['anchor']})";
            }
            $linksText = "
لینک‌های داخلی پیشنهادی:
" . implode("
", $linkLines);
        }

        $elementLabels = [
            'h2_structure' => 'زیرعنوان‌های h2 (حداقل ' . $minHeadings . ' عدد)',
            'table_of_contents' => 'فهرست مطالب در ابتدای مقاله',
            'faq' => 'بخش سؤالات متداول (با تگ‌های strong برای پرسش/پاسخ)',
            'cta' => 'دعوت به اقدام در انتهای مقاله',
            'internal_links' => 'لینک‌های داخلی',
            'steps' => 'مراحل گام‌به‌گام (لیست مرتب <ol>)',
            'list' => 'لیست غیرمرتب <ul>',
            'table' => 'جدول مقایسه یا مشخصات',
            'pros_cons' => 'بخش مزایا و معایب',
            'rating' => 'امتیازدهی (مثلاً ۴ از ۵)',
            'specs' => 'مشخصات فنی جدولی',
            'social_proof' => 'نظرات یا رضایت مشتریان',
        ];
        $requiredText = '';
        foreach ($elements as $el) {
            if (isset($elementLabels[$el])) {
                $requiredText .= "
- {$elementLabels[$el]}";
            }
        }

        // Build guardrail rules for system prompt
        $guardrailRules = '';
        if ($requireCta) {
            $guardrailRules .= "
- حتماً در انتهای مقاله یک CTA (دعوت به اقدام) بنویس";
        }
        if ($requireFaq) {
            $guardrailRules .= "
- حتماً بخش سؤالات متداول (FAQ) با فرمت <strong>پرسش:</strong> و <strong>پاسخ:</strong> بنویس";
        }
        if ($requireLinks && $minLinks > 0) {
            $guardrailRules .= "
- حداقل {$minLinks} لینک داخلی در محتوا قرار بده";
        }
        if ($requireBrand && $siteName !== '') {
            $guardrailRules .= "
- نام برند {$siteName} را حداقل ۲ بار در محتوا ذکر کن";
        }
        if ($forbiddenWords !== []) {
            $forbiddenList = implode('، ', array_slice($forbiddenWords, 0, 20));
            $guardrailRules .= "
- هرگز از کلمات زیر استفاده نکن: {$forbiddenList}";
        }
        $guardrailRules .= "
- حداکثر طول محتوا: {$maxChars} کاراکتر";

        // Use custom system prompt if provided in guardrails, otherwise use default
        $customSystem = $guardrails['system_prompt'] ?? null;
        $system = $customSystem !== null && trim($customSystem) !== ''
            ? $customSystem . "

خروجی باید فقط HTML معتبر باشد."
            : "تو یک متخصص سئو و تولیدکنندهٔ محتوای حرفه‌ای فارسی هستی. خروجی تو فقط HTML معتبر است.

قوانین:
- فقط از تگ‌های h1/h2/h3/p/ul/ol/table/strong/a استفاده کن
- h1 فقط یکبار در ابتدای مقاله
- h2 برای هر بخش اصلی، h3 برای زیربخش
- کلمهٔ کلیدی حتماً در h1 و اولین پاراگراف و حداقل ۲ h2 باشد
- لحن {$tone}
- هرگز ادعای بی‌پشتوانه یا آمار جعلی نزن
- محتوا باید واقعاً برای خواننده مفید باشد
- نوع اسکیما: {$schemaType}
{$guardrailRules}";

        $user = "یک مقالهٔ حرفه‌ای سئو شده بنویس.

عنوان: " . ($title !== '' ? $title : $targetQuery) . "
کلمهٔ کلیدی: " . $targetQuery . "
نام برند: " . $siteName . "
دادهٔ GSC: " . $metricsLine . "

=== الزامات فنی ===
- طول: بین " . $wordMin . " و " . $wordMax . " کلمه
- زیرعنوان‌ها (h2): حداقل " . $minHeadings . " عدد
- ساختار h1 > h2 > h3 (بدون پرش سطح)
- فقط HTML معتبر بدون هیچ متن خارج از تگ

=== الزامات محتوایی ===" . $requiredText . $linksText . "

الان شروع به نوشتن کن — فقط خروجی HTML:";

        return [$system, $user];
    }

    private function metaPrompts(string $kind, array $context): array
    {
        $url = (string) ($context['url'] ?? '');
        $siteName = (string) ($context['site_name'] ?? '');
        $topQuery = (string) ($context['target_query'] ?? $context['top_query'] ?? '');
        $metrics = $context['metrics'] ?? [];
        $existing = (string) ($context['existing_meta'] ?? '');
        $snippet = mb_substr((string) ($context['content_snippet'] ?? ''), 0, 800);

        $metricsLine = sprintf(
            'کلیک‌ها: %d · نمایش‌ها: %d · نرخ کلیک: %s · میانگین جایگاه: %s',
            (int) ($metrics['clicks'] ?? 0),
            (int) ($metrics['impressions'] ?? 0),
            isset($metrics['ctr']) ? round((float) $metrics['ctr'] * 100, 1).'٪' : '—',
            isset($metrics['position']) ? round((float) $metrics['position'], 1) : '—',
        );

        $system = 'تو یک متخصص سئو و کپی‌رایتر فارسی هستی. فقط متن خواسته‌شده را بدون توضیح اضافه، شماره‌گذاری یا نقل‌قول برگردان.';

        if ($kind === 'meta_title') {
            $user = 'برای صفحهٔ زیر یک عنوان متا (meta title) عالی بنویس. حداکثر ۶۰ کاراکتر. کلمهٔ کلیدی حتماً در ابتدا باشد. نام برند حتماً در انتها باشد.
آدرس: ' . $url . '
نام برند: ' . $siteName . '
عبارت اصلی: ' . $topQuery . '
دادهٔ جستجو: ' . $metricsLine . '
عنوان فعلی: ' . ($existing !== '' ? $existing : 'ندارد') . '
نمونهٔ محتوا: ' . ($snippet !== '' ? $snippet : 'در دسترس نیست');
        } else {
            $user = 'برای صفحهٔ زیر یک توضیح متا (meta description) جذاب بنویس. بین ۱۲۰ تا ۱۵۵ کاراکتر. حتماً شامل کلمهٔ کلیدی و دعوت به اقدام باشد.
آدرس: ' . $url . '
نام برند: ' . $siteName . '
عبارت اصلی: ' . $topQuery . '
دادهٔ جستجو: ' . $metricsLine . '
توضیح فعلی: ' . ($existing !== '' ? $existing : 'ندارد') . '
نمونهٔ محتوا: ' . ($snippet !== '' ? $snippet : 'در دسترس نیست');
        }

        return [$system, $user];
    }

    private function contextFromPrompts(string $system, string $user): array
    {
        $title = '';
        $keyword = '';
        if (preg_match('/^عنوان:\s*(.+)$/m', $user, $m)) {
            $title = trim($m[1]);
        }
        if (preg_match('/^کلمهٔ کلیدی:\s*(.+)$/m', $user, $m)) {
            $keyword = trim($m[1]);
        }

        return [
            'title' => $title,
            'target_query' => $keyword,
            'site_name' => '',
        ];
    }
}
