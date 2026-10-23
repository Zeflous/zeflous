<?php

declare(strict_types=1);

namespace Zef\Test\Tooling;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Test\Support\Tooling\BaselineGate;

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

    /**
     * The flow-mapping spelling PHPStan also emits -- `- {message: '...',
     * count: 1, path: '...'}` -- is an ignored error the ratchet must
     * count: the message key sits directly behind the mapping's opening
     * brace rather than at the start of its own line.
     */
    public function testCountsFlowMappingEntries(): void
    {
        $baseline = "parameters:\n    ignoreErrors:\n        - {message: '#a#', count: 1, path: 'src/A.php'}\n";

        self::assertStringContainsString('ignores 1 error(s)', new BaselineGate()->evaluate($baseline)->message);
    }

    /**
     * The identifier-only alternative PHPStan documents -- an entry that
     * carries `identifier:` instead of `message:` -- is an ignored error
     * the same way, in both the block spelling and the flow mapping.
     */
    public function testCountsIdentifierOnlyEntries(): void
    {
        $baseline = "parameters:\n    ignoreErrors:\n"
            . "        - identifier: class.notFound\n"
            . "        - {identifier: 'method.notFound', count: 1, path: 'src/B.php'}\n";

        self::assertStringContainsString('ignores 2 error(s)', new BaselineGate()->evaluate($baseline)->message);
    }

    /**
     * A compact flow mapping whose message key rides after the comma that
     * separates it from a preceding key is still one key position the
     * count must see.
     */
    public function testCountsAMidFlowMessageKey(): void
    {
        $baseline = "parameters:\n    ignoreErrors:\n        - {count: 1, message: '#c#', path: 'src/C.php'}\n";

        self::assertStringContainsString('ignores 1 error(s)', new BaselineGate()->evaluate($baseline)->message);
    }
}
