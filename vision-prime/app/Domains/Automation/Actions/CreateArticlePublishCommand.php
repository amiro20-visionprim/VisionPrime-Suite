<?php

declare(strict_types=1);

namespace App\Domains\Automation\Actions;

use App\Domains\Audit\Actions\RecordAuditLog;
use App\Domains\Automation\Services\ConfidenceScorer;
use App\Domains\Workspace\Models\Site;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * فاز ۲ — از پیش‌نویس مقالهٔ تأییدشده (ai_generation با kind=article)،
 * کامند publish_new_article می‌سازد تا از جریان auto_publish عبور کند.
 *
 * - نوع: publish_new_article، محتوا: article، ریسک: R3
 * - payload شامل title/content/slug/اسکیمای Schema.org/تصویر شاخص است
 * - امتیاز اطمینان از سیگنال‌های واقعی: تازگی GSC + تأیید انسانی + توافق منابع + سابقه
 * - idempotent: ساختن کامند برای یک پیش‌نویس دوبار، همان کامند را برمی‌گرداند
 */
class CreateArticlePublishCommand
{
    public function __construct(
        private readonly ConfidenceScorer $scorer,
        private readonly RecordAuditLog $audit,
    ) {}

    /** @return int The command id (existing or newly created). */
    public function handle(Site $site, int $generationId): int
    {
        $existing = DB::table('commands')
            ->where('site_id', $site->id)
            ->where('source_type', 'ai_generation')
            ->where('source_id', $generationId)
            ->first();

        if ($existing !== null) {
            return (int) $existing->id;
        }

        $generation = DB::table('ai_generations')
            ->where('site_id', $site->id)
            ->where('id', $generationId)
            ->firstOrFail();

        $version = DB::table('ai_generation_versions')->where('id', $generation->current_version_id)->firstOrFail();
        $output = json_decode($version->output, true) ?? [];
        $input = json_decode($generation->input_redacted, true) ?? [];

        $kind = (string) ($output['kind'] ?? '');
        if (! in_array($kind, ['article', 'product'], true)) {
            throw new \InvalidArgumentException('فقط پیش‌نویس مقاله یا محصول (kind=article/product) قابل انتشار است.');
        }
        $contentType = $kind === 'product' ? 'product' : 'article';

        $title = (string) ($output['profile']['title'] ?? $output['text'] ?? '');
        $content = (string) ($output['text'] ?? '');
        if ($title === '' || $content === '') {
            throw new \RuntimeException('پیش‌نویس '.($contentType === 'product' ? 'محصول' : 'مقاله').' محتوای خالی دارد.');
        }

        $confidence = $this->assessConfidence($site, $output);

        $commandId = DB::table('commands')->insertGetId([
            'site_id' => $site->id,
            'source_type' => 'ai_generation',
            'source_id' => $generationId,
            'type' => 'publish_new_article',
            'content_type' => $contentType,
            'risk_tier' => 'R3',
            'payload' => json_encode([
                'title' => $title,
                'content' => $content,
                'slug' => $this->slugFrom((string) ($input['url'] ?? ''), $title),
                'target_query' => (string) ($input['target_query'] ?? ''),
                'meta_title' => mb_substr($title, 0, 60, 'UTF-8'),
                'schema' => $output['schema'] ?? [],
                'featured_image' => $output['featured_image'] ?? null,
                'content_type' => $contentType,
                // استاندارد مؤثر پیش‌نویس — گیت کیفیت دقیقاً با همین استاندارد ارزیابی می‌شود
                'standard' => $output['standard'] ?? [],
                'profile' => $output['profile'] ?? [],
            ], JSON_UNESCAPED_UNICODE),
            'idempotency_key' => (string) Str::uuid(),
            'status' => 'pending_approval',
            'confidence_score' => $confidence['score'],
            'confidence_factors' => json_encode($confidence['factors'], JSON_UNESCAPED_UNICODE),
            'expires_at' => now()->addDays(7),
            'policy_version' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->audit->handle(
            action: 'command.created_from_ai_generation',
            subject: $site,
            after: [
                'command_id' => $commandId,
                'generation_id' => $generationId,
                'type' => 'publish_new_article',
                'risk_tier' => 'R3',
                'confidence_score' => $confidence['score'],
            ],
        );

        return (int) $commandId;
    }

    /**
     * امتیاز اطمینان برای انتشار مقالهٔ جدید:
     *  - data_quality: تازگی آخرین import GSC (≤۷ روز = ۱٫۰)
     *  - signal_strength: تأیید انسانی + عبور از گیت کیفیت → سیگنال قوی
     *  - sources: [source پیش‌نویس، human_review] → توافق منابع
     *  - history: نرخ موفقیت publish_new_article از حلقهٔ یادگیری
     *
     * @param  array<string, mixed>  $output
     * @return array{score: int, factors: array<string, mixed>}
     */
    private function assessConfidence(Site $site, array $output): array
    {
        $dataQuality = $this->dataQuality((int) $site->id);
        $source = (string) ($output['source'] ?? 'rule_based');
        $history = $this->history((int) $site->id);

        $result = $this->scorer->score([
            'data_quality' => $dataQuality,
            'signal_strength' => 1.0, // تأیید انسانی + گیت کیفیت پاس شده
            'sources' => [$source, 'human_review'],
            'history' => $history,
        ]);

        return [
            'score' => $result['score'],
            'factors' => array_merge($result['factors'], [
                'human_approved' => true,
                'gsc_freshness' => round($dataQuality, 3),
                'source' => 'ai_generation:'.$source,
                'history' => $history,
            ]),
        ];
    }

    private function dataQuality(int $siteId): float
    {
        $property = DB::table('gsc_properties')->where('site_id', $siteId)->first();
        if ($property === null) {
            return 0.3;
        }

        $lastRun = DB::table('gsc_import_runs')
            ->where('gsc_property_id', $property->id)
            ->where('status', 'completed')
            ->orderByDesc('finished_at')
            ->value('finished_at');

        if ($lastRun === null) {
            return 0.3;
        }

        $days = (int) now()->diffInDays(Carbon::parse($lastRun));

        return match (true) {
            $days <= 7 => 1.0,
            $days <= 30 => 1.0 - 0.7 * (($days - 7) / 23),
            default => 0.3,
        };
    }

    /** @return array{total: int, successful: int}|null */
    private function history(int $siteId): ?array
    {
        $row = DB::table('automation_learning_history')
            ->where('site_id', $siteId)
            ->where('command_type', 'publish_new_article')
            ->first();

        if ($row === null) {
            return null;
        }

        return ['total' => (int) $row->total, 'successful' => (int) $row->successful];
    }

    private function slugFrom(string $url, string $title): string
    {
        $path = trim((string) parse_url($url, PHP_URL_PATH), '/');
        if ($path !== '') {
            $segments = array_values(array_filter(explode('/', $path), fn (string $s): bool => $s !== ''));
            $last = $segments !== [] ? (string) end($segments) : '';
            if ($last !== '') {
                return $last;
            }
        }

        return (string) Str::slug(mb_substr($title, 0, 60, 'UTF-8'));
    }
}
