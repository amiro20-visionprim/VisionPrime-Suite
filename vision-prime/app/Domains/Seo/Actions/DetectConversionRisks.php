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
            $snapshot = \DB::table('content_snapshots')->where('url_profile_id', $profile->id)->latest('captured_at')->first();
            $content = $snapshot?->content ?? '';
            $hasSyncedContent = $snapshot !== null;
            $risks = [];

            // Content-based checks are only meaningful when we actually
            // synced the page content (WordPress connector). Without a
            // snapshot we must not fabricate "thin content" issues.
            if ($hasSyncedContent && mb_strlen($content) < 500) {
                $risks[] = ['key' => 'thin_content', 'severity' => 'high', 'score' => 75, 'explanation' => 'محتوای صفحه برای یک صفحهٔ تجاری کافی نیست.'];
            }

            if ($hasSyncedContent && ! preg_match('/(تماس|خرید|رزرو|ثبت نام|مشاوره|contact|buy|book)/iu', $content)) {
                $risks[] = ['key' => 'weak_cta', 'severity' => 'medium', 'score' => 55, 'explanation' => 'دعوت به اقدام واضحی در صفحه وجود ندارد.'];
            }

            // Only judge meta quality when the profile was actually synced
            // (GSC-derived profiles have no meta yet and must not be penalized).
            if (! isset($meta['gsc']) && empty($meta['meta_title'])) {
                $risks[] = ['key' => 'unclear_offer', 'severity' => 'medium', 'score' => 50, 'explanation' => 'عنوان متا پیشنهاد اصلی صفحه را به‌وضوح منتقل نمی‌کند.'];
            }

            foreach ($risks as $risk) {
                \DB::table('conversion_risks')->updateOrInsert(
                    ['url_profile_id' => $profile->id, 'key' => $risk['key']],
                    [
                        'severity' => $risk['severity'],
                        'score' => $risk['score'],
                        'explanation' => $risk['explanation'],
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );
                $id = \DB::table('conversion_risks')->where('url_profile_id', $profile->id)->where('key', $risk['key'])->value('id');
                \DB::table('conversion_risk_factors')->updateOrInsert(
                    ['conversion_risk_id' => $id, 'key' => $risk['key']],
                    [
                        'weight' => 1,
                        'value' => $risk['score'] / 100,
                        'explanation' => $risk['explanation'],
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );
                $count++;
            }
        }

        return $count;
    }
}
