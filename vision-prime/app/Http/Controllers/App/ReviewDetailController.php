<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Domains\Organization\Contracts\CurrentOrganization;
use App\Domains\Workspace\Models\Site;
use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class ReviewDetailController extends Controller
{
    public function show(int $review, CurrentOrganization $org): Response
    {
        $siteIds = Site::query()->where('organization_id', $org->id())->pluck('id');
        $item = \DB::table('review_items')->whereIn('site_id', $siteIds)->where('id', $review)->firstOrFail();
        $decisions = \DB::table('review_decisions')->where('review_item_id', $item->id)->get();

        return Inertia::render('App/Reviews/Show', ['item' => $item, 'decisions' => $decisions]);
    }
}
