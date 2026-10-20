<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Scoring\PenaltyRule;
use PHPUnit\Framework\TestCase;

/**
 * Jazo chegarasi — SPEC §17: rejaning 50% yoki kamini bajargan jazolanadi.
 */
class PenaltyRuleTest extends TestCase
{
    public function test_exactly_half_is_penalized_one_more_is_safe(): void
    {
        $rule = new PenaltyRule(50);

        // 6 odat × 10 kun = 60
        $this->assertTrue($rule->isPenalized(30, 60), '50% — jazo');
        $this->assertTrue($rule->isPenalized(0, 60));
        $this->assertFalse($rule->isPenalized(31, 60), '50% dan ko\'p — xavfsiz');
        $this->assertSame(31, $rule->safeAt(60));
    }

    public function test_odd_totals_round_in_the_players_favour_only_above_the_line(): void
    {
        $rule = new PenaltyRule(50);

        // 7 dan 3.5 — 3 jazo, 4 xavfsiz
        $this->assertTrue($rule->isPenalized(3, 7));
        $this->assertFalse($rule->isPenalized(4, 7));
        $this->assertSame(4, $rule->safeAt(7));
    }

    public function test_nothing_planned_means_nothing_to_punish(): void
    {
        $rule = new PenaltyRule(50);

        $this->assertFalse($rule->isPenalized(0, 0));
        $this->assertSame(0, $rule->safeAt(0));
    }
}
