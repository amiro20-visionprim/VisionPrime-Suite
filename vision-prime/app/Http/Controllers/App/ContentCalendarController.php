<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Domains\Ai\Actions\GenerateArticleDraft;
use App\Domains\Automation\Actions\AutoPublish;
use App\Domains\Automation\Actions\SchedulePublish;
use App\Domains\Automation\Services\SuggestPublishSlot;
use App\Domains\Content\Services\ContentProfiler;
use App\Domains\Organization\Contracts\CurrentOrganization;
use App\Domains\Workspace\Models\Site;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * تقویم محتوایی — برنامه‌ریزی انتشار پیش‌نویس‌های مقاله/محصول (publish_new_article).
 *
 * نمای ماهانه/هفتگی تقویم جلالی با کامندهای زمان‌بندی‌شده/منتشرشده + اکشن‌های
 * زمان‌بندی، تغییر زمان، لغو و **انتشار فوری** + ساخت پیش‌نویس زمان‌بندی‌شده از
 * داخل تقویم + پیشنهاد هوشمند روز انتشار از دادهٔ GSC. کامندهای زمان‌بندی‌شده
 * (status=scheduled) در لحظهٔ موعد توسط ReleaseScheduledCommands از AutoPublish
 * عبور می‌کنند.
 */
class ContentCalendarController extends Controller
{
    public function index(CurrentOrganization $org, Request $request): Response
    {
        $siteIds = Site::query()->where('organization_id', $org->id())->pluck('id');

        $sites = DB::table('sites')->whereIn('id', $siteIds)->orderBy('name')->get(['id', 'name']);

        $profiles = DB::table('url_profiles')
            ->join('sites', 'sites.id', '=', 'url_profiles.site_id')
            ->where('sites.organization_id', $org->id())
            ->whereIn('url_profiles.content_type', ['page', 'post', 'product'])
            ->get(['url_profiles.id', 'url_profiles.site_id', 'url_profiles.canonical_url', 'url_profiles.content_type'])
            ->map(fn (object $p): array => [
                'id' => (int) $p->id,
                'site_id' => (int) $p->site_id,
                'canonical_url' => (string) $p->canonical_url,
                'content_type' => (string) $p->content_type,
            ])
            ->values()
            ->all();

        // بازهٔ نمایش (گرگوری) — کلاینت بازهٔ معادل ماه/هفتهٔ جلالی جاری را می‌فرستد
        $from = (string) $request->query('from', now()->startOfMonth()->toDateString());
        $to = (string) $request->query('to', now()->endOfMonth()->toDateString());
        $range = [Carbon::parse($from)->startOfDay(), Carbon::parse($to)->endOfDay()];
        $siteFilter = (int) $request->query('site', 0);

        $query = DB::table('commands')
            ->whereIn('site_id', $siteIds)
            ->where('type', 'publish_new_article')
            ->where(function ($q) use ($range): void {
                // روز نمایش: موعد برنامه‌ریزی، وگرنه تاریخ انتشار، وگرنه تاریخ ساخت
                $q->whereBetween('scheduled_for', $range)
                    ->orWhereBetween('published_at', $range)
                    ->orWhereBetween('created_at', $range);
            });

        if ($siteFilter > 0) {
            $query->where('site_id', $siteFilter);
        }

        $rows = $query->orderByRaw('COALESCE(scheduled_for, published_at, created_at) DESC')->get();

        $items = $rows->map(function (object $command): array {
            $payload = json_decode((string) ($command->payload ?? '{}'), true) ?? [];

            return [
                'id' => (int) $command->id,
                'site_id' => (int) $command->site_id,
                'title' => (string) ($payload['title'] ?? $payload['content_type'] ?? 'پیش‌نویس'),
                'content_type' => (string) ($command->content_type ?? 'article'),
                'status' => (string) $command->status,
                'scheduled_for' => $command->scheduled_for ?? null,
                'published_at' => $command->published_at ?? null,
                'created_at' => $command->created_at ?? null,
                'confidence_score' => $command->confidence_score !== null ? (int) $command->confidence_score : null,
                'risk_tier' => (string) $command->risk_tier,
            ];
        })->all();

        $itemsByDate = [];
        foreach ($items as $item) {
            $date = substr((string) ($item['scheduled_for'] ?? $item['published_at'] ?? $item['created_at'] ?? ''), 0, 10);
            if ($date === '') {
                continue;
            }
            $itemsByDate[$date][] = $item;
        }

        // پیشنهاد هوشمند روز انتشار (از میانگین کلیک روزهای هفته در GSC اخیر) به‌ازای هر سایت
        $suggest = app(SuggestPublishSlot::class);
        $suggestions = [];
        foreach ($sites as $site) {
            $slot = $suggest->suggest((int) $site->id);
            if ($slot !== null) {
                $suggestions[(int) $site->id] = $slot;
            }
        }

        return Inertia::render('App/ContentCalendar', [
            'items' => $items,
            'itemsByDate' => $itemsByDate,
            'sites' => $sites,
            'profiles' => $profiles,
            'subtypes' => ContentProfiler::subtypeLabels(),
            'suggestions' => $suggestions,
            'from' => $range[0]->toDateString(),
            'to' => $range[1]->toDateString(),
            'siteFilter' => $siteFilter,
        ]);
    }

