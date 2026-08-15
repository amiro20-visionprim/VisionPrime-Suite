<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Domains\Ai\Actions\GenerateArticleDraft;
use App\Domains\Ai\Actions\GenerateMetaDraft;
use App\Domains\Content\Services\ContentProfiler;
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
    public function createArticle(CurrentOrganization $org): Response
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

        return Inertia::render('App/ArticleDraft/Create', [
            'sites' => $sites,
            'profiles' => $profiles,
            'subtypes' => ContentProfiler::subtypeLabels(),
        ]);
    }

    /** صفحهٔ مستقل «تولید پیشنویس محصول» — سایت، URL محصول و زیرنوع (توضیح کوتاه/بلند/فنی/مقایسه‌ای). */
    public function createProduct(CurrentOrganization $org): Response
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

        return Inertia::render('App/ProductDraft/Create', [
            'sites' => $sites,
            'profiles' => $profiles,
            'subtypes' => $productSubtypes,
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
}
