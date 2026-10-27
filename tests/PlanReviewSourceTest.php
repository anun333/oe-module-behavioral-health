<?php

/**
 * "Treatment plan reviews" as the Staff Worklist collects it. The tab is built on openhealthcare-common's Worklist
 * contract, so it's tested here without the Worklist installed.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    anun333 <anun333@posteo.net>
 * @copyright Copyright (c) 2026 anun333 <anun333@posteo.net>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace {
    if (!function_exists('xl')) {
        function xl(string $text): string
        {
            return $text;
        }
    }
    if (!function_exists('oeFormatShortDate')) {
        function oeFormatShortDate(string $date): string
        {
            return $date;
        }
    }
}

namespace OpenHealthcare\BehavioralHealth\Tests {

    use OpenEMR\Common\Database\QueryUtils;
    use OpenHealthcare\BehavioralHealth\Worklist\PlanReviewSource;
    use OpenHealthcare\Common\Contract\Worklist\WorkItem;
    use OpenHealthcare\Common\Contract\Worklist\Worklist;
    use PHPUnit\Framework\TestCase;

    if (!class_exists(QueryUtils::class)) {
        require_once __DIR__ . '/Stubs/QueryUtils.php';
    }

    final class PlanReviewSourceTest extends TestCase
    {
        private const NOW = '2026-10-26 09:00:00';

        protected function setUp(): void
        {
            QueryUtils::$results = [];
            QueryUtils::$calls = [];
        }

        /** @return array<string, mixed> a plan row with its latest review, as PlanRepository reads it */
        private static function plan(int $id, int $pid, string $reviewedOn, string $nextDue, int $formId): array
        {
            return [
                'id' => $id, 'pid' => $pid, 'started_on' => '2026-01-05', 'interval_days' => 180, 'ended_on' => null, 'end_reason' => '',
                'r_id' => $id * 10, 'r_plan_id' => $id, 'r_pid' => $pid, 'kind' => 'review', 'reviewed_on' => $reviewedOn,
                'care_plan_form_id' => $formId, 'endorsement' => 'patient', 'participants' => '', 'progress' => '[]', 'changes' => '',
                'next_due' => $nextDue, 'document_id' => null, 'reviewed_by' => 3,
            ];
        }

        /** @return array<string, mixed> */
        private static function patient(int $pid, string $last): array
        {
            return ['pid' => $pid, 'fname' => 'Jan', 'lname' => $last, 'DOB' => '1980-02-03', 'pubpid' => "P$pid", 'deceased_date' => null, 'portal_access' => 0];
        }

        public function testDueAndOverdueReviewsBecomeItemsKeyedByTheLatestReview(): void
        {
            QueryUtils::$results = [
                [], // settings: the defaults, 14 days ahead
                [
                    self::plan(1, 7, '2026-05-01', '2026-10-20', 501), // overdue since the 20th
                    self::plan(2, 9, '2026-05-10', '2026-11-03', 502), // due within 14 days
                    self::plan(3, 11, '2026-06-05', '2026-12-01', 503), // not due yet
                ],
                [self::patient(7, 'Doe'), self::patient(9, 'Roe')],
                [['form_id' => 501, 'sensitivity' => 'high'], ['form_id' => 502, 'sensitivity' => '']],
            ];
            $source = new PlanReviewSource(new \DateTimeImmutable(self::NOW));
            $items = $source->collect();

            $this->assertSame('bhplan', $source->id());
            $this->assertContainsOnlyInstancesOf(WorkItem::class, $items);
            $this->assertSame(['bhplan:7:10', 'bhplan:9:20'], array_map(static fn(WorkItem $i): string => (string)$i->key, $items));
            [$overdue, $due] = $items;
            $this->assertSame([WorkItem::PRIORITY_HIGH, 'high', 'Review overdue since 2026-10-20'], [$overdue->priority, $overdue->sensitivity, $overdue->title]);
            $this->assertSame([WorkItem::PRIORITY_NORMAL, null, 'Review due 2026-11-03'], [$due->priority, $due->sensitivity, $due->title]);
            $this->assertSame(['Doe, Jan', 'chart', '2026-10-20'], [$overdue->patient->name, $overdue->actionMode, $overdue->since->format('Y-m-d')]);
            $this->assertStringEndsWith('/public/plan.php?pid=7', $overdue->actionUrl);
            $this->assertSame([7, 9], QueryUtils::$calls[2]['binds'], 'patients read for the due plans only');
        }

        public function testOnePatientsReviewIsCheckedOnItsOwn(): void
        {
            QueryUtils::$results = [[], [self::plan(1, 7, '2026-05-01', '2026-10-20', 501)], [self::patient(7, 'Doe')], []];
            $items = (new PlanReviewSource(new \DateTimeImmutable(self::NOW)))->collect(7);
            $this->assertSame(['bhplan:7:10'], array_map(static fn(WorkItem $i): string => (string)$i->key, $items));
            $this->assertStringContainsString('p.`pid` = ?', QueryUtils::$calls[1]['sql']);
            $this->assertSame([7], QueryUtils::$calls[1]['binds']);
        }

        public function testNothingDueReadsNoPatients(): void
        {
            QueryUtils::$results = [[], [self::plan(3, 11, '2026-06-05', '2026-12-01', 503)]];
            $this->assertSame([], (new PlanReviewSource(new \DateTimeImmutable(self::NOW)))->collect());
            $this->assertCount(2, QueryUtils::$calls);
        }

        public function testTheTabReachesAWorklistThroughTheContract(): void
        {
            // A Worklist with contribute() (0.9.0); one from 0.8.x takes it through addSource(), as the library's tests show.
            $event = new class (new \DateTimeImmutable(self::NOW)) {
                /** @var list<array{object, string, string}> */
                public array $got = [];

                public function __construct(public readonly \DateTimeImmutable $now)
                {
                }

                public function contribute(object $source, string $label, string $ownerRole): void
                {
                    $this->got[] = [$source, $label, $ownerRole];
                }
            };
            $this->assertTrue(Worklist::contribute($event, new PlanReviewSource(Worklist::now($event)), 'Treatment plan reviews', 'Clinician'));
            $this->assertInstanceOf(PlanReviewSource::class, $event->got[0][0]);
        }
    }
}
