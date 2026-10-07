<?php

declare(strict_types=1);

namespace Zef\Test\Tooling;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Kernel;
use Zef\Framework\Tooling\PersistentWorkerSmoke;
use Zef\Framework\Tooling\SmokeResult;

/**
 * @internal
 */
#[CoversClass(PersistentWorkerSmoke::class)]
#[CoversClass(SmokeResult::class)]
final class PersistentWorkerSmokeTest extends TestCase
{
    public function testRunPerformsEveryIteration(): void
    {
        $smokeResult = new PersistentWorkerSmoke(1000, 2_000_000)->run(new Kernel());

        self::assertSame(1000, $smokeResult->iterations);
    }

    public function testRunStaysWithinTheBudget(): void
    {
        $persistentWorkerSmoke = new PersistentWorkerSmoke(1000, 2_000_000);

        self::assertTrue($persistentWorkerSmoke->passes($persistentWorkerSmoke->run(new Kernel())));
    }

    public function testPassesWithinBudget(): void
    {
        self::assertTrue(new PersistentWorkerSmoke(1, 2_000_000)->passes(new SmokeResult(1, 0)));
        self::assertTrue(new PersistentWorkerSmoke(1, 2_000_000)->passes(new SmokeResult(1, 2_000_000)));
    }

    public function testFailsOverBudget(): void
    {
        self::assertFalse(new PersistentWorkerSmoke(1, 2_000_000)->passes(new SmokeResult(1, 2_000_001)));
    }

    public function testMessageDescribesTheRun(): void
    {
        $message = new PersistentWorkerSmoke(7, 123)->message(new SmokeResult(7, 45));

        self::assertStringContainsString('7 iterations', $message);
        self::assertStringContainsString('memory growth 45 bytes', $message);
        self::assertStringContainsString('budget 123', $message);
    }
}
