<?php

/**
 * The period an accounting of disclosures covers: the disclosures made with consent in the 3 years
 * before the date of the request, or a shorter period the patient chooses (42 CFR 2.25(a)). Each
 * entry carries what 45 CFR 164.528(b)(2) asks for: the date, the recipient and their address if
 * known, a brief description of what was disclosed, and the purpose.
 *
 * HHS tolled the compliance date for all of 2.25 "until the HIPAA Accounting of Disclosures provision
 * at 45 CFR 164.528 is revised" (89 FR 12476), and eCFR shows 164.528 unchanged since 2016, so as of
 * 2026-10-21 the accounting is offered, not yet required. Disclosures for treatment, payment and
 * health care operations made through an EHR (2.25(b)) aren't covered.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    anun333 <anun333@posteo.net>
 * @copyright Copyright (c) 2026 anun333 <anun333@posteo.net>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenHealthcare\BehavioralHealth\Domain;

final class Accounting
{
    public const YEARS = 3;

    /** The first day the accounting covers. A chosen start before the 3 years, or after the request, is ignored. */
    public static function from(\DateTimeImmutable $requested, ?\DateTimeImmutable $chosen): \DateTimeImmutable
    {
        $requested = $requested->setTime(0, 0);
        $earliest = $requested->modify('-' . self::YEARS . ' years');
        if ($chosen === null) {
            return $earliest;
        }
        $chosen = $chosen->setTime(0, 0);
        return $chosen > $earliest && $chosen <= $requested ? $chosen : $earliest;
    }
}
