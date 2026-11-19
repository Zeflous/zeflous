<?php

declare(strict_types=1);

namespace Zef\Test\Health;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Health\HealthAggregator;
use Zef\Framework\Health\HealthChecker;
use Zef\Framework\Health\HealthExposition;
use Zef\Framework\Health\HealthGroups;
use Zef\Framework\Health\HealthOutcome;
use Zef\Framework\Health\HealthProbe;
use Zef\Framework\Health\HealthScrapeClock;
use Zef\Framework\Health\HealthStatuses;
use Zef\Framework\Health\HealthVerdicts;
use Zef\Framework\Health\ProbeName;
use Zef\Test\Support\AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAFailureException as OverlongFailureException; // phpcs:ignore
use Zef\Test\Support\FixedGroupedHealthCheck;
use Zef\Test\Support\FixedHealthCheck;
use Zef\Test\Support\Http\SteppingClock;
use Zef\Test\Support\ThrowingHealthCheck; // phpcs:ignore
// phpcs:ignore
use Zef\Test\Support\ÜnïcödëÜnïcödëÜnïcödëÜnïcödëÜnïcödë\U11111111111111111111111111111111111111111111111111Exception as MultibyteOverlongFailureException;

/**
 * The health scrape, on its own terms: the status math over the
 * registry's probes, the group views (the plain check's own ready
 * default, the live declaration, the exact custom match), the
 * overall deadline's skip, the crash containment, the name
 * sanitisation, the zero-probe degraded posture, the tookMs the
 * frozen clock pins exactly, and the two renderings -- the campaign's
 * closing family's health half, over the already-live registry seam.
 *
 * @internal
 */
#[CoversClass(HealthAggregator::class)]
#[CoversClass(HealthChecker::class)]
#[CoversClass(HealthExposition::class)]
#[CoversClass(HealthGroups::class)]
#[CoversClass(HealthOutcome::class)]
#[CoversClass(HealthProbe::class)]
#[CoversClass(HealthVerdicts::class)]
#[CoversClass(HealthScrapeClock::class)]
#[CoversClass(HealthStatuses::class)]
#[CoversClass(ProbeName::class)]
final class HealthAggregatorTest extends TestCase
{
    public function testEveryPassingProbeAnswersOk(): void
    {
        $healthChecker = new HealthChecker();
        $healthChecker->add(new FixedHealthCheck('cache', true));
        $healthChecker->add(new FixedHealthCheck('queue', true));

        $report = $this->aggregator($healthChecker, epoch: 0, step: 2_000_000)->aggregate();

        self::assertSame(HealthStatuses::STATUS_OK, $report['status']);
        self::assertSame(
            [
                ['name' => 'cache', 'status' => 'up', 'message' => ''],
                ['name' => 'queue', 'status' => 'up', 'message' => ''],
            ],
            $report['checks'],
        );
        self::assertSame(2.0, $report['tookMs'], "the scrape spans the frozen clock's one step");
    }

    public function testAFailingProbeDegradesTheScrape(): void
    {
        $healthChecker = new HealthChecker();
        $healthChecker->add(new FixedHealthCheck('cache', true));
        $healthChecker->add(new FixedHealthCheck('database', false));

        $report = $this->aggregator($healthChecker, epoch: 0, step: 0)->aggregate();

        self::assertSame(HealthStatuses::STATUS_DEGRADED, $report['status']);
        self::assertSame('down', $report['checks'][1]['status']);
        self::assertSame(
            '',
            $report['checks'][1]['message'],
            "the boolean seam's own honest shape: no reason, just down",
        );
    }

    public function testACrashingProbeIsContainedAsDown(): void
    {
        $healthChecker = new HealthChecker();
        $healthChecker->add(new ThrowingHealthCheck());

        $report = $this->aggregator($healthChecker, epoch: 0, step: 0)->aggregate();

        self::assertSame(HealthStatuses::STATUS_DEGRADED, $report['status']);
        self::assertSame(
            [
                'name' => 'exploding',
                'status' => 'down',
                'message' => 'probe failure: RuntimeException',
            ],
            $report['checks'][0],
        );
    }

