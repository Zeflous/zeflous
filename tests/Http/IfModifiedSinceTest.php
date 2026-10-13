<?php

declare(strict_types=1);

namespace Zef\Test\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Http\HttpDate;
use Zef\Framework\Http\IfModifiedSince;

/**
 * @internal
 */
#[CoversClass(IfModifiedSince::class)]
#[CoversClass(HttpDate::class)]
final class IfModifiedSinceTest extends TestCase
{
    private const string LAST_MODIFIED = 'Mon, 01 Jan 2024 10:00:00 GMT';

    public function testAnEqualTimestampIsNotModified(): void
    {
        self::assertTrue(IfModifiedSince::notModified(self::LAST_MODIFIED, self::LAST_MODIFIED));
    }

    public function testAServerNewerThanTheClientIsModified(): void
    {
        self::assertFalse(IfModifiedSince::notModified('Mon, 01 Jan 2024 09:59:59 GMT', self::LAST_MODIFIED));
    }

    public function testAClientNewerThanTheServerIsNotModified(): void
    {
        self::assertTrue(IfModifiedSince::notModified('Mon, 01 Jan 2024 10:00:01 GMT', self::LAST_MODIFIED));
    }

    public function testAnUnparsableClientTimestampIsNoPrecondition(): void
    {
        self::assertFalse(IfModifiedSince::notModified('not-a-date', self::LAST_MODIFIED));
    }

    public function testAnUnparsableServerTimestampIsNoPrecondition(): void
    {
        self::assertFalse(IfModifiedSince::notModified(self::LAST_MODIFIED, 'not-a-date'));
    }

    public function testBothSidesMayUseAnyGrammarShape(): void
    {
        $zoneLessClient = 'Mon, 01 Jan 2024 10:00:00';
        $offsetServer = 'Monday, 01 Jan 2024 05:00:00 +05:00';

        self::assertTrue(IfModifiedSince::notModified($zoneLessClient, $offsetServer));
        self::assertTrue(IfModifiedSince::notModified('Monday, 01 Jan 2024 10:00:00 GMT', 'Mon, 01 Jan 2024 10:00:00'));
    }

    public function testARolledOverClientDateIsNoPrecondition(): void
    {
        self::assertFalse(IfModifiedSince::notModified('Mon, 32 Jan 2024 10:00:00 GMT', self::LAST_MODIFIED));
    }
}
