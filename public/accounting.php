<?php

/**
 * An accounting of the disclosures made with the patient's consent (42 CFR 2.25(a)): every Part 2
 * disclosure in OpenEMR's Disclosures record from the start of the period, with the date, the
 * recipient and their address if known, what was disclosed and the purpose (45 CFR 164.528(b)(2)).
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
use OpenHealthcare\BehavioralHealth\Domain\Accounting;
use OpenHealthcare\BehavioralHealth\Labels;
use OpenHealthcare\BehavioralHealth\Repository\DisclosureRepository;
use OpenHealthcare\Common\OpenEmr;

if (!AclMain::aclCheckCore('patients', 'disclosure')) {
    OpenEmr::deny('ACL check failed for patients/disclosure: Behavioral Health', xl('Accounting of Disclosures'), 'Behavioral Health');
}
$pid = (int)($_GET['pid'] ?? 0);
$patient = QueryUtils::fetchRecords('SELECT `fname`, `lname`, `DOB`, `pubpid` FROM `patient_data` WHERE `pid` = ?', [$pid])[0] ?? null;
if ($patient === null) {
    Bootstrap::stop(xl('Accounting of Disclosures'), xl('No such patient.'));
}
$today = new DateTimeImmutable('today');
$chosen = DateTimeImmutable::createFromFormat('!Y-m-d', (string)($_GET['from'] ?? ''));
$from = Accounting::from($today, $chosen === false ? null : $chosen);
$disclosureRepo = new DisclosureRepository();
$disclosures = $disclosureRepo->forPatient($pid, $from, $today);
// Dated after today on core's Disclosures page: outside an accounting that runs to today, but said.
$later = $disclosureRepo->countAfter($pid, $today);
$facility = QueryUtils::fetchRecords('SELECT `name` FROM `facility` ORDER BY `primary_business_entity` DESC, `id` LIMIT 1')[0]['name'] ?? '';
OpenEmr::audit('patient-record-select', 'Behavioral Health: accounting of Part 2 disclosures from ' . $from->format('Y-m-d'), $pid);
?>
<!DOCTYPE html>
<?php // The module's own text is written in English. ?>
<html lang="<?php echo attr(\OpenHealthcare\BehavioralHealth\Page::lang()); ?>" dir="<?php echo attr(\OpenHealthcare\BehavioralHealth\Page::dir()); ?>">
<head>
    <meta charset="utf-8">
    <title><?php echo xlt('Accounting of Disclosures'); ?></title>
    <?php Header::setupHeader(['common']); ?>
    <?php echo \OpenHealthcare\BehavioralHealth\Contrast::style(); ?>
    <style>@media print { .no-print { display: none; } }</style>
</head>
<body class="bg-white">
<main class="container-fluid py-3" style="max-width: 70rem">
    <div class="no-print mb-3">
        <form method="get" class="form-inline">
            <input type="hidden" name="pid" value="<?php echo attr((string)$pid); ?>">
            <label for="from" class="mr-2"><?php echo xlt('From'); ?></label>
            <input type="date" class="form-control form-control-sm mr-2" id="from" name="from" value="<?php echo attr($from->format('Y-m-d')); ?>" min="<?php echo attr($today->modify('-' . Accounting::YEARS . ' years')->format('Y-m-d')); ?>" max="<?php echo attr($today->format('Y-m-d')); ?>">
            <button type="submit" class="btn btn-sm btn-secondary mr-2"><?php echo xlt('Show'); ?></button>
            <button type="button" class="btn btn-sm btn-primary mr-2" onclick="window.print()"><?php echo xlt('Print'); ?></button>
            <a href="<?php echo attr(Bootstrap::url('patient.php', ['pid' => $pid])); ?>" onclick="top.restoreSession()"><?php echo xlt('Back'); ?></a>
        </form>
        <p class="small text-muted mt-2 mb-0"><?php echo xlt('42 CFR 2.25 gives patients this accounting for 3 years, or a shorter period they choose. HHS has put off the date programs must comply until it revises the HIPAA accounting rule (89 FR 12476), so giving it now is a choice.'); ?></p>
    </div>

    <h1 class="h4"><?php echo xlt('Accounting of Disclosures Made with Your Consent'); ?></h1>
    <p class="mb-1"><?php echo text($facility); ?></p>
    <p>
        <?php echo text(trim($patient['fname'] . ' ' . $patient['lname'])); ?>
        · <?php echo xlt('DOB'); ?> <?php echo text(oeFormatShortDate((string)$patient['DOB'])); ?>
        · <?php echo xlt('ID'); ?> <?php echo text((string)$patient['pubpid']); ?>
        <br><?php echo xlt('Disclosures from'); ?> <?php echo text(Labels::date($from)); ?> <?php echo xlt('to'); ?> <?php echo text(Labels::date($today)); ?>
    </p>
    <?php if ($later > 0) { ?>
        <div class="alert alert-warning no-print"><?php echo text(sprintf(xl('%d Part 2 disclosures are dated after today, so they are not in this accounting. Check their dates on the Disclosures page of OpenEMR.'), $later)); ?></div>
    <?php } ?>
    <?php $unlinked = count(array_filter($disclosures, static fn($d): bool => $d->purpose === '')); ?>
    <?php if ($unlinked > 0) { ?>
        <div class="alert alert-warning no-print"><?php echo text((string)$unlinked); ?> <?php echo xlt('of these were entered on the Disclosures page of OpenEMR with no consent linked, so their purpose is not recorded here. Add it to their description there before giving this to the patient.'); ?></div>
    <?php } ?>
    <?php if ($disclosures === []) { ?>
        <p><?php echo xlt('No disclosures were made with your consent in this period.'); ?></p>
    <?php } else { ?>
    <table class="table table-sm table-bordered">
        <thead><tr>
            <th scope="col"><?php echo xlt('Date'); ?></th>
            <th scope="col"><?php echo xlt('Disclosed to'); ?></th>
            <th scope="col"><?php echo xlt('What was disclosed'); ?></th>
            <th scope="col"><?php echo xlt('Purpose'); ?></th>
        </tr></thead>
        <tbody>
        <?php foreach ($disclosures as $d) { ?>
            <tr>
                <td><?php echo text(Labels::date($d->date)); ?></td>
                <td><?php echo text($d->recipient); ?><?php if ($d->recipientAddress !== '') { ?><br><?php echo text($d->recipientAddress); ?><?php } ?></td>
                <td><?php echo text($d->description); ?></td>
                <td><?php echo $d->purpose !== '' ? text($d->purpose) : xlt('Not recorded'); ?></td>
            </tr>
        <?php } ?>
        </tbody>
    </table>
    <?php } ?>
</main>
</body>
</html>
