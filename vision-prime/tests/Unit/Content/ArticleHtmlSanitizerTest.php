<?php

declare(strict_types=1);

namespace Tests\Unit\Content;

use App\Domains\Content\Services\ArticleHtmlSanitizer;
use PHPUnit\Framework\TestCase;

class ArticleHtmlSanitizerTest extends TestCase
{
    public function test_strips_scripts_and_dangerous_tags(): void
    {
        $sanitizer = new ArticleHtmlSanitizer;
        $result = $sanitizer->sanitize(
            '<h1>عنوان</h1><script>alert(1)</script><p>متن سالم</p><iframe src="https://evil.example"></iframe><p>بعد</p>'
        );

        $this->assertStringNotContainsString('script', $result['safe_html']);
        $this->assertStringNotContainsString('iframe', $result['safe_html']);
        $this->assertStringNotContainsString('alert', $result['safe_html']);
        $this->assertStringContainsString('<h1>عنوان</h1>', $result['safe_html']);
        $this->assertStringContainsString('متن سالم', $result['safe_html']);
        $this->assertStringContainsString('بعد', $result['safe_html']);
    }

    public function test_strips_event_handlers_and_javascript_urls(): void
    {
        $sanitizer = new ArticleHtmlSanitizer;
        $result = $sanitizer->sanitize(
            '<p onclick="alert(1)">متن</p><a href="javascript:alert(1)">لینک</a><a href="https://safesite.example/page">امن</a>'
        );

        $this->assertStringNotContainsString('onclick', $result['safe_html']);
        $this->assertStringNotContainsString('javascript:', $result['safe_html']);
        $this->assertStringContainsString('<a href="https://safesite.example/page"', $result['safe_html']);
    }

    public function test_strips_unknown_tags_and_attributes_but_keeps_content(): void
    {
        $sanitizer = new ArticleHtmlSanitizer;
        $result = $sanitizer->sanitize(
            '<p data-x="1" class="foo">متن <span>داخل</span></p><unknown attr="x">قیمت</unknown>'
        );

        // span و unknown حذف می‌شوند ولی محتوا می‌ماند؛ ویژگی‌های نامجاز هم حذف می‌شوند
        $this->assertStringNotContainsString('<span', $result['safe_html']);
        $this->assertStringNotContainsString('<unknown', $result['safe_html']);
        $this->assertStringNotContainsString('class="foo"', $result['safe_html']);
        $this->assertStringContainsString('داخل', $result['safe_html']);
        $this->assertStringContainsString('قیمت', $result['safe_html']);
    }

    public function test_extracts_headings_word_count_and_required_elements(): void
    {
        $sanitizer = new ArticleHtmlSanitizer;
        $result = $sanitizer->sanitize(
            '<h1>راهنمای کامل سئو</h1><h2>مقدمه</h2><p>متن اول درباره سئو.</p>'
            .'<h2>سؤالات متداول</h2><p>پرسش و پاسخ درباره سئو.</p>'
            .'<h2>مقایسه در یک نگاه</h2><table><tr><td>الف</td></tr></table>',
            ['required_elements' => ['h2_structure', 'faq', 'table', 'cta']]
        );

        $structure = $result['structure'];

        $this->assertCount(4, $structure['headings']);
        $this->assertSame(1, $structure['headings'][0]['level']);
        $this->assertSame('راهنمای کامل سئو', $structure['headings'][0]['text']);
        $this->assertSame(2, $structure['headings'][1]['level']);

        $this->assertGreaterThan(0, $structure['word_count']);

        $this->assertTrue($structure['elements']['h2_structure']);
        $this->assertTrue($structure['elements']['faq']);
        $this->assertTrue($structure['elements']['table']);
        $this->assertFalse($structure['elements']['cta']);
    }

    public function test_keeps_table_attributes_but_strips_unknown_ones(): void
    {
        $sanitizer = new ArticleHtmlSanitizer;
        $result = $sanitizer->sanitize(
            '<table><tr><td colspan="2">گسترده</td><td style="color:red" data-x="1">ساده</td></tr></table>'
        );

        $this->assertStringContainsString('colspan="2"', $result['safe_html']);
        $this->assertStringNotContainsString('style=', $result['safe_html']);
        $this->assertStringNotContainsString('data-x', $result['safe_html']);
    }
}
