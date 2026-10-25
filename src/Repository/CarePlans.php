<?php

/**
 * A patient's care plan forms (core's form_care_plan, one form per visit), read only: the treatment plan's goals,
 * interventions and health concerns are written there, with core's own form. Core rewrites a form's rows on each
 * save, so a review keeps each goal as it read rather than a row id.
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

final class CarePlans
{
    /** The kinds of entry a treatment plan shows, in this order; core's Plan_of_Care_Type list has more. */
    public const SHOWN = ['health_concern', 'goal', 'intervention'];

    private static ?bool $hasStatus = null;

    /**
     * The patient's care plan forms not deleted, newest first, each with its entries of the kinds shown.
     *
     * @return list<array{formId: int, encounter: int, date: string, sensitivity: string, entries: list<array{type: string, code: string, text: string, status: string, target: string}>}>
     */
    public function forPatient(int $pid): array
    {
        // The care plan's status and target date came in OpenEMR 7.0.4; before it, there are neither.
        $extra = self::hasStatus() ? ', fcp.`plan_status`, fcp.`proposed_date`' : ", '' AS plan_status, NULL AS proposed_date";
        $rows = QueryUtils::fetchRecords(
            'SELECT f.`form_id`, f.`encounter`, f.`date`, COALESCE(fe.`sensitivity`, \'\') AS sensitivity, fcp.`care_plan_type`, fcp.`code`, fcp.`codetext`,
                    fcp.`description`' . $extra . '
               FROM `forms` AS f
               JOIN `form_care_plan` AS fcp ON fcp.`id` = f.`form_id` AND fcp.`pid` = f.`pid`
               LEFT JOIN `form_encounter` AS fe ON fe.`encounter` = f.`encounter` AND fe.`pid` = f.`pid`
              WHERE f.`formdir` = \'care_plan\' AND f.`deleted` = 0 AND f.`pid` = ? AND COALESCE(fcp.`activity`, 1) = 1
              ORDER BY f.`date` DESC, f.`form_id` DESC',
            [$pid],
        );
        $forms = [];
        foreach ($rows as $r) {
            $id = (int)$r['form_id'];
            $forms[$id] ??= ['formId' => $id, 'encounter' => (int)$r['encounter'], 'date' => substr((string)$r['date'], 0, 10),
                'sensitivity' => (string)$r['sensitivity'], 'entries' => []];
            $type = (string)$r['care_plan_type'];
            if (!in_array($type, self::SHOWN, true)) {
                continue;
            }
            $text = trim((string)$r['description']) !== '' ? trim((string)$r['description']) : trim((string)$r['codetext']);
            $forms[$id]['entries'][] = ['type' => $type, 'code' => trim((string)$r['code']), 'text' => $text,
                'status' => (string)($r['plan_status'] ?? ''), 'target' => substr((string)($r['proposed_date'] ?? ''), 0, 10)];
        }
        foreach ($forms as &$form) {
            usort($form['entries'], static fn(array $a, array $b): int => array_search($a['type'], self::SHOWN, true) <=> array_search($b['type'], self::SHOWN, true));
        }
        unset($form);
        return array_values($forms);
    }

    /**
     * The goals of one form, in its order.
     *
     * @param array{entries: list<array{type: string, code: string, text: string, status: string, target: string}>} $form
     * @return list<array{code: string, text: string}>
     */
    public static function goals(array $form): array
    {
        $goals = [];
        foreach ($form['entries'] as $e) {
            if ($e['type'] === 'goal') {
                $goals[] = ['code' => $e['code'], 'text' => $e['text']];
            }
        }
        return $goals;
    }

    private static function hasStatus(): bool
    {
        return self::$hasStatus ??= QueryUtils::fetchRecords("SHOW COLUMNS FROM `form_care_plan` LIKE 'proposed_date'") !== [];
    }
}