    public function testTheCrashContainmentTruncatesTheOverlongClassName(): void
    {
        $healthChecker = new HealthChecker();
        $healthChecker->add(new ThrowingHealthCheck('overlong', OverlongFailureException::class));

        $report = self::aggregator($healthChecker, epoch: 0, step: 0)->aggregate();

        self::assertSame(
            'probe failure: ' . mb_substr(OverlongFailureException::class, 0, 128),
            $report['checks'][0]['message'],
            "the failure's class carries at most its 128-character bound",
        );
    }

    public function testTheCrashContainmentReadsTheCharacterScaleNotTheByteScale(): void
    {
        // The multibyte twin of the overlong pin: a failure's class
        // carrying two-byte `Ü` characters across the containment
        // bound makes the character-position cut and the byte-position
        // cut disagree about where the bound lands -- only the
        // character scale keeps the record's own name readable, and
        // only a multibyte-bearing name can pin which scale the
        // containment reads.
        $healthChecker = new HealthChecker();
        $healthChecker->add(new ThrowingHealthCheck('multibyte', MultibyteOverlongFailureException::class));

        $report = self::aggregator($healthChecker, epoch: 0, step: 0)->aggregate();

        self::assertSame(
            'probe failure: ' . mb_substr(MultibyteOverlongFailureException::class, 0, 128),
            $report['checks'][0]['message'],
            'the containment cuts at its 128-character bound, never at the byte count a multibyte name carries',
        );
    }

    public function testTheOverrunRecordCarriesItsOwnProbeName(): void
    {
        // The overrun builder's own record battery: the name the
        // record carries is the probe's own, the status the record's
        // own -- the builder answers its verdicts directly, so the
        // record's shape never rides the judgement's arithmetic to
        // be observed.
        $record = HealthVerdicts::overran('cache');

        self::assertSame('cache', $record['name'], "the overrun record carries its own probe's name");
        self::assertSame(HealthStatuses::STATUS_DOWN, $record['status'], 'the overrun record is down');
        self::assertSame(
            'overran the scrape deadline: the answer the deadline can no longer vouch for',
            $record['message'],
            'the overrun record carries its own marker',
        );
    }

    public function testTheEmptyRegistryIsDegradedNeverGreen(): void
    {
        $report = $this->aggregator(new HealthChecker(), epoch: 0, step: 0)->aggregate();

        self::assertSame(HealthStatuses::STATUS_DEGRADED, $report['status']);
        self::assertSame(
            [
                [
                    'name' => 'health.indicators',
                    'status' => 'down',
                    'message' => "no health indicators registered for group 'all'",
                ],
            ],
            $report['checks'],
        );
    }

    public function testThePlainCheckReadsAsReady(): void
    {
        $healthChecker = new HealthChecker();
        $liveProbe = new FixedGroupedHealthCheck('worker.heartbeat', true, HealthGroups::GROUP_LIVE);
        $readyProbe = new FixedGroupedHealthCheck('database', true, HealthGroups::GROUP_READY);
        $fixedHealthCheck = new FixedHealthCheck('cache', true);
        $healthChecker->add($liveProbe);
        $healthChecker->add($readyProbe);
        $healthChecker->add($fixedHealthCheck);

        $report = $this->aggregator($healthChecker, epoch: 0, step: 0)->aggregateFor(HealthGroups::GROUP_READY);

        self::assertSame(HealthStatuses::STATUS_OK, $report['status']);
        self::assertSame(
            ['database', 'cache'],
            array_column($report['checks'], 'name'),
            'the ready view is everything the live declaration does not own',
        );
        self::assertSame(0, $liveProbe->probes, 'the live probe never ran');
    }

