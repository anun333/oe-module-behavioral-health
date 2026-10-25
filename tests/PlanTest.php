<?php

/**
 * The treatment plan review schedule: the next review within the plan's interval (CCBHC criterion 2.b.2 sets six
 * months as the longest; states and payers set shorter), when a review is due or overdue, and each goal's progress.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    anun333 <anun333@posteo.net>
 * @copyright Copyright (c) 2026 anun333 <anun333@posteo.net>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenHealthcare\BehavioralHealth\Tests;

use OpenHealthcare\BehavioralHealth\Domain\Plan;
use OpenHealthcare\BehavioralHealth\Domain\PlanReview;
use PHPUnit\Framework\TestCase;

final class PlanTest extends TestCase
{
    private static function day(string $ymd): \DateTimeImmutable
    {
        return new \DateTimeImmutable($ymd);
    }

    private static function plan(string $reviewed, ?string $nextDue, int $interval = 180, ?string $ended = null): Plan
    {
        $review = new PlanReview(5, 1, 7, PlanReview::REVIEW, self::day($reviewed), 30, PlanReview::SIGNED, 'The person and their counselor', [], '',
            self::day($nextDue ?? $reviewed), null);
        return new Plan(1, 7, self::day('2026-01-05'), $interval, $ended === null ? null : self::day($ended), '', $nextDue === null ? null : $review);
    }

    public function testTheNextReviewIsTheLastDayTheIntervalAllowsUnlessOneSoonerIsAsked(): void
    {
        self::assertSame('2026-07-04', PlanReview::nextDue(self::day('2026-01-05'), 180)->format('Y-m-d'));
        self::assertSame('2026-04-01', PlanReview::nextDue(self::day('2026-01-05'), 180, self::day('2026-04-01'))->format('Y-m-d'));
        // Later than the interval allows: held to the interval.
        self::assertSame('2026-07-04', PlanReview::nextDue(self::day('2026-01-05'), 180, self::day('2026-12-31'))->format('Y-m-d'));
        // On or before the review itself: the day after.
        self::assertSame('2026-01-06', PlanReview::nextDue(self::day('2026-01-05'), 180, self::day('2026-01-01'))->format('Y-m-d'));
        // A Medicare community mental health center's 30 days (42 CFR 485.916(d)).
        self::assertSame('2026-02-04', PlanReview::nextDue(self::day('2026-01-05'), 30)->format('Y-m-d'));
    }

    public function testAReviewIsDueWithinTheDaysAheadAndOverdueTheDayAfterItsDate(): void
    {
        $plan = self::plan('2026-01-05', '2026-04-01');
        self::assertSame(Plan::NOT_DUE, $plan->state(self::day('2026-03-17'), 14));
        self::assertSame(Plan::DUE, $plan->state(self::day('2026-03-18'), 14));
        self::assertSame(Plan::DUE, $plan->state(self::day('2026-04-01'), 14));
        self::assertSame(Plan::OVERDUE, $plan->state(self::day('2026-04-02'), 14));
        self::assertSame(Plan::DUE, $plan->state(self::day('2026-04-01'), 0), 'due on its own date with no days ahead');
        self::assertSame(Plan::ENDED, self::plan('2026-01-05', '2026-04-01', 180, '2026-03-01')->state(self::day('2026-05-01'), 14));
    }

    public function testShorteningThePlanIntervalBringsTheNextReviewForward(): void
    {
        self::assertSame('2026-06-01', self::plan('2026-01-05', '2026-06-01', 180)->nextDue()->format('Y-m-d'));
        self::assertSame('2026-04-05', self::plan('2026-01-05', '2026-06-01', 90)->nextDue()->format('Y-m-d'));
        self::assertSame('2026-07-04', self::plan('2026-01-05', null, 180)->nextDue()->format('Y-m-d'), 'with no review yet, from the start');
    }

    public function testTheIntervalIsKeptBetweenAWeekAndAYear(): void
    {
        self::assertSame(180, Plan::interval('abc'));
        self::assertSame(7, Plan::interval(1));
        self::assertSame(366, Plan::interval(1000));
        self::assertSame(90, Plan::interval('90'));
    }

    public function testEachGoalKeepsItsWordingWithTheProgressChosen(): void
    {
        $goals = [['code' => '', 'text' => 'Sleep through the night most nights'], ['code' => '', 'text' => 'Back at school full days'], ['code' => 'X1', 'text' => 'Fewer panic attacks']];
        self::assertSame(
            [['goal' => 'Sleep through the night most nights', 'code' => '', 'progress' => PlanReview::PARTLY],
             ['goal' => 'Fewer panic attacks', 'code' => 'X1', 'progress' => PlanReview::MET]],
            PlanReview::progressOf($goals, ['0' => PlanReview::PARTLY, '1' => '', '2' => PlanReview::MET, '3' => PlanReview::MET]),
        );
        self::assertSame([], PlanReview::progressOf($goals, ['0' => 'excellent']), 'a progress not offered is left out');
    }
}
