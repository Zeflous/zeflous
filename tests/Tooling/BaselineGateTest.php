<?php

declare(strict_types=1);

namespace Zef\Test\Tooling;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Tooling\BaselineGate;

/**
 * @internal
 */
#[CoversClass(BaselineGate::class)]
final class BaselineGateTest extends TestCase
{
    public function testPassesWhenNoBaselineExists(): void
    {
        $gateResult = new BaselineGate()->evaluate(null);

        self::assertTrue($gateResult->passed);
        self::assertStringContainsString('no baseline file present', $gateResult->message);
    }

    public function testPassesWhenTheBaselineIsEmpty(): void
    {
        $gateResult = new BaselineGate()->evaluate("parameters:\n    ignoreErrors: []\n");

        self::assertTrue($gateResult->passed);
        self::assertStringContainsString('baseline is empty', $gateResult->message);
    }

    public function testFailsWhenTheBaselineIgnoresErrors(): void
    {
        $gateResult = new BaselineGate()->evaluate("parameters:\n    ignoreErrors:\n        - message: '#foo#'\n");

        self::assertFalse($gateResult->passed);
        self::assertStringContainsString('ignores 1 error(s)', $gateResult->message);
    }

    public function testCountsEveryIgnoredError(): void
    {
        $baseline = "parameters:\n    ignoreErrors:\n        - message: '#a#'\n        - message: '#b#'\n";

        self::assertStringContainsString('ignores 2 error(s)', new BaselineGate()->evaluate($baseline)->message);
    }
}
