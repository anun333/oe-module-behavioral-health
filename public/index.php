<?php

/**
 * Part 2 disclosures across patients: those recorded in the last 30 days, and consents whose end
 * date falls in the next 30 days. Each patient's own page is reached from the card on their
 * dashboard or from here.
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
use OpenEMR\Core\Header;
use OpenHealthcare\BehavioralHealth\Bootstrap;
use OpenHealthcare\BehavioralHealth\Guide;
use OpenHealthcare\BehavioralHealth\Labels;
use OpenHealthcare\BehavioralHealth\Repository\ConsentRepository;
use OpenHealthcare\BehavioralHealth\Repository\DisclosureRepository;
use OpenHealthcare\Common\OpenEmr;

// The same access as Bootstrap::ACL, written out: tools/footprint.php lists each check it can read.
if (!AclMain::aclCheckCore('patients', 'disclosure')) {
    OpenEmr::deny('ACL check failed for patients/disclosure: Behavioral Health', xl('Part 2 Disclosures'), 'Behavioral Health');
}
OpenEmr::audit('patient-record-select', 'Behavioral Health: Part 2 disclosures across patients viewed');
$today = new DateTimeImmutable('today');
$recent = (new DisclosureRepository())->since($today->modify('-30 days'));
$ending = (new ConsentRepository())->endingSoon($today, 30);
?>
<!DOCTYPE html>
<?php // The module's own text is written in English. ?>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title><?php echo xlt('Part 2 Disclosures'); ?></title>
    <?php Header::setupHeader(['common']); ?>
    <?php echo \OpenHealthcare\BehavioralHealth\Contrast::style(); ?>
</head>
<body class="body_top">
<main class="container-fluid py-3" style="max-width: 70rem">
    <h1 class="h4"><?php echo xlt('Part 2 Disclosures'); ?></h1>
    <p class="small mb-2"><a href="<?php echo attr(Guide::url()); ?>" target="_blank" rel="noopener"><?php echo xlt('Help'); ?><span class="sr-only"> <?php echo xlt('(opens in a new window)'); ?></span></a></p>
    <p class="text-muted small"><?php echo xlt('The consents and disclosures of each patient are on the Part 2 card of their dashboard.'); ?></p>

    <h2 class="h5 mt-4"><?php echo xlt('Disclosures in the last 30 days'); ?></h2>
    <?php if ($recent === []) { ?>
        <p class="text-muted"><?php echo xlt('None.'); ?></p>
    <?php } else { ?>
    <div class="table-responsive">
    <table class="table table-sm table-striped">
        <thead><tr>
            <th scope="col"><?php echo xlt('Date'); ?></th>
            <th scope="col"><?php echo xlt('Patient'); ?></th>
            <th scope="col"><?php echo xlt('Recipient'); ?></th>
            <th scope="col"><?php echo xlt('What was disclosed'); ?></th>
            <th scope="col"><?php echo xlt('Consent linked'); ?></th>
        </tr></thead>
        <tbody>
        <?php foreach ($recent as $d) { ?>
            <tr>
                <td><?php echo text(Labels::date($d->date)); ?></td>
                <td><a href="<?php echo attr(Bootstrap::url('patient.php', ['pid' => $d->pid])); ?>" onclick="top.restoreSession()"><?php echo text($d->patientName); ?></a></td>
                <td><?php echo text($d->recipient); ?></td>
                <td><?php echo text($d->description); ?></td>
                <td><?php echo $d->consentId === null ? xlt('No') : xlt('Yes'); ?></td>
            </tr>
        <?php } ?>
        </tbody>
    </table>
    </div>
    <?php } ?>

    <h2 class="h5 mt-4"><?php echo xlt('Consents ending in the next 30 days'); ?></h2>
    <?php if ($ending === []) { ?>
        <p class="text-muted"><?php echo xlt('None.'); ?></p>
    <?php } else { ?>
    <div class="table-responsive">
    <table class="table table-sm table-striped">
        <thead><tr>
            <th scope="col"><?php echo xlt('Ends'); ?></th>
            <th scope="col"><?php echo xlt('Patient'); ?></th>
            <th scope="col"><?php echo xlt('Who may receive the records'); ?></th>
            <th scope="col"><?php echo xlt('Purpose'); ?></th>
        </tr></thead>
        <tbody>
        <?php foreach ($ending as $row) { ?>
            <tr>
                <td><?php echo text(Labels::date($row['consent']->expiresOn)); ?></td>
                <td><a href="<?php echo attr(Bootstrap::url('patient.php', ['pid' => $row['consent']->pid])); ?>" onclick="top.restoreSession()"><?php echo text($row['patient']); ?></a></td>
                <td><?php echo text($row['consent']->recipient); ?></td>
                <td><?php echo text($row['consent']->purpose); ?></td>
            </tr>
        <?php } ?>
        </tbody>
    </table>
    </div>
    <?php } ?>
</main>
</body>
</html>
