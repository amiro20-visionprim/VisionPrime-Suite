<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Domains\Audit\Actions\RecordAuditLog;
use App\Domains\Organization\Contracts\CurrentOrganization;
use App\Domains\Organization\Models\Organization;
use App\Domains\Workspace\Services\OrganizationPermission;
use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\LeadNote;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MarketingLeadController extends Controller
{
    public function __construct(
        private readonly OrganizationPermission $permission,
        private readonly CurrentOrganization $currentOrganization,
        private readonly RecordAuditLog $audit,
    ) {}

    public function index(Request $request): Response
    {
        $organization = $this->currentOrganization->get();
        $this->authorizeView($organization);

        $query = Lead::query();

        $status = $request->string('status')->toString();
        if ($status !== '' && array_key_exists($status, Lead::STATUS_LABELS)) {
            $query->where('status', $status);
        }

        $source = $request->string('source')->toString();
        if (in_array($source, ['demo', 'support'], true)) {
            $query->where('source', $source);
        }

        $campaign = $request->string('campaign')->trim()->toString();
        if ($campaign !== '') {
            $query->where('utm_campaign', 'like', "%{$campaign}%");
        }

        $search = $request->string('q')->trim()->toString();
        if ($search !== '') {
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('company', 'like', "%{$search}%");
            });
        }

        $from = $request->string('from')->toString();
        if ($from !== '' && strtotime($from) !== false) {
            $query->whereDate('created_at', '>=', $from);
        }

        $to = $request->string('to')->toString();
        if ($to !== '' && strtotime($to) !== false) {
            $query->whereDate('created_at', '<=', $to);
        }

        $sort = $request->string('sort')->toString();
        $query = $sort === 'score' ? $query->orderByDesc('score') : $query->orderByDesc('created_at');

        $leads = (clone $query)
            ->limit(200)
            ->get()
            ->map(fn (Lead $lead): array => $this->leadItem($lead))
            ->values();

        $stats = [
            'total' => Lead::query()->count(),
            'thisWeek' => Lead::query()->where('created_at', '>=', now()->subDays(7))->count(),
            'byStatus' => collect(Lead::STATUS_LABELS)->mapWithKeys(
                fn (string $label, string $key): array => [$key => Lead::query()->where('status', $key)->count()]
            )->all(),
            'bySource' => [
                'demo' => Lead::query()->where('source', 'demo')->count(),
                'support' => Lead::query()->where('source', 'support')->count(),
            ],
            'topCampaigns' => Lead::query()
                ->whereNotNull('utm_campaign')
                ->where('utm_campaign', '!=', '')
                ->selectRaw('utm_campaign as campaign, count(*) as count')
                ->groupBy('utm_campaign')
                ->orderByDesc('count')
                ->limit(5)
                ->get()
                ->map(fn ($row): array => ['campaign' => (string) $row->campaign, 'count' => (int) $row->count])
                ->all(),
            'topSources' => Lead::query()
                ->whereNotNull('utm_source')
                ->where('utm_source', '!=', '')
                ->selectRaw('utm_source as source, count(*) as count')
                ->groupBy('utm_source')
                ->orderByDesc('count')
                ->limit(5)
                ->get()
                ->map(fn ($row): array => ['source' => (string) $row->source, 'count' => (int) $row->count])
                ->all(),
        ];

        return Inertia::render('App/Marketing/Index', [
            'leads' => $leads,
            'stats' => $stats,
            'filters' => [
                'status' => $status,
                'source' => $source,
                'campaign' => $campaign,
                'q' => $search,
                'from' => $from,
                'to' => $to,
                'sort' => $sort,
            ],
            'statusLabels' => Lead::STATUS_LABELS,
            'canManage' => $this->canManage($organization),
        ]);
    }

    public function show(Request $request, Lead $lead): Response
    {
        $organization = $this->currentOrganization->get();
        $this->authorizeView($organization);

        return Inertia::render('App/Marketing/Show', [
            'lead' => $this->leadItem($lead, full: true),
            'notes' => $lead->notes()
                ->with('user:id,name,email')
                ->get()
                ->map(fn (LeadNote $note): array => [
                    'id' => $note->getKey(),
                    'body' => $note->body,
                    'createdAt' => $note->created_at?->toIso8601String(),
                    'user' => $note->user === null ? null : [
                        'name' => $note->user->name,
                        'email' => $note->user->email,
                    ],
                ])
                ->values(),
            'statusLabels' => Lead::STATUS_LABELS,
            'canManage' => $this->canManage($organization),
        ]);
    }

    public function updateStatus(Request $request, Lead $lead): RedirectResponse
    {
        $organization = $this->currentOrganization->get();
        $this->authorizeManage($organization);

        $data = $request->validate([
            'status' => ['required', 'string', 'in:'.implode(',', array_keys(Lead::STATUS_LABELS))],
        ]);

        $previous = $lead->status;
        $lead->update(['status' => $data['status']]);

        $this->audit->handle(
            action: 'marketing.lead_status_changed',
            subject: $lead,
            before: ['status' => $previous],
            after: ['status' => $lead->status],
            organization: $organization,
        );

        return back()->with('status', 'وضعیت لید به‌روزرسانی شد.');
    }

    public function storeNote(Request $request, Lead $lead): RedirectResponse
    {
        $organization = $this->currentOrganization->get();
        $this->authorizeManage($organization);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
        ]);

        $note = LeadNote::query()->create([
            'lead_id' => $lead->getKey(),
            'user_id' => $request->user()?->getKey(),
            'body' => trim($data['body']),
        ]);

        $this->audit->handle(
            action: 'marketing.lead_note_added',
            subject: $note,
            after: ['lead_id' => $lead->getKey(), 'body' => $note->body],
            organization: $organization,
        );

        return back()->with('status', 'یادداشت ثبت شد.');
    }

    /** @return array<string, mixed> */
    private function leadItem(Lead $lead, bool $full = false): array
    {
        $metadata = $lead->metadata ?? [];

        $item = [
            'id' => $lead->getKey(),
            'name' => $lead->name,
            'email' => $lead->email,
            'company' => $lead->company,
            'website' => $lead->website,
            'message' => $lead->message,
            'source' => $lead->source,
            'status' => $lead->status,
            'utmSource' => $lead->utm_source,
            'utmMedium' => $lead->utm_medium,
            'utmCampaign' => $lead->utm_campaign,
            'utmTerm' => $lead->utm_term,
            'utmContent' => $lead->utm_content,
            'landingPage' => $lead->landing_page,
            'referrer' => $lead->referrer,
            'contact' => $metadata['contact'] ?? null,
            'device' => $metadata['device'] ?? null,
            'score' => $lead->score,
            'scoreBreakdown' => $metadata['score_breakdown']['items'] ?? [],
            'createdAt' => $lead->created_at?->toIso8601String(),
        ];

        if ($full) {
            $item['userAgent'] = $metadata['user_agent'] ?? null;
            $item['locale'] = $metadata['locale'] ?? null;
        }

        return $item;
    }

    private function canManage(Organization $organization): bool
    {
        $user = request()->user();

        return $user !== null && $this->permission->allows($user, $organization, 'marketing.manage.organization');
    }

    private function authorizeView(Organization $organization): void
    {
        if (! $this->canView(request()->user(), $organization)) {
            abort(403, 'شما دسترسی به دادهٔ بازاریابی را ندارید.');
        }
    }

    private function canView(?User $user, Organization $organization): bool
    {
        return $user !== null && $this->permission->allows($user, $organization, 'marketing.view.organization');
    }

    private function authorizeManage(Organization $organization): void
    {
        if (! $this->canManage($organization)) {
            abort(403, 'شما دسترسی مدیریت لیدها را ندارید.');
        }
    }
}
