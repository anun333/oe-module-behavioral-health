<?php

declare(strict_types=1);

namespace OpenHealthcare\BehavioralHealth\Tests;

use OpenHealthcare\BehavioralHealth\Page;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

final class PageTest extends TestCase
{
    public function testWithNoSessionThePageIsEnglishLeftToRight(): void
    {
        self::assertSame('en', Page::lang());
        self::assertSame('ltr', Page::dir());
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testAnOlderLibraryLoadedFirstGivesEnglishInsteadOfStoppingThePage(): void
    {
        // What a site with hand-copied modules can load: openhealthcare-common before 1.4.0, without pageLang().
        eval('namespace OpenHealthcare\Common; final class OpenEmr { public static function webRoot(): string { return ""; } }');

        self::assertSame('en', Page::lang());
        self::assertSame('ltr', Page::dir());
    }
}
