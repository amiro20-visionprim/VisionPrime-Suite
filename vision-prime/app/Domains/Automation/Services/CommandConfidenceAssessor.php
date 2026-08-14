<?php

declare(strict_types=1);

namespace App\Domains\Automation\Services;

use App\Domains\Seo\Models\Recommendation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * اتصال موتور امتیازدهی به خط لوله (D-013 گام ۳).
 *
 * هنگام ساخت command از توصیه، سیگنال‌های تصمیم‌ساز را جمع می‌کند:
 *  - data_quality: تازگی آخرین همگام‌سازی GSC سایت (≤۷ روز = ۱٫۰، تا ۳۰ روز محو می‌شود)
 *  - signal_strength: confidence فرصتِ مبدأ (opportunities.confidence) یا نگاشت اولویت
 *  - sources: توافق منابع پیش‌نویس (فعلاً rule_based؛ افزودن ai در آینده)
 *  - history: نرخ موفقیت همان نوع تغییر از automation_learning_history (حلقهٔ یادگیری)
 * و خروجی score + factors را برمی‌گرداند تا روی commands ذخیره شود.
 */
class CommandConfidenceAssessor
{
    public function __construct(private readonly ConfidenceScorer $scorer) {}

    /**
     * @return array{score: int, factors: array<string, mixed>}
     */
    public function assess(Recommendation $recommendation, string $commandType): array
    {
        $dataQuality = $this->dataQuality((int) $recommendation->site_id);
        $signalStrength = $this->signalStrength($recommendation);
        $history = $this->history((int) $recommendation->site_id, $commandType);

        $result = $this->scorer->score([
            'data_quality' => $dataQuality,
            'signal_strength' => $signalStrength,
            'sources' => ['rule_based'],
            'history' => $history,
        ]);

        return [
            'score' => $result['score'],
            'factors' => array_merge($result['factors'], [
                'gsc_freshness' => round($dataQuality, 3),
                'source' => 'recommendation:'.($recommendation->source_type ?? 'manual'),
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

    private function signalStrength(Recommendation $recommendation): float
    {
        if ($recommendation->source_type === 'opportunity' && $recommendation->source_id !== null) {
            $confidence = DB::table('opportunities')->where('id', $recommendation->source_id)->value('confidence');
            if ($confidence !== null) {
                return max(0.0, min(1.0, (float) $confidence));
            }
        }

        return match ($recommendation->priority) {
            'high' => 0.9,
            'low' => 0.6,
            default => 0.75,
        };
    }

    /** @return array{total: int, successful: int}|null */
    private function history(int $siteId, string $commandType): ?array
    {
        $row = DB::table('automation_learning_history')
            ->where('site_id', $siteId)
            ->where('command_type', $commandType)
            ->first();

        if ($row === null) {
            return null;
        }

        return ['total' => (int) $row->total, 'successful' => (int) $row->successful];
    }
}