    public function testTheLiveViewIsTheDeclarationAlone(): void
    {
        $healthChecker = new HealthChecker();
        $liveProbe = new FixedGroupedHealthCheck('worker.heartbeat', true, HealthGroups::GROUP_LIVE);
        $readyProbe = new FixedGroupedHealthCheck('database', true, HealthGroups::GROUP_READY);
        $healthChecker->add($liveProbe);
        $healthChecker->add($readyProbe);

        $report = $this->aggregator($healthChecker, epoch: 0, step: 0)->aggregateFor(HealthGroups::GROUP_LIVE);

        self::assertSame(HealthStatuses::STATUS_OK, $report['status']);
        self::assertSame(['worker.heartbeat'], array_column($report['checks'], 'name'));
        self::assertSame(0, $readyProbe->probes, 'the ready probe never ran');
    }

    public function testACustomGroupMatchesItsDeclarationExactly(): void
    {
        $healthChecker = new HealthChecker();
        $healthChecker->add(new FixedGroupedHealthCheck('cache.warmup', true, 'startup'));
        $healthChecker->add(new FixedGroupedHealthCheck('database', true, HealthGroups::GROUP_READY));

        $report = $this->aggregator($healthChecker, epoch: 0, step: 0)->aggregateFor('startup');

        self::assertSame(HealthStatuses::STATUS_OK, $report['status']);
        self::assertSame(['cache.warmup'], array_column($report['checks'], 'name'));
    }

    public function testTheReadyViewAdmitsTheCustomDeclaration(): void
    {
        $healthChecker = new HealthChecker();
        $healthChecker->add(new FixedGroupedHealthCheck('cache.warmup', true, 'startup'));
        $healthChecker->add(new FixedGroupedHealthCheck('worker.heartbeat', true, HealthGroups::GROUP_LIVE));

        $report = self::aggregator($healthChecker, epoch: 0, step: 0)->aggregateFor(HealthGroups::GROUP_READY);

        self::assertSame(HealthStatuses::STATUS_OK, $report['status']);
        self::assertSame(
            ['cache.warmup'],
            array_column($report['checks'], 'name'),
            'the ready view is every declaration the live one does not own -- a custom declaration included',
        );
    }

    public function testAViewWithoutProbesAnswersTheZeroProbeMarker(): void
    {
        $healthChecker = new HealthChecker();
        $healthChecker->add(new FixedGroupedHealthCheck('database', true, HealthGroups::GROUP_READY));

        $report = $this->aggregator($healthChecker, epoch: 0, step: 0)->aggregateFor(HealthGroups::GROUP_LIVE);

        self::assertSame(HealthStatuses::STATUS_DEGRADED, $report['status']);
        self::assertSame(
            "no health indicators registered for group 'live'",
            $report['checks'][0]['message'],
        );
    }

    public function testTheDeadlineSkipsTheRemainingProbes(): void
    {
        $healthChecker = new HealthChecker();
        $healthChecker->add(new FixedHealthCheck('cache', true));
        $healthChecker->add(new FixedHealthCheck('queue', true));

        // The readings: 1_000_000_000 at the start; 1_400_000_000
        // before the first probe and 1_800_000_000 after it -- under
        // the 1s deadline over 1_000_000_000, so the first probe
        // answers its own verdict; 2_200_000_000 before the second
        // -- exactly past the deadline, the skip it owns.
        $report = $this->aggregator($healthChecker, epoch: 1_000_000_000, step: 400_000_000)
            ->aggregate(1000)
        ;

        self::assertSame(HealthStatuses::STATUS_DEGRADED, $report['status']);
        self::assertSame('up', $report['checks'][0]['status']);
        self::assertSame('cache', $report['checks'][0]['name']);
        self::assertSame('queue', $report['checks'][1]['name'], "the skipped record still carries its probe's name");
        self::assertSame(
            'skipped: overall health deadline exceeded',
            $report['checks'][1]['message'],
            'a probe at the deadline is skipped -- the boundary is inclusive',
        );
        self::assertSame(1600.0, $report['tookMs']);
    }

