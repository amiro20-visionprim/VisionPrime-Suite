<?php

declare(strict_types=1);

namespace App\Domains\Seo\Services;

use App\Domains\Workspace\Models\Client;

class ClientGrowthSummary
{
    public function for(Client $client): array
    {
        $siteIds = \DB::table('sites')->join('projects', 'projects.id', '=', 'sites.project_id')->where('projects.client_id', $client->id)->pluck('sites.id');
        $profiles = \DB::table('url_profiles')->whereIn('site_id', $siteIds)->pluck('id');

        return ['priority_money_pages' => \DB::table('money_page_audits')->whereIn('url_profile_id', $profiles)->where('score', '<', 70)->count(), 'high_conversion_risks' => \DB::table('conversion_risks')->whereIn('url_profile_id', $profiles)->where('severity', 'high')->count(), 'recommendations' => \DB::table('recommendations')->whereIn('site_id', $siteIds)->whereIn('status', ['draft', 'active'])->count()];
    }
}
