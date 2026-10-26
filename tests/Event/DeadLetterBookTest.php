<?php

declare(strict_types=1);

namespace Zef\Test\Event;

use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Zef\Framework\Event\DeadLetter;
use Zef\Framework\Event\DeadLetterBook;
use Zef\Framework\Event\EventName;

/**
 * Pins the failure record and its book: the letter's own readers,
 * the append-order invariant, and the per-listener attribution by
 * object identity -- the number the quarantine threshold reads.
 *
 * @internal
 */
#[CoversClass(DeadLetter::class)]
#[CoversClass(DeadLetterBook::class)]
final class DeadLetterBookTest extends TestCase
{
    public function testTheLetterReadsBackTheWholeStory(): void
    {
        $recordingListener = new RecordingListener(new EventLog(), 'x');
        $deadLetter = new DeadLetter(
            new EventName('user.created'),
            $recordingListener,
            3,
            RuntimeException::class,
            'the listener failed',
            5_000,
        );

        self::assertSame('user.created', $deadLetter->eventName()->spelling());
        self::assertSame($recordingListener, $deadLetter->eventListener());
        self::assertSame(3, $deadLetter->attempts());
        self::assertSame('RuntimeException', $deadLetter->failureClass());
        self::assertSame('the listener failed', $deadLetter->failureMessage());
        self::assertSame(5_000, $deadLetter->deadAt());
    }

    public function testTheBookKeepsTheDrainOrderAndCounts(): void
    {
        $deadLetterBook = new DeadLetterBook();
        $first = new DeadLetter(
            new EventName('user.created'),
            new RecordingListener(new EventLog(), 'x'),
            1,
            RuntimeException::class,
            'first',
            100,
        );
        $second = new DeadLetter(
            new EventName('user.updated'),
            new RecordingListener(new EventLog(), 'y'),
            2,
            LogicException::class,
            'second',
            200,
        );

        $deadLetterBook->append($first);
        $deadLetterBook->append($second);

        self::assertSame([$first, $second], $deadLetterBook->all());
        self::assertSame(2, $deadLetterBook->count());
    }

    public function testAttributionCountsByListenerObjectIdentity(): void
    {
        $deadLetterBook = new DeadLetterBook();
        $poison = new RecordingListener(new EventLog(), 'poison');
        $other = new RecordingListener(new EventLog(), 'other');

        $deadLetterBook->append(new DeadLetter(
            new EventName('user.created'),
            $poison,
            3,
            RuntimeException::class,
            'a',
            100,
        ));
        $deadLetterBook->append(new DeadLetter(
            new EventName('user.updated'),
            $other,
            3,
            RuntimeException::class,
            'b',
            200,
        ));
        $deadLetterBook->append(new DeadLetter(
            new EventName('user.deleted'),
            $poison,
            3,
            RuntimeException::class,
            'c',
            300,
        ));

        self::assertSame(2, $deadLetterBook->attributedTo($poison));
        self::assertSame(1, $deadLetterBook->attributedTo($other));
        self::assertSame(0, $deadLetterBook->attributedTo(new RecordingListener(new EventLog(), 'poison')));
    }

    public function testAnEmptyBookAnswersZeroEverywhere(): void
    {
        $deadLetterBook = new DeadLetterBook();

        self::assertSame([], $deadLetterBook->all());
        self::assertSame(0, $deadLetterBook->count());
        self::assertSame(0, $deadLetterBook->attributedTo(new RecordingListener(new EventLog(), 'x')));
    }
}
