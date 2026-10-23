<?php

/**
 * The Part 2 rules the module applies: when a consent can be relied on (42 CFR 2.31(c)), the period
 * an accounting covers (2.25(a)), and the 2.32 statements in the regulation's words.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    anun333 <anun333@posteo.net>
 * @copyright Copyright (c) 2026 anun333 <anun333@posteo.net>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenHealthcare\BehavioralHealth\Tests;

use OpenHealthcare\BehavioralHealth\Domain\Accounting;
use OpenHealthcare\BehavioralHealth\Domain\Consent;
use OpenHealthcare\BehavioralHealth\Domain\Notice;
use PHPUnit\Framework\TestCase;

final class DomainTest extends TestCase
{
    private static function day(string $ymd): \DateTimeImmutable
    {
        return new \DateTimeImmutable($ymd);
    }

    private static function consent(?string $expires = '2027-01-31', string $event = '', ?string $ended = null, ?string $revoked = null): Consent
    {
        return new Consent(1, 7, 'Seed County Treatment Court', '', 'Treatment progress reports', 'Attendance and urine drug screen results',
            self::day('2026-02-01'), $expires === null ? null : self::day($expires), $event,
            $ended === null ? null : self::day($ended), null, $revoked === null ? null : self::day($revoked), '');
    }

    public function testAConsentHoldsFromSigningToItsExpiryDateInclusive(): void
    {
        $c = self::consent();
        self::assertSame(Consent::NOT_YET_SIGNED, $c->refusal(self::day('2026-01-31')));
        self::assertNull($c->refusal(self::day('2026-02-01')));
        self::assertNull($c->refusal(self::day('2027-01-31 15:30')), 'the expiry date itself is still in force');
        self::assertSame(Consent::EXPIRED, $c->refusal(self::day('2027-02-01')));
    }

    public function testARevocationStopsDisclosuresFromItsDateButNotThoseBefore(): void
    {
        $c = self::consent(revoked: '2026-06-15');
        self::assertTrue($c->inForceOn(self::day('2026-06-14')), 'acted on in reliance before the revocation (2.31(a)(6))');
        self::assertSame(Consent::REVOKED, $c->refusal(self::day('2026-06-15')));
        self::assertSame(Consent::REVOKED, $c->refusal(self::day('2027-02-01')), 'revoked is the reason given, even after expiry');
    }

    public function testAnExpirationEventEndsTheConsentOnTheDayStaffRecord(): void
    {
        $open = self::consent(null, 'End of treatment');
        self::assertNull($open->refusal(self::day('2030-01-01')), 'an event-based consent has no date to pass');
        self::assertSame('End of treatment', $open->ends());
        $ended = self::consent(null, 'End of treatment', '2026-09-30');
        self::assertTrue($ended->inForceOn(self::day('2026-09-29')));
        self::assertSame(Consent::ENDED, $ended->refusal(self::day('2026-09-30')));
        self::assertSame('2027-01-31', self::consent()->ends());
    }

    public function testTheAccountingCoversThreeYearsOrTheShorterPeriodChosen(): void
    {
        $requested = self::day('2026-10-23 14:00');
        self::assertSame('2023-10-23', Accounting::from($requested, null)->format('Y-m-d'));
        self::assertSame('2025-01-01', Accounting::from($requested, self::day('2025-01-01'))->format('Y-m-d'));
        self::assertSame('2023-10-23', Accounting::from($requested, self::day('2020-01-01'))->format('Y-m-d'), 'no longer than 3 years');
        self::assertSame('2023-10-23', Accounting::from($requested, self::day('2026-12-01'))->format('Y-m-d'), 'a start after the request is ignored');
    }

    public function testTheStatementsAreTheRegulationsWords(): void
    {
        self::assertSame(['42 CFR part 2 prohibits unauthorized use or disclosure of these records.'], Notice::paragraphs(Notice::STATEMENT_2));
        $one = Notice::paragraphs(Notice::STATEMENT_1);
        self::assertCount(5, $one);
        self::assertStringStartsWith('This record which has been disclosed to you is protected by Federal confidentiality rules (42 CFR part 2).', $one[0]);
        self::assertStringEndsWith('unless at least one of the following applies:', $one[0]);
        self::assertSame(['(i) ', '(ii) ', '(iii) '], [substr($one[1], 0, 4), substr($one[2], 0, 5), substr($one[3], 0, 6)]);
        self::assertSame('A general authorization for the release of medical or other information is NOT sufficient to meet the required elements of written consent to further use or redisclose the record (see 42 CFR 2.31).', $one[4]);
        // The text as eCFR printed it on 2026-10-21 (89 FR 12626), normalised to single spaces.
        self::assertSame('d82205a18044f9f4', substr(hash('sha256', implode(' ', $one)), 0, 16), 'Statement 1 changed: compare it with 42 CFR 2.32(a)(1) again');
    }

    public function testAStatementOutsideTheRegulationIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Notice::paragraphs(3);
    }
}
