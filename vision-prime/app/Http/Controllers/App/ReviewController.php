<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Domains\Organization\Contracts\CurrentOrganization;
use App\Domains\Workspace\Models\Site;
use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class ReviewController extends Controller
{
    public function index(CurrentOrganization $org): Response
    {
        $siteIds = Site::query()->where('organization_id', $org->id())->pluck('id');
        $items = \DB::table('review_items')
            ->join('sites', 'sites.id', '=', 'review_items.site_id')
            ->whereIn('review_items.site_id', $siteIds)
            ->latest('review_items.id')
            ->paginate(50, ['review_items.*', 'sites.name as site_name'])
            ->through(function (object $item): array {
                return [
                    'id' => $item->id,
                    'subject_type' => $item->subject_type,
                    'subject_id' => $item->subject_id,
                    'status' => $item->status,
                    'assigned_to' => $item->assigned_to,
                    'due_at' => $item->due_at,
                    'created_at' => $item->created_at,
                    'site_name' => $item->site_name,
                    'subject_label' => $this->subjectLabel((string) $item->subject_type, (int) $item->subject_id),
                ];
            });

        return Inertia::render('App/Reviews/Index', ['items' => $items]);
    }

    private function aiGenerationLabel(int $subjectId): ?string
    {
        $row = \DB::table('ai_generations')
            ->join('ai_generation_versions', 'ai_generation_versions.id', '=', 'ai_generations.current_version_id')
            ->where('ai_generations.id', $subjectId)
            ->first(['ai_generation_versions.output']);

        if ($row === null) {
            return null;
        }

        $output = json_decode($row->output, true) ?? [];
        $kind = (string) ($output['kind'] ?? '');
        $text = (string) ($output['text'] ?? '');

        if ($kind === 'article') {
            // برای مقاله فقط عنوان (h1) و پیش‌نمایش کوتاه متن نشان داده می‌شود
            if (preg_match('/<h1[^>]*>(.*?)<\/h1>/su', $text, $m)) {
                return '📄 '.strip_tags((string) $m[1]);
            }

            return '📄 '.mb_substr(strip_tags($text), 0, 120, 'UTF-8');
        }

        return mb_substr($text, 0, 160, 'UTF-8');
    }

    private function subjectLabel(string $subjectType, int $subjectId): ?string
    {
        return match ($subjectType) {
            'money_page_audit' => \DB::table('money_page_audits')
                ->join('url_profiles', 'url_profiles.id', '=', 'money_page_audits.url_profile_id')
                ->where('money_page_audits.id', $subjectId)
                ->value('url_profiles.canonical_url'),
            'ai_generation' => $this->aiGenerationLabel($subjectId),
            'command' => \DB::table('commands')->where('id', $subjectId)->value('type'),
            default => null,
        };
    }
}
