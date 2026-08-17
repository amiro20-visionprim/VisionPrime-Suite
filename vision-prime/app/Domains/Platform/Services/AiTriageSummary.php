<?php

declare(strict_types=1);

namespace App\Domains\Platform\Services;

use Illuminate\Support\Facades\Http;

/**
 * خلاصهٔ هوشمند تصمیم‌های در انتظار برای مالک پلتفرم.
 *
 * اگر کلید AI پلتفرم تنظیم شده باشد (PLATFORM_AI_*)، تصمیم‌ها با هوش مصنوعی
 * اولویت‌بندی و خلاصه می‌شوند؛ در غیر این صورت یک خلاصهٔ قطعی و خوانا تولید
 * می‌شود تا داشبورد و Briefing همیشه کار کنند (منطق RuleBasedDraft در دامنهٔ AI).
 */
class AiTriageSummary
{
    /**
     * @param  array<int, array{type: string, severity: string, organization_id: int|null, created_at: string, payload?: array<string, mixed>}>  $decisions
     */
    public function summarize(array $decisions): array
    {
        if ($decisions === []) {
            return ['source' => 'none', 'summary' => 'همه‌چیز تحت کنترل است؛ تصمیمِ در انتظاری وجود ندارد.', 'priority' => []];
        }

        $priority = $this->ruleBasedPriority($decisions);

        try {
            $ai = $this->aiSummary($decisions);
            if ($ai !== null) {
                return ['source' => 'ai', 'summary' => $ai, 'priority' => $priority];
            }
        } catch (\Throwable) {
            // fallback قطعی در زیر
        }

        return ['source' => 'rule', 'summary' => $this->ruleSummary($decisions, $priority), 'priority' => $priority];
    }

    /**
     * @param  array<int, array<string, mixed>>  $decisions
     * @return list<array{type: string, severity: string, organization_id: int|null, created_at: string}>
     */
    private function ruleBasedPriority(array $decisions): array
    {
        $severityRank = ['critical' => 0, 'warning' => 1, 'info' => 2];
        $sorted = $decisions;
        usort($sorted, fn ($a, $b): int => ($severityRank[$a['severity']] ?? 9) <=> ($severityRank[$b['severity']] ?? 9));

        return array_map(fn ($d): array => [
            'type' => (string) $d['type'],
            'severity' => (string) $d['severity'],
            'organization_id' => isset($d['organization_id']) ? (int) $d['organization_id'] : null,
            'created_at' => (string) $d['created_at'],
        ], array_slice($sorted, 0, 5));
    }

    /**
     * @param  array<int, array<string, mixed>>  $decisions
     * @return list<string>
     */
    private function ruleSummary(array $decisions, array $priority): string
    {
        $labels = $this->typeLabels();
        $critical = count(array_filter($decisions, fn ($d): bool => $d['severity'] === 'critical'));
        $warning = count(array_filter($decisions, fn ($d): bool => $d['severity'] === 'warning'));

        $lines = [];
        if ($critical > 0) {
            $lines[] = "{$critical} مورد بحرانی نیاز به اقدام فوری دارد";
        }
        if ($warning > 0) {
            $lines[] = "{$warning} مورد هشدار در نوبت بررسی است";
        }
        foreach (array_slice($priority, 0, 3) as $p) {
            $label = $labels[$p['type']] ?? $p['type'];
            $org = $p['organization_id'] !== null ? " (سازمان #{$p['organization_id']})" : '';
            $lines[] = "• {$label}{$org} — ".(string) $p['severity'];
        }

        return implode(' — ', $lines);
    }

    /**
     * @param  array<int, array<string, mixed>>  $decisions
     */
    private function aiSummary(array $decisions): ?string
    {
        $apiKey = (string) config('services.platform_ai.api_key');

        if ($apiKey === '') {
            return null;
        }

        $labels = $this->typeLabels();
        $items = array_map(
            fn ($d): string => sprintf(
                '- %s [%s] (سازمان %s، %s)',
                $labels[$d['type']] ?? $d['type'],
                (string) $d['severity'],
                $d['organization_id'] ?? '—',
                (string) $d['created_at'],
            ),
            $decisions,
        );

        $baseUrl = rtrim((string) config('services.platform_ai.base_url'), '/') ?: 'https://api.openai.com/v1';
        $model = (string) config('services.platform_ai.model', 'gpt-4o-mini');

        $response = Http::timeout(20)
            ->withToken($apiKey)
            ->post($baseUrl.'/chat/completions', [
                'model' => $model,
                'messages' => [
                    ['role' => 'system', 'content' => 'تو دستیار مالک یک پلتفرم SaaS هستی. در حداکثر ۴ جمله به فارسی، مهم‌ترین تصمیم‌های در انتظار را اولویت‌بندی و خلاصه کن؛ مستقیم و عملی بگو چه کاری فوری است.'],
                    ['role' => 'user', 'content' => "تصمیم‌های در انتظار:\n".implode("\n", $items)],
                ],
                'max_tokens' => 300,
            ])
            ->throw()
            ->json();

        $text = trim((string) ($response['choices'][0]['message']['content'] ?? ''));

        return $text !== '' ? $text : null;
    }

    /** @return array<string, string> */
    private function typeLabels(): array
    {
        return [
            'subscription.expiring' => 'انقضای اشتراک نزدیک است',
            'payment.failed' => 'پرداخت ناموفق',
            'site.disconnected' => 'اتصال سایت قطع شده',
            'job.failed' => 'کار زمان‌بندی‌شده ناموفق',
            'ai.usage_high' => 'مصرف AI نزدیک سقف',
            'review.queue' => 'صف بازبینی',
        ];
    }
}
