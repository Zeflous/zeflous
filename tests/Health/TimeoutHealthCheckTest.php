<?php

declare(strict_types=1);

namespace Zef\Test\Health;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Zef\Framework\Health\HealthGroups;
use Zef\Framework\Health\TimeoutHealthCheck;
use Zef\Test\Support\FixedGroupedHealthCheck;
use Zef\Test\Support\FixedHealthCheck;

/**
 * The per-probe budget wrapper, on its own terms: the direct
 * delegation without a runner, the runner's enforcement (the budget
 * it was handed, the answer it returns, the crash it contains, the
 * non-boolean answer it refuses), the positive-budget invariant, and
 * the group delegation that keeps a wrapped probe's declared view.
 *
 * @internal
 */
#[CoversClass(TimeoutHealthCheck::class)]
final class TimeoutHealthCheckTest extends TestCase
{
    public function testWithoutARunnerTheProbeRunsDirectly(): void
    {
        $healthy = new TimeoutHealthCheck(new FixedHealthCheck('cache', true));
        $unhealthy = new TimeoutHealthCheck(new FixedHealthCheck('database', false));

        self::assertTrue($healthy->isHealthy());
        self::assertFalse($unhealthy->isHealthy());
    }

    public function testTheNameDelegates(): void
    {
        self::assertSame('cache', new TimeoutHealthCheck(new FixedHealthCheck('cache', true))->name());
    }

    public function testTheRunnerReceivesTheProbeAndTheBudget(): void
    {
        $seenSeconds = [];
        $seenAnswers = [];
        $timeoutHealthCheck = new TimeoutHealthCheck(
            new FixedHealthCheck('cache', true),
            1.5,
            static function (callable $probe, float $seconds) use (&$seenSeconds, &$seenAnswers): bool {
                $seenSeconds[] = $seconds;
                $seenAnswers[] = $probe();

                return true;
            },
        );

        self::assertTrue($timeoutHealthCheck->isHealthy());
        self::assertSame([1.5], $seenSeconds, 'the runner was handed the declared budget');
        self::assertSame([true], $seenAnswers, 'the runner was handed the probe itself');
    }

    public function testTheRunnerAnswerIsTheProbeAnswer(): void
    {
        $timeoutHealthCheck = new TimeoutHealthCheck(
            new FixedHealthCheck('cache', true),
            1.0,
            static fn (): bool => false,
        );

        self::assertFalse($timeoutHealthCheck->isHealthy(), 'a runner that answers down answers down');
    }

    public function testTheRunnerCrashIsContainedAsDown(): void
    {
        $timeoutHealthCheck = new TimeoutHealthCheck(
            new FixedHealthCheck('cache', true),
            0.25,
            static function (): never {
                throw new RuntimeException('the deadline passed mid-probe');
            },
        );

        self::assertFalse($timeoutHealthCheck->isHealthy(), 'a runner timeout or crash answers down, never a throw');
    }

    public function testANonBooleanRunnerAnswerReadsAsDown(): void
    {
        $timeoutHealthCheck = new TimeoutHealthCheck(
            new FixedHealthCheck('cache', true),
            1.0,
            static fn (): string => 'truthy',
        );

        self::assertFalse($timeoutHealthCheck->isHealthy(), "the seam's own vocabulary is boolean alone");
    }

    public function testTheBudgetMustBePositive(): void
    {
        try {
            new TimeoutHealthCheck(new FixedHealthCheck('cache', true), 0);
            self::fail('A non-positive budget must halt the construction.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                'The health check timeout must be a positive number of seconds.',
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testTheWrappedGroupedProbeKeepsItsGroup(): void
    {
        $timeoutHealthCheck = new TimeoutHealthCheck(
            new FixedGroupedHealthCheck('worker.heartbeat', true, HealthGroups::GROUP_LIVE),
        );

        self::assertSame(HealthGroups::GROUP_LIVE, $timeoutHealthCheck->healthGroup());
    }

    public function testTheWrappedPlainProbeReadsAsReady(): void
    {
        $timeoutHealthCheck = new TimeoutHealthCheck(new FixedHealthCheck('cache', true));

        self::assertSame(HealthGroups::GROUP_READY, $timeoutHealthCheck->healthGroup());
    }
}
