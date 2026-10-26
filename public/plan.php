<?php

/**
 * A patient's treatment plan: the goals, interventions and health concerns of their care plan forms (written with
 * core's Care Plan form), the plan's review schedule, and each making and review of it, with the person's
 * endorsement, who took part, each goal's progress and the signed plan in Documents.
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
use OpenHealthcare\BehavioralHealth\Domain\PlanReview;
use OpenHealthcare\BehavioralHealth\Guide;
use OpenHealthcare\BehavioralHealth\Labels;
use OpenHealthcare\BehavioralHealth\Repository\CarePlans;
use OpenHealthcare\BehavioralHealth\Repository\PlanRepository;
use OpenHealthcare\BehavioralHealth\Repository\Settings;
use OpenHealthcare\Common\OpenEmr;

// The same access as Bootstrap::PLAN_ACL, written out: patients/med to see; write or addonly to record; write to end.
if (!AclMain::aclCheckCore('patients', 'med')) {
    OpenEmr::deny('ACL check failed for patients/med: Behavioral Health treatment plan', xl('Treatment Plan'), 'Behavioral Health');
}
$canRecord = AclMain::aclCheckCore('patients', 'med', '', 'write') || AclMain::aclCheckCore('patients', 'med', '', 'addonly');
$canEnd = AclMain::aclCheckCore('patients', 'med', '', 'write');

$pid = (int)($_GET['pid'] ?? OpenEmr::session('pid') ?? 0);
$patient = QueryUtils::fetchRecords('SELECT `pid`, `fname`, `lname`, `DOB`, `pubpid` FROM `patient_data` WHERE `pid` = ?', [$pid])[0] ?? null;
if ($patient === null) {
    Bootstrap::stop(xl('Treatment Plan'), xl('No such patient.'));
}
$repo = new PlanRepository();
$settings = new Settings();
$today = new DateTimeImmutable('today');
$userId = (int)OpenEmr::session('authUserID');
$errors = [];
$done = '';

// The care plan forms this user may see: a visit marked with a sensitivity shows only to users core allows it.
$all = (new CarePlans())->forPatient($pid);
$forms = [];
foreach ($all as $f) {
    if ($f['sensitivity'] === '' || AclMain::aclCheckCore('sensitivities', $f['sensitivity'])) {
        $forms[$f['formId']] = $f;
    }
}
$hidden = count($all) - count($forms);
$documents = [];
foreach (QueryUtils::fetchRecords('SELECT `id`, `name`, `date` FROM `documents` WHERE `foreign_id` = ? AND `deleted` = 0 ORDER BY `date` DESC, `id` DESC LIMIT 200', [$pid]) as $r) {
    $documents[(int)$r['id']] = trim((string)$r['name']) . ' (' . oeFormatShortDate(substr((string)$r['date'], 0, 10)) . ')';
}

$date = static function (string $field): ?DateTimeImmutable {
    $d = DateTimeImmutable::createFromFormat('!Y-m-d', trim((string)($_POST[$field] ?? '')));
    return $d === false ? null : $d;
};
$text = static fn(string $field): string => trim((string)($_POST[$field] ?? ''));

$active = $repo->active($pid)[0] ?? null;
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    OpenEmr::requireCsrf($_POST['csrf_token_form'] ?? '');
    $action = (string)($_POST['action'] ?? '');
    if (($action === 'start' || $action === 'review') && $canRecord) {
        $on = $date('reviewed_on');
        $formId = (int)($_POST['care_plan_form_id'] ?? 0);
        $endorsement = (string)($_POST['endorsement'] ?? '');
        $kind = $action === 'start' ? PlanReview::INITIAL : (string)($_POST['kind'] ?? '');
        $doc = (int)($_POST['document_id'] ?? 0);
        if ($action === 'start' && $active !== null) {
            $errors[] = xl('This patient already has a treatment plan in progress. Record a review of it, or end it first.');
        } elseif ($action === 'review' && $active === null) {
            $errors[] = xl('This patient has no treatment plan in progress.');
        }
        if (!isset($forms[$formId])) {
            $errors[] = xl('Choose the care plan form the plan is written in.');
        } elseif (CarePlans::goals($forms[$formId]) === []) {
            $errors[] = xl('That care plan form has no goals. Add the goals in the Care Plan form of the visit first.');
        }
        if ($on === null || $on > $today) {
            $errors[] = xl('The date must be today or earlier.');
        } elseif ($active !== null && $active->latest !== null && $on < $active->latest->reviewedOn) {
            $errors[] = xl('A review can not be dated before the last one.');
        }
        if (!in_array($endorsement, PlanReview::ENDORSEMENTS, true) || !in_array($kind, PlanReview::KINDS, true)) {
            $errors[] = xl('Choose whether the person endorsed the plan.');
        }
        if ($text('participants') === '') {
            $errors[] = xl('Enter who took part: the person, and family, caregivers or a guardian as the person wishes, and the team.');
        }
        if ($doc > 0 && !isset($documents[$doc])) {
            $errors[] = xl('Choose a document of this patient, or none.');
        }
        if ($errors === [] && $on !== null) {
            $goals = CarePlans::goals($forms[$formId]);
            $interval = $active?->intervalDays ?? $settings->intervalDays();
            $progress = $action === 'start'
                ? array_map(fn(array $g): array => ['goal' => $g['text'], 'code' => $g['code'], 'progress' => ''], $goals)
                : PlanReview::progressOf($goals, (array)($_POST['progress'] ?? []));
            $asked = $date('next_due');
            $review = new PlanReview(0, $active->id ?? 0, $pid, $kind, $on, $formId, $endorsement, $text('participants'), $progress,
                $text('changes'), PlanReview::nextDue($on, $interval, $asked), $doc > 0 ? $doc : null);
            if ($action === 'start') {
                $planId = $repo->start($pid, $interval, $review, $userId);
                OpenEmr::audit('patient-record-insert', "Behavioral Health: treatment plan $planId started", $pid);
                $done = xl('The treatment plan is recorded.');
            } else {
                $id = $repo->addReview($review, $userId);
                OpenEmr::audit('patient-record-insert', "Behavioral Health: treatment plan {$active->id} review $id recorded", $pid);
                $done = xl('The review is recorded.');
            }
            if ($asked !== null && $asked->format('Y-m-d') !== $review->nextDue->format('Y-m-d')) {
                $done .= ' ' . sprintf($review->nextDue > $asked
                    ? xl('The next review is set to %s, the earliest it can be, the day after this review.')
                    : xl('The next review is set to %s, the latest the plan interval allows.'), Labels::date($review->nextDue));
            }
            $active = $repo->active($pid)[0] ?? null;
        }
    } elseif ($action === 'end' && $canEnd && $active !== null) {
        $on = $date('ended_on');
        if ($on === null || $on > $today || $on < $active->startedOn) {
            $errors[] = xl('Enter a date between the day the plan started and today.');
        } elseif ($text('end_reason') === '') {
            $errors[] = xl('Enter why the plan ended, such as discharge or transfer.');
        } else {
            $repo->end($active->id, $pid, $on, $text('end_reason'), $userId);
            OpenEmr::audit('patient-record-update', "Behavioral Health: treatment plan {$active->id} ended", $pid);
            $done = xl('The plan is recorded as ended.');
            $active = null;
        }
    }
}
OpenEmr::audit('patient-record-select', 'Behavioral Health: treatment plan viewed', $pid);

$plans = $repo->forPatient($pid);
$ahead = $settings->aheadDays();
// The care plan the review form reads: the one asked for, or the newest.
$chosen = (int)($_GET['form'] ?? 0);
$reviewForm = $forms[$chosen] ?? (array_values($forms)[0] ?? null);
$csrf = OpenEmr::csrfToken();
$webRoot = OpenEmr::webRoot();
$docLink = static fn(int $id): string => $webRoot . '/controller.php?document&retrieve&patient_id=' . $pid . '&document_id=' . $id . '&as_file=false';
?>
<!DOCTYPE html>
<?php // The module's own text is written in English. ?>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title><?php echo xlt('Treatment Plan'); ?></title>
    <?php Header::setupHeader(['common']); ?>
    <?php echo \OpenHealthcare\BehavioralHealth\Contrast::style(); ?>
</head>
<body class="body_top">
<main class="container-fluid py-3" style="max-width: 80rem">
    <h1 class="h4"><?php echo xlt('Treatment Plan'); ?></h1>
    <p class="small mb-2"><a href="<?php echo attr(Guide::url()); ?>" target="_blank" rel="noopener"><?php echo xlt('Help'); ?><span class="sr-only"> <?php echo xlt('(opens in a new window)'); ?></span></a></p>
    <p class="mb-2">
        <strong><?php echo text(trim($patient['fname'] . ' ' . $patient['lname'])); ?></strong>
        · <?php echo xlt('DOB'); ?> <?php echo text(oeFormatShortDate((string)$patient['DOB'])); ?>
        · <?php echo xlt('ID'); ?> <?php echo text((string)$patient['pubpid']); ?>
    </p>
    <p class="small text-muted"><?php echo xlt('Write the goals, interventions and health concerns in the Care Plan form of a visit, then record the plan or its review here. The paper plan for the person to sign is the Behavioral Health Treatment Plan in Clinic Forms; scan the signed copy into Documents and choose it below.'); ?></p>

    <?php foreach ($errors as $e) { ?>
        <div class="alert alert-danger py-2"><?php echo text($e); ?></div>
    <?php } ?>
    <?php if ($done !== '') { ?>
        <div class="alert alert-success py-2"><?php echo text($done); ?></div>
    <?php } ?>

    <?php if ($active !== null) { ?>
        <div class="border rounded p-3 mb-3">
            <h2 class="h5"><?php echo xlt('In progress'); ?></h2>
            <p class="mb-1 <?php echo $active->nextDue() < $today ? 'text-danger' : ''; ?>"><strong><?php echo text(Labels::planState($active, $today, $ahead)); ?></strong></p>
            <p class="mb-0 small"><?php echo text(sprintf(xl('Started %1$s · reviewed at least every %2$d days'), Labels::date($active->startedOn), $active->intervalDays)); ?></p>
        </div>
    <?php } ?>

    <h2 class="h5 mt-4"><?php echo xlt('Care plans'); ?></h2>
    <?php if ($hidden > 0) { ?>
        <p class="small text-muted"><?php echo text(sprintf(xl('%d care plan forms are in visits you are not allowed to see.'), $hidden)); ?></p>
    <?php } ?>
    <?php if ($forms === []) { ?>
        <p class="text-muted"><?php echo xlt('No care plan form for this patient. Add one to a visit, with the goals of the plan.'); ?></p>
    <?php } ?>
    <?php foreach (array_slice($forms, 0, 3, true) as $f) { ?>
        <h3 class="h6 mt-3"><?php echo text(sprintf(xl('Care plan of %s'), oeFormatShortDate($f['date']))); ?></h3>
        <div class="table-responsive">
        <table class="table table-sm">
            <thead><tr><th scope="col"><?php echo xlt('Kind'); ?></th><th scope="col"><?php echo xlt('Entry'); ?></th><th scope="col"><?php echo xlt('Code'); ?></th>
                <th scope="col"><?php echo xlt('Status'); ?></th><th scope="col"><?php echo xlt('Target date'); ?></th></tr></thead>
            <tbody>
            <?php foreach ($f['entries'] as $e) { ?>
                <tr><td><?php echo text(Labels::entryType($e['type'])); ?></td><td><?php echo text($e['text']); ?></td><td><?php echo text($e['code']); ?></td>
                    <td><?php echo text($e['status']); ?></td><td><?php echo text($e['target'] === '' ? '' : oeFormatShortDate($e['target'])); ?></td></tr>
            <?php } ?>
            </tbody>
        </table>
        </div>
    <?php } ?>

    <?php if ($canRecord && $reviewForm !== null) { ?>
    <h2 class="h5 mt-4"><?php echo $active === null ? xlt('Record the plan') : xlt('Record a review'); ?></h2>
    <?php if (count($forms) > 1) { ?>
    <form method="get" class="form-inline mb-2">
        <input type="hidden" name="pid" value="<?php echo attr((string)$pid); ?>">
        <label class="mr-2" for="form"><?php echo xlt('Care plan it reads'); ?></label>
        <select class="form-control form-control-sm mr-2" id="form" name="form">
            <?php foreach ($forms as $f) { ?>
                <option value="<?php echo attr((string)$f['formId']); ?>"<?php echo $f['formId'] === $reviewForm['formId'] ? ' selected' : ''; ?>><?php echo text(oeFormatShortDate($f['date'])); ?></option>
            <?php } ?>
        </select>
        <button type="submit" class="btn btn-sm btn-outline-secondary"><?php echo xlt('Show'); ?></button>
    </form>
    <?php } ?>
    <form method="post" class="border rounded p-3 mb-4">
        <input type="hidden" name="csrf_token_form" value="<?php echo attr($csrf); ?>">
        <input type="hidden" name="action" value="<?php echo $active === null ? 'start' : 'review'; ?>">
        <input type="hidden" name="care_plan_form_id" value="<?php echo attr((string)$reviewForm['formId']); ?>">
        <p class="small"><?php echo text(sprintf(xl('Reads the care plan of %s.'), oeFormatShortDate($reviewForm['date']))); ?></p>
        <div class="form-row">
            <div class="form-group col-md-3">
                <label for="reviewed_on"><?php echo $active === null ? xlt('Date the plan was made') : xlt('Date of the review'); ?></label>
                <input type="date" class="form-control" id="reviewed_on" name="reviewed_on" value="<?php echo attr($today->format('Y-m-d')); ?>" max="<?php echo attr($today->format('Y-m-d')); ?>" required>
            </div>
            <?php if ($active !== null) { ?>
            <div class="form-group col-md-4">
                <label for="kind"><?php echo xlt('Kind of review'); ?></label>
                <select class="form-control" id="kind" name="kind">
                    <option value="<?php echo attr(PlanReview::REVIEW); ?>"><?php echo text(Labels::kind(PlanReview::REVIEW)); ?></option>
                    <option value="<?php echo attr(PlanReview::UPDATE); ?>"><?php echo text(Labels::kind(PlanReview::UPDATE)); ?></option>
                </select>
            </div>
            <?php } ?>
            <div class="form-group col-md-3">
                <label for="next_due"><?php echo xlt('Next review by'); ?></label>
                <input type="date" class="form-control" id="next_due" name="next_due">
                <small class="form-text text-muted"><?php echo text(sprintf(xl('Blank: %d days on, the latest allowed.'), $active?->intervalDays ?? $settings->intervalDays())); ?></small>
            </div>
        </div>
        <?php if ($active !== null) { ?>
        <fieldset class="form-group">
            <legend class="col-form-label pt-0"><?php echo xlt('Progress toward each goal'); ?></legend>
            <?php foreach (CarePlans::goals($reviewForm) as $i => $g) { ?>
                <div class="form-row align-items-center mb-1">
                    <div class="col-md-7"><label for="progress-<?php echo attr((string)$i); ?>" class="mb-0"><?php echo text($g['text']); ?></label></div>
                    <div class="col-md-4">
                        <select class="form-control form-control-sm" id="progress-<?php echo attr((string)$i); ?>" name="progress[<?php echo attr((string)$i); ?>]">
                            <option value=""></option>
                            <?php foreach (PlanReview::PROGRESS as $p) { ?>
                                <option value="<?php echo attr($p); ?>"><?php echo text(Labels::progress($p)); ?></option>
                            <?php } ?>
                        </select>
                    </div>
                </div>
            <?php } ?>
        </fieldset>
        <?php } ?>
        <fieldset class="form-group">
            <legend class="col-form-label pt-0"><?php echo xlt('Endorsed by the person'); ?></legend>
            <?php foreach (PlanReview::ENDORSEMENTS as $en) { ?>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="endorsement" id="en-<?php echo attr($en); ?>" value="<?php echo attr($en); ?>"<?php echo $en === PlanReview::SIGNED ? ' checked' : ''; ?>>
                    <label class="form-check-label" for="en-<?php echo attr($en); ?>"><?php echo text(Labels::endorsement($en)); ?></label>
                </div>
            <?php } ?>
        </fieldset>
        <div class="form-group">
            <label for="participants"><?php echo xlt('Who took part (the person, family or guardian as they wish, the team)'); ?></label>
            <textarea class="form-control" id="participants" name="participants" rows="2" required></textarea>
        </div>
        <div class="form-group">
            <label for="changes"><?php echo $active === null ? xlt('Notes') : xlt('What changes in the plan'); ?></label>
            <textarea class="form-control" id="changes" name="changes" rows="2"></textarea>
        </div>
        <div class="form-group">
            <label for="document_id"><?php echo xlt('Signed plan in Documents'); ?></label>
            <select class="form-control" id="document_id" name="document_id">
                <option value="0"><?php echo xlt('None yet'); ?></option>
                <?php foreach ($documents as $id => $name) { ?>
                    <option value="<?php echo attr((string)$id); ?>"><?php echo text($name); ?></option>
                <?php } ?>
            </select>
        </div>
        <button type="submit" class="btn btn-primary"><?php echo $active === null ? xlt('Record the plan') : xlt('Record the review'); ?></button>
    </form>
    <?php } ?>

    <?php foreach ($plans as $plan) { ?>
        <h2 class="h5 mt-4"><?php echo text(sprintf(xl('Plan started %s'), Labels::date($plan->startedOn))); ?>
            <?php if ($plan->endedOn !== null) { ?><small class="text-muted"> · <?php echo text(sprintf(xl('ended %1$s: %2$s'), Labels::date($plan->endedOn), $plan->endReason)); ?></small><?php } ?></h2>
        <div class="table-responsive">
        <table class="table table-sm table-striped">
            <thead><tr>
                <th scope="col"><?php echo xlt('Date'); ?></th>
                <th scope="col"><?php echo xlt('Kind'); ?></th>
                <th scope="col"><?php echo xlt('Endorsement'); ?></th>
                <th scope="col"><?php echo xlt('Who took part'); ?></th>
                <th scope="col"><?php echo xlt('Goals'); ?></th>
                <th scope="col"><?php echo xlt('Changes or notes'); ?></th>
                <th scope="col"><?php echo xlt('Next review by'); ?></th>
                <th scope="col"><?php echo xlt('Signed plan'); ?></th>
            </tr></thead>
            <tbody>
            <?php foreach ($repo->reviews($plan->id, $pid) as $r) { ?>
                <tr>
                    <td><?php echo text(Labels::date($r->reviewedOn)); ?></td>
                    <td><?php echo text(Labels::kind($r->kind)); ?></td>
                    <td><?php echo text(Labels::endorsement($r->endorsement)); ?></td>
                    <td><?php echo text($r->participants); ?></td>
                    <td><ul class="mb-0 pl-3 small">
                        <?php foreach ($r->progress as $p) { ?>
                            <li><?php echo text($p['goal'] . ($p['progress'] === '' ? '' : ': ' . Labels::progress($p['progress']))); ?></li>
                        <?php } ?>
                    </ul></td>
                    <td><?php echo text($r->changes); ?></td>
                    <td><?php echo text(Labels::date($r->nextDue)); ?></td>
                    <td><?php if ($r->documentId !== null) { ?><a href="<?php echo attr($docLink($r->documentId)); ?>" target="_blank" rel="noopener" onclick="top.restoreSession()"><?php echo text($documents[$r->documentId] ?? xl('Document') . ' ' . $r->documentId); ?></a><?php } ?></td>
                </tr>
            <?php } ?>
            </tbody>
        </table>
        </div>
    <?php } ?>

    <?php if ($canEnd && $active !== null) { ?>
    <h2 class="h5 mt-4"><?php echo xlt('End the plan'); ?></h2>
    <form method="post" class="form-inline border rounded p-3">
        <input type="hidden" name="csrf_token_form" value="<?php echo attr($csrf); ?>">
        <input type="hidden" name="action" value="end">
        <label class="mr-2" for="ended_on"><?php echo xlt('Ended on'); ?></label>
        <input type="date" class="form-control mr-3" id="ended_on" name="ended_on" value="<?php echo attr($today->format('Y-m-d')); ?>" max="<?php echo attr($today->format('Y-m-d')); ?>">
        <label class="mr-2" for="end_reason"><?php echo xlt('Why'); ?></label>
        <input type="text" class="form-control mr-3" id="end_reason" name="end_reason" maxlength="255" placeholder="<?php echo xla('Discharge, transfer, moved'); ?>">
        <button type="submit" class="btn btn-outline-danger"><?php echo xlt('End the plan'); ?></button>
    </form>
    <?php } ?>
</main>
</body>
</html>
