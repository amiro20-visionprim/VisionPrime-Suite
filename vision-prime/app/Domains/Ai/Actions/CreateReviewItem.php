<?php

declare(strict_types=1);

namespace App\Domains\Ai\Actions;

use App\Domains\Audit\Actions\RecordAuditLog;
use App\Domains\Workspace\Models\Site;
use Illuminate\Support\Facades\DB;

/**
 * Creates a review item for a subject (money page audit, AI generation...).
 *
 * Review items surface in the "بررسی و تأییدها" queue and (once approved)
 * feed the client-approval loop. Creating the same subject twice is
 * idempotent: the existing review item is returned.
 */
class CreateReviewItem
{
    public function __construct(private readonly RecordAuditLog $audit) {}

    /** @return int The review_item id (existing or newly created). */
    public function handle(Site $site, string $subjectType, int $subjectId, ?int $assignedTo = null, ?\DateTimeInterface $dueAt = null): int
    {
        $existing = DB::table('review_items')
            ->where('site_id', $site->id)
            ->where('subject_type', $subjectType)
            ->where('subject_id', $subjectId)
            ->first();

        if ($existing !== null) {
            return (int) $existing->id;
        }

        $id = DB::table('review_items')->insertGetId([
            'site_id' => $site->id,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'status' => 'pending_review',
            'assigned_to' => $assignedTo,
            'due_at' => $dueAt ?? now()->addDays(3),
            'policy_snapshot' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->audit->handle(
            action: 'review.item_created',
            subject: $site,
            after: ['review_id' => $id, 'subject_type' => $subjectType, 'subject_id' => $subjectId],
        );

        return (int) $id;
    }
}
