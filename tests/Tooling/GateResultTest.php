<?php

declare(strict_types=1);

namespace Zef\Test\Tooling;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Tooling\GateResult;

/**
 * @internal
 */
#[CoversClass(GateResult::class)]
final class GateResultTest extends TestCase
{
    public function testPassedCarriesTheMessageAndPasses(): void
    {
        $gateResult = GateResult::passed('all good');

        self::assertTrue($gateResult->passed);
        self::assertSame('all good', $gateResult->message);
        self::assertSame(0, $gateResult->exitCode());
    }

    public function testFailedCarriesTheMessageAndFails(): void
    {
        $gateResult = GateResult::failed('boom');

        self::assertFalse($gateResult->passed);
        self::assertSame('boom', $gateResult->message);
        self::assertSame(1, $gateResult->exitCode());
    }
}
