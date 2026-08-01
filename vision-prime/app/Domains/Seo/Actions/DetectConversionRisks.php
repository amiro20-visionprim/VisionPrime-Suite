<?php

declare(strict_types=1);

namespace App\Domains\Seo\Actions;

use App\Domains\Workspace\Models\Site;

class DetectConversionRisks
{
    public function handle(Site $site): int
    {
        $profiles = \DB::table('url_profiles')->where('site_id', $site->id)->get();
        $count = 0;
        foreach ($profiles as $profile) {
            $meta = json_decode($profile->metadata, true) ?? [];
            $content = \DB::table('content_snapshots')->where('url_profile_id', $profile->id)->latest('captured_at')->value('content') ?? '';
            $risks = [];
            if (mb_strlen($content) < 500) {
                $risks[] = ['key' => 'thin_content', 'severity' => 'high', 'score' => 75, 'explanation' => 'Page content is too thin for a commercial page.'];
            }if (! preg_match('/(تماس|خرید|رزرو|ثبت نام|مشاوره|contact|buy|book)/iu', $content)) {
                $risks[] = ['key' => 'weak_cta', 'severity' => 'medium', 'score' => 55, 'explanation' => 'No clear conversion CTA detected.'];
            }if (empty($meta['meta_title'])) {
                $risks[] = ['key' => 'unclear_offer', 'severity' => 'medium', 'score' => 50, 'explanation' => 'Meta title does not communicate a clear offer.'];
            }foreach ($risks as $risk) {
                $id = \DB::table('conversion_risks')->insertGetId(['url_profile_id' => $profile->id, ...$risk, 'created_at' => now(), 'updated_at' => now()]);
                \DB::table('conversion_risk_factors')->insert(['conversion_risk_id' => $id, 'key' => $risk['key'], 'weight' => 1, 'value' => $risk['score'] / 100, 'explanation' => $risk['explanation'], 'created_at' => now(), 'updated_at' => now()]);
                $count++;
            }
        }

        return $count;
    }
}
