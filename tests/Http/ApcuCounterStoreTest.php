<?php

declare(strict_types=1);

namespace Zef\Test;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Http\ApcuCounterStore;

/**
 * The APCu extension boundary, verified completely: the real `apcu_*`
 * calls run inside a child process against eval-defined function doubles
 * (the test environment carries no APCu extension, and the doubles give
 * every branch a deterministic answer), so the boundary carries its own
 * coverage and its mutants are killed without a single ignore entry.
 *
 * The doubles record every call's arguments into `$GLOBALS` and answer
 * from `$GLOBALS`-configured returns, which is exactly the contract the
 * boundary needs: an atomic increment with its initialisation TTL, an
 * add-only epoch claim, and the corrupt-entry fallbacks.
 *
 * @internal
 */
#[CoversClass(ApcuCounterStore::class)]
final class ApcuCounterStoreTest extends TestCase
{
    #[RunInSeparateProcess]
    public function testTheIncrementDelegatesWithTheTtlAndAnswersTheCount(): void
    {
        $this->defineApcuDoubles();
        $GLOBALS['__apcu_inc'] = 5;

        $apcuCounterStore = new ApcuCounterStore();

        self::assertSame(5, $apcuCounterStore->increment('zef:ratelimit:count:abc', 60));
        self::assertSame(
            [['zef:ratelimit:count:abc', 1, 60]],
            $GLOBALS['__apcu_inc_calls'],
            'the step is one and the TTL travels to the extension',
        );
    }

    #[RunInSeparateProcess]
    public function testACorruptIncrementAnswerFailsClosedToOne(): void
    {
        $this->defineApcuDoubles();
        $GLOBALS['__apcu_inc'] = false;

        $apcuCounterStore = new ApcuCounterStore();

        self::assertSame(1, $apcuCounterStore->increment('zef:ratelimit:count:abc', 60));
    }

    #[RunInSeparateProcess]
    public function testASuccessfulEpochClaimAnswersTheClaimedEpoch(): void
    {
        $this->defineApcuDoubles();
        $GLOBALS['__apcu_add'] = true;
        $GLOBALS['__apcu_fetch'] = 999;

        $apcuCounterStore = new ApcuCounterStore();

        self::assertSame(160, $apcuCounterStore->claimResetEpoch('zef:ratelimit:reset:abc', 160, 60));
        self::assertSame(
            [['zef:ratelimit:reset:abc', 160, 60]],
            $GLOBALS['__apcu_add_calls'],
        );
        self::assertSame([], $GLOBALS['__apcu_fetch_calls'], 'a fresh claim never reads the store back');
    }

    #[RunInSeparateProcess]
    public function testARefusedClaimReadsTheExistingEpochBack(): void
    {
        $this->defineApcuDoubles();
        $GLOBALS['__apcu_add'] = false;
        $GLOBALS['__apcu_fetch'] = 140;

        $apcuCounterStore = new ApcuCounterStore();

        self::assertSame(140, $apcuCounterStore->claimResetEpoch('zef:ratelimit:reset:abc', 160, 60));
    }

    #[RunInSeparateProcess]
    public function testACorruptExistingEpochFailsClosedToTheClaimedEpoch(): void
    {
        $this->defineApcuDoubles();
        $GLOBALS['__apcu_add'] = false;
        $GLOBALS['__apcu_fetch'] = 'not-an-epoch';

        $apcuCounterStore = new ApcuCounterStore();

        self::assertSame(160, $apcuCounterStore->claimResetEpoch('zef:ratelimit:reset:abc', 160, 60));
    }

    /**
     * Defines the apcu function doubles in this child process: absent
     * from the environment, so the definition is the test's own; each
     * answers from the `$GLOBALS`-configured return and records its
     * arguments there.
     */
    private function defineApcuDoubles(): void
    {
        $doubles = <<<'PHP'
            namespace {
                if (!function_exists('apcu_inc')) {
                    function apcu_inc(string $key, int $step = 1, ?bool &$success = null, int $ttl = 0): int|false
                    {
                        $GLOBALS['__apcu_inc_calls'][] = [$key, $step, $ttl];
                        $success = true;

                        return $GLOBALS['__apcu_inc'] ?? 1;
                    }
                }

                if (!function_exists('apcu_add')) {
                    function apcu_add(string $key, mixed $value, int $ttl = 0): bool
                    {
                        $GLOBALS['__apcu_add_calls'][] = [$key, $value, $ttl];

                        return $GLOBALS['__apcu_add'] ?? true;
                    }
                }

                if (!function_exists('apcu_fetch')) {
                    function apcu_fetch(string $key, ?bool &$success = null): mixed
                    {
                        $GLOBALS['__apcu_fetch_calls'][] = [$key];
                        $success = true;

                        return $GLOBALS['__apcu_fetch'] ?? false;
                    }
                }
            }
            PHP;
        eval($doubles);

        $GLOBALS['__apcu_inc_calls'] = [];
        $GLOBALS['__apcu_add_calls'] = [];
        $GLOBALS['__apcu_fetch_calls'] = [];
    }
}