    /**
     * The probe that answers past the deadline (issue #788, finding
     * 3): the skip marker bounds the probes that never RAN -- the
     * probe that ran and ate the whole budget answers its own
     * overrun marker, down, because the response was already over
     * budget while it ran and the probe that ate the budget is the
     * reason the scrape degrades.
     */
    public function testAProbeThatAnswersPastTheDeadlineAnswersTheOverrunMarker(): void
    {
        $healthChecker = new HealthChecker();
        $healthChecker->add(new FixedHealthCheck('cache', true));
        $healthChecker->add(new FixedHealthCheck('queue', true));

        // The readings: 0 at the start; 400_000_000 before the first
        // probe (under the 500ms deadline over 0) and 800_000_000
        // after it -- past the deadline, the overrun the healthy
        // probe itself now answers; 1_200_000_000 before the second
        // -- the skip the remaining probe owns.
        $report = $this->aggregator($healthChecker, epoch: 0, step: 400_000_000)
            ->aggregate(500)
        ;

        self::assertSame(HealthStatuses::STATUS_DEGRADED, $report['status']);
        self::assertSame('down', $report['checks'][0]['status']);
        self::assertSame(
            'overran the scrape deadline: the answer the deadline can no longer vouch for',
            $report['checks'][0]['message'],
            'the probe that ate the whole budget answers its own overrun, never the green verdict',
        );
        self::assertSame(
            'skipped: overall health deadline exceeded',
            $report['checks'][1]['message'],
            'the probe behind the overrunner is the skip the deadline owns',
        );
    }

    /**
     * The boundary the inclusive comparisons own (the mutation
     * campaign's own demand): the probe whose BEFORE reading lands
     * exactly ON the deadline is the skip itself -- a strictly-
     * greater reading would have run the probe and answered its own
     * overrun instead, and the timeline freezes the exact landing.
     */
    public function testAProbeAtTheExactDeadlineBoundaryIsTheSkipItself(): void
    {
        $healthChecker = new HealthChecker();
        $healthChecker->add(new FixedHealthCheck('cache', true));

        // The readings: 0 at the start; 500_000_000 before the probe --
        // exactly the 500ms deadline over 0, the skip the boundary
        // owns.
        $report = $this->aggregator($healthChecker, epoch: 0, step: 500_000_000)
            ->aggregate(500)
        ;

        self::assertSame(
            'skipped: overall health deadline exceeded',
            $report['checks'][0]['message'],
            'a probe at the exact deadline boundary is the skip itself -- the boundary is inclusive',
        );
    }

    /**
     * The overrun's own boundary twin: the probe whose AFTER reading
     * lands exactly ON the deadline is the overrun itself -- the
     * answer the deadline can no longer vouch for includes the one
     * that landed AT it, and a strictly-greater reading would have
     * answered the green verdict instead.
     */
    public function testAProbeAnsweringAtTheExactDeadlineBoundaryIsTheOverrunItself(): void
    {
        $healthChecker = new HealthChecker();
        $healthChecker->add(new FixedHealthCheck('cache', true));

        // The readings: 0 at the start; 250_000_000 before the probe
        // (under the 500ms deadline over 0) and 500_000_000 after it
        // -- exactly the deadline, the overrun the probe itself now
        // answers.
        $report = $this->aggregator($healthChecker, epoch: 0, step: 250_000_000)
            ->aggregate(500)
        ;

        self::assertSame(
            'down',
            $report['checks'][0]['status'],
            'a probe answering at the exact deadline boundary is itself down',
        );
        self::assertSame(
            'overran the scrape deadline: the answer the deadline can no longer vouch for',
            $report['checks'][0]['message'],
            'the overrun boundary is inclusive -- the answer at the deadline is the overrun itself',
        );
    }

    public function testTheElapsedReportingCarriesThreeDecimals(): void
    {
        $healthChecker = new HealthChecker();
        $healthChecker->add(new FixedHealthCheck('cache', true));

        $report = self::aggregator($healthChecker, epoch: 0, step: 1_234_567)->aggregate();

        self::assertSame(1.235, $report['tookMs'], 'the three-decimal shape the report carries');
    }

