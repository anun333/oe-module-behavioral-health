<?php

/**
 * Patients' Part 2 consents, as staff record them from the signed forms.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    anun333 <anun333@posteo.net>
 * @copyright Copyright (c) 2026 anun333 <anun333@posteo.net>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenHealthcare\BehavioralHealth\Repository;

use OpenEMR\Common\Database\QueryUtils;
use OpenHealthcare\BehavioralHealth\Domain\Consent;

final class ConsentRepository
{
    private const COLUMNS = 'c.`id`, c.`pid`, c.`recipient`, c.`recipient_address`, c.`purpose`, c.`information`, c.`signed_on`,
        c.`expires_on`, c.`expiration_event`, c.`ended_on`, c.`document_id`, c.`revoked_on`, c.`note`';

    /** @return list<Consent> newest first */
    public function forPatient(int $pid): array
    {
        $rows = QueryUtils::fetchRecords(
            'SELECT ' . self::COLUMNS . ' FROM `behavioral_health_part2_consent` AS c WHERE c.`pid` = ? ORDER BY c.`signed_on` DESC, c.`id` DESC',
            [$pid],
        );
        return array_map(self::consent(...), $rows);
    }

    public function find(int $id, int $pid): ?Consent
    {
        $rows = QueryUtils::fetchRecords(
            'SELECT ' . self::COLUMNS . ' FROM `behavioral_health_part2_consent` AS c WHERE c.`id` = ? AND c.`pid` = ?',
            [$id, $pid],
        );
        return $rows === [] ? null : self::consent($rows[0]);
    }

    public function add(Consent $c, int $userId): int
    {
        return (int)QueryUtils::sqlInsert(
            'INSERT INTO `behavioral_health_part2_consent` (`pid`, `recipient`, `recipient_address`, `purpose`, `information`, `signed_on`,
                `expires_on`, `expiration_event`, `document_id`, `note`, `created_by`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$c->pid, $c->recipient, $c->recipientAddress, $c->purpose, $c->information, $c->signedOn->format('Y-m-d'),
                $c->expiresOn?->format('Y-m-d'), $c->expirationEvent, $c->documentId, $c->note, $userId],
        );
    }

    /** Records a written revocation, effective on $on. */
    public function revoke(int $id, int $pid, \DateTimeImmutable $on, string $note, int $userId): void
    {
        QueryUtils::sqlStatementThrowException(
            'UPDATE `behavioral_health_part2_consent` SET `revoked_on` = ?, `note` = TRIM(CONCAT(`note`, ?)), `updated_by` = ?
              WHERE `id` = ? AND `pid` = ? AND `revoked_on` IS NULL',
            [$on->format('Y-m-d'), $note === '' ? '' : "\nRevoked: $note", $userId, $id, $pid],
        );
    }

    /** Records the day the consent's expiration event happened. */
    public function end(int $id, int $pid, \DateTimeImmutable $on, int $userId): void
    {
        QueryUtils::sqlStatementThrowException(
            'UPDATE `behavioral_health_part2_consent` SET `ended_on` = ?, `updated_by` = ?
              WHERE `id` = ? AND `pid` = ? AND `ended_on` IS NULL AND `expires_on` IS NULL',
            [$on->format('Y-m-d'), $userId, $id, $pid],
        );
    }

    /**
     * Consents with an end date in the next $days days, not revoked or ended.
     *
     * @return list<array{consent: Consent, patient: string}>
     */
    public function endingSoon(\DateTimeImmutable $today, int $days): array
    {
        $rows = QueryUtils::fetchRecords(
            'SELECT ' . self::COLUMNS . ", CONCAT_WS(', ', p.`lname`, p.`fname`) AS patient_name
               FROM `behavioral_health_part2_consent` AS c JOIN `patient_data` AS p ON p.`pid` = c.`pid`
              WHERE c.`expires_on` BETWEEN ? AND ? AND c.`revoked_on` IS NULL AND c.`ended_on` IS NULL
              ORDER BY c.`expires_on`, c.`id`",
            [$today->format('Y-m-d'), $today->modify("+$days days")->format('Y-m-d')],
        );
        return array_map(static fn(array $r): array => ['consent' => self::consent($r), 'patient' => (string)$r['patient_name']], $rows);
    }

    /** @param array<string, mixed> $r */
    private static function consent(array $r): Consent
    {
        $date = static fn(mixed $v): ?\DateTimeImmutable => $v === null || $v === '' ? null : new \DateTimeImmutable((string)$v);
        return new Consent(
            (int)$r['id'],
            (int)$r['pid'],
            (string)$r['recipient'],
            (string)$r['recipient_address'],
            (string)$r['purpose'],
            (string)$r['information'],
            new \DateTimeImmutable((string)$r['signed_on']),
            $date($r['expires_on']),
            (string)$r['expiration_event'],
            $date($r['ended_on']),
            $r['document_id'] === null ? null : (int)$r['document_id'],
            $date($r['revoked_on']),
            (string)($r['note'] ?? ''),
        );
    }
}
