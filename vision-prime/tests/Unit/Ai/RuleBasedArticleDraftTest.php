<?php

declare(strict_types=1);

namespace Tests\Unit\Ai;

use App\Domains\Ai\Services\RuleBasedDraft;
use PHPUnit\Framework\TestCase;

class RuleBasedArticleDraftTest extends TestCase
{
    public function test_generates_structured_article_within_word_range(): void
    {
        $draft = new RuleBasedDraft;
        $result = $draft->generateArticle([
            'title' => 'راهنمای جامع سئو',
            'target_query' => 'آموزش سئو',
            'site_name' => 'لیونا',
            'standard' => [
                'word_min' => 400,
                'word_max' => 2000,
                'min_headings' => 3,
                'required_elements' => ['faq', 'cta'],
                'tone' => 'informative',
            ],
        ]);

        $this->assertSame('rule_based', $result['source']);
        $this->assertSame('rule-based', $result['model']);

        $content = (string) $result['content'];
        $this->assertStringContainsString('<h1>', $content);
        // حداقل زیرعنوان‌ها
        $this->assertGreaterThanOrEqual(3, preg_match_all('/<h2>/', $content));

        // عناصر الزامی (faq + cta)
        $this->assertStringContainsString('سؤالات متداول', $content);
        $this->assertStringContainsString('گام بعدی', $content);

        // تعداد کلمات داخل بازه
        $plain = trim((string) preg_replace('/<[^>]+>/', ' ', $content));
        $words = preg_split('/[\s'.chr(0xE2).chr(0x80).chr(0x8C).']+/u', $plain) ?: [];
        $count = count(array_filter($words, fn (string $w): bool => trim($w) !== ''));
        $this->assertGreaterThanOrEqual(400, $count);
        $this->assertLessThanOrEqual(2500, $count);

        // هیچ placeholder/lorem وجود ندارد
        $this->assertStringNotContainsString('lorem', mb_strtolower($content, 'UTF-8'));
    }

    public function test_respects_high_word_min_requirement(): void
    {
        $draft = new RuleBasedDraft;
        $result = $draft->generateArticle([
            'title' => 'مقایسه ابزارها',
            'target_query' => 'مقایسه ابزار سئو',
            'site_name' => 'لیونا',
            'standard' => [
                'word_min' => 900,
                'word_max' => 2500,
                'min_headings' => 4,
                'required_elements' => ['table', 'pros_cons'],
                'tone' => 'neutral',
            ],
        ]);

        $content = (string) $result['content'];
        $this->assertStringContainsString('مقایسه در یک نگاه', $content);
        $this->assertStringContainsString('<table>', $content);
        $this->assertStringContainsString('مزایا و معایب', $content);

        $plain = trim((string) preg_replace('/<[^>]+>/', ' ', $content));
        $words = preg_split('/[\s'.chr(0xE2).chr(0x80).chr(0x8C).']+/u', $plain) ?: [];
        $count = count(array_filter($words, fn (string $w): bool => trim($w) !== ''));
        $this->assertGreaterThanOrEqual(900, $count);
    }

    public function test_meta_generation_still_works(): void
    {
        $draft = new RuleBasedDraft;
        $result = $draft->generate('meta_title', ['top_query' => 'سرم پوست', 'site_name' => 'لیونا']);

        $this->assertSame('rule_based', $result['source']);
        $this->assertStringContainsString('سرم پوست', (string) $result['content']);
    }
}
