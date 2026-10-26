<?php

declare(strict_types=1);

namespace Zef\Test\Event;

use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Zef\Framework\Event\DeadLetter;
use Zef\Framework\Event\DeferredDelivery;
use Zef\Framework\Event\Event;
use Zef\Framework\Event\EventName;
use Zef\Framework\Event\EventPayload;
use Zef\Framework\Event\FailureVerdict;

/**
 * Pins the failure verdict's own shape: the two named constructors,
 * the predicate-gated readers, and the misuse guards -- reading a
 * verdict against its own shape is the caller's bug, and the guards
 * say so loudly instead of answering a null.
 *
 * @internal
 */
#[CoversClass(FailureVerdict::class)]
final class FailureVerdictTest extends TestCase
{
    public function testARetryVerdictReadsBackItsEntry(): void
    {
        $retryEntry = $this->retryEntry();
        $verdict = FailureVerdict::retry($retryEntry);

        self::assertTrue($verdict->isRetry());
        self::assertSame($retryEntry, $verdict->retryEntry());
        self::assertFalse($verdict->quarantines());
    }

    public function testADeathVerdictReadsBackItsLetterAndQuarantine(): void
    {
        $deadLetter = $this->deadLetter();
        $verdict = FailureVerdict::death($deadLetter, true);

        self::assertFalse($verdict->isRetry());
        self::assertSame($deadLetter, $verdict->deadLetter());
        self::assertTrue($verdict->quarantines());
    }

    public function testADeathVerdictMayCarryNoQuarantine(): void
    {
        $verdict = FailureVerdict::death($this->deadLetter(), false);

        self::assertFalse($verdict->quarantines());
    }

    public function testReadingTheRetryEntryOfADeathVerdictIsRefused(): void
    {
        $verdict = FailureVerdict::death($this->deadLetter(), true);

        try {
            $verdict->retryEntry();
            self::fail('The death verdict answered a retry entry.');
        } catch (LogicException $logicException) {
            self::assertSame(
                'The verdict is not a retry; read it through isRetry().',
                $logicException->getMessage(),
            );
        }
    }

    public function testReadingTheDeadLetterOfARetryVerdictIsRefused(): void
    {
        $verdict = FailureVerdict::retry($this->retryEntry());

        try {
            $verdict->deadLetter();
            self::fail('The retry verdict answered a dead letter.');
        } catch (LogicException $logicException) {
            self::assertSame(
                'The verdict is not a death; read it through isRetry().',
                $logicException->getMessage(),
            );
        }
    }

    public function testTheRetryVerdictNeverQuarantines(): void
    {
        // The drain never reads the quarantine flag on a retry path
        // (it continues past the verdict first); the pin is the VO's
        // own contract, stated here so a mutation of the flag cannot
        // hide behind the drain's control flow.
        self::assertFalse(FailureVerdict::retry($this->retryEntry())->quarantines());
    }

    private function retryEntry(): DeferredDelivery
    {
        return new DeferredDelivery(
            new Event(new EventName('user.created'), new EventPayload(['userId' => 7])),
            new RecordingListener(new EventLog(), 'x'),
            2,
            1_010,
        );
    }

    private function deadLetter(): DeadLetter
    {
        return new DeadLetter(
            new EventName('user.created'),
            new RecordingListener(new EventLog(), 'x'),
            3,
            RuntimeException::class,
            'the listener failed',
            5_000,
        );
    }
}
