<?php

declare(strict_types=1);

namespace Zef\Test\Event;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Event\EventDispatcher;
use Zef\Framework\Event\EventName;
use Zef\Framework\Event\EventShape;
use Zef\Framework\Event\ListenerPriority;
use Zef\Framework\Event\OutboxMessage;
use Zef\Framework\Event\OutboxRelayOutcome;
use Zef\Framework\Event\RetryPolicy;
use Zef\Framework\Event\TransactionalEventDispatcher;

/**
 * Pins the transactional facade end to end, through the
 * composition seam the dispatcher owns: the defer that appends
 * one outbox row riding the caller's own scope, the producer's
 * boundary refusals, and the relay that feeds the durable arm --
 * the pool snapshot taken at the relay (a listener subscribed
 * between append and relay joins, the DU11 doctrine read from
 * the relay side), the counts the outcome reports, and the
 * both-or-neither a refused handoff rolls back.
 *
 * @internal
 */
#[CoversClass(TransactionalEventDispatcher::class)]
#[CoversClass(EventDispatcher::class)]
final class TransactionalEventDispatcherTest extends TestCase
{
    public function testADeferredEventAppendsOneOutboxRow(): void
    {
        $inMemoryOutboxStore = new InMemoryOutboxStore();
        $transactional = $this->transactional($inMemoryOutboxStore, []);

        $outboxMessage = $transactional->defer(new EventName('order.paid'), ['amount' => 12], 100);

        self::assertSame('order.paid', $outboxMessage->name()->spelling());
        self::assertSame(['amount' => 12], $outboxMessage->values());
        self::assertSame(100, $outboxMessage->appendedAt());
        self::assertSame(1, $inMemoryOutboxStore->pendingCount());
        self::assertSame(
            $outboxMessage->messageId()->spelling(),
            $inMemoryOutboxStore->pendingRows()[$outboxMessage->messageId()->spelling()]
                ->messageId()->spelling(),
        );
    }

