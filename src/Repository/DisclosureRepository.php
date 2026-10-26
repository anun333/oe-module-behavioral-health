<?php

/**
 * Part 2 disclosures. Each one is written to OpenEMR's own Disclosures record (`extended_log`,
 * through EventAuditLogger::recordDisclosure(), as core's Disclosures page does), so it shows there
 * with the rest of the patient's disclosures, and the module keeps a row beside it linking the
 * consent and what accompanied the records. Reading starts from core's record: a Part 2 disclosure
 * entered on core's page, or edited there, reads as core holds it, and one deleted there is gone.
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
use OpenEMR\Common\Logging\EventAuditLogger;
use OpenHealthcare\BehavioralHealth\Domain\Consent;
use OpenHealthcare\BehavioralHealth\Domain\Disclosure;
use OpenHealthcare\BehavioralHealth\Domain\Notice;

final class DisclosureRepository
{
    /** The disclosure type for these in core's `disclosure_type` list; sql/install.sql adds it. */
    public const EVENT = 'disclosure-part2-consent';

    private const SELECT = 'SELECT el.`id` AS log_id, el.`patient_id`, el.`date`, el.`recipient`, el.`description`, el.`user`,
            d.`id` AS link_id, d.`consent_id`, d.`statement`, d.`accompaniment`, c.`purpose`, c.`recipient_address`
          FROM `extended_log` AS el
          LEFT JOIN `behavioral_health_part2_disclosure` AS d ON d.`extended_log_id` = el.`id`
          LEFT JOIN `behavioral_health_part2_consent` AS c ON c.`id` = d.`consent_id`';

    /**
     * Records a disclosure made under $consent and returns the module's row for it. The text written
     * to core's record names the consent and the statement, so core's Disclosures page shows them.
     */
    public function record(Consent $consent, \DateTimeImmutable $on, string $description, int $statement, string $accompaniment, string $username, int $userId): int
    {
        $text = sprintf(
            '%s (Part 2 consent %d; 42 CFR 2.32 statement %d; with %s)',
            $description,
            $consent->id,
            $statement,
            $accompaniment === Notice::COPY_OF_CONSENT ? 'a copy of the consent' : 'an explanation of its scope',
        );
        $logger = method_exists(EventAuditLogger::class, 'getInstance') ? EventAuditLogger::getInstance() : EventAuditLogger::instance();
        $logger->recordDisclosure($on->format('Y-m-d'), self::EVENT, $consent->pid, $consent->recipient, $text, $username);
        // recordDisclosure() returns nothing, so find the row it wrote.
        $logId = (int)(QueryUtils::fetchRecords(
            'SELECT MAX(`id`) AS id FROM `extended_log` WHERE `patient_id` = ? AND `event` = ? AND `user` = ? AND `recipient` = ? AND `description` = ?',
            [$consent->pid, self::EVENT, $username, $consent->recipient, $text],
        )[0]['id'] ?? 0);
        if ($logId === 0) {
            throw new \RuntimeException('The disclosure was not found in OpenEMR\'s Disclosures record after it was written.');
        }
        return (int)QueryUtils::sqlInsert(
            'INSERT INTO `behavioral_health_part2_disclosure` (`extended_log_id`, `consent_id`, `pid`, `statement`, `accompaniment`, `recorded_by`)
             VALUES (?, ?, ?, ?, ?, ?)',
            [$logId, $consent->id, $consent->pid, $statement, $accompaniment, $userId],
        );
    }

    /**
     * @return list<Disclosure> newest first; from $from when given, and through $through (the whole
     *         day) when given
     */
    public function forPatient(int $pid, ?\DateTimeImmutable $from = null, ?\DateTimeImmutable $through = null): array
    {
        $binds = [$pid, self::EVENT];
        $sql = self::SELECT . ' WHERE el.`patient_id` = ? AND el.`event` = ?';
        if ($from !== null) {
            $sql .= ' AND el.`date` >= ?';
            $binds[] = $from->format('Y-m-d');
        }
        if ($through !== null) {
            $sql .= ' AND el.`date` < ?';
            $binds[] = self::dayAfter($through);
        }
        return array_map(self::disclosure(...), QueryUtils::fetchRecords($sql . ' ORDER BY el.`date` DESC, el.`id` DESC', $binds));
    }

    /**
     * How many of the patient's Part 2 disclosures are dated after $day. Core's Disclosures page
     * accepts a future date, and an accounting that runs to today leaves those out, so the page says
     * how many it left out rather than hiding them.
     */
    public function countAfter(int $pid, \DateTimeImmutable $day): int
    {
        $rows = QueryUtils::fetchRecords(
            'SELECT COUNT(*) AS n FROM `extended_log` AS el WHERE el.`patient_id` = ? AND el.`event` = ? AND el.`date` >= ?',
            [$pid, self::EVENT, self::dayAfter($day)],
        );
        return (int)($rows[0]['n'] ?? 0);
    }

    /** The start of the day after $day, as `extended_log.date` compares it. */
    private static function dayAfter(\DateTimeImmutable $day): string
    {
        return $day->setTime(0, 0)->modify('+1 day')->format('Y-m-d H:i:s');
    }

    /** @return list<Disclosure> every patient's, from $from, newest first */
    public function since(\DateTimeImmutable $from, int $limit = 200): array
    {
        $rows = QueryUtils::fetchRecords(
            str_replace(' FROM `extended_log` AS el', ", CONCAT_WS(', ', p.`lname`, p.`fname`) AS patient_name FROM `extended_log` AS el", self::SELECT)
            . ' JOIN `patient_data` AS p ON p.`pid` = el.`patient_id`
              WHERE el.`event` = ? AND el.`date` >= ? ORDER BY el.`date` DESC, el.`id` DESC LIMIT ' . max(1, $limit),
            [self::EVENT, $from->format('Y-m-d')],
        );
        return array_map(self::disclosure(...), $rows);
    }

    public function find(int $linkId): ?Disclosure
    {
        $rows = QueryUtils::fetchRecords(self::SELECT . ' WHERE d.`id` = ? AND el.`event` = ?', [$linkId, self::EVENT]);
        return $rows === [] ? null : self::disclosure($rows[0]);
    }

    /** @param array<string, mixed> $r */
    private static function disclosure(array $r): Disclosure
    {
        return new Disclosure(
            (int)$r['log_id'],
            (int)$r['patient_id'],
            new \DateTimeImmutable((string)$r['date']),
            (string)($r['recipient'] ?? ''),
            (string)($r['description'] ?? ''),
            (string)($r['user'] ?? ''),
            $r['link_id'] === null ? null : (int)$r['link_id'],
            $r['consent_id'] === null ? null : (int)$r['consent_id'],
            $r['statement'] === null ? null : (int)$r['statement'],
            $r['accompaniment'] === null ? null : (string)$r['accompaniment'],
            (string)($r['purpose'] ?? ''),
            (string)($r['recipient_address'] ?? ''),
            (string)($r['patient_name'] ?? ''),
        );
    }
}
