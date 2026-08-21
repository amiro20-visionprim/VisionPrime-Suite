<?php

declare(strict_types=1);

namespace App\Domains\Content\Services;

/**
 * محاسبه خوانایی محتوای فارسی.
 *
 * معیارها:
 * - میانگین طول جمله (کلمات)
 * - میانگین طول کلمه (کاراکتر)
 * - نسبت جملات طولانی
 * - نسبت کلمات پیچیده (بیش از ۸ کاراکتر)
 * - امتیاز نهایی: ۰ تا ۱۰۰
 */
class ReadabilityService
{
    /**
     * محاسبه خوانایی متن فارسی.
     *
     * @return array{score: int, label: string, sentence_avg_length: float, word_avg_length: float, long_sentences_pct: float, complex_words_pct: float, details: string}
     */
    public function analyze(string $text): array
    {
        $plain = trim((string) preg_replace('/<[^>]+>/', ' ', $text));
        $plain = preg_replace('/\s+/u', ' ', $plain);

        if ($plain === '' || mb_strlen($plain, 'UTF-8') < 20) {
            return [
                'score' => 0,
                'label' => 'نامشخص',
                'sentence_avg_length' => 0,
                'word_avg_length' => 0,
                'long_sentences_pct' => 0,
                'complex_words_pct' => 0,
                'details' => 'متن خیلی کوتاه است',
            ];
        }

        $sentences = $this->splitSentences($plain);
        $words = $this->splitWords($plain);

        $sentenceCount = count($sentences);
        $wordCount = count($words);

        if ($sentenceCount === 0 || $wordCount === 0) {
            return [
                'score' => 0,
                'label' => 'نامشخص',
                'sentence_avg_length' => 0,
                'word_avg_length' => 0,
                'long_sentences_pct' => 0,
                'complex_words_pct' => 0,
                'details' => 'جمله یا کلمه‌ای یافت نشد',
            ];
        }

        // ۱) میانگین طول جمله
        $sentenceLengths = array_map(fn(string $s) => count($this->splitWords($s)), $sentences);
        $avgSentenceLength = array_sum($sentenceLengths) / $sentenceCount;

        // ۲) میانگین طول کلمه
        $wordLengths = array_map(fn(string $w) => mb_strlen($w, 'UTF-8'), $words);
        $avgWordLength = array_sum($wordLengths) / $wordCount;

        // ۳) درصد جملات طولانی (بیش از ۲۰ کلمه)
        $longSentences = count(array_filter($sentenceLengths, fn(int $len): bool => $len > 20));
        $longSentencesPct = ($longSentences / $sentenceCount) * 100;

        // ۴) درصد کلمات پیچیده (بیش از ۸ کاراکتر)
        $complexWords = count(array_filter($wordLengths, fn(int $len): bool => $len > 8));
        $complexWordsPct = ($complexWords / $wordCount) * 100;

        // ۵) محاسبه امتیاز (الگوی ساده‌شده Flesch فارسی)
        // هرچه جملات کوتاه‌تر و کلمات ساده‌تر → امتیاز بالاتر
        $score = 100;

        // جریمه برای جملات طولانی
        if ($avgSentenceLength > 25) {
            $score -= min(30, ($avgSentenceLength - 25) * 2);
        } elseif ($avgSentenceLength > 15) {
            $score -= min(10, ($avgSentenceLength - 15) * 0.5);
        }

        // جریمه برای کلمات پیچیده
        if ($complexWordsPct > 30) {
            $score -= min(25, ($complexWordsPct - 30) * 1.5);
        } elseif ($complexWordsPct > 20) {
            $score -= min(10, ($complexWordsPct - 20) * 0.5);
        }

        // جریمه برای درصد جملات طولانی
        if ($longSentencesPct > 40) {
            $score -= min(20, ($longSentencesPct - 40) * 0.8);
        }

        // جایزه برای جملات کوتاه
        if ($avgSentenceLength <= 12 && $complexWordsPct <= 15) {
            $score = min(100, $score + 10);
        }

        $score = max(0, min(100, (int) round($score)));

        // برچسب خوانایی
        $label = match (true) {
            $score >= 80 => 'عالی',
            $score >= 60 => 'خوب',
            $score >= 40 => 'متوسط',
            $score >= 20 => 'ضعیف',
            default => 'خیلی ضعیف',
        };

        $details = sprintf(
            '%s — جملات: %.0f کلمه | کلمات: %.1f کاراکتر | جملات طولانی: %.0f%% | کلمات پیچیده: %.0f%%',
            $label,
            $avgSentenceLength,
            $avgWordLength,
            $longSentencesPct,
            $complexWordsPct,
        );

        return [
            'score' => $score,
            'label' => $label,
            'sentence_avg_length' => round($avgSentenceLength, 1),
            'word_avg_length' => round($avgWordLength, 1),
            'long_sentences_pct' => round($longSentencesPct, 1),
            'complex_words_pct' => round($complexWordsPct, 1),
            'details' => $details,
        ];
    }

    /**
     * تقطیع متن به جملات (فارسی و انگلیسی).
     */
    private function splitSentences(string $text): array
    {
        // جداکننده‌های جمله: نقطه، علامت سوال، علامت تعجب، newline
        $sentences = preg_split('/[.؟!。\n]+/u', $text, -1, PREG_SPLIT_NO_EMPTY);
        return array_values(array_filter(
            array_map('trim', $sentences ?? []),
            fn(string $s): bool => mb_strlen($s, 'UTF-8') > 3,
        ));
    }

    /**
     * تقطیع متن به کلمات.
     */
    private function splitWords(string $text): array
    {
        $words = preg_split('/[\s,.؟!؛:،\[\](){}*#\-–—\/\\\\|]+/u', $text, -1, PREG_SPLIT_NO_EMPTY);
        return array_values(array_filter(
            $words ?? [],
            fn(string $w): bool => mb_strlen($w, 'UTF-8') > 0,
        ));
    }
}
