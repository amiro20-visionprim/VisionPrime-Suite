<?php

declare(strict_types=1);

namespace App\Domains\Seo\Services;

class RuleBasedIntentClassifier
{
    public function classify(string $query): array
    {
        $q = mb_strtolower(trim($query));
        $rules = ['transactional' => ['خرید', 'سفارش', 'رزرو', 'ثبت نام', 'قیمت', 'purchase', 'buy', 'order'], 'commercial' => ['بهترین', 'مقایسه', 'بررسی', 'نظرات', 'best', 'compare', 'review'], 'informational' => ['چیست', 'چگونه', 'آموزش', 'راهنما', 'تفاوت', 'what is', 'how to', 'guide'], 'local' => ['نزدیک من', 'در تهران', 'در کرج', 'آدرس', 'near me', 'tehran'], 'branded' => ['vision prime']];
        foreach ($rules as $intent => $patterns) {
            foreach ($patterns as $pattern) {
                if (mb_strpos($q, $pattern) !== false) {
                    return ['intent' => $intent, 'confidence' => .82, 'explanation' => "Rule matched: {$pattern}", 'method' => 'rules', 'rules_version' => 'v1'];
                }
            }
        }

        return ['intent' => 'unknown', 'confidence' => .2, 'explanation' => 'No intent rule matched.', 'method' => 'rules', 'rules_version' => 'v1'];
    }
}
