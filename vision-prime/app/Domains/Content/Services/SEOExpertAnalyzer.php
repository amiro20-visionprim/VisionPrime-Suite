<?php

declare(strict_types=1);

namespace App\Domains\Content\Services;

use App\Domains\Ai\Services\AiGateway;
use Illuminate\Support\Facades\Log;

class SEOExpertAnalyzer
{
    public function analyze(array $content): array
    {
        $body = $content["body"] ?? "";
        $title = $content["title"] ?? "";
        $keyword = $content["keyword"] ?? "";
        $audit = $content["audit_log"] ?? [];

        $scores = $this->scoreDimensions($body, $title, $keyword, $audit);
        $strengths = $this->identifyStrengths($scores, $audit);
        $weaknesses = $this->identifyWeaknesses($scores, $audit);
        $recommendations = $this->generateRecommendations($weaknesses);
        $summary = $this->generateAISummary($title, $keyword, $scores, $strengths, $weaknesses);

        return [
            "summary" => $summary,
            "strengths" => $strengths,
            "weaknesses" => $weaknesses,
            "recommendations" => $recommendations,
            "scores" => $scores,
            "overall_score" => (int) round(array_sum($scores) / count($scores)),
        ];
    }

    private function scoreDimensions(string $body, string $title, string $keyword, array $audit): array
    {
        $s = [];
        $on = 0;
        if (($audit["heading_count"] ?? 0) >= 3) $on += 15;
        if (str_contains(mb_strtolower($body), mb_strtolower($keyword))) $on += 15;
        if (($audit["bold_count"] ?? 0) >= 5) $on += 10;
        if (($audit["has_toc"] ?? "no") === "yes") $on += 10;
        if (($audit["word_count"] ?? 0) >= 1500) $on += 15;
        if (($audit["ls_keywords"] ?? 0) >= 5) $on += 10;
        if (($audit["active_sentences_pct"] ?? 0) >= 70) $on += 10;
        if (($audit["external_link_count"] ?? 0) >= 1) $on += 10;
        if (($audit["image_count"] ?? 0) >= 1) $on += 5;
        $s["onpage"] = min(100, $on);
        $s["eeat"] = min(100, $audit["eeat_score"] ?? 0);
        $s["readability"] = $audit["readability_score"] ?? 0;
        $st = 0;
        if (($audit["heading_count"] ?? 0) >= 3) $st += 30;
        if (($audit["has_toc"] ?? "no") === "yes") $st += 25;
        if (($audit["image_count"] ?? 0) >= 1) $st += 20;
        if (preg_match("/<table/i", $body)) $st += 15;
        if (preg_match("/<(ul|ol)/i", $body)) $st += 10;
        $s["structure"] = min(100, $st);
        $img = 0;
        if (($audit["image_count"] ?? 0) >= 1) $img += 50;
        if (($audit["image_with_alt"] ?? 0) >= 1) $img += 50;
        $s["images"] = min(100, $img);
        $s["freshness"] = ($audit["freshness"] ?? false) ? 100 : 30;
        $s["uniqueness"] = max(0, 100 - ($audit["uniqueness"] ?? 0));
        return $s;
    }

    private function identifyStrengths(array $scores, array $audit): array
    {
        $r = [];
        if ($scores["onpage"] >= 70) $r[] = "سئوی On-Page قوی";
        if ($scores["eeat"] >= 50) $r[] = "سیگنال‌های E-E-A-T خوب";
        if ($scores["readability"] >= 70) $r[] = "خوانایی عالی";
        if ($scores["structure"] >= 70) $r[] = "ساختار منظم";
        if ($scores["images"] >= 50) $r[] = "تصاویر مناسب";
        if ($scores["freshness"] >= 80) $r[] = "محتوا به‌روز";
        if ($scores["uniqueness"] >= 80) $r[] = "محتوا یکتا";
        if (($audit["active_sentences_pct"] ?? 0) >= 70) $r[] = "جملات فعال";
        if (($audit["bold_count"] ?? 0) >= 5) $r[] = "bold مناسب";
        return $r;
    }

    private function identifyWeaknesses(array $scores, array $audit): array
    {
        $r = [];
        if ($scores["onpage"] < 50) $r[] = "سئوی On-Page ضعیف";
        if ($scores["eeat"] < 40) $r[] = "E-E-A-T ناکافی";
        if ($scores["readability"] < 50) $r[] = "خوانایی پایین";
        if ($scores["structure"] < 50) $r[] = "ساختار نیاز به بهبود";
        if ($scores["images"] < 30) $r[] = "تصاویر ناکافی";
        if (!($audit["freshness"] ?? false)) $r[] = "عدم ذکر سال";
        if ($scores["uniqueness"] < 50) $r[] = "محتوا تکراری";
        if (($audit["ls_keywords"] ?? 0) < 5) $r[] = "LSI ناکافی";
        if (($audit["active_sentences_pct"] ?? 0) < 70) $r[] = "جملات منفعل";
        return $r;
    }

    private function generateRecommendations(array $weaknesses): array
    {
        $r = [];
        foreach ($weaknesses as $w) {
            if (str_contains($w, "On-Page")) $r[] = "H2 و لینک داخلی اضافه کنید";
            if (str_contains($w, "E-E-A-T")) $r[] = "منابع معتبر اضافه کنید";
            if (str_contains($w, "خوانایی")) $r[] = "جملات کوتاه‌تر بنویسید";
            if (str_contains($w, "ساختار")) $r[] = "فهرست مطالب اضافه کنید";
            if (str_contains($w, "تصاویر")) $r[] = "تصویر با alt text اضافه کنید";
            if (str_contains($w, "سال")) $r[] = "سال جاری ذکر کنید";
            if (str_contains($w, "تکراری")) $r[] = "محتوا را یکتا بنویسید";
            if (str_contains($w, "LSI")) $r[] = "کلمات مرتبط بیشتری اضافه کنید";
            if (str_contains($w, "منفعل")) $r[] = "جملات فعال بنویسید";
        }
        return array_unique($r);
    }

    private function generateAISummary(string $title, string $keyword, array $scores, array $strengths, array $weaknesses): string
    {
        $overall = (int) round(array_sum($scores) / count($scores));
        $system = "تو یک متخصص سئو هستی.";
        $user = "محتوا را تحلیل کن و ۵ خط بنویس:\nعنوان: {$title}\nکلیدواژه: {$keyword}\nامتیاز: {$overall}/100\nنقاط قوت: " . implode(", ", array_slice($strengths, 0, 3)) . "\nنقاط ضعف: " . implode(", ", array_slice($weaknesses, 0, 3)) . "\nفقط ۵ خط بنویس.";

        try {
            $gateway = app(AiGateway::class);
            $result = $gateway->generate($system, $user, "expert_analysis");
            return $result["content"];
        } catch (\Throwable $e) {
            $s = "امتیاز: {$overall}/100. ";
            $s .= !empty($strengths) ? "نقاط قوت: " . $strengths[0] . ". " : "";
            $s .= !empty($weaknesses) ? "نقاط ضعف: " . $weaknesses[0] . ". " : "";
            $s .= "برای بهبود رتبه، موارد ضعف را رفع کنید.";
            return $s;
        }
    }
}