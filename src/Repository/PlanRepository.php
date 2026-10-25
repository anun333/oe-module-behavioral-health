<?php

/**
 * Treatment plans and their reviews.
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
use OpenHealthcare\BehavioralHealth\Domain\Plan;
use OpenHealthcare\BehavioralHealth\Domain\PlanReview;

final class PlanRepository
{
    private const PLAN = 'p.`id`, p.`pid`, p.`started_on`, p.`interval_days`, p.`ended_on`, p.`end_reason`';
    private const REVIEW = 'r.`id` AS r_id, r.`plan_id` AS r_plan_id, r.`pid` AS r_pid, r.`kind`, r.`reviewed_on`, r.`care_plan_form_id`,
        r.`endorsement`, r.`participants`, r.`progress`, r.`changes`, r.`next_due`, r.`document_id`, r.`reviewed_by`';
    /** Each plan with its latest review. */
    private const FROM = 'FROM `behavioral_health_plan` AS p
        LEFT JOIN `behavioral_health_plan_review` AS r ON r.`id` = (SELECT MAX(r2.`id`) FROM `behavioral_health_plan_review` AS r2 WHERE r2.`plan_id` = p.`id`)';

    /** @return list<Plan> plans not ended, the patient's or everyone's, soonest review first */
    public function active(?int $pid = null): array
    {
        $rows = QueryUtils::fetchRecords(
            'SELECT ' . self::PLAN . ', ' . self::REVIEW . ' ' . self::FROM . ' WHERE p.`ended_on` IS NULL' . ($pid === null ? '' : ' AND p.`pid` = ?')
            . ' ORDER BY r.`next_due`, p.`id`',
            $pid === null ? [] : [$pid],
        );
        return array_map(self::plan(...), $rows);
    }

    /** @return list<Plan> the patient's plans, newest first */
    public function forPatient(int $pid): array
    {
        $rows = QueryUtils::fetchRecords(
            'SELECT ' . self::PLAN . ', ' . self::REVIEW . ' ' . self::FROM . ' WHERE p.`pid` = ? ORDER BY p.`started_on` DESC, p.`id` DESC',
            [$pid],
        );
        return array_map(self::plan(...), $rows);
    }

    public function find(int $id, int $pid): ?Plan
    {
        $rows = QueryUtils::fetchRecords('SELECT ' . self::PLAN . ', ' . self::REVIEW . ' ' . self::FROM . ' WHERE p.`id` = ? AND p.`pid` = ?', [$id, $pid]);
        return $rows === [] ? null : self::plan($rows[0]);
    }

    /** @return list<PlanReview> newest first */
    public function reviews(int $planId, int $pid): array
    {
        $rows = QueryUtils::fetchRecords(
            'SELECT ' . self::REVIEW . ' FROM `behavioral_health_plan_review` AS r WHERE r.`plan_id` = ? AND r.`pid` = ? ORDER BY r.`reviewed_on` DESC, r.`id` DESC',
            [$planId, $pid],
        );
        return array_map(self::review(...), $rows);
    }

    /** Starts a plan with its first review (kind initial); returns the plan's id. */
    public function start(int $pid, int $intervalDays, PlanReview $initial, int $userId): int
    {
        $id = (int)QueryUtils::sqlInsert(
            'INSERT INTO `behavioral_health_plan` (`pid`, `started_on`, `interval_days`, `created_by`) VALUES (?, ?, ?, ?)',
            [$pid, $initial->reviewedOn->format('Y-m-d'), $intervalDays, $userId],
        );
        try {
            $this->addReview(new PlanReview(0, $id, $pid, PlanReview::INITIAL, $initial->reviewedOn, $initial->carePlanFormId, $initial->endorsement,
                $initial->participants, $initial->progress, $initial->changes, $initial->nextDue, $initial->documentId), $userId);
        } catch (\Throwable $e) {
            QueryUtils::sqlStatementThrowException('DELETE FROM `behavioral_health_plan` WHERE `id` = ?', [$id]);
            throw $e;
        }
        return $id;
    }

    public function addReview(PlanReview $r, int $userId): int
    {
        return (int)QueryUtils::sqlInsert(
            'INSERT INTO `behavioral_health_plan_review` (`plan_id`, `pid`, `kind`, `reviewed_on`, `care_plan_form_id`, `endorsement`, `participants`,
                `progress`, `changes`, `next_due`, `document_id`, `reviewed_by`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$r->planId, $r->pid, $r->kind, $r->reviewedOn->format('Y-m-d'), $r->carePlanFormId, $r->endorsement, $r->participants,
                json_encode($r->progress, JSON_UNESCAPED_UNICODE), $r->changes, $r->nextDue->format('Y-m-d'), $r->documentId, $userId],
        );
    }

    public function end(int $id, int $pid, \DateTimeImmutable $on, string $reason, int $userId): void
    {
        QueryUtils::sqlStatementThrowException(
            'UPDATE `behavioral_health_plan` SET `ended_on` = ?, `end_reason` = ?, `updated_by` = ? WHERE `id` = ? AND `pid` = ? AND `ended_on` IS NULL',
            [$on->format('Y-m-d'), $reason, $userId, $id, $pid],
        );
    }

    /** @param array<string, mixed> $row */
    private static function plan(array $row): Plan
    {
        return new Plan(
            (int)$row['id'],
            (int)$row['pid'],
            new \DateTimeImmutable((string)$row['started_on']),
            (int)$row['interval_days'],
            ($row['ended_on'] ?? null) === null ? null : new \DateTimeImmutable((string)$row['ended_on']),
            (string)$row['end_reason'],
            ($row['r_id'] ?? null) === null ? null : self::review($row),
        );
    }

    /** @param array<string, mixed> $row */
    private static function review(array $row): PlanReview
    {
        $progress = json_decode((string)($row['progress'] ?? ''), true);
        $goals = [];
        foreach (is_array($progress) ? $progress : [] as $p) {
            if (is_array($p) && is_string($p['goal'] ?? null) && is_string($p['progress'] ?? null)) {
                $goals[] = ['goal' => $p['goal'], 'code' => is_string($p['code'] ?? null) ? $p['code'] : '', 'progress' => $p['progress']];
            }
        }
        return new PlanReview(
            (int)$row['r_id'],
            (int)$row['r_plan_id'],
            (int)$row['r_pid'],
            (string)$row['kind'],
            new \DateTimeImmutable((string)$row['reviewed_on']),
            ($row['care_plan_form_id'] ?? null) === null ? null : (int)$row['care_plan_form_id'],
            (string)$row['endorsement'],
            (string)($row['participants'] ?? ''),
            $goals,
            (string)($row['changes'] ?? ''),
            new \DateTimeImmutable((string)$row['next_due']),
            ($row['document_id'] ?? null) === null ? null : (int)$row['document_id'],
            (int)$row['reviewed_by'],
        );
    }
}