    public function testAnUndefinedEventRefusesAtTheDeferCall(): void
    {
        $transactional = $this->transactional(new InMemoryOutboxStore(), []);

        try {
            $transactional->defer(new EventName('never.defined'), ['amount' => 12], 100);
            self::fail('An undefined event must refuse at the defer call.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertStringContainsString(
                'never.defined',
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testAPayloadTheShapeRefusesRefusesAtTheDeferCall(): void
    {
        $transactional = $this->transactional(new InMemoryOutboxStore(), []);

        try {
            $transactional->defer(new EventName('order.paid'), ['amount' => 'twelve'], 100);
            self::fail('A payload the shape refuses must refuse at the defer call.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertStringContainsString('amount', $invalidArgumentException->getMessage());
        }
    }

    public function testAValueThatCannotSurviveTheSpellingRefusesAtTheDeferCall(): void
    {
        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->define(
            new EventName('order.paid'),
            new EventShape(['memo' => 'string']),
        );
        $transactional = $eventDispatcher->transactionalDelivery(
            new InMemoryOutboxStore(),
            $eventDispatcher->durableDelivery(
                new InMemoryDeliveryStore(),
                new FixedResolver([]),
                new RetryPolicy(3, 10),
                1,
            ),
        );

        try {
            $transactional->defer(new EventName('order.paid'), ['memo' => "\xB1\x31"], 100);
            self::fail('A value that cannot survive the serialized spelling must refuse.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertStringContainsString(
                'not valid UTF-8',
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testANegativeAppendMomentRefuses(): void
    {
        $transactional = $this->transactional(new InMemoryOutboxStore(), []);

        try {
            $transactional->defer(new EventName('order.paid'), ['amount' => 12], -1);
            self::fail('A negative append moment must refuse.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                "An outbox row's appended-at moment cannot be negative, got -1.",
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testTheRelayFeedsTheDurableArmAndRemovesTheRows(): void
    {
        $inMemoryOutboxStore = new InMemoryOutboxStore();
        $inMemoryDeliveryStore = new InMemoryDeliveryStore();
        $durableRecordingListener = new DurableRecordingListener(new EventLog(), 'audit', 'app.orders.audit');
        $transactional = $this->transactionalWithDispatcher(
            $inMemoryOutboxStore,
            $inMemoryDeliveryStore,
            [$durableRecordingListener],
        )[2];

        $transactional->defer(new EventName('order.paid'), ['amount' => 12], 100);
        $transactional->defer(new EventName('order.paid'), ['amount' => 30], 101);

        $outboxRelayOutcome = $transactional->relay(8);

        self::assertSame(2, $outboxRelayOutcome->relayed());
        self::assertSame(2, $outboxRelayOutcome->admitted());
        self::assertSame(0, $outboxRelayOutcome->skippedQuarantined());
        self::assertSame(0, $inMemoryOutboxStore->pendingCount());
        self::assertSame(2, $inMemoryDeliveryStore->pendingCount());
    }

    public function testTheRelayReadsAtMostTheLimit(): void
    {
        $inMemoryOutboxStore = new InMemoryOutboxStore();
        $transactional = $this->transactional($inMemoryOutboxStore, []);

        $transactional->defer(new EventName('order.paid'), ['amount' => 12], 100);
        $transactional->defer(new EventName('order.paid'), ['amount' => 30], 101);

        $outboxRelayOutcome = $transactional->relay(1);

        self::assertSame(1, $outboxRelayOutcome->relayed());
        self::assertSame(1, $inMemoryOutboxStore->pendingCount());
    }

    public function testAnEmptyOutboxRelaysNothing(): void
    {
        $transactional = $this->transactional(new InMemoryOutboxStore(), []);

        $outboxRelayOutcome = $transactional->relay(8);

        self::assertSame(0, $outboxRelayOutcome->relayed());
        self::assertSame(0, $outboxRelayOutcome->admitted());
        self::assertSame(0, $outboxRelayOutcome->skippedQuarantined());
    }

    public function testThePendingCountMirrorsTheOutboxsOwn(): void
    {
        $inMemoryOutboxStore = new InMemoryOutboxStore();
        $transactional = $this->transactional($inMemoryOutboxStore, []);

        self::assertSame(0, $transactional->pendingCount());

        $transactional->defer(new EventName('order.paid'), ['amount' => 12], 100);

        self::assertSame(1, $transactional->pendingCount());
        self::assertSame(1, $inMemoryOutboxStore->pendingCount());
    }

    public function testThePoolSnapshotIsTakenAtTheRelayNotTheAppend(): void
    {
        $inMemoryOutboxStore = new InMemoryOutboxStore();
        $inMemoryDeliveryStore = new InMemoryDeliveryStore();
        $durableRecordingListener = new DurableRecordingListener(new EventLog(), 'audit', 'app.orders.audit');
        $lateRecordingListener = new DurableRecordingListener(new EventLog(), 'late', 'app.orders.late');
        [$eventDispatcher, , $transactional] = $this->transactionalWithDispatcher(
            $inMemoryOutboxStore,
            $inMemoryDeliveryStore,
            [$durableRecordingListener],
        );

        $transactional->defer(new EventName('order.paid'), ['amount' => 12], 100);

        $eventDispatcher->subscribe(new EventName('order.paid'), $lateRecordingListener, new ListenerPriority(0));

        $outboxRelayOutcome = $transactional->relay(8);

        self::assertSame(1, $outboxRelayOutcome->relayed());
        self::assertSame(2, $outboxRelayOutcome->admitted());
        self::assertSame(2, $inMemoryDeliveryStore->pendingCount());
    }

    public function testTheQuarantinedListenerIsSkippedAndNamed(): void
    {
        $inMemoryOutboxStore = new InMemoryOutboxStore();
        $inMemoryDeliveryStore = new InMemoryDeliveryStore();
        $durableRecordingListener = new DurableRecordingListener(new EventLog(), 'audit', 'app.orders.audit');
        [$eventDispatcher, $durableEventDispatcher] = $this->durable(
            $inMemoryDeliveryStore,
            [$durableRecordingListener],
        );
        $transactional = $eventDispatcher->transactionalDelivery($inMemoryOutboxStore, $durableEventDispatcher);

        $durableEventDispatcher->defer(new EventName('order.paid'), ['amount' => 1]);
        $inMemoryDeliveryStore->quarantineListener($durableRecordingListener->listenerIdentity(), 0);

        $transactional->defer(new EventName('order.paid'), ['amount' => 12], 100);

        $outboxRelayOutcome = $transactional->relay(8);

        self::assertSame(1, $outboxRelayOutcome->relayed());
        self::assertSame(0, $outboxRelayOutcome->admitted());
        self::assertSame(1, $outboxRelayOutcome->skippedQuarantined());
        self::assertSame(0, $inMemoryOutboxStore->pendingCount());
    }

    public function testARefusedHandoffRollsTheWholeRelayBack(): void
    {
        $inMemoryOutboxStore = new InMemoryOutboxStore();
        $inMemoryDeliveryStore = new InMemoryDeliveryStore(capacity: 1);
        $durableRecordingListener = new DurableRecordingListener(new EventLog(), 'audit', 'app.orders.audit');
        $transactional = $this->transactionalWithDispatcher(
            $inMemoryOutboxStore,
            $inMemoryDeliveryStore,
            [$durableRecordingListener],
        )[2];

        $inMemoryDeliveryStore->save(new \Zef\Framework\Event\DeliveryEnvelope(
            new \Zef\Framework\Event\DeliveryEntryId(str_repeat('a', 32)),
            new EventName('order.paid'),
            ['amount' => 99],
            $durableRecordingListener->listenerIdentity(),
            1,
            0,
        ));

        $transactional->defer(new EventName('order.paid'), ['amount' => 12], 100);

        try {
            $transactional->relay(8);
            self::fail('A refused handoff must roll the whole relay back.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertStringContainsString('full', $invalidArgumentException->getMessage());
        }

        self::assertSame(1, $inMemoryOutboxStore->pendingCount());
    }

    public function testARelayBelowOneRowRefuses(): void
    {
        $transactional = $this->transactional(new InMemoryOutboxStore(), []);

        try {
            $transactional->relay(0);
            self::fail('A relay pass relays at least one row.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                'A relay pass relays at least one row, got 0.',
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testTheRelayOutcomeCarriesItsThreeCounts(): void
    {
        $outboxRelayOutcome = new OutboxRelayOutcome(3, 5, 2);

        self::assertSame(3, $outboxRelayOutcome->relayed());
        self::assertSame(5, $outboxRelayOutcome->admitted());
        self::assertSame(2, $outboxRelayOutcome->skippedQuarantined());
    }

    public function testTheMessageIdentityGrammarRefusesItsOwnOffenses(): void
    {
        try {
            new \Zef\Framework\Event\OutboxMessageId('not-hex');
            self::fail('A non-hex spelling must refuse.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                "An outbox message id must be exactly 32 lowercase hexadecimal characters, got 'not-hex'.",
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testTheMintedIdentitySpellsThirtyTwoHexCharacters(): void
    {
        self::assertSame(1, preg_match('/^[0-9a-f]{32}$/D', \Zef\Framework\Event\OutboxMessageId::mint()->spelling()));
    }

    public function testTheMessageConstructionWalksTheValuesForSurvival(): void
    {
        try {
            $this->messageWithValues(['tags' => [(object) []]]);
            self::fail('A value that cannot survive the round trip must refuse.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertStringContainsString(
                'stdClass at tags[0]',
                $invalidArgumentException->getMessage(),
            );
        }
    }

    /**
     * The message a hostile producer spells -- the payload walked
     * at the construction boundary regardless of what it carries.
     *
     * @param array<string, null|array<mixed>|bool|float|int|string> $values
     */
    private function messageWithValues(array $values): OutboxMessage
    {
        return new OutboxMessage(
            \Zef\Framework\Event\OutboxMessageId::mint(),
            new EventName('order.paid'),
            $values,
            100,
        );
    }

    /**
     * Composes the transactional facade over an empty durable pool,
     * the shape the append-and-relay-without-listeners answers pin.
     *
     * @param list<\Zef\Framework\Event\DurableEventListener> $listeners
     */
    private function transactional(
        InMemoryOutboxStore $inMemoryOutboxStore,
        array $listeners,
    ): TransactionalEventDispatcher {
        return $this->transactionalWithDispatcher(
            $inMemoryOutboxStore,
            null,
            $listeners,
        )[2];
    }

    /**
     * Composes the transactional facade over the full dispatcher
     * pair, with the delivery store the relay feeds spelled
     * separately when the test observes it.
     *
     * @param list<\Zef\Framework\Event\DurableEventListener> $listeners
     *
     * @return array{EventDispatcher, \Zef\Framework\Event\DurableEventDispatcher, TransactionalEventDispatcher}
     */
    private function transactionalWithDispatcher(
        InMemoryOutboxStore $inMemoryOutboxStore,
        ?InMemoryDeliveryStore $inMemoryDeliveryStore,
        array $listeners,
    ): array {
        [$eventDispatcher, $durableEventDispatcher] = $this->durable(
            $inMemoryDeliveryStore ?? new InMemoryDeliveryStore(),
            $listeners,
        );

        return [
            $eventDispatcher,
            $durableEventDispatcher,
            $eventDispatcher->transactionalDelivery($inMemoryOutboxStore, $durableEventDispatcher),
        ];
    }

    /**
     * Composes the durable facade the relay feeds.
     *
     * @param list<\Zef\Framework\Event\DurableEventListener> $listeners
     *
     * @return array{EventDispatcher, \Zef\Framework\Event\DurableEventDispatcher}
     */
    private function durable(
        InMemoryDeliveryStore $inMemoryDeliveryStore,
        array $listeners,
    ): array {
        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->define(new EventName('order.paid'), new EventShape(['amount' => 'int']));

        $table = [];

        foreach ($listeners as $listener) {
            $eventDispatcher->subscribe(new EventName('order.paid'), $listener, new ListenerPriority(0));
            $table[$listener->listenerIdentity()->spelling()] = $listener;
        }

        return [
            $eventDispatcher,
            $eventDispatcher->durableDelivery(
                $inMemoryDeliveryStore,
                new FixedResolver($table),
                new RetryPolicy(3, 10),
                1,
            ),
        ];
    }
}
