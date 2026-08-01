<?php

declare(strict_types=1);

namespace App\Domains\Ai\Actions;

use App\Domains\Audit\Actions\RecordAuditLog;
use App\Models\User;

class DecideReviewItem
{
    public function __construct(private readonly RecordAuditLog $audit) {}

    public function handle(int $reviewId, User $reviewer, string $decision, ?string $note = null): void
    {
        $item = \DB::table('review_items')->where('id', $reviewId)->firstOrFail();
        abort_unless($item->assigned_to === null || $item->assigned_to === $reviewer->id, 403);
        abort_unless(in_array($decision, ['approved', 'rejected', 'changes_requested'], true), 422);
        \DB::transaction(function () use ($item, $reviewer, $decision, $note) {
            \DB::table('review_decisions')->insert(['review_item_id' => $item->id, 'decision' => $decision, 'note' => $note, 'decided_by' => $reviewer->id, 'decided_at' => now()]);
            \DB::table('review_items')->where('id', $item->id)->update(['status' => $decision, 'updated_at' => now()]);
        });
        $this->audit->handle(action: 'review.decided', after: ['review_id' => $reviewId, 'decision' => $decision]);
    }
}
