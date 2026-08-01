<?php

declare(strict_types=1);

namespace App\Domains\Automation\Services;

class CommandPolicyEvaluator
{
    public function evaluate(object $policy, string $type, string $risk): array
    {
        $rules = json_decode($policy->rules ?? '{}', true) ?? [];
        if ($policy->emergency_stopped_at) {
            return ['allowed' => false, 'reason' => 'Emergency stop is active.'];
        }$max = $rules['max_risk_tier'] ?? 'R0';
        $rank = ['R0' => 0, 'R1' => 1, 'R2' => 2, 'R3' => 3, 'R4' => 4];
        if (($rank[$risk] ?? 99) > ($rank[$max] ?? 0)) {
            return ['allowed' => false, 'reason' => 'Risk exceeds site policy.'];
        }if (! empty($rules['allowed_command_types']) && ! in_array($type, $rules['allowed_command_types'], true)) {
            return ['allowed' => false, 'reason' => 'Command type is not allowed.'];
        }

        return ['allowed' => true, 'reason' => 'Policy allows command.'];
    }
}
