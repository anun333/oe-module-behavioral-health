<?php

/**
 * The cover sheet that goes with records disclosed under a Part 2 consent: the 42 CFR 2.32(a)
 * statement chosen when the disclosure was recorded, in the regulation's words, and either a note
 * that a copy of the consent is attached or an explanation of the consent's scope (2.32(b)).
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
use OpenHealthcare\BehavioralHealth\Domain\Notice;
use OpenHealthcare\BehavioralHealth\Labels;
use OpenHealthcare\BehavioralHealth\Repository\ConsentRepository;
use OpenHealthcare\BehavioralHealth\Repository\DisclosureRepository;
use OpenHealthcare\Common\OpenEmr;

if (!AclMain::aclCheckCore('patients', 'disclosure')) {
    OpenEmr::deny('ACL check failed for patients/disclosure: Behavioral Health', xl('Part 2 Consents and Disclosures'), 'Behavioral Health');
}
$disclosure = (new DisclosureRepository())->find((int)($_GET['link'] ?? 0));
$consent = $disclosure?->consentId === null ? null : (new ConsentRepository())->find($disclosure->consentId, $disclosure->pid);
if ($disclosure === null || $consent === null || $disclosure->statement === null) {
    Bootstrap::stop(xl('Notice to Accompany Disclosure'), xl('No such disclosure.'));
}
$patient = QueryUtils::fetchRecords('SELECT `fname`, `lname`, `DOB` FROM `patient_data` WHERE `pid` = ?', [$disclosure->pid])[0] ?? [];
$facility = QueryUtils::fetchRecords(
    'SELECT `name`, `street`, `city`, `state`, `postal_code`, `phone` FROM `facility` ORDER BY `primary_business_entity` DESC, `id` LIMIT 1',
)[0] ?? [];
OpenEmr::audit('patient-record-select', "Behavioral Health: cover sheet printed for Part 2 disclosure {$disclosure->linkId}", $disclosure->pid);
$place = trim(implode(', ', array_filter([(string)($facility['street'] ?? ''), trim((string)($facility['city'] ?? '') . ' ' . (string)($facility['state'] ?? '') . ' ' . (string)($facility['postal_code'] ?? ''))])));
?>
<!DOCTYPE html>
<?php // The module's own text is written in English; the statement is the regulation's. ?>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title><?php echo xlt('Notice to Accompany Disclosure'); ?></title>
    <?php Header::setupHeader(['common']); ?>
    <?php echo \OpenHealthcare\BehavioralHealth\Contrast::style(); ?>
    <style>
        @media print { .no-print { display: none; } body { font-size: 12pt; } }
        .statement { border: 2px solid #000; padding: 1rem; }
    </style>
</head>
<body class="bg-white">
<main class="container py-3" style="max-width: 48rem">
    <p class="no-print"><button type="button" class="btn btn-primary" onclick="window.print()"><?php echo xlt('Print'); ?></button></p>
    <p class="mb-0"><strong><?php echo text((string)($facility['name'] ?? '')); ?></strong></p>
    <p class="mb-0"><?php echo text($place); ?></p>
    <p><?php echo text((string)($facility['phone'] ?? '')); ?></p>

    <h1 class="h4 mt-3"><?php echo xlt('Notice to Accompany Disclosure'); ?></h1>
    <table class="table table-sm table-borderless w-auto">
        <tr><th scope="row"><?php echo xlt('To'); ?></th><td><?php echo text($disclosure->recipient); ?><?php if ($consent->recipientAddress !== '') { ?>, <?php echo text($consent->recipientAddress); ?><?php } ?></td></tr>
        <tr><th scope="row"><?php echo xlt('Date'); ?></th><td><?php echo text(Labels::date($disclosure->date)); ?></td></tr>
        <tr><th scope="row"><?php echo xlt('Patient'); ?></th><td><?php echo text(trim(($patient['fname'] ?? '') . ' ' . ($patient['lname'] ?? ''))); ?>, <?php echo xlt('DOB'); ?> <?php echo text(oeFormatShortDate((string)($patient['DOB'] ?? ''))); ?></td></tr>
        <tr><th scope="row"><?php echo xlt('Records'); ?></th><td><?php echo text($disclosure->description); ?></td></tr>
    </table>

    <div class="statement my-3">
        <?php foreach (Notice::paragraphs($disclosure->statement) as $paragraph) { ?>
            <p><?php echo text($paragraph); ?></p>
        <?php } ?>
    </div>

    <?php if ($disclosure->accompaniment === Notice::COPY_OF_CONSENT) { ?>
        <p><?php echo xlt('A copy of the written consent of the patient to this disclosure is attached.'); ?></p>
    <?php } else { ?>
        <h2 class="h6"><?php echo xlt('The consent and its scope'); ?></h2>
        <p><?php echo xlt('The patient consented in writing, on'); ?> <?php echo text(Labels::date($consent->signedOn)); ?>, <?php echo xlt('to disclose the following to the recipient named above.'); ?></p>
        <table class="table table-sm table-bordered">
            <tr><th scope="row"><?php echo xlt('Information'); ?></th><td><?php echo text($consent->information); ?></td></tr>
            <tr><th scope="row"><?php echo xlt('Purpose'); ?></th><td><?php echo text($consent->purpose); ?></td></tr>
            <tr><th scope="row"><?php echo xlt('Ends'); ?></th><td><?php echo text($consent->expiresOn !== null ? Labels::date($consent->expiresOn) : $consent->expirationEvent); ?></td></tr>
        </table>
        <p><?php echo xlt('The patient may revoke the consent in writing, except to the extent that action has already been taken in reliance on it.'); ?></p>
    <?php } ?>
</main>
</body>
</html>
