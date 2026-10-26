<?php

/**
 * A patient's Part 2 consents and disclosures: record a consent from the signed form, record its
 * revocation or the end of its expiration event, and record a disclosure made under a consent in
 * force, which goes into OpenEMR's Disclosures record and gets a cover sheet with the 42 CFR 2.32
 * statement.
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
use OpenHealthcare\BehavioralHealth\Domain\Consent;
use OpenHealthcare\BehavioralHealth\Domain\Notice;
use OpenHealthcare\BehavioralHealth\Guide;
use OpenHealthcare\BehavioralHealth\Labels;
use OpenHealthcare\BehavioralHealth\Repository\ConsentRepository;
use OpenHealthcare\BehavioralHealth\Repository\DisclosureRepository;
use OpenHealthcare\Common\OpenEmr;

// The access core's own Disclosures page asks for: patients/disclosure to see, write or addonly to add.
if (!AclMain::aclCheckCore('patients', 'disclosure')) {
    OpenEmr::deny('ACL check failed for patients/disclosure: Behavioral Health', xl('Part 2 Consents and Disclosures'), 'Behavioral Health');
}
$canAdd = AclMain::aclCheckCore('patients', 'disclosure', '', 'write') || AclMain::aclCheckCore('patients', 'disclosure', '', 'addonly');
$canChange = AclMain::aclCheckCore('patients', 'disclosure', '', 'write');

$pid = (int)($_GET['pid'] ?? OpenEmr::session('pid') ?? 0);
$patient = QueryUtils::fetchRecords('SELECT `pid`, `fname`, `lname`, `DOB`, `pubpid` FROM `patient_data` WHERE `pid` = ?', [$pid])[0] ?? null;
if ($patient === null) {
    Bootstrap::stop(xl('Part 2 Consents and Disclosures'), xl('No such patient.'));
}
$consentRepo = new ConsentRepository();
$disclosureRepo = new DisclosureRepository();
$today = new DateTimeImmutable('today');
$userId = (int)OpenEmr::session('authUserID');
$username = (string)OpenEmr::session('authUser');
$errors = [];
$done = '';

// The patient's documents, for linking a consent to its signed form.
$documents = [];
foreach (QueryUtils::fetchRecords('SELECT `id`, `name`, `date` FROM `documents` WHERE `foreign_id` = ? AND `deleted` = 0 ORDER BY `date` DESC, `id` DESC LIMIT 200', [$pid]) as $r) {
    $documents[(int)$r['id']] = trim((string)$r['name']) . ' (' . oeFormatShortDate(substr((string)$r['date'], 0, 10)) . ')';
}

$date = static function (string $field): ?DateTimeImmutable {
    $d = DateTimeImmutable::createFromFormat('!Y-m-d', trim((string)($_POST[$field] ?? '')));
    return $d === false ? null : $d;
};
$text = static fn(string $field): string => trim((string)($_POST[$field] ?? ''));

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    OpenEmr::requireCsrf($_POST['csrf_token_form'] ?? '');
    $action = (string)($_POST['action'] ?? '');
    if ($action === 'consent' && $canAdd) {
        $signed = $date('signed_on');
        $expires = $date('expires_on');
        $event = $text('expiration_event');
        $doc = (int)($_POST['document_id'] ?? 0);
        if ($text('recipient') === '' || $text('purpose') === '' || $text('information') === '') {
            $errors[] = xl('Enter who may receive the records, the purpose, and what information the consent covers.');
        }
        if ($signed === null || $signed > $today) {
            $errors[] = xl('The date the consent was signed must be today or earlier.');
        }
        if (($expires === null) === ($event === '')) {
            $errors[] = xl('Enter either the date the consent expires or the event that ends it.');
        } elseif ($expires !== null && $signed !== null && $expires < $signed) {
            $errors[] = xl('The consent can not expire before it was signed.');
        }
        if ($doc > 0 && !isset($documents[$doc])) {
            $errors[] = xl('Choose a document of this patient, or none.');
        }
        if ($errors === [] && $signed !== null) {
            $id = $consentRepo->add(new Consent(0, $pid, $text('recipient'), $text('recipient_address'), $text('purpose'), $text('information'),
                $signed, $expires, $expires === null ? $event : '', null, $doc > 0 ? $doc : null, null, $text('note')), $userId);
            OpenEmr::audit('patient-record-insert', "Behavioral Health: Part 2 consent $id recorded", $pid);
            $done = xl('The consent is recorded.');
        }
    } elseif (($action === 'revoke' || $action === 'end') && $canChange) {
        $consent = $consentRepo->find((int)($_POST['consent_id'] ?? 0), $pid);
        $on = $date('on');
        if ($consent === null || $on === null || $on > $today || $on < $consent->signedOn) {
            $errors[] = xl('Enter a date between the day the consent was signed and today.');
        } elseif ($action === 'revoke') {
            $consentRepo->revoke($consent->id, $pid, $on, $text('note'), $userId);
            OpenEmr::audit('patient-record-update', "Behavioral Health: Part 2 consent {$consent->id} revoked", $pid);
            $done = xl('The revocation is recorded. Disclosures made before it still stand.');
        } else {
            $consentRepo->end($consent->id, $pid, $on, $userId);
            OpenEmr::audit('patient-record-update', "Behavioral Health: Part 2 consent {$consent->id} ended", $pid);
            $done = xl('The consent is recorded as ended.');
        }
    } elseif ($action === 'disclose' && $canAdd) {
        $consent = $consentRepo->find((int)($_POST['consent_id'] ?? 0), $pid);
        $on = $date('disclosed_on');
        $statement = (int)($_POST['statement'] ?? 0);
        $with = (string)($_POST['accompaniment'] ?? '');
        if ($consent === null) {
            $errors[] = xl('Choose the consent the disclosure relies on.');
        } elseif ($on === null || $on > $today) {
            $errors[] = xl('The disclosure date must be today or earlier.');
        } elseif (($why = $consent->refusal($on)) !== null) {
            $errors[] = xl('That consent can not be relied on for that date') . ': ' . Labels::refusal($why) . '. '
                . xl('42 CFR 2.31(c) rules out a consent that has expired or is known to have been revoked.');
        }
        if ($text('description') === '') {
            $errors[] = xl('Describe what was disclosed.');
        }
        if (!isset(Notice::TEXT[$statement]) || !in_array($with, Notice::ACCOMPANIMENTS, true)) {
            $errors[] = xl('Choose the 42 CFR 2.32 statement and what went with it.');
        }
        if (($_POST['within_scope'] ?? '') !== '1') {
            $errors[] = xl('Confirm that the recipient and the information are the ones the consent names.');
        }
        if ($errors === [] && $consent !== null && $on !== null) {
            $link = $disclosureRepo->record($consent, $on, $text('description'), $statement, $with, $username, $userId);
            OpenEmr::audit('patient-record-insert', "Behavioral Health: Part 2 disclosure $link recorded under consent {$consent->id}", $pid);
            header('Location: ' . Bootstrap::url('patient.php', ['pid' => $pid, 'recorded' => $link]));
            exit;
        }
    }
}
OpenEmr::audit('patient-record-select', 'Behavioral Health: Part 2 consents and disclosures viewed', $pid);

$consents = $consentRepo->forPatient($pid);
$disclosures = $disclosureRepo->forPatient($pid);
$recorded = (int)($_GET['recorded'] ?? 0);
$csrf = OpenEmr::csrfToken();
$webRoot = OpenEmr::webRoot();
?>
<!DOCTYPE html>
<?php // The module's own text is written in English. ?>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title><?php echo xlt('Part 2 Consents and Disclosures'); ?></title>
    <?php Header::setupHeader(['common', 'datetime-picker']); ?>
    <?php echo \OpenHealthcare\BehavioralHealth\Contrast::style(); ?>
</head>
<body class="body_top">
<main class="container-fluid py-3" style="max-width: 80rem">
    <h1 class="h4"><?php echo xlt('Part 2 Consents and Disclosures'); ?></h1>
    <p class="small mb-2"><a href="<?php echo attr(Guide::url()); ?>" target="_blank" rel="noopener"><?php echo xlt('Help'); ?><span class="sr-only"> <?php echo xlt('(opens in a new window)'); ?></span></a></p>
    <p class="mb-2">
        <strong><?php echo text(trim($patient['fname'] . ' ' . $patient['lname'])); ?></strong>
        · <?php echo xlt('DOB'); ?> <?php echo text(oeFormatShortDate((string)$patient['DOB'])); ?>
        · <?php echo xlt('ID'); ?> <?php echo text((string)$patient['pubpid']); ?>
    </p>
    <p class="small">
        <a href="<?php echo attr(Bootstrap::url('accounting.php', ['pid' => $pid])); ?>" onclick="top.restoreSession()"><?php echo xlt('Accounting of disclosures'); ?></a>
        · <a href="<?php echo attr($webRoot . '/interface/patient_file/summary/disclosure_full.php'); ?>" onclick="top.restoreSession()"><?php echo xlt('All disclosures for this patient in OpenEMR'); ?></a>
    </p>

    <?php foreach ($errors as $e) { ?>
        <div class="alert alert-danger py-2"><?php echo text($e); ?></div>
    <?php } ?>
    <?php if ($done !== '') { ?>
        <div class="alert alert-success py-2"><?php echo text($done); ?></div>
    <?php } ?>
    <?php if ($recorded > 0) { ?>
        <div class="alert alert-success py-2">
            <?php echo xlt('The disclosure is recorded in the Disclosures record of OpenEMR.'); ?>
            <a class="alert-link" href="<?php echo attr(Bootstrap::url('cover.php', ['link' => $recorded])); ?>" target="_blank" rel="noopener"><?php echo xlt('Print the cover sheet to send with the records'); ?></a>
        </div>
    <?php } ?>

    <h2 class="h5 mt-4"><?php echo xlt('Consents'); ?></h2>
    <?php if ($consents === []) { ?>
        <p class="text-muted"><?php echo xlt('No Part 2 consent recorded for this patient.'); ?></p>
    <?php } else { ?>
    <div class="table-responsive">
    <table class="table table-sm table-striped">
        <thead><tr>
            <th scope="col"><?php echo xlt('Signed'); ?></th>
            <th scope="col"><?php echo xlt('Who may receive the records'); ?></th>
            <th scope="col"><?php echo xlt('Purpose'); ?></th>
            <th scope="col"><?php echo xlt('Information'); ?></th>
            <th scope="col"><?php echo xlt('Ends'); ?></th>
            <th scope="col"><?php echo xlt('Status'); ?></th>
            <th scope="col"><?php echo xlt('Signed form'); ?></th>
            <?php if ($canChange) { ?><th scope="col"><?php echo xlt('Record a change'); ?></th><?php } ?>
        </tr></thead>
        <tbody>
        <?php foreach ($consents as $c) { ?>
            <tr>
                <td><?php echo text(Labels::date($c->signedOn)); ?></td>
                <td><?php echo text($c->recipient); ?><?php if ($c->recipientAddress !== '') { ?><br><span class="small text-muted"><?php echo text($c->recipientAddress); ?></span><?php } ?></td>
                <td><?php echo text($c->purpose); ?></td>
                <td><?php echo text($c->information); ?></td>
                <td><?php echo text($c->expiresOn !== null ? Labels::date($c->expiresOn) : $c->expirationEvent); ?></td>
                <td>
                    <?php echo text(Labels::status($c, $today)); ?>
                    <?php if ($c->revokedOn !== null) { ?><br><span class="small"><?php echo xlt('Revoked'); ?> <?php echo text(Labels::date($c->revokedOn)); ?></span><?php } ?>
                    <?php if ($c->endedOn !== null) { ?><br><span class="small"><?php echo xlt('Ended'); ?> <?php echo text(Labels::date($c->endedOn)); ?></span><?php } ?>
                </td>
                <td>
                    <?php if ($c->documentId !== null) { ?>
                        <a href="<?php echo attr($webRoot . '/controller.php?document&retrieve&patient_id=' . $pid . '&document_id=' . $c->documentId . '&as_file=false'); ?>" target="_blank" rel="noopener" onclick="top.restoreSession()"><?php echo text($documents[$c->documentId] ?? xl('Document') . ' ' . $c->documentId); ?></a>
                    <?php } else { ?>
                        <span class="text-muted small"><?php echo xlt('Not linked'); ?></span>
                    <?php } ?>
                </td>
                <?php if ($canChange) { ?>
                <td>
                    <?php if ($c->revokedOn === null && $c->endedOn === null) { ?>
                    <form method="post" class="form-inline mb-1">
                        <input type="hidden" name="csrf_token_form" value="<?php echo attr($csrf); ?>">
                        <input type="hidden" name="consent_id" value="<?php echo attr((string)$c->id); ?>">
                        <label class="sr-only" for="on-<?php echo attr((string)$c->id); ?>"><?php echo xlt('Date'); ?></label>
                        <input type="date" class="form-control form-control-sm mr-1" id="on-<?php echo attr((string)$c->id); ?>" name="on" value="<?php echo attr($today->format('Y-m-d')); ?>" max="<?php echo attr($today->format('Y-m-d')); ?>">
                        <button type="submit" name="action" value="revoke" class="btn btn-sm btn-outline-danger mr-1"><?php echo xlt('Revoked'); ?></button>
                        <?php if ($c->expiresOn === null) { ?>
                            <button type="submit" name="action" value="end" class="btn btn-sm btn-outline-secondary"><?php echo xlt('Event happened'); ?></button>
                        <?php } ?>
                    </form>
                    <?php } ?>
                </td>
                <?php } ?>
            </tr>
        <?php } ?>
        </tbody>
    </table>
    </div>
    <?php } ?>

    <?php if ($canAdd && $consents !== []) { ?>
    <h2 class="h5 mt-4"><?php echo xlt('Record a disclosure'); ?></h2>
    <form method="post" class="border rounded p-3 mb-4">
        <input type="hidden" name="csrf_token_form" value="<?php echo attr($csrf); ?>">
        <input type="hidden" name="action" value="disclose">
        <div class="form-row">
            <div class="form-group col-md-6">
                <label for="consent_id"><?php echo xlt('Under the consent'); ?></label>
                <select class="form-control" id="consent_id" name="consent_id" required>
                    <?php foreach ($consents as $c) { ?>
                        <option value="<?php echo attr((string)$c->id); ?>"><?php echo text($c->recipient . ' · ' . $c->purpose . ' · ' . Labels::status($c, $today)); ?></option>
                    <?php } ?>
                </select>
            </div>
            <div class="form-group col-md-3">
                <label for="disclosed_on"><?php echo xlt('Date disclosed'); ?></label>
                <input type="date" class="form-control" id="disclosed_on" name="disclosed_on" value="<?php echo attr($today->format('Y-m-d')); ?>" max="<?php echo attr($today->format('Y-m-d')); ?>" required>
            </div>
        </div>
        <div class="form-group">
            <label for="description"><?php echo xlt('What was disclosed'); ?></label>
            <textarea class="form-control" id="description" name="description" rows="2" required></textarea>
        </div>
        <fieldset class="form-group">
            <legend class="col-form-label pt-0"><?php echo xlt('Statement sent with the records (42 CFR 2.32(a))'); ?></legend>
            <?php foreach ([Notice::STATEMENT_1, Notice::STATEMENT_2] as $s) { ?>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="statement" id="statement-<?php echo attr((string)$s); ?>" value="<?php echo attr((string)$s); ?>" <?php echo $s === Notice::STATEMENT_1 ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="statement-<?php echo attr((string)$s); ?>"><?php echo text(Labels::statement($s)); ?></label>
                </div>
            <?php } ?>
        </fieldset>
        <fieldset class="form-group">
            <legend class="col-form-label pt-0"><?php echo xlt('Also sent with the records (42 CFR 2.32(b))'); ?></legend>
            <?php foreach (Notice::ACCOMPANIMENTS as $a) { ?>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="accompaniment" id="with-<?php echo attr($a); ?>" value="<?php echo attr($a); ?>" <?php echo $a === Notice::COPY_OF_CONSENT ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="with-<?php echo attr($a); ?>"><?php echo text(Labels::accompaniment($a)); ?></label>
                </div>
            <?php } ?>
        </fieldset>
        <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" name="within_scope" id="within_scope" value="1">
            <label class="form-check-label" for="within_scope"><?php echo xlt('The recipient and the information are the ones the consent names.'); ?></label>
        </div>
        <button type="submit" class="btn btn-primary"><?php echo xlt('Record the disclosure'); ?></button>
    </form>
    <?php } ?>

    <h2 class="h5 mt-4"><?php echo xlt('Part 2 disclosures'); ?></h2>
    <?php if ($disclosures === []) { ?>
        <p class="text-muted"><?php echo xlt('None recorded.'); ?></p>
    <?php } else { ?>
    <div class="table-responsive">
    <table class="table table-sm table-striped">
        <thead><tr>
            <th scope="col"><?php echo xlt('Date'); ?></th>
            <th scope="col"><?php echo xlt('Recipient'); ?></th>
            <th scope="col"><?php echo xlt('What was disclosed'); ?></th>
            <th scope="col"><?php echo xlt('Statement and what went with it'); ?></th>
            <th scope="col"><?php echo xlt('Recorded by'); ?></th>
            <th scope="col"><?php echo xlt('Cover sheet'); ?></th>
        </tr></thead>
        <tbody>
        <?php foreach ($disclosures as $d) { ?>
            <tr>
                <td><?php echo text(Labels::date($d->date)); ?></td>
                <td><?php echo text($d->recipient); ?></td>
                <td><?php echo text($d->description); ?></td>
                <td><?php echo $d->linkId === null ? xlt('Entered on the Disclosures page of OpenEMR, with no consent linked') : text(Labels::statement($d->statement) . '; ' . Labels::accompaniment($d->accompaniment)); ?></td>
                <td><?php echo text($d->user); ?></td>
                <td><?php if ($d->linkId !== null) { ?><a href="<?php echo attr(Bootstrap::url('cover.php', ['link' => $d->linkId])); ?>" target="_blank" rel="noopener"><?php echo xlt('Print'); ?></a><?php } ?></td>
            </tr>
        <?php } ?>
        </tbody>
    </table>
    </div>
    <?php } ?>

    <?php if ($canAdd) { ?>
    <h2 class="h5 mt-4"><?php echo xlt('Record a consent'); ?></h2>
    <p class="small text-muted"><?php echo xlt('From the signed form: the Part 2 consent in Clinic Forms, or your own. Scan the signed form into Documents first, then choose it here.'); ?></p>
    <form method="post" class="border rounded p-3">
        <input type="hidden" name="csrf_token_form" value="<?php echo attr($csrf); ?>">
        <input type="hidden" name="action" value="consent">
        <div class="form-row">
            <div class="form-group col-md-6">
                <label for="recipient"><?php echo xlt('Who may receive the records (name or general designation)'); ?></label>
                <input type="text" class="form-control" id="recipient" name="recipient" maxlength="255" required>
            </div>
            <div class="form-group col-md-6">
                <label for="recipient_address"><?php echo xlt('Their address, if known'); ?></label>
                <input type="text" class="form-control" id="recipient_address" name="recipient_address" maxlength="255">
            </div>
        </div>
        <div class="form-group">
            <label for="purpose"><?php echo xlt('Purpose'); ?></label>
            <input type="text" class="form-control" id="purpose" name="purpose" maxlength="255" required>
        </div>
        <div class="form-group">
            <label for="information"><?php echo xlt('How much and what kind of information'); ?></label>
            <textarea class="form-control" id="information" name="information" rows="2" required></textarea>
        </div>
        <div class="form-row">
            <div class="form-group col-md-3">
                <label for="signed_on"><?php echo xlt('Signed on'); ?></label>
                <input type="date" class="form-control" id="signed_on" name="signed_on" max="<?php echo attr($today->format('Y-m-d')); ?>" required>
            </div>
            <div class="form-group col-md-3">
                <label for="expires_on"><?php echo xlt('Expires on'); ?></label>
                <input type="date" class="form-control" id="expires_on" name="expires_on">
            </div>
            <div class="form-group col-md-6">
                <label for="expiration_event"><?php echo xlt('Or the event that ends it (for example, end of treatment)'); ?></label>
                <input type="text" class="form-control" id="expiration_event" name="expiration_event" maxlength="255">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group col-md-6">
                <label for="document_id"><?php echo xlt('Signed form in Documents'); ?></label>
                <select class="form-control" id="document_id" name="document_id">
                    <option value="0"><?php echo xlt('Not scanned yet'); ?></option>
                    <?php foreach ($documents as $id => $name) { ?>
                        <option value="<?php echo attr((string)$id); ?>"><?php echo text($name); ?></option>
                    <?php } ?>
                </select>
            </div>
            <div class="form-group col-md-6">
                <label for="note"><?php echo xlt('Note'); ?></label>
                <input type="text" class="form-control" id="note" name="note" maxlength="255">
            </div>
        </div>
        <button type="submit" class="btn btn-primary"><?php echo xlt('Record the consent'); ?></button>
    </form>
    <?php } ?>
</main>
</body>
</html>
