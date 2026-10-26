<?php

/**
 * A patient's written consent to disclose their Part 2 records (42 CFR 2.31), as staff record it
 * from the signed form: who may receive the records, for what purpose, how much and what kind of
 * information, and when the consent ends. The signed form itself stays in OpenEMR's Documents.
 *
 * A disclosure may rely on a consent only while it is in force. 42 CFR 2.31(c) rules out a consent
 * that has expired or is known to have been revoked; a revocation doesn't undo what was already
 * disclosed in reliance on the consent (2.31(a)(6)), so disclosures dated before it still stand.
 * An expiration event (rather than a date) can't be told from the record, so staff record the day
 * it happened.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    anun333 <anun333@posteo.net>
 * @copyright Copyright (c) 2026 anun333 <anun333@posteo.net>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenHealthcare\BehavioralHealth\Domain;

final readonly class Consent
{
    public const NOT_YET_SIGNED = 'not_yet_signed';
    public const REVOKED = 'revoked';
    public const EXPIRED = 'expired';
    public const ENDED = 'ended';

    /**
     * @param string $recipient        the name or general designation of who may receive the records (2.31(a)(4))
     * @param string $purpose          the purpose of the disclosure (2.31(a)(5))
     * @param string $information      how much and what kind of information may be disclosed (2.31(a)(3))
     * @param string $expirationEvent  the expiration event, when the consent names one instead of a date (2.31(a)(7))
     * @param ?\DateTimeImmutable $endedOn the day staff recorded that the expiration event happened
     * @param ?int   $documentId       the signed form in OpenEMR's Documents, when it was scanned
     */
    public function __construct(
        public int $id,
        public int $pid,
        public string $recipient,
        public string $recipientAddress,
        public string $purpose,
        public string $information,
        public \DateTimeImmutable $signedOn,
        public ?\DateTimeImmutable $expiresOn,
        public string $expirationEvent,
        public ?\DateTimeImmutable $endedOn,
        public ?int $documentId,
        public ?\DateTimeImmutable $revokedOn,
        public string $note,
    ) {
    }

    /** Why a disclosure on $date can't rely on this consent, or null when it can. */
    public function refusal(\DateTimeImmutable $date): ?string
    {
        $date = $date->setTime(0, 0);
        return match (true) {
            $date < $this->signedOn => self::NOT_YET_SIGNED,
            $this->revokedOn !== null && $date >= $this->revokedOn => self::REVOKED,
            $this->endedOn !== null && $date >= $this->endedOn => self::ENDED,
            $this->expiresOn !== null && $date > $this->expiresOn => self::EXPIRED,
            default => null,
        };
    }

    /**
     * Why staff can't record this consent as revoked or ended now, or null when they can: it already
     * is (a second submit, or a page left open), or, for ended, it ends on a date, not an event.
     */
    public function changeRefusal(string $action): ?string
    {
        return match (true) {
            $action === 'revoke' && $this->revokedOn !== null => self::REVOKED,
            $action === 'end' && $this->endedOn !== null => self::ENDED,
            $action === 'end' && $this->expiresOn !== null => self::EXPIRED,
            default => null,
        };
    }

    public function inForceOn(\DateTimeImmutable $date): bool
    {
        return $this->refusal($date) === null;
    }

    /** Each consent's when-it-ends, as the consent says it. */
    public function ends(): string
    {
        return $this->expiresOn !== null ? $this->expiresOn->format('Y-m-d') : $this->expirationEvent;
    }
}
