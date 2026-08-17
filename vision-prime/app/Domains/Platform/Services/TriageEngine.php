<?php

declare(strict_types=1);

namespace App\Domains\Platform\Services;

use App\Domains\Audit\Actions\RecordAuditLog;
use Illuminate\Support\Facades\DB;

/**
 * Triage Engine — قلب «اتاق فرماندهی استثنامحور» (سند ۳۷ بخش ۳.۵).
 *
 * هر رویداد به یکی از سه دسته طبقه‌بندی می‌شود:
 *   - normal    → بی‌صدا ثبت می‌شود (فقط در خلاصهٔ روزانه)
 *   - exception → نیاز به اعلان فعال دارد (تلگرام/ایمیل)
 *   - decision  → فقط این‌ها وارد «صف تصمیم» می‌شوند (اقدام انسانی لازم است)
 */
class TriageEngine
{
    public const TRIAGE_NORMAL = 'normal';

    public const TRIAGE_EXCEPTION = 'exception';

    public const TRIAGE_DECISION = 'decision';

    /** رویدادهایی که همیشه «تصمیم» هستند (اقدام انسانی لازم) */
    private const DECISION_TYPES = [
        'review.awaiting',
        'command.awaiting',
        'payment.failed', // مالک تصمیم می‌گیرد: یادآوری / تعلیق / صرف‌نظر
    ];

    /** رویدادهایی که «استثنا» هستند (نیاز به اعلان فعال) */
    private const EXCEPTION_TYPES = [
        'subscription.expiring',
        'ai.cost_spike',
        'site.disconnected',
        'job.failure',
        'subscription.past_due',
        'subscription.suspended',
    ];

    public function __construct(private readonly RecordAuditLog $audit) {}

    public function classify(string $type): string
    {
        if (in_array($type, self::DECISION_TYPES, true)) {
            return self::TRIAGE_DECISION;
        }

        if (in_array($type, self::EXCEPTION_TYPES, true)) {
            return self::TRIAGE_EXCEPTION;
        }

        return self::TRIAGE_NORMAL;
    }

    /**
     * ثبت رویداد با طبقه‌بندی خودکار (idempotent روی همان type+org+payload-hash در ۲۴ ساعت).
     *
     * @param  array<string, mixed>  $payload
     */
    public function record(?int $organizationId, string $type, string $severity, array $payload = []): int
    {
        $hash = md5($type.'|'.($organizationId ?? 'all').'|'.json_encode($payload));

        $existing = DB::table('platform_events')
            ->where('type', $type)
            ->where('organization_id', $organizationId)
            ->where('created_at', '>=', now()->subDay())
            ->get()
            ->first(fn ($row): bool => md5($type.'|'.($organizationId ?? 'all').'|'.json_encode(json_decode((string) $row->payload, true) ?? [])) === $hash);

        if ($existing !== null) {
            return (int) $existing->id;
        }

        $triage = $this->classify($type);

        $id = DB::table('platform_events')->insertGetId([
            'organization_id' => $organizationId,
            'type' => $type,
            'severity' => $severity,
            'triage' => $triage,
            'payload' => json_encode($payload),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($triage !== self::TRIAGE_NORMAL) {
            $this->audit->handle(
                action: 'platform.event.'.($triage === self::TRIAGE_DECISION ? 'decision' : 'exception'),
                after: ['type' => $type, 'organization_id' => $organizationId, 'payload' => $payload],
                organization: null,
                source: 'platform',
            );
        }

        return (int) $id;
    }

    /** @return array<int, array<string, mixed>> */
    public function pendingDecisions(int $limit = 20): array
    {
        return DB::table('platform_events')
            ->where('triage', self::TRIAGE_DECISION)
            ->whereNull('resolved_at')
            ->orderByDesc('severity')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(fn ($row): array => [
                'id' => (int) $row->id,
                'type' => (string) $row->type,
                'severity' => (string) $row->severity,
                'payload' => json_decode((string) $row->payload, true) ?? [],
                'organization_id' => $row->organization_id,
                'created_at' => (string) $row->created_at,
            ])->all();
    }

    /** @return array<int, array<string, mixed>> */
    public function unresolvedExceptions(int $limit = 20): array
    {
        return DB::table('platform_events')
            ->where('triage', self::TRIAGE_EXCEPTION)
            ->whereNull('resolved_at')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(fn ($row): array => [
                'id' => (int) $row->id,
                'type' => (string) $row->type,
                'severity' => (string) $row->severity,
                'payload' => json_decode((string) $row->payload, true) ?? [],
                'organization_id' => $row->organization_id,
                'created_at' => (string) $row->created_at,
            ])->all();
    }

    public function resolve(int $eventId, ?int $actorId, string $note = ''): void
    {
        DB::table('platform_events')
            ->where('id', $eventId)
            ->update([
                'resolved_at' => now(),
                'resolved_by' => $actorId,
                'resolution_note' => $note,
                'updated_at' => now(),
            ]);

        $this->audit->handle(
            action: 'platform.event.resolved',
            after: ['event_id' => $eventId, 'note' => $note],
            organization: null,
            source: 'platform',
        );
    }
}
