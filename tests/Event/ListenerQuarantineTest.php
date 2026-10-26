<?php

declare(strict_types=1);

namespace Zef\Test\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Event\ListenerQuarantine;

/**
 * Pins the poison-pill book: the quarantine verdict by object
 * identity, and the idempotent quarantine write (a listener the
 * book already holds is not counted twice).
 *
 * @internal
 */
#[CoversClass(ListenerQuarantine::class)]
final class ListenerQuarantineTest extends TestCase
{
    public function testAQuarantinedListenerIsRecognisedByIdentity(): void
    {
        $listenerQuarantine = new ListenerQuarantine();
        $recordingListener = new RecordingListener(new EventLog(), 'x');

        self::assertFalse($listenerQuarantine->isQuarantined($recordingListener));
        self::assertSame(0, $listenerQuarantine->count());

        $listenerQuarantine->quarantine($recordingListener);

        self::assertTrue($listenerQuarantine->isQuarantined($recordingListener));
        self::assertSame(1, $listenerQuarantine->count());
    }

    public function testTwoListenersOfTheSameShapeAreTwoIdentities(): void
    {
        $listenerQuarantine = new ListenerQuarantine();
        $first = new RecordingListener(new EventLog(), 'twin');
        $second = new RecordingListener(new EventLog(), 'twin');

        $listenerQuarantine->quarantine($first);

        self::assertTrue($listenerQuarantine->isQuarantined($first));
        self::assertFalse($listenerQuarantine->isQuarantined($second));
        self::assertSame(1, $listenerQuarantine->count());
    }

    public function testQuarantiningAHeldListenerIsIdempotent(): void
    {
        $listenerQuarantine = new ListenerQuarantine();
        $recordingListener = new RecordingListener(new EventLog(), 'x');

        $listenerQuarantine->quarantine($recordingListener);
        $listenerQuarantine->quarantine($recordingListener);

        self::assertSame(1, $listenerQuarantine->count());
    }
}
