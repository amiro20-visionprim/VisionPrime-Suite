<?php

declare(strict_types=1);

namespace Tests\Unit\Automation;

use App\Domains\Automation\Services\ConfidenceScorer;
use Tests\TestCase;

class ConfidenceScorerTest extends TestCase
{
    public function test_strong_signals_produce_high_confidence(): void
    {
        $result = app(ConfidenceScorer::class)->score([
            'data_quality' => 0.9,
            'signal_strength' => 0.9,
            'sources' => ['rule_based', 'ai'],
            'history' => ['total' => 20, 'successful' => 18],
        ]);

        $this->assertGreaterThanOrEqual(90, $result['score']);
        // عوامل نرمال‌شده‌اند و جمعشان عددی بین ۰ تا ۴ است (نه درصد).
        $this->assertLessThanOrEqual(4, array_sum($result['factors']));
    }

    public function test_poor_data_drags_score_below_default_threshold(): void
    {
        $result = app(ConfidenceScorer::class)->score([
            'data_quality' => 0.2,
            'signal_strength' => 0.6,
            'sources' => ['rule_based'],
            'history' => null,
        ]);

        $this->assertLessThan(80, $result['score']);
    }

    public function test_source_disagreement_lowers_score(): void
    {
        $scorer = app(ConfidenceScorer::class);
        $single = $scorer->score([
            'data_quality' => 0.8,
            'signal_strength' => 0.8,
            'sources' => ['ai'],
            'history' => null,
        ]);
        $both = $scorer->score([
            'data_quality' => 0.8,
            'signal_strength' => 0.8,
            'sources' => ['rule_based', 'ai'],
            'history' => null,
        ]);

        $this->assertLessThan($both['score'], $single['score']);
    }

    public function test_failed_history_reduces_confidence(): void
    {
        $scorer = app(ConfidenceScorer::class);
        $bad = $scorer->score([
            'data_quality' => 0.8,
            'signal_strength' => 0.8,
            'sources' => ['rule_based', 'ai'],
            'history' => ['total' => 10, 'successful' => 1],
        ]);
        $neutral = $scorer->score([
            'data_quality' => 0.8,
            'signal_strength' => 0.8,
            'sources' => ['rule_based', 'ai'],
            'history' => null,
        ]);

        $this->assertLessThan($neutral['score'], $bad['score']);
    }

    public function test_score_is_clamped_to_0_100(): void
    {
        $result = app(ConfidenceScorer::class)->score([
            'data_quality' => 1.5,
            'signal_strength' => -0.2,
            'sources' => [],
            'history' => ['total' => 1, 'successful' => 1],
        ]);

        $this->assertGreaterThanOrEqual(0, $result['score']);
        $this->assertLessThanOrEqual(100, $result['score']);
    }

    public function test_custom_weights_change_outcome(): void
    {
        $scorer = new ConfidenceScorer(['data_quality' => 0.8, 'signal_strength' => 0.0, 'source_agreement' => 0.1, 'history' => 0.1]);
        $result = $scorer->score([
            'data_quality' => 1.0,
            'signal_strength' => 0.0,
            'sources' => [],
            'history' => null,
        ]);

        // 0.8*1.0 + 0.1*0.3 (بدون منبع) + 0.1*0.5 (خنثی) = 0.88 → ۸۸
        $this->assertSame(88, $result['score']);
    }
}
