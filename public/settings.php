<?php

/**
 * Treatment plan settings: the longest a plan may go between reviews, and how many days ahead the Staff Worklist
 * shows a review as due. For administrators.
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
use OpenHealthcare\BehavioralHealth\Domain\Plan;
use OpenHealthcare\BehavioralHealth\Guide;
use OpenHealthcare\BehavioralHealth\Repository\Settings;
use OpenHealthcare\Common\OpenEmr;

if (!AclMain::aclCheckCore('admin', 'super')) {
    OpenEmr::deny('ACL check failed for admin/super: Behavioral Health settings', xl('Treatment plan settings'), 'Behavioral Health');
}
$settings = new Settings();
$saved = false;
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    OpenEmr::requireCsrf($_POST['csrf_token_form'] ?? '');
    $user = (int)OpenEmr::session('authUserID');
    $interval = Plan::interval($_POST['interval_days'] ?? Plan::DEFAULT_INTERVAL_DAYS);
    $settings->save(Settings::INTERVAL_DAYS, $interval, $user);
    $settings->save(Settings::AHEAD_DAYS, max(0, min(60, (int)($_POST['ahead_days'] ?? Plan::DEFAULT_AHEAD_DAYS))), $user);
    if (($_POST['apply_to_active'] ?? '') === '1') {
        QueryUtils::sqlStatementThrowException('UPDATE `behavioral_health_plan` SET `interval_days` = ?, `updated_by` = ? WHERE `ended_on` IS NULL', [$interval, $user]);
    }
    OpenEmr::audit('other', 'Behavioral Health: treatment plan settings saved');
    $saved = true;
    $settings = new Settings();
}
$csrf = OpenEmr::csrfToken();
?>
<!DOCTYPE html>
<?php // The module's own text is written in English. ?>
<html lang="<?php echo attr(\OpenHealthcare\BehavioralHealth\Page::lang()); ?>" dir="<?php echo attr(\OpenHealthcare\BehavioralHealth\Page::dir()); ?>">
<head>
    <meta charset="utf-8">
    <title><?php echo xlt('Treatment plan settings'); ?></title>
    <?php Header::setupHeader(['common']); ?>
    <?php echo \OpenHealthcare\BehavioralHealth\Contrast::style(); ?>
</head>
<body class="body_top">
<main class="container-fluid py-3" style="max-width: 60rem">
    <h1 class="h4"><?php echo xlt('Treatment plan settings'); ?></h1>
    <p class="small mb-2"><a href="<?php echo attr(Guide::url()); ?>" target="_blank" rel="noopener"><?php echo xlt('Help'); ?><span class="sr-only"> <?php echo xlt('(opens in a new window)'); ?></span></a></p>
    <p><a href="plans.php" onclick="top.restoreSession()"><?php echo xlt('Back to treatment plans'); ?></a></p>
    <?php if ($saved) { ?>
        <div class="alert alert-success py-2"><?php echo xlt('Saved.'); ?></div>
    <?php } ?>
    <form method="post">
        <input type="hidden" name="csrf_token_form" value="<?php echo attr($csrf); ?>">
        <div class="form-group">
            <label for="interval_days"><?php echo xlt('The longest a plan may go between reviews, in days'); ?></label>
            <input type="number" class="form-control" style="max-width: 10rem" id="interval_days" name="interval_days"
                   min="<?php echo attr((string)Plan::MIN_INTERVAL_DAYS); ?>" max="<?php echo attr((string)Plan::MAX_INTERVAL_DAYS); ?>" value="<?php echo attr((string)$settings->intervalDays()); ?>">
            <small class="form-text text-muted"><?php echo xlt('A certified community behavioral health clinic reviews each plan at least every 6 months (SAMHSA criterion 2.b.2); 180 days keeps within that. Your state, payer or accreditor may ask for less: for example every 30 days in a Medicare community mental health center (42 CFR 485.916), every 90 days for some state programs. Use the shortest rule that applies to you. A review may always set the next one sooner.'); ?></small>
        </div>
        <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" id="apply_to_active" name="apply_to_active" value="1">
            <label class="form-check-label" for="apply_to_active"><?php echo xlt('Apply this to plans already in progress (a shorter interval brings their next review forward)'); ?></label>
        </div>
        <div class="form-group">
            <label for="ahead_days"><?php echo xlt('Show a review in the Staff Worklist this many days before it is due'); ?></label>
            <input type="number" class="form-control" style="max-width: 10rem" id="ahead_days" name="ahead_days" min="0" max="60" value="<?php echo attr((string)$settings->aheadDays()); ?>">
        </div>
        <button type="submit" class="btn btn-primary"><?php echo xlt('Save'); ?></button>
    </form>
</main>
</body>
</html>
