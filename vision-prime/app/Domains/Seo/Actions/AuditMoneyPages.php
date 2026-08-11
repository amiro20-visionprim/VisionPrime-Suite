<?php

declare(strict_types=1);

namespace App\Domains\Seo\Actions;

use App\Domains\Workspace\Models\Site;

class AuditMoneyPages
{
    public function handle(Site $site): int
    {
        $profiles = \DB::table('url_profiles')->where('site_id', $site->id)->get();
        $count = 0;

        foreach ($profiles as $profile) {
            $meta = json_decode($profile->metadata, true) ?? [];
            $url = mb_strtolower($profile->canonical_url);
            $isMoney = str_contains($url, 'service') || str_contains($url, 'product') || str_contains($url, 'shop') || str_contains($url, 'خدمات') || str_contains($url, 'محصول');

            if (! $isMoney) {
                continue;
            }

            // Pages derived from GSC (no WordPress sync yet) are scored from
            // real search signals instead of fabricated "missing meta" issues.
            if (isset($meta['gsc'])) {
                $this->auditFromGscSignals($profile, $meta['gsc']);
                $count++;

                continue;
            }

            $score = 100;
            $issues = [];

            if (empty($meta['meta_title'])) {
                $score -= 20;
                $issues[] = ['key' => 'missing_meta_title', 'severity' => 'high', 'explanation' => 'Meta title is missing.'];
            }

            if (empty($meta['meta_description'])) {
                $score -= 15;
                $issues[] = ['key' => 'missing_meta_description', 'severity' => 'medium', 'explanation' => 'Meta description is missing.'];
            }

            $auditId = \DB::table('money_page_audits')->insertGetId([
                'url_profile_id' => $profile->id,
                'score' => $score,
                'summary' => json_encode(['issues' => count($issues)]),
                'audited_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach ($issues as $issue) {
                \DB::table('money_page_issues')->insert([
                    'money_page_audit_id' => $auditId,
                    ...$issue,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            $count++;
        }

        return $count;
    }

    /**
     * @param  object  $profile  url_profiles row
     * @param  array<string, mixed>  $gsc  aggregated metrics stored by CreateUrlProfilesFromGsc
     */
    private function auditFromGscSignals(object $profile, array $gsc): void
    {
        $score = 100;
        $issues = [];
        $position = (float) ($gsc['position'] ?? 100);
        $ctr = (float) ($gsc['ctr'] ?? 0);
        $impressions = (int) ($gsc['impressions'] ?? 0);
        $clicks = (int) ($gsc['clicks'] ?? 0);

        if ($position > 20) {
            $score -= 25;
            $issues[] = ['key' => 'low_visibility', 'severity' => 'high', 'explanation' => sprintf('میانگین جایگاه صفحه %s است؛ خارج از صفحهٔ اول نتایج دیده نمی‌شود.', number_format($position, 1))];
        } elseif ($position > 10) {
            $score -= 10;
            $issues[] = ['key' => 'weak_visibility', 'severity' => 'medium', 'explanation' => sprintf('میانگین جایگاه صفحه %s است؛ نزدیک به صفحهٔ دوم نتایج.', number_format($position, 1))];
        }

        if ($ctr < 0.02 && $impressions >= 20) {
            $score -= 15;
            $issues[] = ['key' => 'low_ctr', 'severity' => 'medium', 'explanation' => 'نرخ کلیک صفحه پایین است؛ عنوان و توضیحات متا نیاز به بهبود دارند.'];
        }

        if ($clicks === 0 && $impressions >= 50) {
            $score -= 10;
            $issues[] = ['key' => 'no_clicks', 'severity' => 'medium', 'explanation' => 'صفحه نمایش‌های قابل توجهی دارد اما کلیکی دریافت نمی‌کند.'];
        }

        \DB::table('money_page_audits')->updateOrInsert(
            ['url_profile_id' => $profile->id],
            [
                'score' => max(0, $score),
                'summary' => json_encode([
                    'issues' => count($issues),
                    'gsc' => ['clicks' => $clicks, 'impressions' => $impressions, 'ctr' => $ctr, 'position' => $position],
                ], JSON_UNESCAPED_UNICODE),
                'audited_at' => now(),
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        $audit = \DB::table('money_page_audits')->where('url_profile_id', $profile->id)->first();

        foreach ($issues as $issue) {
            \DB::table('money_page_issues')->updateOrInsert(
                ['money_page_audit_id' => $audit->id, 'key' => $issue['key']],
                [
                    'severity' => $issue['severity'],
                    'explanation' => $issue['explanation'],
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }
}
