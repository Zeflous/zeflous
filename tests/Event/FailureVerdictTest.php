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
 * say so loudly instead of answering a null. The verdict carries no
 * quarantine verdict of its own: the crossing is the book's own
 * read, taken after the letter is appended (or attributed), so
 * nothing here pins one.
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
    }

    public function testADeathVerdictReadsBackItsLetter(): void
    {
        $deadLetter = $this->deadLetter();
        $verdict = FailureVerdict::death($deadLetter);

        self::assertFalse($verdict->isRetry());
        self::assertSame($deadLetter, $verdict->deadLetter());
    }

    public function testReadingTheRetryEntryOfADeathVerdictIsRefused(): void
    {
        $verdict = FailureVerdict::death($this->deadLetter());

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
