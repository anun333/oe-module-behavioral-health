<?php

/**
 * One making or review of a treatment plan: the care plan form it read, the person's endorsement (CCBHC criterion
 * 4.e.2 asks that the plan be "endorsed by the person receiving services"), who took part, each goal's progress as
 * the goal read that day, and when the next review is due.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    anun333 <anun333@posteo.net>
 * @copyright Copyright (c) 2026 anun333 <anun333@posteo.net>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenHealthcare\BehavioralHealth\Domain;

final readonly class PlanReview
{
    public const INITIAL = 'initial';
    public const REVIEW = 'review';
    /** A review out of turn, after a change in status, response to treatment or goals (CCBHC 2.b.2). */
    public const UPDATE = 'update';
    public const KINDS = [self::INITIAL, self::REVIEW, self::UPDATE];

    /** The person signed the plan. */
    public const SIGNED = 'signed';
    /** The person agreed, and staff noted it (New York's clinic rule asks only for a notation). */
    public const VERBAL = 'verbal';
    public const DECLINED = 'declined';
    /** The person could not take part this time. */
    public const UNABLE = 'unable';
    public const ENDORSEMENTS = [self::SIGNED, self::VERBAL, self::DECLINED, self::UNABLE];

    public const MET = 'met';
    public const PARTLY = 'partly';
    public const NOT_MET = 'not_met';
    public const CHANGED = 'changed';
    public const PROGRESS = [self::MET, self::PARTLY, self::NOT_MET, self::CHANGED];

    /**
     * @param list<array{goal: string, code: string, progress: string}> $progress each goal as it read, and its progress
     */
    public function __construct(
        public int $id,
        public int $planId,
        public int $pid,
        public string $kind,
        public \DateTimeImmutable $reviewedOn,
        public ?int $carePlanFormId,
        public string $endorsement,
        public string $participants,
        public array $progress,
        public string $changes,
        public \DateTimeImmutable $nextDue,
        public ?int $documentId,
        public int $reviewedBy = 0,
    ) {
    }

    /**
     * The next review date: the one asked for, kept within the plan's interval; the last day the interval allows
     * when none is asked for, or one later is.
     */
    public static function nextDue(\DateTimeImmutable $reviewedOn, int $intervalDays, ?\DateTimeImmutable $asked = null): \DateTimeImmutable
    {
        $latest = $reviewedOn->modify('+' . max(1, $intervalDays) . ' days');
        if ($asked === null || $asked > $latest) {
            return $latest;
        }
        $earliest = $reviewedOn->modify('+1 day');
        return $asked < $earliest ? $earliest : $asked;
    }

    /**
     * The goals of a care plan, with the progress chosen for each; a goal with no progress chosen, or one not in
     * PROGRESS, is left out.
     *
     * @param list<array{code: string, text: string}> $goals
     * @param array<int|string, mixed>                $chosen progress by the goal's position
     * @return list<array{goal: string, code: string, progress: string}>
     */
    public static function progressOf(array $goals, array $chosen): array
    {
        $out = [];
        foreach ($goals as $i => $g) {
            $p = $chosen[$i] ?? '';
            if (is_string($p) && in_array($p, self::PROGRESS, true)) {
                $out[] = ['goal' => $g['text'], 'code' => $g['code'], 'progress' => $p];
            }
        }
        return $out;
    }
}
