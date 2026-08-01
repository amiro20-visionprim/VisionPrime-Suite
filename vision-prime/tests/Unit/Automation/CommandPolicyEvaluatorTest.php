<?php

declare(strict_types=1);

namespace Tests\Unit\Automation;

use App\Domains\Automation\Services\CommandPolicyEvaluator;
use Tests\TestCase;

class CommandPolicyEvaluatorTest extends TestCase
{
    public function test_policy_rejects_high_risk_and_emergency_stop(): void
    {
        $e = app(CommandPolicyEvaluator::class);
        $policy = (object) ['rules' => json_encode(['max_risk_tier' => 'R1', 'allowed_command_types' => ['update_meta_title']]), 'emergency_stopped_at' => null];
        $this->assertFalse($e->evaluate($policy, 'update_meta_title', 'R3')['allowed']);
        $this->assertFalse($e->evaluate((object) ['rules' => '{}', 'emergency_stopped_at' => now()], 'update_meta_title', 'R0')['allowed']);
        $this->assertTrue($e->evaluate($policy, 'update_meta_title', 'R1')['allowed']);
    }
}
