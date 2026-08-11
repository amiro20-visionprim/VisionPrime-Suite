<?php

declare(strict_types=1);

namespace App\Domains\Ai\Services;

use App\Domains\Organization\Models\Organization;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Client for AI meta-draft generation.
 *
 * Reads the organization's active provider setting (encrypted). When no
 * provider is configured it falls back to the deterministic RuleBasedDraft
 * so the workflow stays functional offline and in tests.
 *
 * Supported providers: openai, openrouter (OpenAI-compatible chat) and
 * anthropic (Messages API).
 */
class AiClient
{
    public const PROVIDERS = ['openai', 'openrouter', 'anthropic'];

    public function __construct(private readonly RuleBasedDraft $fallback) {}

    /**
     * @param  array<string, mixed>  $context  page context (kind, url, site_name, top_query, metrics...)
     * @return array{content: string, model: string, source: string, usage: array<string, mixed>}
     */
    public function generateMetaDraft(Organization $org, array $context): array
    {
        $kind = in_array($context['kind'] ?? '', ['meta_title', 'meta_description'], true)
            ? $context['kind']
            : 'meta_title';

        $settings = DB::table('ai_provider_settings')
            ->where('organization_id', $org->getKey())
            ->where('status', 'active')
            ->first();

        if ($settings === null) {
            return $this->fallback->generate($kind, $context);
        }

        $config = json_decode(Crypt::decryptString($settings->encrypted_config), true) ?? [];
        $apiKey = (string) ($config['api_key'] ?? '');

        if ($apiKey === '') {
            throw new \RuntimeException('کلید API سرویس هوش مصنوعی تنظیم نشده است.');
        }

        $model = (string) ($config['model'] ?? match ($settings->provider) {
            'anthropic' => 'claude-3-5-haiku-latest',
            'openrouter' => 'openai/gpt-4o-mini',
            default => 'gpt-4o-mini',
        });

        [$system, $user] = $this->prompts($kind, $context);

        return match ($settings->provider) {
            'anthropic' => $this->anthropic($apiKey, $model, $system, $user),
            default => $this->openaiCompatible((string) $settings->provider, $apiKey, $model, $system, $user),
        };
    }

    /** @param  array<string, mixed>  $context
     * @return array{0: string, 1: string}
     */
    private function prompts(string $kind, array $context): array
    {
        $url = (string) ($context['url'] ?? '');
        $siteName = (string) ($context['site_name'] ?? '');
        $topQuery = (string) ($context['top_query'] ?? '');
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

        $user = match ($kind) {
            'meta_title' => sprintf(
                "برای صفحهٔ زیر یک عنوان متا (meta title) عالی و حداکثر ۶۰ کاراکتر بنویس.\nآدرس: %s\nنام برند: %s\nعبارت اصلی: %s\nدادهٔ جستجو: %s\nعنوان فعلی: %s\nنمونهٔ محتوا: %s",
                $url,
                $siteName,
                $topQuery,
                $metricsLine,
                $existing !== '' ? $existing : 'ندارد',
                $snippet !== '' ? $snippet : 'در دسترس نیست',
            ),
            default => sprintf(
                "برای صفحهٔ زیر یک توضیح متا (meta description) جذاب، حداکثر ۱۵۵ کاراکتر و با دعوت به اقدام بنویس.\nآدرس: %s\nنام برند: %s\nعبارت اصلی: %s\nدادهٔ جستجو: %s\nتوضیح فعلی: %s\nنمونهٔ محتوا: %s",
                $url,
                $siteName,
                $topQuery,
                $metricsLine,
                $existing !== '' ? $existing : 'ندارد',
                $snippet !== '' ? $snippet : 'در دسترس نیست',
            ),
        };

        return [$system, $user];
    }

    /** @return array{content: string, model: string, source: string, usage: array<string, mixed>} */
    private function openaiCompatible(string $provider, string $apiKey, string $model, string $system, string $user): array
    {
        $endpoint = $provider === 'openrouter'
            ? 'https://openrouter.ai/api/v1/chat/completions'
            : 'https://api.openai.com/v1/chat/completions';

        $response = Http::timeout(60)
            ->acceptJson()
            ->withToken($apiKey)
            ->post($endpoint, [
                'model' => $model,
                'messages' => [
                    ['role' => 'system', 'content' => $system],
                    ['role' => 'user', 'content' => $user],
                ],
                'temperature' => 0.7,
            ]);

        if (! $response->successful()) {
            throw new \RuntimeException('سرویس هوش مصنوعی با خطای '.$response->status().' پاسخ داد: '.mb_substr($response->body(), 0, 200));
        }

        $content = trim((string) ($response->json('choices.0.message.content') ?? ''));

        return [
            'content' => $content,
            'model' => $model,
            'source' => 'ai',
            'usage' => [
                'input_tokens' => (int) ($response->json('usage.prompt_tokens') ?? 0),
                'output_tokens' => (int) ($response->json('usage.completion_tokens') ?? 0),
            ],
        ];
    }

    /** @return array{content: string, model: string, source: string, usage: array<string, mixed>} */
    private function anthropic(string $apiKey, string $model, string $system, string $user): array
    {
        $response = Http::timeout(60)
            ->acceptJson()
            ->withHeaders([
                'x-api-key' => $apiKey,
                'anthropic-version' => '2023-06-01',
            ])
            ->post('https://api.anthropic.com/v1/messages', [
                'model' => $model,
                'max_tokens' => 1024,
                'system' => $system,
                'messages' => [['role' => 'user', 'content' => $user]],
            ]);

        if (! $response->successful()) {
            throw new \RuntimeException('سرویس هوش مصنوعی با خطای '.$response->status().' پاسخ داد: '.mb_substr($response->body(), 0, 200));
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
}
