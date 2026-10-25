<?php

/**
 * A patient's treatment plan as a review schedule. Its content (goals, interventions, health concerns) is core's care
 * plan form; this is how long it may go between reviews and when the next one is due. SAMHSA's CCBHC criterion 2.b.2
 * says "no less frequently than every 6 months, unless the state, federal, or applicable accreditation standards are
 * more stringent", so each clinic sets its own interval, and a review may set the next one sooner.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    anun333 <anun333@posteo.net>
 * @copyright Copyright (c) 2026 anun333 <anun333@posteo.net>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenHealthcare\BehavioralHealth\Domain;

final readonly class Plan
{
    /** 180 days: the CCBHC six months, counted a few days short so a review set on the last day is never late. */
    public const DEFAULT_INTERVAL_DAYS = 180;
    public const MIN_INTERVAL_DAYS = 7;
    public const MAX_INTERVAL_DAYS = 366;
    public const DEFAULT_AHEAD_DAYS = 14;

    public const ENDED = 'ended';
    public const NOT_DUE = 'not_due';
    public const DUE = 'due';
    public const OVERDUE = 'overdue';

    public function __construct(
        public int $id,
        public int $pid,
        public \DateTimeImmutable $startedOn,
        public int $intervalDays,
        public ?\DateTimeImmutable $endedOn,
        public string $endReason,
        public ?PlanReview $latest,
    ) {
    }

    /** The date the last review set, or sooner when the plan's interval has since been shortened. */
    public function nextDue(): \DateTimeImmutable
    {
        if ($this->latest === null) {
            return PlanReview::nextDue($this->startedOn, $this->intervalDays);
        }
        return PlanReview::nextDue($this->latest->reviewedOn, $this->intervalDays, $this->latest->nextDue);
    }

    /** ENDED, NOT_DUE, DUE (due within $aheadDays, today included) or OVERDUE (the due date has passed). */
    public function state(\DateTimeImmutable $today, int $aheadDays): string
    {
        if ($this->endedOn !== null) {
            return self::ENDED;
        }
        $due = $this->nextDue();
        if ($due < $today) {
            return self::OVERDUE;
        }
        return $due <= $today->modify('+' . max(0, $aheadDays) . ' days') ? self::DUE : self::NOT_DUE;
    }

    public static function interval(mixed $days): int
    {
        return is_numeric($days) ? max(self::MIN_INTERVAL_DAYS, min(self::MAX_INTERVAL_DAYS, (int)$days)) : self::DEFAULT_INTERVAL_DAYS;
    }
}
