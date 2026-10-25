<?php

/**
 * The site's settings for treatment plans: the longest a plan may go between reviews, and how many days ahead the
 * Staff Worklist shows a review as due.
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
use OpenHealthcare\BehavioralHealth\Domain\Plan;

final class Settings
{
    public const INTERVAL_DAYS = 'plan_interval_days';
    public const AHEAD_DAYS = 'plan_ahead_days';

    /** @var array<string, mixed>|null */
    private ?array $values = null;

    public function intervalDays(): int
    {
        return Plan::interval($this->all()[self::INTERVAL_DAYS] ?? Plan::DEFAULT_INTERVAL_DAYS);
    }

    public function aheadDays(): int
    {
        $days = $this->all()[self::AHEAD_DAYS] ?? Plan::DEFAULT_AHEAD_DAYS;
        return is_numeric($days) ? max(0, min(60, (int)$days)) : Plan::DEFAULT_AHEAD_DAYS;
    }

    public function save(string $name, mixed $value, int $userId): void
    {
        QueryUtils::sqlStatementThrowException(
            'REPLACE INTO `behavioral_health_setting` (`name`, `value`, `updated_by`) VALUES (?, ?, ?)',
            [$name, json_encode($value), $userId]
        );
        $this->values = null;
    }

    /** @return array<string, mixed> */
    private function all(): array
    {
        if ($this->values === null) {
            $this->values = [];
            foreach (QueryUtils::fetchRecords('SELECT `name`, `value` FROM `behavioral_health_setting`') as $row) {
                $this->values[(string)$row['name']] = json_decode((string)$row['value'], true);
            }
        }
        return $this->values;
    }
}
