<?php

declare(strict_types=1);

namespace OpenHealthcare\BehavioralHealth\Tests;

use OpenEMR\Common\Database\QueryUtils;
use OpenHealthcare\BehavioralHealth\Repository\DisclosureRepository;
use PHPUnit\Framework\TestCase;

if (!class_exists(QueryUtils::class)) {
    require_once __DIR__ . '/Stubs/QueryUtils.php';
}

final class DisclosureRepositoryTest extends TestCase
{
    protected function setUp(): void
    {
        QueryUtils::$results = [];
        QueryUtils::$calls = [];
    }

    public function testAnAccountingThroughTodayLeavesOutWhatIsDatedAfterIt(): void
    {
        (new DisclosureRepository())->forPatient(7, new \DateTimeImmutable('2023-10-26'), new \DateTimeImmutable('2026-10-26 15:30'));

        $call = QueryUtils::$calls[0];
        self::assertStringContainsString('el.`date` >= ?', $call['sql']);
        self::assertStringContainsString('el.`date` < ?', $call['sql'], 'bounded at the end of the day it runs through');
        self::assertSame([7, DisclosureRepository::EVENT, '2023-10-26', '2026-10-27 00:00:00'], $call['binds']);
    }

    public function testWithoutAnEndEveryDisclosureIsListed(): void
    {
        (new DisclosureRepository())->forPatient(7);

        self::assertStringNotContainsString('el.`date` <', QueryUtils::$calls[0]['sql'], 'the patient page lists them all');
        self::assertSame([7, DisclosureRepository::EVENT], QueryUtils::$calls[0]['binds']);
    }

    public function testItCountsTheDisclosuresDatedAfterADay(): void
    {
        QueryUtils::$results = [[['n' => '2']]];

        self::assertSame(2, (new DisclosureRepository())->countAfter(7, new \DateTimeImmutable('2026-10-26 09:00')));
        self::assertStringContainsString('el.`date` >= ?', QueryUtils::$calls[0]['sql']);
        self::assertSame([7, DisclosureRepository::EVENT, '2026-10-27 00:00:00'], QueryUtils::$calls[0]['binds']);
    }
}
