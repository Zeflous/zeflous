<?php

declare(strict_types=1);

namespace Zef\Test\Http;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Http\ApcuReplayProtector;
use Zef\Framework\Http\ApcuReplayStore;
use Zef\Framework\Http\HttpException;
use Zef\Framework\Http\ReplayDecision;
use Zef\Test\Support\Http\ApcuDoubleState;
use Zef\Test\Support\Http\ApcuFunctionDoubles;

/**
 * The shared APCu replay protector, verified completely and
 * environment-agnostically: the sight boundary calls the apcu
 * functions unqualified, so the shared doubles
 * ({@see ApcuFunctionDoubles}) shadow the global calls in every
 * environment, extension present or not -- the same doctrine the
 * rate-limit counter boundary's own battery keeps. The verdicts under
 * test are the process-tree semantics the audit demanded: one atomic
 * first-sight claim per replay id across every worker, the window the
 * claim's own TTL carries, and the fail-closed outage a refused claim
 * that reads nothing back answers.
 *
 * @internal
 */
#[CoversClass(ApcuReplayProtector::class)]
#[CoversClass(ApcuReplayStore::class)]
final class ApcuReplayProtectorTest extends TestCase
{
    #[Override]
    protected function setUp(): void
    {
        ApcuFunctionDoubles::define();
    }

    public function testANullIdNeedsNoProtection(): void
    {
        self::assertSame(
            ReplayDecision::NOT_REQUIRED,
            new ApcuReplayProtector()->check(null, 1_000),
        );
    }

    public function testAMalformedIdIsRejected(): void
    {
        self::assertSame(
            ReplayDecision::REJECTED,
            new ApcuReplayProtector()->check(str_repeat('a', 200), 1_000),
        );
    }

    public function testTheFirstSightIsAcceptedAndClaimsTheSharedTable(): void
    {
        $apcuReplayProtector = new ApcuReplayProtector(300_000);

        $decision = $apcuReplayProtector->check('replay-1', 1_000);

        self::assertSame(ReplayDecision::ACCEPT, $decision);
        self::assertSame(
            [['zef.replay.replay-1', 1_000, 300]],
            ApcuDoubleState::$addCalls,
            'the claim is one atomic add whose TTL is the window in whole seconds',
        );
        self::assertSame([], ApcuDoubleState::$fetchCalls, 'a won claim never reads the store back');
    }

    public function testTheWindowRoundsUpSoItIsNeverSilentlyShortened(): void
    {
        new ApcuReplayProtector(301_000)->check('replay-1', 1_000);

        self::assertSame(301, ApcuDoubleState::$addCalls[0][2], '301 seconds, the ceiling of 301 000 ms');
    }

    public function testASecondWorkerClaimingTheSameIdAnswersTheDuplicate(): void
    {
        // The audit's defect shape: the second WORKER, not the second
        // request to one worker. The shared table refuses it.
        $firstWorker = new ApcuReplayProtector(300_000);
        $secondWorker = new ApcuReplayProtector(300_000);

        self::assertSame(ReplayDecision::ACCEPT, $firstWorker->check('replay-1', 1_000));
        self::assertSame(ReplayDecision::DUPLICATE, $secondWorker->check('replay-1', 1_500));
    }

    public function testAnExpiredSightIsFreshAgain(): void
    {
        $apcuReplayProtector = new ApcuReplayProtector(300_000);

        self::assertSame(ReplayDecision::ACCEPT, $apcuReplayProtector->check('replay-1', 1_000));

        // The TTL expired the sight: a later sight is a fresh one again,
        // exactly as the in-memory table's cutoff makes it.
        ApcuDoubleState::$entries = [];

        self::assertSame(ReplayDecision::ACCEPT, $apcuReplayProtector->check('replay-1', 400_000));
    }

    public function testARefusedClaimThatReadsNothingBackIsTheFailClosedOutage(): void
    {
        ApcuDoubleState::$addSuccess = false;

        $decision = new ApcuReplayProtector(300_000)->check('replay-1', 1_000);

        self::assertSame(ReplayDecision::UNAVAILABLE, $decision, 'an outage is never an unaccounted admission');
    }

    public function testACorruptEntryAnswersTheOutageToo(): void
    {
        ApcuDoubleState::$entries = ['zef.replay.replay-1' => 'not-a-clock'];

        $decision = new ApcuReplayProtector(300_000)->check('replay-1', 1_000);

        self::assertSame(ReplayDecision::UNAVAILABLE, $decision, 'a corrupt sight is an outage, never a duplicate');
    }

    public function testAWindowBelowOneMillisecondIsRefused(): void
    {
        $this->expectException(HttpException::class);

        new ApcuReplayProtector(0);
    }

    /**
     * The window's round-up boundary series, pinned at every edge the
     * padding can cross: 999 ms and 1 000 ms both round up to one
     * second (the second is the ceiling of 1 999 and the exact 1 999),
     * 1 001 ms and 1 002 ms both answer two seconds. A padding one
     * short or a divisor one off moves an edge, and the series sees it.
     */
    public function testTheWindowRoundsUpAtEveryEdgeThePaddingCanCross(): void
    {
        $edges = [
            999 => 1,
            1_000 => 1,
            1_001 => 2,
            1_002 => 2,
        ];

        foreach ($edges as $windowMs => $expectedTtl) {
            ApcuFunctionDoubles::define();
            new ApcuReplayProtector($windowMs)->check('replay-' . $windowMs, 1_000);

            self::assertSame(
                $expectedTtl,
                ApcuDoubleState::$addCalls[0][2],
                \sprintf('a %d ms window claims the table for %d second(s)', $windowMs, $expectedTtl),
            );
        }
    }

    public function testTheDefaultWindowClaimsTheSharedTableForThreeHundredSeconds(): void
    {
        new ApcuReplayProtector()->check('replay-default', 1_000);

        self::assertSame(
            [['zef.replay.replay-default', 1_000, 300]],
            ApcuDoubleState::$addCalls,
            'the default five-minute window is the 300-second TTL the default ctor hands the store',
        );
    }

    public function testAOneMillisecondWindowIsAdmittedAndClaimsForOneSecond(): void
    {
        $decision = new ApcuReplayProtector(1)->check('replay-one', 1_000);

        self::assertSame(ReplayDecision::ACCEPT, $decision);
        self::assertSame(
            1,
            ApcuDoubleState::$addCalls[0][2],
            'the smallest admitted window still claims a whole second',
        );
    }
}
