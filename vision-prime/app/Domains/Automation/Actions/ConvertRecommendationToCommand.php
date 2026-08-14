<?php

declare(strict_types=1);

namespace App\Domains\Automation\Actions;

use App\Domains\Audit\Actions\RecordAuditLog;
use App\Domains\Automation\Services\CommandConfidenceAssessor;
use App\Domains\Seo\Models\Recommendation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Converts an approved recommendation into an executable command (pending client approval).
 *
 * The command is created with status `pending_approval` so it appears in the client
 * portal ("نیازمند تصمیم شما") and can be approved/rejected by the client.
 * Converting the same recommendation twice is idempotent: the existing command is returned.
 */
class ConvertRecommendationToCommand
{
    public const SUPPORTED_TYPES = ['update_meta_title', 'update_meta_description'];

    public function __construct(
        private readonly RecordAuditLog $audit,
        private readonly CommandConfidenceAssessor $assessor,
    ) {}

    /** @return int The command id (existing or newly created). */
    public function handle(Recommendation $recommendation, string $type, string $targetUrl, string $newValue): int
    {
        if (! in_array($type, self::SUPPORTED_TYPES, true)) {
            throw new \InvalidArgumentException('نوع تغییر اجرایی پشتیبانی نمی‌شود.');
        }

        $existing = DB::table('commands')
            ->where('site_id', $recommendation->site_id)
            ->where('source_type', 'recommendation')
            ->where('source_id', $recommendation->id)
            ->first();

        if ($existing !== null) {
            return (int) $existing->id;
        }

        $payloadKey = $type === 'update_meta_title' ? 'title' : 'description';
        $riskTier = match ($recommendation->priority) {
            'high' => 'R3',
            'low' => 'R1',
            default => 'R2',
        };

        // D-013 گام ۳: امتیاز اطمینان از سیگنال‌های GSC + توافق منابع + سابقهٔ یادگیری.
        $confidence = $this->assessor->assess($recommendation, $type);

        $commandId = DB::table('commands')->insertGetId([
            'site_id' => $recommendation->site_id,
            'source_type' => 'recommendation',
            'source_id' => $recommendation->id,
            'type' => $type,
            'risk_tier' => $riskTier,
            'payload' => json_encode(['url' => $targetUrl, $payloadKey => $newValue], JSON_UNESCAPED_UNICODE),
            'idempotency_key' => (string) Str::uuid(),
            'status' => 'pending_approval',
            'confidence_score' => $confidence['score'],
            'confidence_factors' => json_encode($confidence['factors'], JSON_UNESCAPED_UNICODE),
            'expires_at' => now()->addDays(7),
            'policy_version' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($recommendation->status === 'draft') {
            $recommendation->update(['status' => 'active']);
        }

        $this->audit->handle(
            action: 'command.created_from_recommendation',
            subject: $recommendation,
            after: [
                'command_id' => $commandId,
                'recommendation_id' => $recommendation->id,
                'type' => $type,
                'risk_tier' => $riskTier,
                'confidence_score' => $confidence['score'],
            ],
        );

        return (int) $commandId;
    }
}
