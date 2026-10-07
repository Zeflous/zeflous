<?php

declare(strict_types=1);

namespace Zef\Test;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Health\HealthChecker;
use Zef\Framework\Health\HealthReport;
use Zef\Test\Support\FixedHealthCheck;

/**
 * @internal
 */
#[CoversClass(HealthChecker::class)]
#[CoversClass(HealthReport::class)]
final class HealthTest extends TestCase
{
    public function testAnEmptyReportIsHealthy(): void
    {
        self::assertTrue(new HealthChecker()->report()->isHealthy());
    }

    public function testCountStartsAtZero(): void
    {
        self::assertSame(0, new HealthChecker()->count());
    }

    public function testCountReflectsTheRegisteredChecks(): void
    {
        $healthChecker = new HealthChecker();
        $healthChecker->add(new FixedHealthCheck('a', true));
        $healthChecker->add(new FixedHealthCheck('b', true));

        self::assertSame(2, $healthChecker->count());
    }

    public function testAddingTheSameNameTwiceOverwrites(): void
    {
        $healthChecker = new HealthChecker();
        $healthChecker->add(new FixedHealthCheck('a', true));
        $healthChecker->add(new FixedHealthCheck('a', true));

        self::assertSame(1, $healthChecker->count());
    }

    public function testReRegisteringANameReplacesInPlaceAndKeepsOrder(): void
    {
        $healthChecker = new HealthChecker();
        $healthChecker->add(new FixedHealthCheck('a', true));
        $healthChecker->add(new FixedHealthCheck('b', true));
        $healthChecker->add(new FixedHealthCheck('a', false));

        self::assertSame(2, $healthChecker->count());
        self::assertSame(['a' => false, 'b' => true], $healthChecker->report()->checks());
    }

    public function testAllHealthyChecksProduceAHealthyReport(): void
    {
        $healthChecker = new HealthChecker();
        $healthChecker->add(new FixedHealthCheck('a', true));

        $report = $healthChecker->report();

        self::assertTrue($report->isHealthy());
        self::assertSame(['a' => true], $report->checks());
    }

    public function testOneUnhealthyCheckMakesTheReportUnhealthy(): void
    {
        $healthChecker = new HealthChecker();
        $healthChecker->add(new FixedHealthCheck('ok', true));
        $healthChecker->add(new FixedHealthCheck('bad', false));

        $report = $healthChecker->report();

        self::assertFalse($report->isHealthy());
        self::assertSame(['ok' => true, 'bad' => false], $report->checks());
    }

    public function testTheReportKeyIsTheCheckName(): void
    {
        $healthChecker = new HealthChecker();
        $healthChecker->add(new FixedHealthCheck('database', true));

        self::assertSame(['database' => true], $healthChecker->report()->checks());
    }
}
