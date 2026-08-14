<?php

declare(strict_types=1);

namespace Tests\Unit\Automation;

use App\Domains\Automation\Services\PolicyEvaluator;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PolicyEvaluatorTest extends TestCase
{
    private PolicyEvaluator $evaluator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->evaluator = app(PolicyEvaluator::class);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function context(array $overrides = []): array
    {
        return array_merge([
            'policy' => (object) [
                'level' => 2,
                'rules' => json_encode(['max_risk_tier' => 'R2', 'allowed_command_types' => ['update_meta_title', 'update_meta_description', 'update_product_title']]),
                'emergency_stopped_at' => null,
                'active_profile_id' => 1,
            ],
            'profile' => (object) [
                'id' => 1,
                'automation_level' => 2,
                'ai_policy' => 'bounded_auto',
                'confidence_threshold' => 80,
                'high_risk_threshold' => 90,
                'risk_tier_max' => 'R2',
                'enabled_content_types' => json_encode(['meta', 'product'], JSON_UNESCAPED_UNICODE),
                'daily_command_limit' => 10,
                'daily_mutation_limit' => 5,
                'execution_window' => null,
            ],
            'command' => [
                'type' => 'update_meta_title',
                'risk_tier' => 'R1',
                'confidence_score' => 85,
                'content_type' => 'meta',
                'policy_version' => 3,
            ],
        ], $overrides);
    }

    public function test_emergency_stop_blocks_everything(): void
    {
        $result = $this->evaluator->evaluate($this->context([
            'policy' => (object) ['level' => 4, 'rules' => '{}', 'emergency_stopped_at' => now()],
        ]));

        $this->assertSame(PolicyEvaluator::DECISION_BLOCKED, $result['decision']);
    }

    public function test_l1_always_routes_to_human_approval(): void
    {
        $result = $this->evaluator->evaluate($this->context(['profile' => (object) ['id' => 1, 'automation_level' => 1, 'ai_policy' => 'draft_only']]));

        $this->assertSame(PolicyEvaluator::DECISION_PENDING_APPROVAL, $result['decision']);
    }

    public function test_l2_high_confidence_r1_auto_publishes(): void
    {
        $result = $this->evaluator->evaluate($this->context());

        $this->assertSame(PolicyEvaluator::DECISION_AUTO_PUBLISH, $result['decision']);
    }

    public function test_confidence_below_threshold_routes_to_approval(): void
    {
        $result = $this->evaluator->evaluate($this->context(['command' => ['type' => 'update_meta_title', 'risk_tier' => 'R1', 'confidence_score' => 70, 'content_type' => 'meta']]));

        $this->assertSame(PolicyEvaluator::DECISION_PENDING_APPROVAL, $result['decision']);
        $this->assertStringContainsString('below threshold', $result['reason']);
    }

    public function test_null_confidence_fails_closed(): void
    {
        $result = $this->evaluator->evaluate($this->context(['command' => ['type' => 'update_meta_title', 'risk_tier' => 'R1', 'confidence_score' => null, 'content_type' => 'meta']]));

        $this->assertSame(PolicyEvaluator::DECISION_PENDING_APPROVAL, $result['decision']);
        $this->assertStringContainsString('No confidence score', $result['reason']);
    }

    public function test_l2_does_not_auto_publish_r2(): void
    {
        $result = $this->evaluator->evaluate($this->context(['command' => ['type' => 'update_meta_description', 'risk_tier' => 'R2', 'confidence_score' => 95, 'content_type' => 'meta']]));

        $this->assertSame(PolicyEvaluator::DECISION_PENDING_APPROVAL, $result['decision']);
    }

    public function test_l3_auto_publishes_r2_with_high_confidence(): void
    {
        $context = $this->context([
            'profile' => (object) ['id' => 1, 'automation_level' => 3, 'ai_policy' => 'bounded_auto', 'confidence_threshold' => 80, 'high_risk_threshold' => 90, 'risk_tier_max' => 'R2', 'enabled_content_types' => json_encode(['meta', 'product'], JSON_UNESCAPED_UNICODE)],
            'command' => ['type' => 'update_meta_description', 'risk_tier' => 'R2', 'confidence_score' => 95, 'content_type' => 'meta'],
        ]);

        $this->assertSame(PolicyEvaluator::DECISION_AUTO_PUBLISH, $this->evaluator->evaluate($context)['decision']);
    }

    public function test_r3_always_requires_human_approval_even_at_l4(): void
    {
        $result = $this->evaluator->evaluate($this->context([
            'profile' => (object) ['id' => 1, 'automation_level' => 4, 'ai_policy' => 'bounded_auto', 'confidence_threshold' => 80, 'high_risk_threshold' => 90, 'risk_tier_max' => 'R3'],
            'command' => ['type' => 'update_meta_title', 'risk_tier' => 'R3', 'confidence_score' => 99, 'content_type' => 'meta'],
        ]));

        $this->assertSame(PolicyEvaluator::DECISION_PENDING_APPROVAL, $result['decision']);
    }

    public function test_risk_exceeding_site_max_is_rejected(): void
    {
        $result = $this->evaluator->evaluate($this->context([
            'profile' => (object) ['id' => 1, 'automation_level' => 4, 'ai_policy' => 'bounded_auto', 'risk_tier_max' => 'R1'],
            'command' => ['type' => 'update_meta_title', 'risk_tier' => 'R2', 'confidence_score' => 95, 'content_type' => 'meta'],
        ]));

        $this->assertSame(PolicyEvaluator::DECISION_REJECTED, $result['decision']);
    }

    public function test_disallowed_command_type_is_rejected(): void
    {
        $result = $this->evaluator->evaluate($this->context(['command' => ['type' => 'publish_new_article', 'risk_tier' => 'R2', 'confidence_score' => 95, 'content_type' => 'article']]));

        $this->assertSame(PolicyEvaluator::DECISION_REJECTED, $result['decision']);
    }

    public function test_disabled_content_type_is_rejected(): void
    {
        $result = $this->evaluator->evaluate($this->context(['command' => ['type' => 'update_content', 'risk_tier' => 'R2', 'confidence_score' => 95, 'content_type' => 'article']]));

        $this->assertSame(PolicyEvaluator::DECISION_REJECTED, $result['decision']);
    }

    public function test_daily_command_limit_reached_delays(): void
    {
        $result = $this->evaluator->evaluate($this->context(['today_counts' => ['daily_commands' => 10, 'daily_mutations' => 1]]));

        $this->assertSame(PolicyEvaluator::DECISION_DELAYED, $result['decision']);
        $this->assertStringContainsString('Daily command limit', $result['reason']);
    }

    public function test_daily_mutation_limit_reached_delays(): void
    {
        $result = $this->evaluator->evaluate($this->context(['today_counts' => ['daily_commands' => 1, 'daily_mutations' => 5]]));

        $this->assertSame(PolicyEvaluator::DECISION_DELAYED, $result['decision']);
    }

    public function test_outside_execution_window_delays(): void
    {
        $result = $this->evaluator->evaluate($this->context([
            'profile' => (object) ['id' => 1, 'automation_level' => 3, 'ai_policy' => 'bounded_auto', 'confidence_threshold' => 80, 'high_risk_threshold' => 90, 'risk_tier_max' => 'R2', 'execution_window' => json_encode(['start' => '23:00', 'end' => '06:00', 'tz' => 'UTC'])],
            'now' => Carbon::create(2026, 8, 14, 12, 0, 0, 'UTC'),
        ]));

        $this->assertSame(PolicyEvaluator::DECISION_DELAYED, $result['decision']);
    }

    public function test_within_execution_window_allows_auto_publish(): void
    {
        $result = $this->evaluator->evaluate($this->context([
            'profile' => (object) ['id' => 1, 'automation_level' => 3, 'ai_policy' => 'bounded_auto', 'confidence_threshold' => 80, 'high_risk_threshold' => 90, 'risk_tier_max' => 'R2', 'execution_window' => json_encode(['start' => '06:00', 'end' => '23:00', 'tz' => 'UTC'])],
            'now' => Carbon::create(2026, 8, 14, 12, 0, 0, 'UTC'),
        ]));

        $this->assertSame(PolicyEvaluator::DECISION_AUTO_PUBLISH, $result['decision']);
    }

    public function test_non_bounded_ai_policy_routes_to_approval(): void
    {
        $result = $this->evaluator->evaluate($this->context([
            'profile' => (object) ['id' => 1, 'automation_level' => 3, 'ai_policy' => 'draft_only'],
        ]));

        $this->assertSame(PolicyEvaluator::DECISION_PENDING_APPROVAL, $result['decision']);
    }

    public function test_legacy_rules_fallback_without_profile(): void
    {
        $result = $this->evaluator->evaluate([
            'policy' => (object) [
                'level' => 2,
                'rules' => json_encode(['max_risk_tier' => 'R1', 'ai_policy' => 'bounded_auto', 'confidence_threshold' => 80]),
                'emergency_stopped_at' => null,
                'active_profile_id' => null,
            ],
            'profile' => null,
            'command' => ['type' => 'update_meta_title', 'risk_tier' => 'R1', 'confidence_score' => 85],
        ]);

        $this->assertSame(PolicyEvaluator::DECISION_AUTO_PUBLISH, $result['decision']);
    }

    public function test_routing_picks_profile_by_content_type(): void
    {
        $l2Profile = (object) ['id' => 2, 'automation_level' => 2, 'ai_policy' => 'bounded_auto', 'confidence_threshold' => 80, 'high_risk_threshold' => 90, 'risk_tier_max' => 'R2'];
        $l3Article = (object) ['id' => 3, 'automation_level' => 3, 'ai_policy' => 'bounded_auto', 'confidence_threshold' => 90, 'high_risk_threshold' => 95, 'risk_tier_max' => 'R3', 'enabled_content_types' => json_encode(['article'], JSON_UNESCAPED_UNICODE)];
        $base = $this->context([
            'policy' => (object) ['level' => 2, 'rules' => '{}', 'emergency_stopped_at' => null, 'active_profile_id' => 2],
            'profile' => $l2Profile,
        ]);

        // meta با اطمینان ۸۵ → پروفایل پایهٔ L2 (آستانه ۸۰) → خودکار
        $metaResult = $this->evaluator->evaluate(array_merge($base, ['routes' => [['content_type' => 'article', 'profile' => (array) $l3Article]]]));
        $this->assertSame(PolicyEvaluator::DECISION_AUTO_PUBLISH, $metaResult['decision']);

        // article با اطمینان ۸۵ → پروفایل route شدهٔ L3 (آستانه ۹۰) → ۸۵ < ۹۰ → تأیید انسانی
        $articleContext = array_merge($base, [
            'command' => ['type' => 'update_content', 'risk_tier' => 'R2', 'confidence_score' => 85, 'content_type' => 'article'],
            'routes' => [['content_type' => 'article', 'profile' => (array) $l3Article]],
        ]);
        $articleResult = $this->evaluator->evaluate($articleContext);
        $this->assertSame(PolicyEvaluator::DECISION_PENDING_APPROVAL, $articleResult['decision']);
        $this->assertSame(3, $articleResult['snapshot']['automation_level']);

        // article با اطمینان ۹۵ → بالاتر از آستانهٔ route → خودکار
        $articleHigh = array_merge($articleContext, ['command' => ['type' => 'update_content', 'risk_tier' => 'R2', 'confidence_score' => 95, 'content_type' => 'article']]);
        $this->assertSame(PolicyEvaluator::DECISION_AUTO_PUBLISH, $this->evaluator->evaluate($articleHigh)['decision']);
    }

    public function test_snapshot_contains_policy_context(): void
    {
        $result = $this->evaluator->evaluate($this->context());

        $this->assertSame(3, $result['snapshot']['policy_version']);
        $this->assertSame(2, $result['snapshot']['automation_level']);
        $this->assertSame('bounded_auto', $result['snapshot']['ai_policy']);
        $this->assertSame(80, $result['snapshot']['confidence_threshold']);
        $this->assertSame(85, $result['snapshot']['confidence_score']);
    }
}
