<?php

declare(strict_types=1);

namespace App\Domains\Automation\Services;

use Illuminate\Support\Carbon;

/**
 * موتور تصمیم‌گیری انتشار (D-013 فاز ۲).
 *
 * با توجه به: سطح خودکارسازی (L0–L4)، AI_policy، آستانهٔ اطمینان (به‌ازای سطح ریسک)،
 * حداکثر ریسک مجاز، whitelist نوع دستور/محتوا، سقف روزانه و پنجرهٔ اجرا تصمیم می‌گیرد که
 * یک command خودکار منتشر شود، منتظر تأیید انسانی بماند، رد شود یا به تأخیر بیفتد.
 *
 * رفتار fail-closed: بدون امتیاز اطمینان (confidence_score=null) هرگز auto_publish نمی‌دهد.
 * در نبود پروفایل فعال، از rules/level قدیمیِ site_automation_policies استفاده می‌کند (backward-compat).
 */
class PolicyEvaluator
{
    public const DECISION_AUTO_PUBLISH = 'auto_publish';

    public const DECISION_PENDING_APPROVAL = 'pending_approval';

    public const DECISION_REJECTED = 'rejected';

    public const DECISION_DELAYED = 'delayed';

    public const DECISION_BLOCKED = 'blocked';

    private const RISK_RANK = ['R0' => 0, 'R1' => 1, 'R2' => 2, 'R3' => 3, 'R4' => 4];

    /**
     * @param  array<string, mixed>  $context  کلیدها:
     *                                         policy (object|array|null)، profile (object|array|null)،
     *                                         command{type, risk_tier, confidence_score|null, content_type|null}،
     *                                         now (Carbon|null)، today_counts{daily_commands, daily_mutations}
     * @return array{decision: string, reason: string, snapshot: array<string, mixed>}
     */
    public function evaluate(array $context): array
    {
        $policy = (array) ($context['policy'] ?? []);
        $profile = $context['profile'] !== null ? (array) $context['profile'] : null;
        $command = (array) ($context['command'] ?? []);
        $now = $context['now'] ?? Carbon::now();
        $counts = (array) ($context['today_counts'] ?? ['daily_commands' => 0, 'daily_mutations' => 0]);

        // فاز ۴: مسیریابی چندپروفایلی بر اساس نوع محتوا — اگر route برای content_type دستور باشد،
        // پروفایل همان route جایگزین پروفایل فعال می‌شود.
        $contentType = $command['content_type'] ?? $this->contentTypeFor((string) ($command['type'] ?? ''));
        $routed = $this->routedProfile($context['routes'] ?? [], $contentType);
        if ($routed !== null) {
            $profile = $routed;
        }

        // مدل سهلایه (D-015): Default ← Profile ← Override — overrides_json سایت روی پروفایل می‌نشیند.
        $overrides = $this->overrides($policy);
        if ($overrides !== []) {
            $profile = $profile !== null ? array_merge($profile, $overrides) : $overrides;
        }

        $rules = $this->rules($policy);
        $level = (int) ($profile['automation_level'] ?? $policy['level'] ?? 1);
        $aiPolicy = (string) ($profile['ai_policy'] ?? $rules['ai_policy'] ?? 'draft_only');
        $riskTier = (string) ($command['risk_tier'] ?? 'R0');
        $type = (string) ($command['type'] ?? '');
        $confidence = isset($command['confidence_score']) ? (int) $command['confidence_score'] : null;

        $snapshot = [
            'policy_version' => (int) ($command['policy_version'] ?? $policy['policy_version'] ?? 1),
            'automation_level' => $level,
            'ai_policy' => $aiPolicy,
            'risk_tier' => $riskTier,
            'profile_id' => isset($profile['id']) ? (int) $profile['id'] : null,
        ];

        // ۱) توقف اضطراری — هیچ انتشار و هیچ تصمیمی
        if (! empty($policy['emergency_stopped_at'])) {
            return $this->result(self::DECISION_BLOCKED, 'Emergency stop is active for this site.', $snapshot);
        }

        // ۲) حالت دستی (زیر L2 یا بدون اجازه‌نامهٔ bounded_auto) → تأیید انسانی، بدون اعمال whitelist خودکار.
        //    (با backward-compat: نبود row سیاست = level پیش‌فرض ۱ = مسیر دستی فعلی)
        if ($level < 2 || $aiPolicy !== 'bounded_auto') {
            return $this->result(self::DECISION_PENDING_APPROVAL, "Level {$level} / AI policy {$aiPolicy} requires human approval.", $snapshot);
        }

        // ۳) سقف ریسک مجاز (فقط در مسیر خودکار)
        $maxRisk = (string) ($profile['risk_tier_max'] ?? $rules['max_risk_tier'] ?? 'R0');
        if (($this->rank($riskTier) ?? 99) > $this->rank($maxRisk)) {
            return $this->result(self::DECISION_REJECTED, "Risk tier {$riskTier} exceeds site maximum ({$maxRisk}).", $snapshot);
        }

        // ۴) whitelist نوع دستور
        $allowedTypes = $rules['allowed_command_types'] ?? null;
        if (is_array($allowedTypes) && $allowedTypes !== [] && ! in_array($type, $allowedTypes, true)) {
            return $this->result(self::DECISION_REJECTED, "Command type {$type} is not allowed.", $snapshot);
        }

        // ۵) whitelist نوع محتوا (پروفایل)
        $enabled = isset($profile['enabled_content_types']) && $profile['enabled_content_types'] !== null
            ? (array) json_decode((string) $profile['enabled_content_types'], true)
            : null;
        if ($contentType !== null && is_array($enabled) && $enabled !== [] && ! in_array($contentType, $enabled, true)) {
            return $this->result(self::DECISION_REJECTED, "Content type {$contentType} is not enabled in the active profile.", $snapshot);
        }

        // ۶) R3 همیشه تأیید انسانی (Enterprise + تأیید صریح در فازهای بعدی)
        if ($riskTier === 'R3') {
            return $this->result(self::DECISION_PENDING_APPROVAL, 'R3 changes always require explicit human approval.', $snapshot);
        }

        // ۷) سقف روزانه
        $dailyLimit = isset($profile['daily_command_limit']) && $profile['daily_command_limit'] !== null ? (int) $profile['daily_command_limit'] : null;
        if ($dailyLimit !== null && (int) ($counts['daily_commands'] ?? 0) >= $dailyLimit) {
            return $this->result(self::DECISION_DELAYED, "Daily command limit ({$dailyLimit}) reached.", $snapshot);
        }
        $mutationLimit = isset($profile['daily_mutation_limit']) && $profile['daily_mutation_limit'] !== null ? (int) $profile['daily_mutation_limit'] : null;
        if ($mutationLimit !== null && $this->isMutation($riskTier) && (int) ($counts['daily_mutations'] ?? 0) >= $mutationLimit) {
            return $this->result(self::DECISION_DELAYED, "Daily mutation limit ({$mutationLimit}) reached.", $snapshot);
        }

        // ۸) پنجرهٔ اجرا
        $window = $profile['execution_window'] ?? $rules['execution_window'] ?? null;
        if (is_string($window) && $window !== '') {
            $window = json_decode($window, true);
        }
        if (is_array($window) && ! empty($window['start']) && ! empty($window['end']) && ! $this->withinWindow($now, $window)) {
            return $this->result(self::DECISION_DELAYED, 'Outside the configured execution window.', $snapshot);
        }

        // ۹) آستانهٔ اطمینان (fail-closed بدون امتیاز)
        $threshold = $riskTier === 'R1' || $riskTier === 'R0'
            ? (int) ($profile['confidence_threshold'] ?? $rules['confidence_threshold'] ?? 80)
            : (int) ($profile['high_risk_threshold'] ?? $rules['high_risk_threshold'] ?? 90);
        $snapshot['confidence_threshold'] = $threshold;
        $snapshot['confidence_score'] = $confidence;
        if ($confidence === null || $confidence < $threshold) {
            return $this->result(
                self::DECISION_PENDING_APPROVAL,
                $confidence === null
                    ? 'No confidence score available; routing to human approval.'
                    : "Confidence {$confidence} is below threshold {$threshold}.",
                $snapshot,
            );
        }

        // ۱۰) انتشار خودکار
        $autoAtLevel2 = $level === 2 && ($riskTier === 'R0' || $riskTier === 'R1');
        $autoAtLevel3Plus = $level >= 3 && in_array($riskTier, ['R0', 'R1', 'R2'], true);
        if ($autoAtLevel2 || $autoAtLevel3Plus) {
            return $this->result(self::DECISION_AUTO_PUBLISH, 'Policy allows automatic publication.', $snapshot);
        }

        return $this->result(self::DECISION_PENDING_APPROVAL, 'Not eligible for automatic publication.', $snapshot);
    }

