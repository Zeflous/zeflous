<?php

declare(strict_types=1);

namespace Zef\Test\Http;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Http\ApcuCounterStore;
use Zef\Framework\Http\HttpException;
use Zef\Test\Support\Http\ApcuDoubleState;
use Zef\Test\Support\Http\ApcuFunctionDoubles;

/**
 * The APCu extension boundary, verified completely and
 * environment-agnostically: the boundary calls the apcu functions
 * unqualified, so PHP resolves them in the boundary's own namespace
 * first -- and the shared doubles ({@see ApcuFunctionDoubles}) shadow
 * the global calls in every environment, extension present or not. The
 * doubles count in the typed state ({@see ApcuDoubleState}), claim
 * add-only, and answer configurable corrupt values, so every branch is
 * the test's own data and the mutation score stays honest without a
 * single ignore entry.
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
        ApcuDoubleState::$counters = ['zef:ratelimit:count:abc' => 4];

        $apcuCounterStore = new ApcuCounterStore();

        self::assertSame(5, $apcuCounterStore->increment('zef:ratelimit:count:abc', 60));
        self::assertSame(
            [['zef:ratelimit:count:abc', 1, 60]],
            ApcuDoubleState::$incCalls,
            'the step is one and the TTL travels to the boundary',
        );
    }

    public function testACorruptIncrementAnswerFailsClosed(): void
    {
        ApcuDoubleState::$incAnswer = 'not-a-count';

        $apcuCounterStore = new ApcuCounterStore();

        $this->assertThrows(
            'The APCu accounting refused the increment.',
            static function () use ($apcuCounterStore): void {
                $apcuCounterStore->increment('zef:ratelimit:count:abc', 60);
            },
        );
    }

    /**
     * The extension present but disabled at runtime (a CLI worker
     * without `apc.enable_cli`) answers a failed step: the accounting
     * outage surfaces as the fail-closed refusal, never a fabricated
     * count of one.
     */
    public function testADisabledExtensionFailsTheIncrementClosed(): void
    {
        ApcuDoubleState::$incSuccess = false;

        $apcuCounterStore = new ApcuCounterStore();

        $this->assertThrows(
            'The APCu accounting refused the increment.',
            static function () use ($apcuCounterStore): void {
                $apcuCounterStore->increment('zef:ratelimit:count:abc', 60);
            },
        );
    }

    public function testASuccessfulEpochClaimAnswersTheClaimedEpochWithoutReadingBack(): void
    {
        ApcuDoubleState::$fetchAnswer = 999;

        $apcuCounterStore = new ApcuCounterStore();

        self::assertSame(160, $apcuCounterStore->claimResetEpoch('zef:ratelimit:reset:abc', 160, 60));
        self::assertSame(
            [['zef:ratelimit:reset:abc', 160, 60]],
            ApcuDoubleState::$addCalls,
        );
        self::assertSame([], ApcuDoubleState::$fetchCalls, 'a fresh claim never reads the store back');
    }

    public function testARefusedClaimReadsTheExistingEpochBack(): void
    {
        ApcuDoubleState::$entries = ['zef:ratelimit:reset:abc' => 140];

        $apcuCounterStore = new ApcuCounterStore();

        self::assertSame(140, $apcuCounterStore->claimResetEpoch('zef:ratelimit:reset:abc', 160, 60));
    }

    public function testACorruptExistingEpochFailsClosed(): void
    {
        ApcuDoubleState::$entries = ['zef:ratelimit:reset:abc' => 'not-an-epoch'];

        $apcuCounterStore = new ApcuCounterStore();

        $this->assertThrows(
            'The APCu accounting refused the reset epoch.',
            static function () use ($apcuCounterStore): void {
                $apcuCounterStore->claimResetEpoch('zef:ratelimit:reset:abc', 160, 60);
            },
        );
    }

    /**
     * A refused claim with nothing to read back -- the disabled-runtime
     * shape -- is the same accounting outage: the freshly computed
     * epoch is never fabricated into a claim.
     */
    public function testADisabledExtensionFailsTheClaimClosed(): void
    {
        ApcuDoubleState::$addSuccess = false;

        $apcuCounterStore = new ApcuCounterStore();

        $this->assertThrows(
            'The APCu accounting refused the reset epoch.',
            static function () use ($apcuCounterStore): void {
                $apcuCounterStore->claimResetEpoch('zef:ratelimit:reset:abc', 160, 60);
            },
        );
    }

    /**
     * @param callable(): void $operation
     */
    private function assertThrows(string $message, callable $operation): void
    {
        try {
            $operation();
            self::fail('HttpException expected.');
        } catch (HttpException $httpException) {
            self::assertSame($message, $httpException->getMessage());
        }
    }
}
