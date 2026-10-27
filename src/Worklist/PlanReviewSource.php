<?php

/**
 * "Treatment plan reviews" in the Staff Worklist: each plan whose next review is due within the settings' days ahead,
 * or past due. Recording the review moves the due date, which clears the row; the next due date is a new row, so a
 * dismissal (with its reason, which the Worklist logs) covers only the one review. Built on openhealthcare-common's
 * Worklist contract (1.5.0), so it loads without the Worklist's classes.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    anun333 <anun333@posteo.net>
 * @copyright Copyright (c) 2026 anun333 <anun333@posteo.net>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenHealthcare\BehavioralHealth\Worklist;

use OpenEMR\Common\Database\QueryUtils;
use OpenHealthcare\BehavioralHealth\Bootstrap;
use OpenHealthcare\BehavioralHealth\Domain\Plan;
use OpenHealthcare\BehavioralHealth\Labels;
use OpenHealthcare\BehavioralHealth\Repository\PlanRepository;
use OpenHealthcare\BehavioralHealth\Repository\Settings;
use OpenHealthcare\Common\Contract\Worklist\ItemKey;
use OpenHealthcare\Common\Contract\Worklist\PatientRef;
use OpenHealthcare\Common\Contract\Worklist\WorkItem;
use OpenHealthcare\Common\Contract\Worklist\WorkSource;

final class PlanReviewSource implements WorkSource
{
    public const SOURCE = 'bhplan';

    public function __construct(
        private readonly \DateTimeImmutable $now,
        private readonly PlanRepository $plans = new PlanRepository(),
        private readonly Settings $settings = new Settings(),
    ) {
    }

    public function id(): string
    {
        return self::SOURCE;
    }

    public function collect(?int $pid = null): array
    {
        $today = $this->now->setTime(0, 0);
        $ahead = $this->settings->aheadDays();
        $due = array_values(array_filter($this->plans->active($pid), fn(Plan $p): bool => $p->latest !== null
            && in_array($p->state($today, $ahead), [Plan::DUE, Plan::OVERDUE], true)));
        if ($due === []) {
            return [];
        }
        $pids = array_values(array_unique(array_map(fn(Plan $p): int => $p->pid, $due)));
        $patients = [];
        foreach (array_chunk($pids, 500) as $chunk) {
            foreach (QueryUtils::fetchRecords('SELECT pd.pid, ' . PatientRef::SQL_COLUMNS . ' FROM patient_data AS pd WHERE pd.pid IN ('
                . implode(', ', array_fill(0, count($chunk), '?')) . ')', $chunk) as $row) {
                $patients[(int)$row['pid']] = $row;
            }
        }
        // The visit of the care plan each review read: a visit marked with a sensitivity shows only to users allowed it.
        $sensitivity = [];
        $forms = array_values(array_filter(array_map(fn(Plan $p): int => (int)$p->latest?->carePlanFormId, $due)));
        foreach (array_chunk($forms, 500) as $chunk) {
            foreach (QueryUtils::fetchRecords("SELECT f.form_id, COALESCE(fe.sensitivity, '') AS sensitivity FROM forms AS f
                JOIN form_encounter AS fe ON fe.encounter = f.encounter AND fe.pid = f.pid
                WHERE f.formdir = 'care_plan' AND f.form_id IN (" . implode(', ', array_fill(0, count($chunk), '?')) . ')', $chunk) as $row) {
                $sensitivity[(int)$row['form_id']] = (string)$row['sensitivity'];
            }
        }
        $items = [];
        foreach ($due as $plan) {
            if (!isset($patients[$plan->pid]) || $plan->latest === null) {
                continue;
            }
            $overdue = $plan->state($today, $ahead) === Plan::OVERDUE;
            $items[] = new WorkItem(
                // The latest review's id: each due date is its own row.
                key: new ItemKey(self::SOURCE, $plan->pid, (string)$plan->latest->id),
                patient: PatientRef::fromRow($plan->pid, $patients[$plan->pid], $this->now),
                title: Labels::planState($plan, $today, $ahead),
                detail: implode(' · ', [
                    sprintf(xl('Last reviewed %s'), Labels::date($plan->latest->reviewedOn)),
                    sprintf(xl('At least every %d days'), $plan->intervalDays),
                ]),
                priority: $overdue ? WorkItem::PRIORITY_HIGH : WorkItem::PRIORITY_NORMAL,
                since: $plan->nextDue(),
                ownerId: null,
                actionLabel: xl('Open the treatment plan'),
                actionUrl: Bootstrap::MODULE_INSTALLATION_PATH . '/public/plan.php?pid=' . $plan->pid,
                actionMode: 'chart',
                dismissible: true,
                sensitivity: ($sensitivity[(int)$plan->latest->carePlanFormId] ?? '') === '' ? null : $sensitivity[(int)$plan->latest->carePlanFormId],
            );
        }
        return $items;
    }
}
