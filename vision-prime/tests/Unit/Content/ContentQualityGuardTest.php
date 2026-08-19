<?php

declare(strict_types=1);

namespace Tests\Unit\Content;

use App\Domains\Content\Services\ContentProfiler;
use App\Domains\Content\Services\ContentQualityGuard;
use Database\Seeders\ContentStandardsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContentQualityGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_profiler_detects_tutorial_article_with_informational_intent(): void
    {
        $profile = app(ContentProfiler::class)->profile([
            'title' => 'آموزش نصب وردپرس روی هاست',
            'target_query' => 'آموزش نصب وردپرس',
        ]);

        $this->assertSame('article', $profile['content_type']);
        $this->assertSame('tutorial', $profile['subtype']);
        $this->assertSame('informational', $profile['intent']);
    }

    public function test_profiler_detects_comparison_product(): void
    {
        // hint از پلن می‌آید (پلن می‌داند هدف محصول است) — زیرنوع از روی عنوان تشخیص داده می‌شود
        $profile = app(ContentProfiler::class)->profile([
            'title' => 'مقایسه آیفون ۱۵ و سامسونگ گلکسی S24',
            'target_query' => 'مقایسه آیفون و سامسونگ',
            'content_type' => 'product',
        ]);

        $this->assertSame('product', $profile['content_type']);
        $this->assertSame('comparison', $profile['subtype']);
        $this->assertSame('commercial', $profile['intent']);
    }

    public function test_quality_guard_passes_valid_tutorial_article(): void
    {
        $this->seed(ContentStandardsSeeder::class);

        $profile = app(ContentProfiler::class)->profile([
            'title' => 'آموزش کامل نصب وردپرس برای مبتدیان',
            'target_query' => 'آموزش نصب وردپرس',
        ]);

        $paragraph = 'در این بخش از آموزش نصب وردپرس، مراحل نصب را گام به گام توضیح می‌دهیم. '
            .'ابتدا باید یک هاست مناسب تهیه کنید و سپس فایل‌های وردپرس را روی آن بارگذاری نمایید. '
            .'پس از بارگذاری، نصب به سادگی انجام می‌شود و می‌توانید وبسایت خود را مدیریت کنید. ';
        $link = '<a href="https://liuna.ir/hosting">راهنمای خرید هاست</a> ';
        $body = '<h2>مقدمه</h2><p>'.$paragraph.' '.$link.'</p>'
            .'<h2>مراحل نصب</h2><p>'.str_repeat($paragraph.' '.$link, 15).'</p>'
            .'<h2>تنظیمات اولیه</h2><p>'.str_repeat($paragraph.' '.$link, 15).'</p>'
            .'<h2>نتیجه‌گیری</h2><p>پس از نصب، سایت شما آماده است. '.$link.$paragraph.'</p>';

        $result = app(ContentQualityGuard::class)->evaluate($profile, [
            'title' => 'آموزش نصب وردپرس: راهنمای کامل برای مبتدیان',
            'body' => $body,
            'keyword' => 'آموزش نصب وردپرس',
            'headings' => ['مقدمه', 'مراحل', 'نتیجه‌گیری'],
        ]);

        $this->assertTrue($result['passed'], implode('; ', $result['failures']));
        $this->assertSame('article×tutorial×informational', $result['standard']['standard_key']);
    }

    public function test_quality_guard_rejects_too_short_article(): void
    {
        $this->seed(ContentStandardsSeeder::class);

        $profile = ['content_type' => 'article', 'subtype' => 'tutorial', 'intent' => 'informational'];

        $result = app(ContentQualityGuard::class)->evaluate($profile, [
            'title' => 'آموزش نصب',
            'body' => '<p>کوتاه است.</p>',
            'keyword' => 'آموزش نصب',
            'headings' => [],
        ]);

        $this->assertFalse($result['passed']);
        $this->assertNotEmpty($result['failures']);
    }

    public function test_quality_guard_rejects_placeholder_content(): void
    {
        $this->seed(ContentStandardsSeeder::class);

        $profile = ['content_type' => 'article', 'subtype' => 'tutorial', 'intent' => 'informational'];

        $longBody = str_repeat('<h2>بخش</h2><p>متن واقعی آموزش نصب وردپرس در این بخش قرار دارد.</p>', 20);

        $result = app(ContentQualityGuard::class)->evaluate($profile, [
            'title' => 'آموزش نصب وردپرس',
            'body' => $longBody.'<p>lorem ipsum dolor sit amet</p>',
            'keyword' => 'آموزش نصب وردپرس',
            'headings' => ['بخش'],
        ]);

        $this->assertFalse($result['passed']);
        $this->assertContains('placeholder_content_detected', $result['failures']);
    }

    public function test_quality_guard_applies_safety_floor_even_without_seed(): void
    {
        // بدون seed — کف امنیت مطلق (۱۵۰ کلمه مقاله) باید اعمال شود
        $profile = ['content_type' => 'article', 'subtype' => 'unknown', 'intent' => 'informational'];

        $shortBody = '<p>'.str_repeat('کلمه ', 60).'</p>';
        $result = app(ContentQualityGuard::class)->evaluate($profile, [
            'title' => 'یک عنوان',
            'body' => $shortBody,
            'keyword' => '',
            'headings' => ['H2'],
        ]);

        $this->assertFalse($result['passed']);
        $this->assertStringContainsString('below min', $result['failures'][0]);
    }
}
