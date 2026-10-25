<?php

/**
 * The treatment plan card on the patient dashboard: the plan's next review, and its goals from the care plan form
 * the last review read.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    anun333 <anun333@posteo.net>
 * @copyright Copyright (c) 2026 anun333 <anun333@posteo.net>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenHealthcare\BehavioralHealth\Card;

use OpenEMR\Common\Acl\AclMain;
use OpenEMR\Events\Patient\Summary\Card\CardModel;
use OpenHealthcare\BehavioralHealth\Bootstrap;
use OpenHealthcare\BehavioralHealth\Labels;
use OpenHealthcare\BehavioralHealth\Repository\CarePlans;
use OpenHealthcare\BehavioralHealth\Repository\PlanRepository;
use OpenHealthcare\BehavioralHealth\Repository\Settings;

final class PlanCard extends CardModel
{
    public function __construct(int $pid)
    {
        $vars = self::variables($pid);
        parent::__construct([
            'acl' => Bootstrap::PLAN_ACL,
            'initiallyCollapsed' => $vars['state'] === '',
            'add' => false,
            'edit' => false,
            'collapse' => true,
            'templateFile' => '@' . Bootstrap::TWIG_NAMESPACE . '/plan_card.html.twig',
            'identifier' => 'behavioral_health_plan',
            'title' => xl('Treatment Plan'),
            'templateVariables' => $vars,
        ]);
    }

    /** @return array{state: string, overdue: bool, goals: list<string>, pageUrl: string} */
    private static function variables(int $pid): array
    {
        $today = new \DateTimeImmutable('today');
        $settings = new Settings();
        $plan = (new PlanRepository())->active($pid)[0] ?? null;
        $goals = [];
        if ($plan !== null && $plan->latest?->carePlanFormId !== null) {
            foreach ((new CarePlans())->forPatient($pid) as $form) {
                if ($form['formId'] === $plan->latest->carePlanFormId
                    && ($form['sensitivity'] === '' || AclMain::aclCheckCore('sensitivities', $form['sensitivity']))) {
                    $goals = array_column(CarePlans::goals($form), 'text');
                }
            }
        }
        return [
            'state' => $plan === null ? '' : Labels::planState($plan, $today, $settings->aheadDays()),
            'overdue' => $plan !== null && $plan->nextDue() < $today,
            'goals' => $goals,
            'pageUrl' => Bootstrap::url('plan.php', ['pid' => $pid]),
        ];
    }
}