    /**
     * @param  array<int, array{content_type: string, profile: array<string, mixed>|null}>  $routes
     * @return array<string, mixed>|null
     */
    private function routedProfile(array $routes, ?string $contentType): ?array
    {
        if ($contentType === null) {
            return null;
        }
        foreach ($routes as $route) {
            if (($route['content_type'] ?? null) === $contentType && is_array($route['profile'] ?? null)) {
                return $route['profile'];
            }
        }

        return null;
    }

    /** @param  array<string, mixed>  $policy
     * @return array<string, mixed>
     */
    private function rules(array $policy): array
    {
        $rules = json_decode((string) ($policy['rules'] ?? '{}'), true);

        return is_array($rules) ? $rules : [];
    }

    /** @param  array<string, mixed>  $policy
     * @return array<string, mixed>
     */
    private function overrides(array $policy): array
    {
        $raw = $policy['overrides_json'] ?? null;
        if ($raw === null || $raw === '') {
            return [];
        }
        $overrides = json_decode((string) $raw, true);

        return is_array($overrides) ? $overrides : [];
    }

    /** @return array{decision: string, reason: string, snapshot: array<string, mixed>} */
    private function result(string $decision, string $reason, array $snapshot): array
    {
        return ['decision' => $decision, 'reason' => $reason, 'snapshot' => $snapshot];
    }

    private function rank(string $tier): ?int
    {
        return self::RISK_RANK[$tier] ?? null;
    }

    private function isMutation(string $riskTier): bool
    {
        return in_array($riskTier, ['R1', 'R2', 'R3'], true);
    }

    private function contentTypeFor(string $type): ?string
    {
        return match (true) {
            str_starts_with($type, 'update_meta_') => 'meta',
            str_starts_with($type, 'update_product_') => 'product',
            in_array($type, ['update_content', 'publish_new_article', 'update_published_content'], true) => 'article',
            default => null,
        };
    }

    /** @param  array{start: string, end: string, tz?: string}  $window */
    private function withinWindow(Carbon $now, array $window): bool
    {
        $tz = $window['tz'] ?? $now->getTimezone()->getName();
        $local = $now->copy()->timezone($tz);
        $start = (int) Carbon::parse($window['start'])->format('Hi');
        $end = (int) Carbon::parse($window['end'])->format('Hi');
        $current = (int) $local->format('Hi');

        return $start <= $end
            ? $current >= $start && $current <= $end
            : $current >= $start || $current <= $end; // پنجرهٔ شب‌گذر
    }
}
