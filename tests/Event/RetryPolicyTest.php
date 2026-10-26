<?php

declare(strict_types=1);

namespace Zef\Test\Event;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Event\RetryPolicy;

/**
 * Pins the retry arithmetic: the two construction refusals, the
 * attempts ceiling's boundary readings, and the exponential
 * backoff's own numbers -- the doubling per attempt past the
 * first, the zero-base degeneration, and the PHP_INT_MAX cap that
 * keeps a large base or a late attempt from ever overflowing into
 * a negative ready-at.
 *
 * @internal
 */
#[CoversClass(RetryPolicy::class)]
final class RetryPolicyTest extends TestCase
{
    public function testThePolicyReadsBackWhatItWasBuiltFrom(): void
    {
        $retryPolicy = new RetryPolicy(3, 10);

        self::assertSame(3, $retryPolicy->maxAttempts());
        self::assertSame(10, $retryPolicy->baseDelaySeconds());
    }

    public function testAnAttemptsCeilingBelowOneIsRefused(): void
    {
        try {
            new RetryPolicy(0, 10);
            self::fail('The zero-attempt policy was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                'A retry policy must permit at least one delivery attempt,'
                . ' got 0 -- a policy of zero would dead-letter a delivery'
                . ' that never tried.',
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testANegativeBaseDelayIsRefused(): void
    {
        try {
            new RetryPolicy(3, -1);
            self::fail('The negative base delay was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                "A retry policy's base delay cannot be negative, got -1.",
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testAttemptsRemainPermittedUntilTheCeilingIsSpent(): void
    {
        $retryPolicy = new RetryPolicy(3, 10);

        self::assertTrue($retryPolicy->permitsAnotherAttempt(1));
        self::assertTrue($retryPolicy->permitsAnotherAttempt(2));
        self::assertFalse($retryPolicy->permitsAnotherAttempt(3));
        self::assertFalse($retryPolicy->permitsAnotherAttempt(4));
    }

    public function testAPolicyOfOnePermitsNoSecondAttempt(): void
    {
        $retryPolicy = new RetryPolicy(1, 10);

        self::assertFalse($retryPolicy->permitsAnotherAttempt(1));
    }

    public function testTheBackoffDoublesOncePerAttemptPastTheFirst(): void
    {
        $retryPolicy = new RetryPolicy(5, 3);

        self::assertSame(1_003, $retryPolicy->readyAtForNextAttempt(1, 1_000));
        self::assertSame(1_006, $retryPolicy->readyAtForNextAttempt(2, 1_000));
        self::assertSame(1_012, $retryPolicy->readyAtForNextAttempt(3, 1_000));
        self::assertSame(1_024, $retryPolicy->readyAtForNextAttempt(4, 1_000));
    }

    public function testAZeroBaseIsDueAtTheNextDrain(): void
    {
        $retryPolicy = new RetryPolicy(3, 0);

        self::assertSame(500, $retryPolicy->readyAtForNextAttempt(1, 500));
        self::assertSame(500, $retryPolicy->readyAtForNextAttempt(2, 500));
    }

    public function testAHugeBackoffCapsAtTheIntegerMaximum(): void
    {
        $retryPolicy = new RetryPolicy(64, \PHP_INT_MAX);

        self::assertSame(\PHP_INT_MAX, $retryPolicy->readyAtForNextAttempt(1, 1));
        self::assertSame(\PHP_INT_MAX, $retryPolicy->readyAtForNextAttempt(63, 1));
    }

    public function testABackoffThatWouldOverflowAtTheDoublingCapsAtTheMaximum(): void
    {
        $base = (PHP_INT_MAX >> 1) + 1;
        $retryPolicy = new RetryPolicy(3, $base);

        self::assertSame($base, $retryPolicy->readyAtForNextAttempt(1, 0));
        self::assertSame(PHP_INT_MAX, $retryPolicy->readyAtForNextAttempt(2, 0));
    }

    public function testABackoffThatFitsAfterTheDoublingStillLandsExactly(): void
    {
        $base = (PHP_INT_MAX >> 2) + 1;
        $retryPolicy = new RetryPolicy(3, $base);

        self::assertSame(2 * $base, $retryPolicy->readyAtForNextAttempt(2, 0));
    }

    public function testABackoffAtTheExactHalvingBoundaryLandsOneShortOfTheMaximum(): void
    {
        $retryPolicy = new RetryPolicy(3, PHP_INT_MAX >> 1);

        self::assertSame(PHP_INT_MAX - 1, $retryPolicy->readyAtForNextAttempt(2, 0));
    }

    public function testABigButRoomyBackoffStillLandsExactly(): void
    {
        $base = 1 << 60;
        $retryPolicy = new RetryPolicy(3, $base);

        self::assertSame(1 + $base, $retryPolicy->readyAtForNextAttempt(1, 1));
        self::assertSame(1 + ($base << 1), $retryPolicy->readyAtForNextAttempt(2, 1));
    }
}
