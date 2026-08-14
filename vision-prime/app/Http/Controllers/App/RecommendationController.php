<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Domains\Audit\Actions\RecordAuditLog;
use App\Domains\Automation\Actions\AutoPublish;
use App\Domains\Automation\Actions\ConvertRecommendationToCommand;
use App\Domains\Organization\Contracts\CurrentOrganization;
use App\Domains\Seo\Models\Recommendation;
use App\Domains\Workspace\Models\Site;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class RecommendationController extends Controller
{
    public function index(CurrentOrganization $org): Response
    {
        $siteIds = Site::query()->where('organization_id', $org->id())->pluck('id');

        $items = Recommendation::query()
            ->with('site:id,name', 'owner:id,name')
            ->whereIn('site_id', $siteIds)
            ->orderByRaw("CASE status WHEN 'active' THEN 0 WHEN 'draft' THEN 1 WHEN 'done' THEN 2 ELSE 3 END")
            ->latest('id')
            ->paginate(25)
            ->through(fn (Recommendation $r): array => [
                'id' => $r->getKey(),
                'title' => $r->title,
                'body' => $r->body,
                'priority' => $r->priority,
                'status' => $r->status,
                'dueAt' => $r->due_at?->toIso8601String(),
                'createdAt' => $r->created_at?->toIso8601String(),
                'site' => $r->site === null ? null : ['id' => $r->site->getKey(), 'name' => $r->site->name],
                'owner' => $r->owner === null ? null : ['id' => $r->owner->getKey(), 'name' => $r->owner->name],
                'targetUrl' => $this->resolveTargetUrl($r),
                'commandId' => $r->source_type === 'opportunity'
                    ? DB::table('commands')->where('source_type', 'recommendation')->where('source_id', $r->getKey())->value('id')
                    : null,
            ]);

        return Inertia::render('App/Recommendations/Index', [
            'recommendations' => $items,
            'members' => $this->members($org),
        ]);
    }

    public function create(CurrentOrganization $org): Response
    {
        $sites = Site::query()->where('organization_id', $org->id())->orderBy('name')->get(['id', 'name']);

        return Inertia::render('App/Recommendations/Create', ['sites' => $sites, 'members' => $this->members($org)]);
    }

    public function store(Request $request, CurrentOrganization $org, RecordAuditLog $audit): RedirectResponse
    {
        $data = $request->validate([
            'site_id' => ['required', 'integer', 'exists:sites,id'],
            'title' => ['required', 'string', 'max:255'],
            'body' => ['nullable', 'string', 'max:4000'],
            'priority' => ['required', 'in:low,medium,high'],
            'owner_id' => ['nullable', 'integer', 'exists:users,id'],
            'due_at' => ['nullable', 'date'],
        ]);

        $site = Site::query()->where('organization_id', $org->id())->where('id', $data['site_id'])->firstOrFail();

        if (! empty($data['owner_id']) && ! $this->isActiveMember((int) $data['owner_id'], $org->id())) {
            return back()->withErrors(['owner_id' => 'مالک باید عضو فعال این سازمان باشد.'])->withInput();
        }

        $recommendation = Recommendation::query()->create([
            'site_id' => $site->id,
            'source_type' => 'manual',
            'title' => $data['title'],
            'body' => $data['body'] ?? '',
            'priority' => $data['priority'],
            'status' => 'active',
            'owner_id' => $data['owner_id'] ?? null,
            'due_at' => $data['due_at'] ?? null,
        ]);

        $audit->handle(
            action: 'recommendation.created',
            subject: $recommendation,
            organization: $site->organization,
            after: ['title' => $recommendation->title, 'priority' => $recommendation->priority],
        );

        return redirect()->route('app.recommendations.index')->with('status', 'پیشنهاد با موفقیت ثبت شد.');
    }

    public function update(Request $request, int $recommendation, CurrentOrganization $org, RecordAuditLog $audit): RedirectResponse
    {
        $item = Recommendation::query()
            ->whereIn('site_id', Site::query()->where('organization_id', $org->id())->pluck('id'))
            ->where('id', $recommendation)
            ->firstOrFail();

        $data = $request->validate([
            'owner_id' => ['nullable', 'integer', 'exists:users,id'],
            'due_at' => ['nullable', 'date'],
            'priority' => ['required', 'in:low,medium,high'],
            'status' => ['required', 'in:active,draft,done,cancelled'],
        ]);

        if (! empty($data['owner_id']) && ! $this->isActiveMember((int) $data['owner_id'], $org->id())) {
            return back()->withErrors(['owner_id' => 'مالک باید عضو فعال این سازمان باشد.']);
        }

        $item->update([
            'owner_id' => $data['owner_id'] ?? null,
            'due_at' => $data['due_at'] ?? null,
            'priority' => $data['priority'],
            'status' => $data['status'],
        ]);

        $audit->handle(
            action: 'recommendation.updated',
            subject: $item,
            organization: $org->get(),
            after: ['status' => $item->status, 'priority' => $item->priority],
        );

        return back()->with('status', 'پیشنهاد به‌روزرسانی شد.');
    }

    public function fromOpportunity(int $opportunity, CurrentOrganization $org, RecordAuditLog $audit): RedirectResponse
    {
        $siteIds = Site::query()->where('organization_id', $org->id())->pluck('id');
        $opp = DB::table('opportunities')->whereIn('site_id', $siteIds)->where('id', $opportunity)->firstOrFail();

        $existing = Recommendation::query()
            ->where('source_type', 'opportunity')
            ->where('source_id', $opp->id)
            ->exists();

        if ($existing) {
            return redirect()->route('app.recommendations.index')
                ->with('status', 'این فرصت قبلاً به پیشنهاد تبدیل شده است.');
        }

        $recommendation = Recommendation::query()->create([
            'site_id' => $opp->site_id,
            'source_type' => 'opportunity',
            'source_id' => $opp->id,
            'title' => 'اقدام برای فرصت رشد (امتیاز '.$opp->score.')',
            'body' => $opp->explanation,
            'priority' => $opp->score >= 80 ? 'high' : ($opp->score >= 60 ? 'medium' : 'low'),
            'status' => 'draft',
        ]);

        $audit->handle(
            action: 'recommendation.created_from_opportunity',
            subject: $recommendation,
            organization: $org->get(),
        );

        return redirect()->route('app.recommendations.index')
            ->with('status', 'فرصت به پیشنهاد تبدیل شد؛ از دکمه ویرایش، مالک و مهلت را تعیین کنید.');
    }

    public function toCommand(Request $request, int $recommendation, CurrentOrganization $org, ConvertRecommendationToCommand $converter, AutoPublish $autoPublish): RedirectResponse
    {
        $siteIds = Site::query()->where('organization_id', $org->id())->pluck('id');
        $item = Recommendation::query()
            ->whereIn('site_id', $siteIds)
            ->where('id', $recommendation)
            ->firstOrFail();

        if (! in_array($item->status, ['active', 'draft'], true)) {
            return back()->with('error', 'فقط پیشنهادهای فعال یا پیش‌نویس به تغییر اجرایی تبدیل می‌شوند.');
        }

        $data = $request->validate([
            'type' => ['required', 'in:'.implode(',', ConvertRecommendationToCommand::SUPPORTED_TYPES)],
            'target_url' => ['required', 'url', 'max:2048'],
            'new_value' => ['required', 'string', 'max:2000'],
        ]);

        $alreadyConverted = DB::table('commands')
            ->where('site_id', $item->site_id)
            ->where('source_type', 'recommendation')
            ->where('source_id', $item->id)
            ->exists();

        $commandId = $converter->handle($item, $data['type'], $data['target_url'], $data['new_value']);

        // خط لولهٔ D-013 گام ۴–۵: PolicyEvaluator تصمیم می‌گیرد و AutoPublish اجرا می‌کند.
        // بدون confidence_score (فعلاً null) همه‌چیز fail-closed به تأیید انسانی می‌رود؛
        // با فعال‌شدن موتور امتیازدهی (فاز ۳) مسیر انتشار خودکار باز می‌شود.
        $auto = $autoPublish->handle($commandId);
        $autoNote = $auto['decision'] === 'auto_publish'
            ? 'تغییر اجرایی طبق سیاست خودکارسازی منتشر شد.'
            : null;

        return back()->with(
            'status',
            $autoNote
            ?? ($alreadyConverted
                ? 'این پیشنهاد قبلاً به تغییر اجرایی تبدیل شده است.'
                : 'تغییر اجرایی ساخته شد و برای تأیید مشتری ارسال گردید.')
        );
    }

    private function resolveTargetUrl(Recommendation $recommendation): ?string
    {
        $profileId = match ($recommendation->source_type) {
            'opportunity' => DB::table('opportunities')->where('id', $recommendation->source_id)->value('url_profile_id'),
            'conversion_risk' => DB::table('conversion_risks')->where('id', $recommendation->source_id)->value('url_profile_id'),
            'money_page_audit' => DB::table('money_page_audits')->where('id', $recommendation->source_id)->value('url_profile_id'),
            default => null,
        };

        if ($profileId === null) {
            return null;
        }

        return DB::table('url_profiles')->where('id', $profileId)->value('canonical_url');
    }

    /** @return array<int, array{id: int, name: string}> */
    private function members(CurrentOrganization $org): array
    {
        return DB::table('memberships')
            ->join('users', 'users.id', '=', 'memberships.user_id')
            ->where('memberships.organization_id', $org->id())
            ->where('memberships.status', 'active')
            ->orderBy('users.name')
            ->get(['users.id', 'users.name'])
            ->map(fn ($row): array => ['id' => (int) $row->id, 'name' => $row->name])
            ->all();
    }

    private function isActiveMember(int $userId, int $organizationId): bool
    {
        return DB::table('memberships')
            ->where('organization_id', $organizationId)
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->exists();
    }
}
