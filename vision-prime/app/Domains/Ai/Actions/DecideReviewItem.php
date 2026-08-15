<?php

declare(strict_types=1);

namespace App\Domains\Ai\Actions;

use App\Domains\Audit\Actions\RecordAuditLog;
use App\Domains\Automation\Actions\AutoPublish;
use App\Domains\Automation\Actions\CreateArticlePublishCommand;
use App\Domains\Workspace\Models\Site;
use App\Models\User;

class DecideReviewItem
{
    public function __construct(
        private readonly RecordAuditLog $audit,
        private readonly CreateArticlePublishCommand $createArticlePublish,
        private readonly AutoPublish $autoPublish,
    ) {}

    /**
     * ثبت تصمیم بازبین و در صورت تأیید پیش‌نویس مقاله/محصول، ساخت کامند publish_new_article
     * و اجرای فوری گیت‌های auto_publish (فاز ۲).
     *
     * @return array{status: string, command_id?: int, auto_publish_decision?: string, auto_publish_reason?: string}
     */
    public function handle(int $reviewId, User $reviewer, string $decision, ?string $note = null): array
    {
        $item = \DB::table('review_items')->where('id', $reviewId)->firstOrFail();
        abort_unless($item->assigned_to === null || $item->assigned_to === $reviewer->id, 403);
        abort_unless(in_array($decision, ['approved', 'rejected', 'changes_requested'], true), 422);
        \DB::transaction(function () use ($item, $reviewer, $decision, $note) {
            \DB::table('review_decisions')->insert(['review_item_id' => $item->id, 'decision' => $decision, 'note' => $note, 'decided_by' => $reviewer->id, 'decided_at' => now()]);
            \DB::table('review_items')->where('id', $item->id)->update(['status' => $decision, 'updated_at' => now()]);
        });
        $this->audit->handle(action: 'review.decided', after: ['review_id' => $reviewId, 'decision' => $decision]);

        // فاز ۲: تأیید پیش‌نویس مقاله → ساخت کامند publish_new_article → گیت‌های auto_publish
        if ($decision !== 'approved' || $item->subject_type !== 'ai_generation') {
            return ['status' => $decision];
        }

        $generation = \DB::table('ai_generations')->where('id', $item->subject_id)->first();
        if ($generation === null) {
            return ['status' => $decision];
        }

        $version = \DB::table('ai_generation_versions')->where('id', $generation->current_version_id)->first();
        $output = $version === null ? [] : (json_decode($version->output, true) ?? []);
        if (! in_array(($output['kind'] ?? ''), ['article', 'product'], true)) {
            return ['status' => $decision];
        }

        try {
            $site = Site::query()->findOrFail($generation->site_id);
            $commandId = $this->createArticlePublish->handle($site, (int) $generation->id);
            $result = $this->autoPublish->handle($commandId);

            $this->audit->handle(
                action: 'article_draft.approved_pipeline',
                subject: $site,
                after: [
                    'review_id' => $reviewId,
                    'generation_id' => (int) $generation->id,
                    'command_id' => $commandId,
                    'auto_publish_decision' => $result['decision'],
                    'auto_publish_reason' => $result['reason'] ?? null,
                ],
            );

            return [
                'status' => $decision,
                'command_id' => $commandId,
                'auto_publish_decision' => $result['decision'],
                'auto_publish_reason' => $result['reason'] ?? null,
            ];
        } catch (\Throwable $e) {
            $this->audit->handle(
                action: 'article_draft.approved_pipeline_failed',
                after: ['review_id' => $reviewId, 'generation_id' => (int) $generation->id, 'error' => $e->getMessage()],
            );

            return ['status' => $decision, 'pipeline_error' => $e->getMessage()];
        }
    }
}
