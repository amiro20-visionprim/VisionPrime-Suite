<?php

declare(strict_types=1);

namespace App\Domains\Content\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * WordPress REST API integration for publishing content directly.
 */
class WordPressPublisher
{
    /**
     * Publish an article to WordPress.
     *
     * @return array{success: bool, post_id?: int, post_url?: string, error?: string}
     */
    public function publish(array $siteConfig, array $article): array
    {
        $baseUrl = rtrim($siteConfig['wp_url'] ?? '', '/');
        $username = $siteConfig['wp_username'] ?? '';
        $appPassword = $siteConfig['wp_app_password'] ?? '';

        if (!$baseUrl || !$username || !$appPassword) {
            return ['success' => false, 'error' => 'تنظیمات وردپرس ناقص است. URL، نام کاربری و Application Password را وارد کنید.'];
        }

        try {
            // Create post
            $response = Http::withBasicAuth($username, $appPassword)
                ->timeout(30)
                ->post("{$baseUrl}/wp-json/wp/v2/posts", [
                    'title' => $article['meta_title'] ?: $article['title'],
                    'content' => $article['content'],
                    'status' => $article['status'] ?? 'draft',
                    'excerpt' => $article['meta_description'] ?? '',
                    'slug' => $article['slug'] ?? '',
                    'categories' => $this->resolveCategories($baseUrl, $username, $appPassword, $article['categories'] ?? []),
                    'tags' => $this->resolveTags($baseUrl, $username, $appPassword, $article['tags'] ?? []),
                    'meta' => [
                        '_yoast_wpseo_title' => $article['meta_title'] ?? '',
                        '_yoast_wpseo_metadesc' => $article['meta_description'] ?? '',
                    ],
                ]);

            if ($response->failed()) {
                Log::warning('WordPress publish failed', ['status' => $response->status(), 'body' => $response->body()]);
                return ['success' => false, 'error' => 'خطا در انتشار: ' . $response->body()];
            }

            $post = $response->json();
            return [
                'success' => true,
                'post_id' => $post['id'],
                'post_url' => $post['link'] ?? '',
                'status' => $post['status'] ?? 'draft',
            ];
        } catch (\Throwable $e) {
            Log::error('WordPress publish exception', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => 'خطای اتصال: ' . $e->getMessage()];
        }
    }

    /**
     * Test WordPress connection.
     */
    public function testConnection(string $url, string $username, string $appPassword): array
    {
        try {
            $response = Http::withBasicAuth($username, $appPassword)
                ->timeout(10)
                ->get(rtrim($url, '/') . '/wp-json/wp/v2/users/me');

            if ($response->successful()) {
                $user = $response->json();
                return [
                    'success' => true,
                    'user' => $user['name'] ?? 'Unknown',
                    'roles' => $user['roles'] ?? [],
                ];
            }
            return ['success' => false, 'error' => 'خطای احراز هویت: ' . $response->status()];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => 'خطای اتصال: ' . $e->getMessage()];
        }
    }

    /**
     * Get WordPress categories and create if needed.
     */
    private function resolveCategories(string $baseUrl, string $user, string $pass, array $names): array
    {
        if (empty($names)) return [];
        $ids = [];
        foreach ($names as $name) {
            $name = trim($name);
            if ($name === '') continue;
            // Search existing
            $res = Http::withBasicAuth($user, $pass)
                ->get("{$baseUrl}/wp-json/wp/v2/categories", ['search' => $name, 'per_page' => 1]);
            if ($res->successful() && count($res->json()) > 0) {
                $ids[] = $res->json()[0]['id'];
            } else {
                // Create
                $res = Http::withBasicAuth($user, $pass)
                    ->post("{$baseUrl}/wp-json/wp/v2/categories", ['name' => $name]);
                if ($res->successful()) {
                    $ids[] = $res->json()['id'];
                }
            }
        }
        return $ids;
    }

    /**
     * Get WordPress tags and create if needed.
     */
    private function resolveTags(string $baseUrl, string $user, string $pass, array $names): array
    {
        if (empty($names)) return [];
        $ids = [];
        foreach ($names as $name) {
            $name = trim($name);
            if ($name === '') continue;
            $res = Http::withBasicAuth($user, $pass)
                ->get("{$baseUrl}/wp-json/wp/v2/tags", ['search' => $name, 'per_page' => 1]);
            if ($res->successful() && count($res->json()) > 0) {
                $ids[] = $res->json()[0]['id'];
            } else {
                $res = Http::withBasicAuth($user, $pass)
                    ->post("{$baseUrl}/wp-json/wp/v2/tags", ['name' => $name]);
                if ($res->successful()) {
                    $ids[] = $res->json()['id'];
                }
            }
        }
        return $ids;
    }
}
