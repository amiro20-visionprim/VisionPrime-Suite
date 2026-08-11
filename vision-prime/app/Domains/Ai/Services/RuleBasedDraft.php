<?php

declare(strict_types=1);

namespace App\Domains\Ai\Services;

/**
 * Deterministic, offline draft generator used when no AI provider is
 * configured. Produces sensible Persian meta title/description drafts from
 * the page context (top query, site name, metrics) so the review workflow
 * works end-to-end even before an API key is added.
 */
class RuleBasedDraft
{
    /** @param  array<string, mixed>  $context */
    public function generate(string $kind, array $context): array
    {
        $siteName = (string) ($context['site_name'] ?? '');
        $topQuery = (string) ($context['top_query'] ?? '');
        $url = (string) ($context['url'] ?? '');

        if ($topQuery === '') {
            $topQuery = $this->queryFromUrl($url);
        }

        return match ($kind) {
            'meta_title' => [
                'content' => trim($topQuery.' | '.$siteName, ' |'),
                'model' => 'rule-based',
                'source' => 'rule_based',
                'usage' => [],
            ],
            default => [
                'content' => trim(sprintf(
                    'خرید %s با ضمانت اصالت و بهترین قیمت از %s؛ ارسال سریع به سراسر کشور و پشتیبانی ۲۴ ساعته.',
                    $topQuery,
                    $siteName,
                ), ' '),
                'model' => 'rule-based',
                'source' => 'rule_based',
                'usage' => [],
            ],
        };
    }

    private function queryFromUrl(string $url): string
    {
        $path = trim((string) parse_url($url, PHP_URL_PATH), '/');
        $slug = mb_substr((string) str_replace('-', ' ', rawurldecode($path)), 0, 60);

        return trim($slug) !== '' ? $slug : 'این صفحه';
    }
}
