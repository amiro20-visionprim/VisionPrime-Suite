<?php

declare(strict_types=1);

namespace App\Domains\Marketing\Actions;

use App\Models\Lead;

/**
 * Automatic lead scoring (0-100) based on campaign attribution and behavioral
 * signals. The breakdown is stored in metadata.score_breakdown so the marketing
 * dashboard can explain every decision to the team.
 */
class ScoreLead
{
    public function handle(Lead $lead): void
    {
        $breakdown = [];
        $score = 0;

        // 1. Source baseline
        if ($lead->source === 'demo') {
            $score += 30;
            $breakdown[] = ['key' => 'source_demo', 'label' => 'درخواست دمو (هدف فروش)', 'points' => 30];
        } elseif ($lead->source === 'support') {
            $score += 15;
            $breakdown[] = ['key' => 'source_support', 'label' => 'پیام پشتیبانی', 'points' => 15];
        }

        // 2. Filled business signals
        if (! empty($lead->company)) {
            $score += 10;
            $breakdown[] = ['key' => 'has_company', 'label' => 'نام شرکت ثبت شده', 'points' => 10];
        }

        if (! empty($lead->website)) {
            $score += 10;
            $breakdown[] = ['key' => 'has_website', 'label' => 'وب‌سایت ثبت شده', 'points' => 10];
        }

        // 3. Landing page intent
        $landing = (string) $lead->landing_page;
        if (str_contains($landing, '/pricing')) {
            $score += 10;
            $breakdown[] = ['key' => 'landing_pricing', 'label' => 'فرود روی صفحهٔ قیمت‌گذاری', 'points' => 10];
        } elseif (str_contains($landing, '/demo')) {
            $score += 5;
            $breakdown[] = ['key' => 'landing_demo', 'label' => 'فرود روی صفحهٔ دمو', 'points' => 5];
        }

        // 4. Paid / high-intent campaign signals
        $medium = strtolower((string) $lead->utm_medium);
        if (in_array($medium, ['cpc', 'cpm', 'paid', 'paid_social', 'ppc'], true)) {
            $score += 10;
            $breakdown[] = ['key' => 'paid_medium', 'label' => 'کانال تبلیغاتی پولی ('.$medium.')', 'points' => 10];
        }

        $source = strtolower((string) $lead->utm_source);
        if (in_array($source, ['google', 'bing'], true)) {
            $score += 5;
            $breakdown[] = ['key' => 'search_source', 'label' => 'جستجوی گوگل/بینگ (نیت بالا)', 'points' => 5];
        }

        // 5. Message intent keywords
        $message = mb_strtolower((string) $lead->message, 'UTF-8');
        $strongKeywords = ['وایتدلیبل', 'برند اختصاصی', 'آژانس', 'بودجه', 'قرارداد', 'چند سایت', '۱۰ سایت', '۱۵ سایت', '۲۰ سایت', 'تیم', 'قیمت پلن'];
        $mediumKeywords = ['کنترل تغییرات', 'گزارش', 'وردپرس', 'اجرا', 'دمو', 'پرتال', 'سرچ کنسول', 'سئو', 'فرصت'];

        foreach ($strongKeywords as $keyword) {
            if (str_contains($message, mb_strtolower($keyword, 'UTF-8'))) {
                $score += 10;
                $breakdown[] = ['key' => 'keyword_strong_'.$this->slugify($keyword), 'label' => 'سیگنال قوی در پیام: «'.$keyword.'»', 'points' => 10];
                break;
            }
        }

        foreach ($mediumKeywords as $keyword) {
            if (str_contains($message, mb_strtolower($keyword, 'UTF-8'))) {
                $score += 4;
                $breakdown[] = ['key' => 'keyword_medium_'.$this->slugify($keyword), 'label' => 'سیگنال در پیام: «'.$keyword.'»', 'points' => 4];
                break;
            }
        }

        if (mb_strlen($message, 'UTF-8') >= 40) {
            $score += 5;
            $breakdown[] = ['key' => 'long_message', 'label' => 'پیام پرمحتوا و مشخص', 'points' => 5];
        }

        // 6. Recency
        if ($lead->created_at !== null && $lead->created_at->gte(now()->subDays(7))) {
            $score += 5;
            $breakdown[] = ['key' => 'recent', 'label' => 'ثبت‌شده در ۷ روز اخیر', 'points' => 5];
        }

        $score = max(0, min(100, $score));

        $metadata = $lead->metadata ?? [];
        $metadata['score_breakdown'] = [
            'total' => $score,
            'items' => $breakdown,
            'computed_at' => now()->toIso8601String(),
        ];

        $lead->forceFill([
            'score' => $score,
            'metadata' => $metadata,
        ])->save();
    }

    private function slugify(string $text): string
    {
        return preg_replace('/[^\p{L}\p{N}]+/u', '_', $text) ?? 'x';
    }
}
