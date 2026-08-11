<?php

declare(strict_types=1);

namespace App\Domains\Seo\Actions;

use App\Domains\Workspace\Models\Site;
use Illuminate\Support\Str;

/**
 * Creates (or updates) url_profiles from real Google Search Console page
 * metrics, so sites without a WordPress content sync still get an
 * intelligence layer based on their actual search performance.
 */
class CreateUrlProfilesFromGsc
{
    public function handle(Site $site): int
    {
        $propertyIds = \DB::table('gsc_properties')->where('site_id', $site->id)->pluck('id');

        if ($propertyIds->isEmpty()) {
            return 0;
        }

        $pages = \DB::table('gsc_page_metrics')
            ->whereIn('gsc_property_id', $propertyIds)
            ->select(
                'page_url',
                \DB::raw('SUM(clicks) as clicks'),
                \DB::raw('SUM(impressions) as impressions'),
                \DB::raw('AVG(position) as position'),
                \DB::raw('CASE WHEN SUM(impressions) = 0 THEN 0 ELSE SUM(clicks) * 1.0 / SUM(impressions) END as ctr')
            )
            ->groupBy('page_url')
            ->get();

        $count = 0;
        foreach ($pages as $page) {
            $existing = \DB::table('url_profiles')
                ->where('site_id', $site->id)
                ->where('canonical_url', $page->page_url)
                ->first();

            $payload = [
                'content_type' => $this->contentType($page->page_url),
                'post_status' => 'publish',
                'metadata' => json_encode([
                    'gsc' => [
                        'clicks' => (int) $page->clicks,
                        'impressions' => (int) $page->impressions,
                        'ctr' => round((float) $page->ctr, 4),
                        'position' => round((float) $page->position, 2),
                    ],
                ], JSON_UNESCAPED_UNICODE),
                'updated_at' => now(),
            ];

            if ($existing) {
                \DB::table('url_profiles')->where('id', $existing->id)->update($payload);
            } else {
                \DB::table('url_profiles')->insert([
                    'site_id' => $site->id,
                    'public_id' => (string) Str::ulid(),
                    'canonical_url' => $page->page_url,
                    'content_type' => $payload['content_type'],
                    'post_status' => 'publish',
                    'metadata' => $payload['metadata'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            $count++;
        }

        return $count;
    }

    private function contentType(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH) ?? '';
        $path = mb_strtolower($path);

        if (str_contains($path, '/shop/') || str_contains($path, '/product') || str_contains($path, '/products/') || str_contains($path, '/product-category/')) {
            return 'product';
        }

        if (str_contains($path, '/blog/') || str_contains($path, '/news/') || str_contains($path, '/magazine/') || str_contains($path, '/articles/')) {
            return 'post';
        }

        return 'page';
    }
}
