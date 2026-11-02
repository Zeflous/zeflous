<?php

declare(strict_types=1);

namespace Zef\Test\Database;

use InvalidArgumentException;
use LogicException;
use PDOException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Zef\Framework\Database\RetryBackoff;
use Zef\Framework\Database\RetryCandidates;
use Zef\Framework\Database\RetryTaxonomy;
use Zef\Framework\Database\UnitOfWorkRetryPolicy;
use Zef\Test\Support\Database\ArmedPdoException;

/**
 * The UoW retry policy value object: validation, backoff shape, and the
 * transient-failure taxonomy (retryable class names + SQLSTATE codes,
 * including the MySQL integer-driver-code normalisation).
 *
 * @internal
 */
#[CoversClass(UnitOfWorkRetryPolicy::class)]
#[CoversClass(RetryTaxonomy::class)]
#[CoversClass(RetryCandidates::class)]
#[CoversClass(RetryBackoff::class)]
final class UnitOfWorkRetryPolicyTest extends TestCase
{
    public function testDefaultConstructsSanely(): void
    {
        $unitOfWorkRetryPolicy = new UnitOfWorkRetryPolicy();

        self::assertSame(3, $unitOfWorkRetryPolicy->maxAttempts);
        self::assertSame(100, $unitOfWorkRetryPolicy->initialDelayMs);
        self::assertSame(30_000, $unitOfWorkRetryPolicy->maxDelayMs);
        self::assertSame(2.0, $unitOfWorkRetryPolicy->multiplier);
        self::assertSame(0, $unitOfWorkRetryPolicy->jitterMs);
        self::assertContains('PDOException', $unitOfWorkRetryPolicy->retryableClassNames);
        self::assertContains('40001', $unitOfWorkRetryPolicy->retryableSqlStates);
        self::assertContains('40P01', $unitOfWorkRetryPolicy->retryableSqlStates);
        self::assertSame(
            UnitOfWorkRetryPolicy::DEFAULT_RETRYABLE_SQL_STATES,
            $unitOfWorkRetryPolicy->retryableSqlStates,
            'the constructor default duplicates the public constant',
        );
    }

    public function testInvalidMaxAttemptsThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new UnitOfWorkRetryPolicy(maxAttempts: 0);
    }

    public function testInvalidInitialDelayThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new UnitOfWorkRetryPolicy(initialDelayMs: -1);
    }

    public function testInvalidMaxDelayThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new UnitOfWorkRetryPolicy(initialDelayMs: 100, maxDelayMs: 50);
    }

    public function testInvalidMultiplierThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new UnitOfWorkRetryPolicy(multiplier: 0.5);
    }

    public function testInvalidJitterThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new UnitOfWorkRetryPolicy(jitterMs: -10);
    }

    public function testShouldRetryUntilExhausted(): void
    {
        $unitOfWorkRetryPolicy = new UnitOfWorkRetryPolicy(maxAttempts: 3);
        self::assertTrue($unitOfWorkRetryPolicy->shouldRetry(1), '1st attempt -> retry possible');
        self::assertTrue($unitOfWorkRetryPolicy->shouldRetry(2), '2nd attempt -> retry possible');
        self::assertFalse($unitOfWorkRetryPolicy->shouldRetry(3), '3rd attempt = maxAttempts -> no retry');
        self::assertFalse($unitOfWorkRetryPolicy->shouldRetry(99));
    }

    public function testDelayMsGrowsExponentiallyAndCapsAtMax(): void
    {
        $unitOfWorkRetryPolicy = new UnitOfWorkRetryPolicy(
            maxAttempts: 5,
            initialDelayMs: 100,
            maxDelayMs: 1_000,
            multiplier: 2.0,
            jitterMs: 0,
        );
        self::assertSame(100, $unitOfWorkRetryPolicy->delayMs(1));
        self::assertSame(200, $unitOfWorkRetryPolicy->delayMs(2));
        self::assertSame(400, $unitOfWorkRetryPolicy->delayMs(3));
        self::assertSame(800, $unitOfWorkRetryPolicy->delayMs(4));
        self::assertSame(1_000, $unitOfWorkRetryPolicy->delayMs(5), 'capped at maxDelayMs');
    }

    public function testDelayMsRejectsNonPositiveAttempt(): void
    {
        $unitOfWorkRetryPolicy = new UnitOfWorkRetryPolicy();
        $this->expectException(InvalidArgumentException::class);
        $unitOfWorkRetryPolicy->delayMs(0);
    }

    public function testJitterAddsWithinItsBudgetAndStaysCapped(): void
    {
        $unitOfWorkRetryPolicy = new UnitOfWorkRetryPolicy(
            maxAttempts: 2,
            initialDelayMs: 90,
            maxDelayMs: 100,
            multiplier: 1.0,
            jitterMs: 20,
        );

        $delay = $unitOfWorkRetryPolicy->delayMs(1);
        self::assertGreaterThanOrEqual(90, $delay);
        self::assertLessThanOrEqual(100, $delay, 'the cap holds even after jitter');
    }

    public function testMultiplierOneKeepsTheDelayFlat(): void
    {
        $unitOfWorkRetryPolicy = new UnitOfWorkRetryPolicy(maxAttempts: 3, initialDelayMs: 50, multiplier: 1.0);
        self::assertSame(50, $unitOfWorkRetryPolicy->delayMs(1));
        self::assertSame(50, $unitOfWorkRetryPolicy->delayMs(2));
        self::assertSame(50, $unitOfWorkRetryPolicy->delayMs(3));
    }

    public function testIsRetryableMatchesClassAndSqlState(): void
    {
        $unitOfWorkRetryPolicy = new UnitOfWorkRetryPolicy();
        $retryable = $this->makePdoException('deadlock', '40P01');
        self::assertTrue($unitOfWorkRetryPolicy->isRetryable($retryable));
    }

    public function testIsRetryableRejectsNonRetryableSqlState(): void
    {
        $unitOfWorkRetryPolicy = new UnitOfWorkRetryPolicy();
        $syntaxError = $this->makePdoException('syntax error', '42601');
        self::assertFalse($unitOfWorkRetryPolicy->isRetryable($syntaxError));
    }

    /**
     * Regression: MySQL surfaces transient failures as INT driver codes
     * (getCode() = 1213, errorInfo[1] = 1213) while the policy stores the
     * taxonomy as SQLSTATE strings -- the strict comparison only matches
     * after the candidates are normalised to strings. Before that
     * normalisation, MySQL deadlocks silently never retried.
     */
    public function testIsRetryableMatchesMysqlIntegerDriverCode(): void
    {
        $unitOfWorkRetryPolicy = new UnitOfWorkRetryPolicy();
        $armedPdoException = new ArmedPdoException(
            'Deadlock found when trying to get lock',
            1213,
            ['HY000', 1213, 'Deadlock found when trying to get lock'],
        );
        self::assertTrue($unitOfWorkRetryPolicy->isRetryable($armedPdoException));
    }

    public function testIsRetryableRejectsUnknownClass(): void
    {
        $unitOfWorkRetryPolicy = new UnitOfWorkRetryPolicy();
        $logicException = new LogicException('boom');
        self::assertFalse($unitOfWorkRetryPolicy->isRetryable($logicException));
    }

    public function testIsRetryableWhenSqlStatesListEmptyMatchesClassOnly(): void
    {
        $unitOfWorkRetryPolicy = new UnitOfWorkRetryPolicy(retryableSqlStates: []);
        $any = $this->makePdoException('whatever', 'XX999');
        self::assertTrue($unitOfWorkRetryPolicy->isRetryable($any));
    }

    /**
     * The errorInfo slots that carry neither a string nor an int (the
     * null a sparsely-populated driver answer leaves behind) are
     * skipped, never normalised: the remaining slots still answer their
     * candidates.
     */
    public function testTheSlotsThatCarryNeitherStringNorIntAreSkipped(): void
    {
        $unitOfWorkRetryPolicy = new UnitOfWorkRetryPolicy();
        $sparse = new ArmedPdoException('deadlock', 0, ['40P01', null, 'deadlock']);
        self::assertTrue($unitOfWorkRetryPolicy->isRetryable($sparse));

        $unhelpful = new ArmedPdoException('whatever', 0, [null, null, 'whatever']);
        self::assertFalse(
            $unitOfWorkRetryPolicy->isRetryable($unhelpful),
            'no slot answers a retryable state, and getCode() answers 0',
        );
    }

    public function testASingleAttemptPolicyIsValid(): void
    {
        // One attempt is the one-shot posture -- a legal spelling, not
        // an invalid one.
        $unitOfWorkRetryPolicy = new UnitOfWorkRetryPolicy(maxAttempts: 1, initialDelayMs: 0, maxDelayMs: 0);
        self::assertFalse($unitOfWorkRetryPolicy->shouldRetry(1), 'the only attempt already spent');
    }

    public function testACapEqualToTheInitialDelayIsValid(): void
    {
        // A flat backoff: the cap equals the initial delay, every wait
        // clamps immediately -- legal, not a mis-declared knob.
        $unitOfWorkRetryPolicy = new UnitOfWorkRetryPolicy(initialDelayMs: 100, maxDelayMs: 100, multiplier: 2.0);
        self::assertSame(100, $unitOfWorkRetryPolicy->delayMs(1));
        self::assertSame(100, $unitOfWorkRetryPolicy->delayMs(2));
        self::assertSame(100, $unitOfWorkRetryPolicy->delayMs(5));
    }

    public function testTheDriverCodeAloneAnswersTheState(): void
    {
        // getCode() is the ONLY slot a plain driver failure populates
        // (no errorInfo array at all): the int driver code normalises
        // to the taxonomy's string form on its own.
        $unitOfWorkRetryPolicy = new UnitOfWorkRetryPolicy();
        self::assertTrue($unitOfWorkRetryPolicy->isRetryable(new PDOException('deadlock', 1213)));
    }

    public function testTheDriverCodeAloneRefusesANonTransientState(): void
    {
        $unitOfWorkRetryPolicy = new UnitOfWorkRetryPolicy();
        self::assertFalse($unitOfWorkRetryPolicy->isRetryable(new PDOException('syntax error', 42601)));
    }

    public function testAFractionalBackoffRoundsToTheNearestMillisecond(): void
    {
        // 25 * 1.5^1 = 37.5 -> 38 (round, neither floor's 37 nor
        // ceil's disagreement at the next step: 25 * 1.5^2 = 56.25
        // -> 56, which ceil would answer as 57).
        $unitOfWorkRetryPolicy = new UnitOfWorkRetryPolicy(maxAttempts: 3, initialDelayMs: 25, multiplier: 1.5);
        self::assertSame(38, $unitOfWorkRetryPolicy->delayMs(2));
        self::assertSame(56, $unitOfWorkRetryPolicy->delayMs(3));
    }

    public function testTheJitterDrawsStayWithinTheirBudgetAndTouchBothEdges(): void
    {
        // jitter of exactly one millisecond: every draw answers the
        // initial delay or one past it, and both edges occur across
        // the corridor -- the zero-arm the mutant that excludes it
        // would erase, and the plus-one the mutant that subtracts
        // would dig under.
        $unitOfWorkRetryPolicy = new UnitOfWorkRetryPolicy(
            initialDelayMs: 50,
            maxDelayMs: 60,
            multiplier: 1.0,
            jitterMs: 1,
        );
        $sawInitial = false;
        $sawOnePast = false;
        for ($i = 0; $i < 300; ++$i) {
            $draw = $unitOfWorkRetryPolicy->delayMs(1);
            self::assertContains($draw, [50, 51], 'every draw stays inside the jitter budget');
            $sawInitial = $sawInitial || $draw === 50;
            $sawOnePast = $sawOnePast || $draw === 51;
        }

        self::assertTrue($sawInitial, 'the zero arm of the jitter is reachable');
        self::assertTrue($sawOnePast, 'the plus-one arm of the jitter is reachable');
    }

    public function testANonPdoThrowableIsNotProbedForErrorInfo(): void
    {
        // A class-name match against a non-PDO throwable must judge
        // the SQLSTATE candidates without ever touching the
        // PDOException-only errorInfo slot.
        $unitOfWorkRetryPolicy = new UnitOfWorkRetryPolicy(
            retryableClassNames: [RuntimeException::class],
            retryableSqlStates: ['1234'],
        );
        self::assertFalse($unitOfWorkRetryPolicy->isRetryable(new RuntimeException('not a driver failure')));
    }

    public function testTheEmptyStringStateIsNotAnsweredBySkippedSlots(): void
    {
        // A skipped slot contributes nothing -- not even an empty
        // string that a policy weird enough to retry on '' would
        // match.
        $unitOfWorkRetryPolicy = new UnitOfWorkRetryPolicy(retryableSqlStates: ['']);
        $sparse = self::makePdoException('whatever', 'XX999');
        $sparse->errorInfo = [null, null, 'whatever'];
        self::assertFalse($unitOfWorkRetryPolicy->isRetryable($sparse));
    }

    public function testASlotAfterASkippedOneStillAnswers(): void
    {
        // offset 0 carries a slot the guards skip; offset 1 carries
        // the retryable state -- the walk must continue past the skip.
        $unitOfWorkRetryPolicy = new UnitOfWorkRetryPolicy();
        $sparse = self::makePdoException('deadlock', 'XX999');
        $sparse->errorInfo = [null, '40P01', 'deadlock'];
        self::assertTrue($unitOfWorkRetryPolicy->isRetryable($sparse));
    }

    public function testCustomRetryableClassNames(): void
    {
        $unitOfWorkRetryPolicy = new UnitOfWorkRetryPolicy(
            retryableClassNames: [RuntimeException::class],
            retryableSqlStates: [],
        );
        self::assertTrue($unitOfWorkRetryPolicy->isRetryable(new RuntimeException('retry me')));
        self::assertFalse($unitOfWorkRetryPolicy->isRetryable(new LogicException('not me')));
    }

    public function testErrorInfoSecondSlotAnswersTheSqlStateToo(): void
    {
        $unitOfWorkRetryPolicy = new UnitOfWorkRetryPolicy();
        // getCode() answers a non-matching code; the driver code in
        // errorInfo[1] carries the retryable one (the MySQL spelling).
        $armedPdoException = new ArmedPdoException('lock wait timeout', 9999, ['HY000', 1205, 'lock wait timeout']);
        self::assertTrue($unitOfWorkRetryPolicy->isRetryable($armedPdoException));
    }

    /**
     * Build a testable PDOException with a SQLSTATE string. Real
     * PDOExceptions are populated by PHP internals at runtime; the public
     * constructor only accepts an int $code, so we extend the class and
     * populate errorInfo directly.
     */
    private function makePdoException(string $message, string $sqlState): ArmedPdoException
    {
        return new ArmedPdoException($message, 0, [$sqlState, 0, $message]);
    }
}
