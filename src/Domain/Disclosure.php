<?php

/**
 * One Part 2 disclosure as OpenEMR's Disclosures record holds it (`extended_log`), with what the
 * module recorded beside it when it was made here: the consent it relied on, the 42 CFR 2.32
 * statement that went with it, and whether a copy of the consent or an explanation of its scope did.
 * A disclosure entered on OpenEMR's own Disclosures page with the Part 2 type has no consent link.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    anun333 <anun333@posteo.net>
 * @copyright Copyright (c) 2026 anun333 <anun333@posteo.net>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenHealthcare\BehavioralHealth\Domain;

final readonly class Disclosure
{
    public function __construct(
        public int $logId,
        public int $pid,
        public \DateTimeImmutable $date,
        public string $recipient,
        public string $description,
        public string $user,
        public ?int $linkId,
        public ?int $consentId,
        public ?int $statement,
        public ?string $accompaniment,
        public string $purpose,
        public string $recipientAddress,
        public string $patientName = '',
    ) {
    }
}