    public function schedule(Request $request, int $command, CurrentOrganization $org, SchedulePublish $schedulePublish): RedirectResponse
    {
        $siteIds = Site::query()->where('organization_id', $org->id())->pluck('id');
        $row = DB::table('commands')
            ->whereIn('site_id', $siteIds)
            ->where('id', $command)
            ->firstOrFail();

        $data = $request->validate([
            'action' => ['required', 'in:schedule,cancel,publish_now'],
            'scheduled_for' => ['nullable', 'date', 'after:now'],
        ]);

        if ($data['action'] === 'cancel') {
            $schedulePublish->cancel((int) $row->id);

            return back()->with('status', 'زمان‌بندی لغو شد و پیش‌نویس به انتظار تأیید بازگشت.');
        }

        if ($data['action'] === 'publish_now') {
            $result = $schedulePublish->publishNow((int) $row->id, app(AutoPublish::class));

            return back()->with('status', $result['decision'] === 'auto_publish'
                ? 'پیش‌نویس بلافاصله منتشر شد.'
                : 'پیش‌نویس به جریان انتشار فرستاده شد ('.($result['decision'] ?? 'pending_approval').').');
        }

        $schedulePublish->schedule((int) $row->id, (string) $data['scheduled_for']);

        return back()->with('status', 'زمان انتشار ثبت شد.');
    }

    /** ساخت پیش‌نویس مقاله/محصول زمان‌بندی‌شده از داخل تقویم. */
    public function storeDraft(Request $request, CurrentOrganization $org, GenerateArticleDraft $generate): RedirectResponse
    {
        $data = $request->validate([
            'site_id' => ['required', 'integer'],
            'url_profile_id' => ['required', 'integer'],
            'title' => ['nullable', 'string', 'max:200'],
            'subtype' => ['nullable', 'string'],
            'scheduled_for' => ['required', 'date', 'after:now'],
        ]);

        $site = Site::query()->where('organization_id', $org->id())->where('id', (int) $data['site_id'])->firstOrFail();

        $profile = DB::table('url_profiles')
            ->where('site_id', $site->id)
            ->where('id', (int) $data['url_profile_id'])
            ->first();
        if ($profile === null) {
            abort(404);
        }

        $scheduledAt = Carbon::parse((string) $data['scheduled_for']);

        try {
            $generationId = $generate->handle(
                $site,
                (int) $profile->id,
                isset($data['title']) ? (string) $data['title'] : null,
                isset($data['subtype']) ? (string) $data['subtype'] : null,
            );
        } catch (\Throwable $e) {
            return back()->with('error', 'ساخت پیش‌نویس ناموفق بود: '.$e->getMessage());
        }

        DB::table('ai_generations')->where('id', $generationId)->update([
            'scheduled_for' => $scheduledAt->toDateTimeString(),
            'updated_at' => now(),
        ]);

        return redirect()->route('app.reviews.index')
            ->with('status', 'پیش‌نویس زمان‌بندی‌شده ساخته شد (#'.$generationId.') — پس از تأیید، در موعد «'.$scheduledAt->format('Y-m-d H:i').'» منتشر می‌شود.');
    }
}
