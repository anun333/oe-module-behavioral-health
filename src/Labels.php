<?php

/**
 * The words the pages show for the module's values.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    anun333 <anun333@posteo.net>
 * @copyright Copyright (c) 2026 anun333 <anun333@posteo.net>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenHealthcare\BehavioralHealth;

use OpenHealthcare\BehavioralHealth\Domain\Consent;
use OpenHealthcare\BehavioralHealth\Domain\Notice;
use OpenHealthcare\BehavioralHealth\Domain\Plan;
use OpenHealthcare\BehavioralHealth\Domain\PlanReview;

final class Labels
{
    /** Why a consent can't be relied on, as a status. */
    public static function refusal(string $why): string
    {
        return match ($why) {
            Consent::NOT_YET_SIGNED => xl('Not yet signed on that date'),
            Consent::REVOKED => xl('Revoked'),
            Consent::ENDED => xl('Ended: its expiration event happened'),
            Consent::EXPIRED => xl('Expired'),
            default => $why,
        };
    }

    public static function status(Consent $c, \DateTimeImmutable $today): string
    {
        $why = $c->refusal($today);
        return $why === null ? xl('In force') : self::refusal($why);
    }

    public static function statement(?int $statement): string
    {
        return match ($statement) {
            Notice::STATEMENT_1 => xl('Statement 1, the full notice'),
            Notice::STATEMENT_2 => xl('Statement 2, the short notice'),
            default => '',
        };
    }

    public static function accompaniment(?string $what): string
    {
        return match ($what) {
            Notice::COPY_OF_CONSENT => xl('A copy of the consent'),
            Notice::SCOPE_EXPLAINED => xl('An explanation of the consent and its scope'),
            default => '',
        };
    }

    public static function kind(string $kind): string
    {
        return match ($kind) {
            PlanReview::INITIAL => xl('First plan'),
            PlanReview::REVIEW => xl('Review'),
            PlanReview::UPDATE => xl('Update after a change'),
            default => $kind,
        };
    }

    public static function endorsement(string $endorsement): string
    {
        return match ($endorsement) {
            PlanReview::SIGNED => xl('Signed by the person'),
            PlanReview::VERBAL => xl('Agreed by the person, and noted'),
            PlanReview::DECLINED => xl('The person did not agree'),
            PlanReview::UNABLE => xl('The person could not take part'),
            default => $endorsement,
        };
    }

    public static function progress(string $progress): string
    {
        return match ($progress) {
            PlanReview::MET => xl('Met'),
            PlanReview::PARTLY => xl('Partly met'),
            PlanReview::NOT_MET => xl('Not met'),
            PlanReview::CHANGED => xl('Changed'),
            default => $progress,
        };
    }

    public static function entryType(string $type): string
    {
        return match ($type) {
            'health_concern' => xl('Health concern'),
            'goal' => xl('Goal'),
            'intervention' => xl('Intervention'),
            default => $type,
        };
    }

    /** Where a plan stands on $today, as a short status. */
    public static function planState(Plan $plan, \DateTimeImmutable $today, int $aheadDays): string
    {
        return match ($plan->state($today, $aheadDays)) {
            Plan::ENDED => xl('Ended') . ' ' . self::date($plan->endedOn),
            Plan::OVERDUE => sprintf(xl('Review overdue since %s'), self::date($plan->nextDue())),
            Plan::DUE => sprintf(xl('Review due %s'), self::date($plan->nextDue())),
            default => sprintf(xl('Next review %s'), self::date($plan->nextDue())),
        };
    }

    public static function date(?\DateTimeImmutable $d): string
    {
        return $d === null ? '' : oeFormatShortDate($d->format('Y-m-d'));
    }
}
