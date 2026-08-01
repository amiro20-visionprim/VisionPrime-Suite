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
            $score = 100;
            $issues = [];
            $url = mb_strtolower($profile->canonical_url);
            $isMoney = str_contains($url, 'service') || str_contains($url, 'product') || str_contains($url, 'shop') || str_contains($url, 'خدمات') || str_contains($url, 'محصول');
            if (! $isMoney) {
                continue;
            }if (empty($meta['meta_title'])) {
                $score -= 20;
                $issues[] = ['key' => 'missing_meta_title', 'severity' => 'high', 'explanation' => 'Meta title is missing.'];
            }if (empty($meta['meta_description'])) {
                $score -= 15;
                $issues[] = ['key' => 'missing_meta_description', 'severity' => 'medium', 'explanation' => 'Meta description is missing.'];
            }$auditId = \DB::table('money_page_audits')->insertGetId(['url_profile_id' => $profile->id, 'score' => $score, 'summary' => json_encode(['issues' => count($issues)]), 'audited_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
            foreach ($issues as $issue) {
                \DB::table('money_page_issues')->insert(['money_page_audit_id' => $auditId, ...$issue, 'created_at' => now(), 'updated_at' => now()]);
            }$count++;
        }

        return $count;
    }
}
