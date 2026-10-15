<?php

declare(strict_types=1);

namespace Zef\Test;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Http\ApcuCounterStore;
use Zef\Test\Support\Http\ApcuFunctionDoubles;

/**
 * The APCu extension boundary, verified completely and
 * environment-agnostically: the boundary calls the apcu functions
 * unqualified, so PHP resolves them in the boundary's own namespace
 * first -- and the shared doubles ({@see ApcuFunctionDoubles}) shadow
 * the global calls in every environment, extension present or not. The
 * doubles count in `$GLOBALS`, claim add-only, and answer configurable
 * corrupt values, so every branch is the test's own data and the mutation
 * score stays honest without a single ignore entry.
 *
 * @internal
 */
#[CoversClass(ApcuCounterStore::class)]
final class ApcuCounterStoreTest extends TestCase
{
    #[Override]
    protected function setUp(): void
    {
        ApcuFunctionDoubles::define();
    }

    public function testTheIncrementDelegatesWithTheTtlAndAnswersTheCount(): void
    {
        $GLOBALS['__apcu_counters'] = ['zef:ratelimit:count:abc' => 4];

        $apcuCounterStore = new ApcuCounterStore();

        self::assertSame(5, $apcuCounterStore->increment('zef:ratelimit:count:abc', 60));
        self::assertSame(
            [['zef:ratelimit:count:abc', 1, 60]],
            $GLOBALS['__apcu_inc_calls'],
            'the step is one and the TTL travels to the boundary',
        );
    }

    public function testACorruptIncrementAnswerFailsClosedToOne(): void
    {
        $GLOBALS['__apcu_inc_answer'] = 'not-a-count';

        $apcuCounterStore = new ApcuCounterStore();

        self::assertSame(1, $apcuCounterStore->increment('zef:ratelimit:count:abc', 60));
    }

    public function testASuccessfulEpochClaimAnswersTheClaimedEpochWithoutReadingBack(): void
    {
        $GLOBALS['__apcu_fetch_answer'] = 999;

        $apcuCounterStore = new ApcuCounterStore();

        self::assertSame(160, $apcuCounterStore->claimResetEpoch('zef:ratelimit:reset:abc', 160, 60));
        self::assertSame(
            [['zef:ratelimit:reset:abc', 160, 60]],
            $GLOBALS['__apcu_add_calls'],
        );
        self::assertSame([], $GLOBALS['__apcu_fetch_calls'], 'a fresh claim never reads the store back');
    }

    public function testARefusedClaimReadsTheExistingEpochBack(): void
    {
        $GLOBALS['__apcu_entries'] = ['zef:ratelimit:reset:abc' => 140];

        $apcuCounterStore = new ApcuCounterStore();

        self::assertSame(140, $apcuCounterStore->claimResetEpoch('zef:ratelimit:reset:abc', 160, 60));
    }

    public function testACorruptExistingEpochFailsClosedToTheClaimedEpoch(): void
    {
        $GLOBALS['__apcu_entries'] = ['zef:ratelimit:reset:abc' => 'not-an-epoch'];

        $apcuCounterStore = new ApcuCounterStore();

        self::assertSame(160, $apcuCounterStore->claimResetEpoch('zef:ratelimit:reset:abc', 160, 60));
    }
}