    public function testAnUnboundedScrapeRunsEveryProbe(): void
    {
        $healthChecker = new HealthChecker();
        $healthChecker->add(new FixedHealthCheck('cache', true));
        $healthChecker->add(new FixedHealthCheck('queue', true));

        $report = $this->aggregator($healthChecker, epoch: 1_000_000_000, step: 500_000_000)->aggregate();

        self::assertSame(HealthStatuses::STATUS_OK, $report['status']);
        self::assertCount(2, $report['checks']);
    }

    public function testTheProbeNamesSanitiseIntoTheExpositionAlphabet(): void
    {
        $healthChecker = new HealthChecker();
        $healthChecker->add(new FixedHealthCheck('data base!', true));
        $healthChecker->add(new FixedHealthCheck('', true));
        $healthChecker->add(new FixedHealthCheck(str_repeat('x', 100), true));

        $report = $this->aggregator($healthChecker, epoch: 0, step: 0)->aggregate();

        self::assertSame('data_base_', $report['checks'][0]['name']);
        self::assertSame('unnamed', $report['checks'][1]['name']);
        self::assertSame(64, mb_strlen($report['checks'][2]['name']));
    }

    public function testTheJsonRenderingKeepsTheSlashesAndUnicodeVerbatim(): void
    {
        $report = [
            'status' => HealthStatuses::STATUS_OK,
            'checks' => [
                ['name' => 'cache/west', 'status' => HealthStatuses::STATUS_UP, 'message' => 'dégradé'],
            ],
            'tookMs' => 0.0,
        ];

        self::assertSame(
            '{"status":"ok","checks":[{"name":"cache/west","status":"up","message":"dégradé"}],"tookMs":0}',
            HealthExposition::json($report),
            'the renderings keep the slash and the unicode verbatim, never escaped',
        );
    }

    public function testTheJsonRenderingCarriesTheReport(): void
    {
        $healthChecker = new HealthChecker();
        $healthChecker->add(new FixedHealthCheck('cache', true));

        $json = HealthExposition::json(
            $this->aggregator($healthChecker, epoch: 0, step: 0)->aggregate(),
        );

        self::assertSame(
            '{"status":"ok","checks":[{"name":"cache","status":"up","message":""}],"tookMs":0}',
            $json,
        );
    }

    public function testThePrometheusRenderingCarriesTheGauges(): void
    {
        $healthChecker = new HealthChecker();
        $healthChecker->add(new FixedHealthCheck('cache', true));
        $healthChecker->add(new FixedHealthCheck('database', false));

        $render = HealthExposition::render(
            $this->aggregator($healthChecker, epoch: 0, step: 0)->aggregate(),
        );

        self::assertSame(
            "# HELP zef_health_check Outcome of each health probe (1 = up, 0 = down).\n"
            . "# TYPE zef_health_check gauge\n"
            . "zef_health_check{check=\"cache\"} 1\n"
            . "zef_health_check{check=\"database\"} 0\n"
            . "# HELP zef_health_status Aggregate health status (1 = ok, 0 = degraded).\n"
            . "# TYPE zef_health_status gauge\n"
            . "zef_health_status 0\n",
            $render,
        );
    }

    public function testThePrometheusRenderingOfAGreenRegistryCarriesOne(): void
    {
        $healthChecker = new HealthChecker();
        $healthChecker->add(new FixedHealthCheck('cache', true));

        $render = HealthExposition::render(
            $this->aggregator($healthChecker, epoch: 0, step: 0)->aggregate(),
        );

        self::assertStringContainsString("zef_health_check{check=\"cache\"} 1\n", $render);
        self::assertStringContainsString("zef_health_status 1\n", $render);
    }

    /**
     * The aggregator over the registry and a frozen monotonic clock.
     */
    private function aggregator(HealthChecker $healthChecker, int $epoch, int $step): HealthAggregator
    {
        $steppingClock = new SteppingClock($epoch, $step);

        return new HealthAggregator($healthChecker, $steppingClock->now());
    }
}
