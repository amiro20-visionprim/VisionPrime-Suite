<?php

declare(strict_types=1);

namespace App\Domains\Content\Services;

use App\Domains\Ai\Services\AiGateway;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * SERP Intelligence — analyzes competitor content structure for a keyword.
 *
 * Uses AI to generate realistic competitor analysis based on the keyword,
 * then compares it against the user's outline to find content gaps.
 */
class SERPAnalyzer
{
    private const CACHE_PREFIX = 'serp_analysis:';
    private const CACHE_TTL = 3600; // 1 hour

    public function __construct(
        private readonly AiGateway $gateway,
    ) {}

    /**
     * Analyze SERP landscape for a keyword.
     *
     * @return array{
     *     competitors: array<int, array{title: string, url: string, headings: string[], word_count: int, snippet: string}>,
     *     avg_word_count: int,
     *     common_headings: string[],
     *     content_gaps: string[],
     *     recommendations: string[],
     *     model: string,
     * }
     */
    public function analyze(string $keyword, string $subtype = 'how_to_guide', array $existingOutline = []): array
    {
        $cacheKey = self::CACHE_PREFIX . md5($keyword . $subtype);
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        [$system, $user] = $this->buildAnalysisPrompt($keyword, $subtype, $existingOutline);

        $result = $this->gateway->generate($system, $user, 'serp_analysis');

        $content = $result['content'] ?? '{}';

        // Try to extract JSON from response
        $analysis = $this->parseJsonResponse($content);

        if (!is_array($analysis) || empty($analysis)) {
            // Fallback: return basic analysis
            $analysis = $this->fallbackAnalysis($keyword, $existingOutline);
        }

        // Ensure required fields
        $analysis['model'] = $result['model'] ?? 'unknown';
        $analysis['competitors'] = $analysis['competitors'] ?? [];
        $analysis['avg_word_count'] = $analysis['avg_word_count'] ?? 0;
        $analysis['common_headings'] = $analysis['common_headings'] ?? [];
        $analysis['content_gaps'] = $analysis['content_gaps'] ?? [];
        $analysis['recommendations'] = $analysis['recommendations'] ?? [];

        Cache::put($cacheKey, $analysis, self::CACHE_TTL);

        return $analysis;
    }

    private function buildAnalysisPrompt(string $keyword, string $subtype, array $existingOutline): array
    {
        $outlineText = '';
        if (!empty($existingOutline)) {
            $headings = array_map(fn($item) => ($item['level'] === 3 ? '  ' : '') . 'H' . $item['level'] . ': ' . $item['heading'], $existingOutline);
            $outlineText = "\n\nساختار فعلی مقاله ما:\n" . implode("\n", $headings);
        }

        $system = 'تو یک متخصص SERP Analysis و تحلیل رقبا هستی. وظیفه تو تحلیل صفحات نتایج جستجوی گوگل برای یک کلمه کلیدی و ارائه تحلیل رقابتی است.\n'
            . 'خروجی تو باید یک JSON object معتبر باشد.\n'
            . 'فقط JSON object برگردان — بدون توضیح اضافه یا markdown code fence.';

        $user = "تحلیل SERP برای کلمه کلیدی: {$keyword}\n"
            . "زیرنوع محتوا: {$subtype}\n"
            . $outlineText . "\n\n"
            . "لطفاً تحلیل زیر را ارائه بده:\n\n"
            . "1. تحلیل ۵ صفحه برتر فرضی (بر اساس تجربه SEO):\n"
            . "   - عنوان صفحه\n"
            . "   - آدرس URL فرضی\n"
            . "   - ساختار عنوان‌ها (فقط H2/H3)\n"
            . "   - تعداد تقریبی کلمات\n"
            . "   - snippet تقریبی\n\n"
            . "2. میانگین تعداد کلمات رقبا\n\n"
            . "3. عنوان‌های مشترک (Headings) که اکثر رقبا دارند\n\n"
            . "4. شکاف‌های محتوایی (Content Gaps):\n"
            . "   - چه موضوعاتی هست که رقبا پوشش دادن ولی ما نداریم؟\n\n"
            . "5. پیشنهادات بهبود outline فعلی:\n"
            . "   - چه بخش‌هایی اضافه/حذف/تغییر کنیم؟\n\n"
            . "فرمت خروجی JSON:\n"
            . "{\n"
            . "  \"competitors\": [{\"title\": \"...\", \"url\": \"...\", \"headings\": [\"H2: ...\", \"H3: ...\"], \"word_count\": 1500, \"snippet\": \"...\"}],\n"
            . "  \"avg_word_count\": 1800,\n"
            . "  \"common_headings\": [\"H2: مقدمه\", \"H2: ...\"],\n"
            . "  \"content_gaps\": [\"topic1\", \"topic2\"],\n"
            . "  \"recommendations\": [\"...\", \"...\"]\n"
            . "}";

        return [$system, $user];
    }

    private function parseJsonResponse(string $content): ?array
    {
        // Strip markdown code fences if present
        $content = preg_replace('/^```json\s*/i', '', trim($content));
        $content = preg_replace('/\s*```$/', '', $content);
        $content = trim($content);

        $decoded = json_decode($content, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        // Try to extract JSON object from text
        if (preg_match('/\{.*\}/s', $content, $matches)) {
            $decoded = json_decode($matches[0], true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }

    private function fallbackAnalysis(string $keyword, array $existingOutline): array
    {
        return [
            'competitors' => [
                [
                    'title' => "راهنمای جامع {$keyword}",
                    'url' => "https://example.com/{$keyword}",
                    'headings' => ['H2: مقدمه', 'H2: تعریف و مفهوم', 'H2: مزایا', 'H2: نحوه اجرا', 'H2: نکات مهم', 'H2: نتیجه‌گیری'],
                    'word_count' => 1500,
                    'snippet' => "در این مقاله به بررسی کامل {$keyword} می‌پردازیم...",
                ],
                [
                    'title' => "{$keyword}: آموزش صفر تا صد",
                    'url' => "https://example2.com/{$keyword}",
                    'headings' => ['H2: آشنایی با {$keyword}', 'H2: ابزارهای مورد نیاز', 'H2: مراحل اجرا', 'H2: سؤالات متداول'],
                    'word_count' => 2000,
                    'snippet' => "همه چیز درباره {$keyword} را اینجا یاد بگیرید...",
                ],
            ],
            'avg_word_count' => 1800,
            'common_headings' => ['H2: مقدمه', 'H2: مفهوم', 'H2: مراحل', 'H2: نکات', 'H2: سؤالات متداول', 'H2: نتیجه‌گیری'],
            'content_gaps' => ['مقایسه با رقبا', 'نمونه عملی', 'آمار و ارقام'],
            'recommendations' => [
                'بخش "سؤالات متداول" اضافه کنید',
                'حداقل ۱۵۰۰ کلمه بنویسید',
                'بخش "مزایا و معایب" اضافه کنید',
            ],
        ];
    }
}
