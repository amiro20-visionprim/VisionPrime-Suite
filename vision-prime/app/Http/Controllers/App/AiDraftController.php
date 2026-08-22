<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Domains\Ai\Actions\GenerateArticleDraft;
use App\Domains\Ai\Actions\GenerateMetaDraft;
use App\Domains\Content\Services\ContentProfiler;
use App\Domains\Content\Services\StandardsKB;
use App\Domains\Content\Models\ContentDraft;
use App\Domains\Organization\Contracts\CurrentOrganization;
use App\Domains\Workspace\Models\Site;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AiDraftController extends Controller
{
    public function store(Request $request, CurrentOrganization $org, GenerateMetaDraft $generate): RedirectResponse
    {
        $data = $request->validate([
            'url_profile_id' => ['required', 'integer'],
            'kind' => ['required', 'in:'.implode(',', GenerateMetaDraft::KINDS)],
        ]);

        $siteIds = Site::query()->where('organization_id', $org->id())->pluck('id');
        $profile = \DB::table('url_profiles')
            ->whereIn('site_id', $siteIds)
            ->where('id', (int) $data['url_profile_id'])
            ->first();

        if ($profile === null) {
            abort(404);
        }

        $site = Site::query()->findOrFail($profile->site_id);

        try {
            $generationId = $generate->handle($site, $data['kind'], (int) $profile->id);
        } catch (\Throwable $e) {
            return back()->with('error', 'تولید پیشنویس ناموفق بود: '.$e->getMessage());
        }

        return back()->with('status', 'پیشنویس با هوش مصنوعی تولید شد و برای بازبینی ثبت گردید.');
    }

    /** صفحهٔ مستقل «تولید مقاله» — انتخاب سایت، URL، عنوان و زیرنوع. */
    public function createArticle(Request $request, CurrentOrganization $org): Response
    {
        $sites = Site::query()
            ->where('organization_id', $org->id())
            ->orderBy('name')
            ->get(['id', 'name', 'canonical_url'])
            ->map(fn (Site $site): array => [
                'id' => $site->id,
                'name' => $site->name,
                'canonical_url' => $site->canonical_url,
            ])
            ->values()
            ->all();

        $profiles = \DB::table('url_profiles')
            ->join('sites', 'sites.id', '=', 'url_profiles.site_id')
            ->where('sites.organization_id', $org->id())
            ->whereIn('url_profiles.content_type', ['page', 'post', 'product'])
            ->get(['url_profiles.id', 'url_profiles.site_id', 'url_profiles.canonical_url', 'url_profiles.content_type', 'url_profiles.post_status'])
            ->map(fn (object $p): array => [
                'id' => (int) $p->id,
                'site_id' => (int) $p->site_id,
                'canonical_url' => (string) $p->canonical_url,
                'content_type' => (string) $p->content_type,
            ])
            ->values()
            ->all();

        // پیش‌نمایش استاندارد برای هر زیرنوع
        $profiler = app(ContentProfiler::class);
        $standardsKb = app(StandardsKB::class);
        $standardsPreview = [];
        foreach (array_keys(ContentProfiler::SUBTYPES['article'] ?? []) as $sub) {
            $profiled = $profiler->profile(['title' => '', 'target_query' => '', 'content_type' => 'article', 'subtype' => $sub]);
            $std = $standardsKb->standardFor($profiled);
            $standardsPreview[$sub] = [
                'word_min' => $std['word_min'],
                'word_max' => $std['word_max'],
                'min_headings' => $std['min_headings'],
                'required_elements' => $std['required_elements'],
                'tone' => $std['tone'] ?? 'informative',
                'schema_type' => $std['schema_type'] ?? 'Article',
                'keyword_density' => [
                    'min' => $std['keyword_guidance']['density_min'] ?? 0.8,
                    'max' => $std['keyword_guidance']['density_max'] ?? 2.5,
                ],
                'meta_title' => $std['meta_title'] ?? ['min_length' => 30, 'max_length' => 60],
                'meta_description' => $std['meta_description'] ?? ['min_length' => 120, 'max_length' => 160],
            ];
        }

        return Inertia::render('App/ArticleDraft/Create', [
            'sites' => $sites,
            'profiles' => $profiles,
            'subtypes' => ContentProfiler::subtypeLabels(),
            'standards' => $standardsPreview,
            'isSuperAdmin' => $request->user()?->isSuperAdmin() ?? false,
        ]);
    }

    /** صفحهٔ مستقل «تولید پیشنویس محصول» — سایت، URL محصول و زیرنوع (توضیح کوتاه/بلند/فنی/مقایسه‌ای). */
    public function createProduct(Request $request, CurrentOrganization $org): Response
    {
        $sites = Site::query()
            ->where('organization_id', $org->id())
            ->orderBy('name')
            ->get(['id', 'name', 'canonical_url'])
            ->map(fn (Site $site): array => [
                'id' => $site->id,
                'name' => $site->name,
                'canonical_url' => $site->canonical_url,
            ])
            ->values()
            ->all();

        $profiles = \DB::table('url_profiles')
            ->join('sites', 'sites.id', '=', 'url_profiles.site_id')
            ->where('sites.organization_id', $org->id())
            ->where('url_profiles.content_type', 'product')
            ->get(['url_profiles.id', 'url_profiles.site_id', 'url_profiles.canonical_url', 'url_profiles.content_type', 'url_profiles.post_status'])
            ->map(fn (object $p): array => [
                'id' => (int) $p->id,
                'site_id' => (int) $p->site_id,
                'canonical_url' => (string) $p->canonical_url,
                'content_type' => (string) $p->content_type,
            ])
            ->values()
            ->all();

        // فقط زیرنوع‌های محصول (توضیح کوتاه/بلند، مشخصات فنی، مقایسه‌ای)
        $productSubtypes = array_intersect_key(
            ContentProfiler::subtypeLabels(),
            array_flip(ContentProfiler::SUBTYPES['product'])
        );

        // پیش‌نمایش استاندارد برای هر زیرنوع محصول
        $profiler = app(ContentProfiler::class);
        $standardsKb = app(StandardsKB::class);
        $standardsPreview = [];
        foreach (array_keys(ContentProfiler::SUBTYPES['product'] ?? []) as $sub) {
            $profiled = $profiler->profile(['title' => '', 'target_query' => '', 'content_type' => 'product', 'subtype' => $sub]);
            $std = $standardsKb->standardFor($profiled);
            $standardsPreview[$sub] = [
                'word_min' => $std['word_min'],
                'word_max' => $std['word_max'],
                'min_headings' => $std['min_headings'],
                'required_elements' => $std['required_elements'],
                'tone' => $std['tone'] ?? 'persuasive',
                'schema_type' => $std['schema_type'] ?? 'Product',
                'keyword_density' => [
                    'min' => $std['keyword_guidance']['density_min'] ?? 1.0,
                    'max' => $std['keyword_guidance']['density_max'] ?? 3.0,
                ],
            ];
        }

        return Inertia::render('App/ProductDraft/Create', [
            'sites' => $sites,
            'profiles' => $profiles,
            'subtypes' => $productSubtypes,
            'standards' => $standardsPreview,
            'isSuperAdmin' => $request->user()?->isSuperAdmin() ?? false,
        ]);
    }

    public function storeProduct(Request $request, CurrentOrganization $org, GenerateArticleDraft $generate): RedirectResponse
    {
        $data = $request->validate([
            'url_profile_id' => ['required', 'integer'],
            'title' => ['nullable', 'string', 'max:200'],
            'subtype' => ['nullable', 'string', 'in:'.implode(',', ContentProfiler::SUBTYPES['product'])],
        ]);

        $siteIds = Site::query()->where('organization_id', $org->id())->pluck('id');
        $profile = \DB::table('url_profiles')
            ->whereIn('site_id', $siteIds)
            ->where('id', (int) $data['url_profile_id'])
            ->where('content_type', 'product')
            ->first();

        if ($profile === null) {
            abort(404);
        }

        $site = Site::query()->findOrFail($profile->site_id);

        try {
            $generationId = $generate->handle(
                $site,
                (int) $profile->id,
                isset($data['title']) ? (string) $data['title'] : null,
                isset($data['subtype']) ? (string) $data['subtype'] : null,
            );
        } catch (\Throwable $e) {
            return back()->with('error', 'تولید پیشنویس محصول ناموفق بود: '.$e->getMessage());
        }

        return redirect()->route('app.reviews.index')->with('status', 'پیشنویس محصول تولید شد و برای بازبینی ثبت گردید (#'.$generationId.').');
    }

    public function storeArticle(Request $request, CurrentOrganization $org, GenerateArticleDraft $generate): RedirectResponse
    {
        $data = $request->validate([
            'url_profile_id' => ['required', 'integer'],
            'title' => ['nullable', 'string', 'max:200'],
            'subtype' => ['nullable', 'string', 'in:'.implode(',', array_keys(ContentProfiler::subtypeLabels()))],
        ]);

        $siteIds = Site::query()->where('organization_id', $org->id())->pluck('id');
        $profile = \DB::table('url_profiles')
            ->whereIn('site_id', $siteIds)
            ->where('id', (int) $data['url_profile_id'])
            ->first();

        if ($profile === null) {
            abort(404);
        }

        $site = Site::query()->findOrFail($profile->site_id);

        try {
            $generationId = $generate->handle(
                $site,
                (int) $profile->id,
                isset($data['title']) ? (string) $data['title'] : null,
                isset($data['subtype']) ? (string) $data['subtype'] : null,
            );
        } catch (\Throwable $e) {
            return back()->with('error', 'تولید پیشنویس مقاله ناموفق بود: '.$e->getMessage());
        }

        return redirect()->route('app.reviews.index')->with('status', 'پیشنویس مقاله تولید شد و برای بازبینی ثبت گردید (#'.$generationId.').');
    }

    /** List all content drafts for the organization. */
    public function index(Request $request, CurrentOrganization $org): Response
    {
        $siteIds = Site::query()->where('organization_id', $org->id())->pluck('id');
        $status = $request->query('status', '');
        $search = $request->query('search', '');

        $query = ContentDraft::query()
            ->whereIn('site_id', $siteIds)
            ->with('site:id,name')
            ->latest();

        if ($status !== '') {
            $query->where('status', $status);
        }
        if ($search !== '') {
            $query->where('title', 'like', '%' . $search . '%');
        }

        $drafts = $query->paginate(20);

        return Inertia::render('App/ArticleDraft/Index', [
            'drafts' => $drafts,
            'filters' => ['status' => $status, 'search' => $search],
        ]);
    }

    /** Edit a content draft. */
    public function edit(int $id, CurrentOrganization $org): Response
    {
        $siteIds = Site::query()->where('organization_id', $org->id())->pluck('id');
        $draft = ContentDraft::whereIn('site_id', $siteIds)->findOrFail($id);

        return Inertia::render('App/ArticleDraft/Edit', [
            'draft' => $draft->load('site:id,name'),
            'subtypes' => ContentProfiler::subtypeLabels(),
        ]);
    }

    /** Update a content draft. */
    public function update(Request $request, int $id, CurrentOrganization $org): RedirectResponse
    {
        $siteIds = Site::query()->where('organization_id', $org->id())->pluck('id');
        $draft = ContentDraft::whereIn('site_id', $siteIds)->findOrFail($id);

        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:200'],
            'content' => ['nullable', 'string'],
            'meta_title' => ['nullable', 'string', 'max:100'],
            'meta_description' => ['nullable', 'string', 'max:300'],
            'status' => ['nullable', 'in:draft,review,published,archived'],
        ]);

        $oldStatus = $draft->status;
        $draft->update($data);

        // Auto-publish to WordPress when status changes to published
        if (($data['status'] ?? '') === 'published' && $oldStatus !== 'published') {
            $this->tryAutoPublish($draft);
        }

        return back()->with('status', 'پیش‌نویس ذخیره شد.');
    }

    private function tryAutoPublish(ContentDraft $draft): void
    {
        try {
            $site = $draft->site;
            if (!$site) return;
            $settings = (array) $site->settings;
            $wp = $settings['wordpress'] ?? null;
            if (!$wp || empty($wp['wp_url'])) return;

            $publisher = app(\App\Domains\Content\Services\WordPressPublisher::class);
            $result = $publisher->publish(
                ['wp_url' => $wp['wp_url'], 'wp_username' => $wp['wp_username'], 'wp_app_password' => $wp['wp_app_password']],
                ['title' => $draft->title, 'content' => $draft->content, 'meta_title' => $draft->meta_title, 'meta_description' => $draft->meta_description, 'slug' => $draft->slug, 'status' => 'publish']
            );
            if ($result['success']) {
                $draft->update(['audit_log' => array_merge($draft->audit_log ?? [], ['wp_post_id' => $result['post_id'], 'wp_post_url' => $result['post_url'], 'auto_published_at' => now()->toISOString()])]);
            }
        } catch (\Throwable $e) { \Illuminate\Support\Facades\Log::warning('Auto-publish failed: ' . $e->getMessage()); }
    }
}