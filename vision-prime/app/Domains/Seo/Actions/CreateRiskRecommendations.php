<?php

declare(strict_types=1);

namespace App\Domains\Seo\Actions;

use App\Domains\Workspace\Models\Site;

class CreateRiskRecommendations
{
    public function handle(Site $site): int
    {
        $risks = \DB::table('conversion_risks')->join('url_profiles', 'url_profiles.id', '=', 'conversion_risks.url_profile_id')->where('url_profiles.site_id', $site->id)->get(['conversion_risks.*', 'url_profiles.canonical_url']);
        $count = 0;
        foreach ($risks as $risk) {
            $title = match ($risk->key) {
                'thin_content' => 'افزایش عمق محتوای صفحه','weak_cta' => 'افزودن CTA واضح','unclear_offer' => 'شفاف‌سازی پیشنهاد صفحه',default => 'رفع ریسک تبدیل'
            };
            \DB::table('recommendations')->updateOrInsert(['site_id' => $site->id, 'source_type' => 'conversion_risk', 'source_id' => $risk->id], ['title' => $title, 'body' => $risk->explanation.' URL: '.$risk->canonical_url, 'priority' => $risk->severity === 'high' ? 'high' : 'medium', 'status' => 'draft', 'updated_at' => now(), 'created_at' => now()]);
            $count++;
        }

        return $count;
    }
}
