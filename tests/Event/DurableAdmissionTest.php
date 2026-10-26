<?php

declare(strict_types=1);

namespace Zef\Test\Event;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Event\DurableAdmission;
use Zef\Framework\Event\Event;
use Zef\Framework\Event\EventListener;
use Zef\Framework\Event\EventName;
use Zef\Framework\Event\EventPayload;
use Zef\Framework\Event\ListenerPriority;
use Zef\Framework\Event\ListenerQuarantine;
use Zef\Framework\Event\ListenerSubscription;
use Zef\Framework\Event\PreparedDelivery;

/**
 * Pins the durable admission fold: one serialized entry per
 * surviving pool listener in pool order (identity spelled,
 * attempt one, ready-at zero), the quarantine skip counted, the
 * anonymous-listener refusal with the class named, and the
 * backpressure the store itself answers.
 *
 * @internal
 */
#[CoversClass(DurableAdmission::class)]
final class DurableAdmissionTest extends TestCase
{
    public function testEachPoolListenerBecomesOneEntryInPoolOrder(): void
    {
        $inMemoryDeliveryStore = new InMemoryDeliveryStore();
        $eventLog = new EventLog();
        $first = new DurableRecordingListener($eventLog, 'first', 'app.first');
        $second = new DurableRecordingListener($eventLog, 'second', 'app.second');

        $outcome = $this->admission($inMemoryDeliveryStore)->admit($this->prepared(
            ['amount' => 12],
            [$first, $second],
        ));

        self::assertSame('order.paid', $outcome->name()->spelling());
        self::assertSame(2, $outcome->admitted());
        self::assertSame(0, $outcome->skippedQuarantined());
        self::assertSame(2, $inMemoryDeliveryStore->pendingCount());

        $identities = [];

        foreach ($inMemoryDeliveryStore->pending() as $deliveryEnvelope) {
            self::assertSame('order.paid', $deliveryEnvelope->eventName()->spelling());
            self::assertSame(['amount' => 12], $deliveryEnvelope->values());
            self::assertSame(1, $deliveryEnvelope->attempt());
            self::assertSame(0, $deliveryEnvelope->readyAt());
            $identities[] = $deliveryEnvelope->listenerIdentity()->spelling();
        }

        self::assertSame(['app.first', 'app.second'], $identities);
    }

    public function testAQuarantinedListenerIsSkippedAndCounted(): void
    {
        $inMemoryDeliveryStore = new InMemoryDeliveryStore();
        $eventLog = new EventLog();
        $quarantined = new DurableRecordingListener($eventLog, 'gone', 'app.gone');
        $standing = new DurableRecordingListener($eventLog, 'stay', 'app.stay');

        $listenerQuarantine = new ListenerQuarantine();
        $listenerQuarantine->quarantine($quarantined);

        $outcome = new DurableAdmission($inMemoryDeliveryStore, $listenerQuarantine)->admit(
            $this->prepared(['amount' => 12], [$quarantined, $standing]),
        );

        self::assertSame(1, $outcome->admitted());
        self::assertSame(1, $outcome->skippedQuarantined());
        self::assertSame(1, $inMemoryDeliveryStore->pendingCount());
    }

    public function testAPoolListenerWithoutADurableIdentityIsRefusedByClass(): void
    {
        $inMemoryDeliveryStore = new InMemoryDeliveryStore();
        $eventLog = new EventLog();
        $recordingListener = new RecordingListener($eventLog, 'anonymous');

        try {
            $this->admission($inMemoryDeliveryStore)->admit($this->prepared(
                ['amount' => 12],
                [$recordingListener],
            ));
            self::fail('The anonymous pool listener was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                'The delivery pool carries a listener without a durable'
                . ' identity: Zef\Test\Event\RecordingListener -- defer it'
                . ' in-process, or spell its identity, but a durable defer'
                . ' can never promise a delivery no later process could'
                . ' resolve.',
                $invalidArgumentException->getMessage(),
            );
        }

        self::assertSame(0, $inMemoryDeliveryStore->pendingCount());
    }

    public function testTheEmptyPoolAdmitsNothingAndSavesNothing(): void
    {
        $inMemoryDeliveryStore = new InMemoryDeliveryStore();

        $outcome = $this->admission($inMemoryDeliveryStore)->admit($this->prepared(['amount' => 12], []));

        self::assertSame(0, $outcome->admitted());
        self::assertSame(0, $inMemoryDeliveryStore->pendingCount());
        self::assertSame(['save:0'], $inMemoryDeliveryStore->actions());
    }

    public function testTheStoresBackpressureRefusalPassesThrough(): void
    {
        $inMemoryDeliveryStore = new InMemoryDeliveryStore(1);
        $eventLog = new EventLog();
        $first = new DurableRecordingListener($eventLog, 'first', 'app.first');
        $second = new DurableRecordingListener($eventLog, 'second', 'app.second');

        try {
            $this->admission($inMemoryDeliveryStore)->admit($this->prepared(
                ['amount' => 12],
                [$first, $second],
            ));
            self::fail('The overflowing batch was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                'The delivery store is full: capacity 1, occupied 0, demanded'
                . ' 2 -- drain before deferring more.',
                $invalidArgumentException->getMessage(),
            );
        }

        self::assertSame(0, $inMemoryDeliveryStore->pendingCount());
    }

    private function admission(InMemoryDeliveryStore $inMemoryDeliveryStore): DurableAdmission
    {
        return new DurableAdmission($inMemoryDeliveryStore, new ListenerQuarantine());
    }

    /**
     * @param array<string, null|array<mixed>|bool|float|int|string> $values
     * @param list<EventListener>                                    $listeners
     */
    private function prepared(array $values, array $listeners): PreparedDelivery
    {
        $pool = [];

        foreach ($listeners as $listener) {
            $pool[] = new ListenerSubscription($listener, new ListenerPriority(0));
        }

        return new PreparedDelivery(
            new Event(new EventName('order.paid'), new EventPayload($values)),
            $pool,
        );
    }
}
