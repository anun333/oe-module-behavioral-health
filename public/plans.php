<?php

/**
 * Treatment plans in progress across patients, soonest review first, with those overdue and due counted.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    anun333 <anun333@posteo.net>
 * @copyright Copyright (c) 2026 anun333 <anun333@posteo.net>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../../globals.php';

use OpenEMR\Common\Acl\AclMain;
use OpenEMR\Common\Database\QueryUtils;
use OpenEMR\Core\Header;
use OpenHealthcare\BehavioralHealth\Bootstrap;
use OpenHealthcare\BehavioralHealth\Domain\Plan;
use OpenHealthcare\BehavioralHealth\Guide;
use OpenHealthcare\BehavioralHealth\Labels;
use OpenHealthcare\BehavioralHealth\Repository\PlanRepository;
use OpenHealthcare\BehavioralHealth\Repository\Settings;
use OpenHealthcare\Common\OpenEmr;

// The same access as Bootstrap::PLAN_ACL, written out: tools/footprint.php lists each check it can read.
if (!AclMain::aclCheckCore('patients', 'med')) {
    OpenEmr::deny('ACL check failed for patients/med: Behavioral Health treatment plans', xl('Treatment Plans'), 'Behavioral Health');
}
$today = new DateTimeImmutable('today');
$ahead = (new Settings())->aheadDays();
$plans = (new PlanRepository())->active();
$show = (string)($_GET['show'] ?? '');
$counts = [Plan::OVERDUE => 0, Plan::DUE => 0, Plan::NOT_DUE => 0];
foreach ($plans as $p) {
    $counts[$p->state($today, $ahead)]++;
}
if (isset($counts[$show])) {
    $plans = array_values(array_filter($plans, fn(Plan $p): bool => $p->state($today, $ahead) === $show));
}
usort($plans, fn(Plan $a, Plan $b): int => $a->nextDue() <=> $b->nextDue());
$names = [];
$pids = array_values(array_unique(array_map(fn(Plan $p): int => $p->pid, $plans)));
foreach (array_chunk($pids, 500) as $chunk) {
    foreach (QueryUtils::fetchRecords('SELECT `pid`, `fname`, `lname`, `pubpid` FROM `patient_data` WHERE `pid` IN (' . implode(', ', array_fill(0, count($chunk), '?')) . ')', $chunk) as $r) {
        $names[(int)$r['pid']] = trim($r['lname'] . ', ' . $r['fname'], ', ') . ' (' . $r['pubpid'] . ')';
    }
}
OpenEmr::audit('other', 'Behavioral Health: treatment plans list viewed');
?>
<!DOCTYPE html>
<?php // The module's own text is written in English. ?>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title><?php echo xlt('Treatment Plans'); ?></title>
    <?php Header::setupHeader(['common']); ?>
    <?php echo \OpenHealthcare\BehavioralHealth\Contrast::style(); ?>
</head>
<body class="body_top">
<main class="container-fluid py-3" style="max-width: 80rem">
    <h1 class="h4"><?php echo xlt('Treatment Plans'); ?></h1>
    <p class="small mb-2"><a href="<?php echo attr(Guide::url()); ?>" target="_blank" rel="noopener"><?php echo xlt('Help'); ?><span class="sr-only"> <?php echo xlt('(opens in a new window)'); ?></span></a></p>
    <p><?php echo xlt('Plans in progress, soonest review first. A plan is written in the Care Plan form of a visit; its reviews are recorded on the patient treatment plan page.'); ?>
        <?php if (AclMain::aclCheckCore('admin', 'super')) { ?><a href="settings.php" onclick="top.restoreSession()"><?php echo xlt('Settings'); ?></a><?php } ?></p>
    <nav class="mb-3">
        <a href="plans.php" class="btn btn-sm <?php echo $show === '' ? 'btn-secondary' : 'btn-outline-secondary'; ?>"><?php echo xlt('All'); ?></a>
        <a href="plans.php?show=overdue" class="btn btn-sm <?php echo $show === Plan::OVERDUE ? 'btn-danger' : 'btn-outline-danger'; ?>"><?php echo text(sprintf(xl('Overdue: %d'), $counts[Plan::OVERDUE])); ?></a>
        <a href="plans.php?show=due" class="btn btn-sm <?php echo $show === Plan::DUE ? 'btn-warning' : 'btn-outline-warning'; ?>"><?php echo text(sprintf(xl('Due in the next %1$d days: %2$d'), $ahead, $counts[Plan::DUE])); ?></a>
        <a href="plans.php?show=not_due" class="btn btn-sm <?php echo $show === Plan::NOT_DUE ? 'btn-secondary' : 'btn-outline-secondary'; ?>"><?php echo text(sprintf(xl('Later: %d'), $counts[Plan::NOT_DUE])); ?></a>
    </nav>
    <?php if ($plans === []) { ?>
        <p class="text-muted"><?php echo xlt('No treatment plan here.'); ?></p>
    <?php } else { ?>
    <div class="table-responsive">
    <table class="table table-sm table-striped">
        <thead><tr>
            <th scope="col"><?php echo xlt('Patient'); ?></th>
            <th scope="col"><?php echo xlt('Started'); ?></th>
            <th scope="col"><?php echo xlt('Last reviewed'); ?></th>
            <th scope="col"><?php echo xlt('Next review'); ?></th>
            <th scope="col"><?php echo xlt('At least every'); ?></th>
        </tr></thead>
        <tbody>
        <?php foreach ($plans as $p) { ?>
            <tr>
                <td><a href="<?php echo attr(Bootstrap::url('plan.php', ['pid' => $p->pid])); ?>" onclick="top.restoreSession()"><?php echo text($names[$p->pid] ?? (string)$p->pid); ?></a></td>
                <td><?php echo text(Labels::date($p->startedOn)); ?></td>
                <td><?php echo text(Labels::date($p->latest?->reviewedOn)); ?></td>
                <td class="<?php echo $p->state($today, $ahead) === Plan::OVERDUE ? 'text-danger' : ''; ?>"><?php echo text(Labels::planState($p, $today, $ahead)); ?></td>
                <td><?php echo text(sprintf(xl('%d days'), $p->intervalDays)); ?></td>
            </tr>
        <?php } ?>
        </tbody>
    </table>
    </div>
    <?php } ?>
</main>
</body>
</html>
