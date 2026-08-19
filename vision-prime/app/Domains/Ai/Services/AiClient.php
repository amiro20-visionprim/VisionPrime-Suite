<?php

declare(strict_types=1);

namespace App\Domains\Ai\Services;

use App\Domains\Organization\Models\Organization;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Client for AI content generation.
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
     * @param  array<string, mixed>  $context
     * @return array{content: string, model: string, source: string, usage: array<string, mixed>}
     */
    public function generateMetaDraft(Organization $org, array $context): array
    {
        $kind = in_array($context['kind'] ?? '', ['meta_title', 'meta_description'], true)
            ? $context['kind']
            : 'meta_title';

        return $this->generate($org, $kind, $context);
    }

    /**
     * تولید مقالهٔ کامل — از طریق AI یا fallback آفلاین.
     */
    public function generateArticleDraft(Organization $org, array $context): array
    {
        $context['kind'] = 'article';

        return $this->generate($org, 'article', $context);
    }

    /**
     * مسیر مشترک تولید: بدون provider فعال → fallback آفلاین؛ در غیر این صورت provider انتخابی.
     */
    private function generate(Organization $org, string $kind, array $context): array
    {
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

        [$system, $user] = $kind === 'article'
            ? $this->articlePrompts($context)
            : $this->prompts($kind, $context);

        return match ($settings->provider) {
            'anthropic' => $this->anthropic($apiKey, $model, $system, $user),
            default => $this->openaiCompatible((string) $settings->provider, $apiKey, $model, $system, $user),
        };
    }

    /**
     * پرامپت مقالهٔ حرفه‌ای — با تمام استانداردهای RankMath/Yoast.
     */
    private function articlePrompts(array $context): array
    {
        $title = (string) ($context['title'] ?? '');
        $targetQuery = (string) ($context['target_query'] ?? '');
        $siteName = (string) ($context['site_name'] ?? '');
        $standard = (array) ($context['standard'] ?? []);
        $metrics = (array) ($context['metrics'] ?? []);
        $internalLinks = $context['internal_links'] ?? [];

        $wordMin = (int) ($standard['word_min'] ?? 400);
        $wordMax = (int) ($standard['word_max'] ?? 2000);
        $minHeadings = max(2, (int) ($standard['min_headings'] ?? 2));
        $elements = (array) ($standard['required_elements'] ?? []);
        $tone = (string) ($standard['tone'] ?? 'informative');
        $schemaType = (string) ($standard['schema_type'] ?? 'Article');
        $keywordGuidance = (array) ($standard['keyword_guidance'] ?? []);

        $metricsLine = sprintf(
            'کلیک‌ها: %d · نمایش‌ها: %d · نرخ کلیک: %s · میانگین جایگاه: %s',
            (int) ($metrics['clicks'] ?? 0),
            (int) ($metrics['impressions'] ?? 0),
            isset($metrics['ctr']) ? round((float) $metrics['ctr'] * 100, 1).'٪' : '—',
            isset($metrics['position']) ? round((float) $metrics['position'], 1) : '—',
        );

        // لینک‌های داخلی پیشنهادی
        $linksText = '';
        if (is_array($internalLinks) && $internalLinks !== []) {
            $linkLines = [];
            foreach (array_slice($internalLinks, 0, 5) as $link) {
                $linkLines[] = "- لینک: {$link['url']} (anchor: {$link['anchor']})";
            }
            $linksText = "\nلینک‌های داخلی پیشنهادی (حداقل " . count($internalLinks) . " لینک در محتوا قرار بده):\n" . implode("\n", $linkLines);
        }

        // الزامات عناصر
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
                $requiredText .= "\n- {$elementLabels[$el]}";
            }
        }

        $system = "تو یک متخصص سئو و تولیدکنندهٔ محتوای حرفه‌ای فارسی هستی. خروجی تو فقط HTML معتبر است.

قوانین:
- فقط از تگ‌های h1/h2/h3/p/ul/ol/table/strong/a استفاده کن
- h1 فقط یکبار در ابتدای مقاله
- h2 برای هر بخش اصلی، h3 برای زیربخش
- کلمهٔ کلیدی حتماً در h1 و اولین پاراگراف و حداقل ۲ h2 باشد
- لحن {$tone}
- هرگز ادعای بی‌پشتوانه یا آمار جعلی نزن
- محتوا باید واقعاً برای خواننده مفید باشد
- FAQ با فرمت <strong>پرسش:</strong> و <strong>پاسخ:</strong> باشد
- CTA در انتها شامل نام برند {$siteName} باشد
- نوع اسکیما: {$schemaType}";

        $user = sprintf(
            "یک مقالهٔ حرفه‌ای سئو شده بنویس.\n\n" .
            "عنوان: %s\n" .
            "کلمهٔ کلیدی: %s\n" .
            "نام برند: %s\n" .
            "دادهٔ GSC: %s\n\n" .
            "=== الزامات فنی ===\n" .
            "- طول: بین %d و %d کلمه\n" .
            "- زیرعنوان‌ها (h2): حداقل %d عدد\n" .
            "- ساختار h1 > h2 > h3 (بدون پرش سطح)\n" .
            "- فقط HTML معتبر بدون هیچ متن خارج از تگ\n\n" .
            "=== الزامات محتوایی ===" . $requiredText . $linksText . "\n\n" .
            "الان شروع به نوشتن کن — فقط خروجی HTML:",
            $title !== '' ? $title : $targetQuery,
            $targetQuery,
            $siteName,
            $metricsLine,
            $wordMin,
            $wordMax,
            $minHeadings,
        );

        return [$system, $user];
    }

    /**
     * پرامپت متا تایتل و دیسکریپشن.
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
                "برای صفحهٔ زیر یک عنوان متا (meta title) عالی بنویس.\nحداکثر ۶۰ کاراکتر.\nکلمهٔ کلیدی حتماً در ابتدا باشد.\nنام برند حتماً در انتها باشد.\nآدرس: %s\nنام برند: %s\nعبارت اصلی: %s\nدادهٔ جستجو: %s\nعنوان فعلی: %s\nنمونهٔ محتوا: %s",
                $url,
                $siteName,
                $topQuery,
                $metricsLine,
                $existing !== '' ? $existing : 'ندارد',
                $snippet !== '' ? $snippet : 'در دسترس نیست',
            ),
            default => sprintf(
                "برای صفحهٔ زیر یک توضیح متا (meta description) جذاب بنویس.\nبین ۱۲۰ تا ۱۵۵ کاراکتر.\nحتماً شامل کلمهٔ کلیدی و دعوت به اقدام باشد.\nآدرس: %s\nنام برند: %s\nعبارت اصلی: %s\nدادهٔ جستجو: %s\nتوضیح فعلی: %s\nنمونهٔ محتوا: %s",
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

    private function openaiCompatible(string $provider, string $apiKey, string $model, string $system, string $user): array
    {
        $endpoint = $provider === 'openrouter'
            ? 'https://openrouter.ai/api/v1/chat/completions'
            : 'https://api.openai.com/v1/chat/completions';

        $response = Http::timeout(120)
            ->acceptJson()
            ->withToken($apiKey)
            ->post($endpoint, [
                'model' => $model,
                'messages' => [
                    ['role' => 'system', 'content' => $system],
                    ['role' => 'user', 'content' => $user],
                ],
                'temperature' => 0.7,
                'max_tokens' => 4096,
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

    private function anthropic(string $apiKey, string $model, string $system, string $user): array
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
