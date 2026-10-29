<?php

declare(strict_types=1);

namespace Zef\Test\Event;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Event\DurableDelivery;
use Zef\Framework\Event\DurableDrainJournal;
use Zef\Framework\Event\DurableEventListener;
use Zef\Framework\Event\Event;
use Zef\Framework\Event\EventBook;
use Zef\Framework\Event\EventListener;
use Zef\Framework\Event\EventName;
use Zef\Framework\Event\EventPayload;
use Zef\Framework\Event\EventShape;
use Zef\Framework\Event\ListenerIdentity;
use Zef\Framework\Event\ListenerPriority;
use Zef\Framework\Event\ListenerSubscription;
use Zef\Framework\Event\PreparedDelivery;
use Zef\Framework\Event\RetryPolicy;

/**
 * Pins the durable engine's own seams: the threshold refusal, the
 * composition of defer and drain over the store and resolver the
 * host spelled, the BOOKS the store owns (the attribution count
 * and the quarantine the threshold reads), the pending-count seam
 * over the store, and the boundary crossing the durable books
 * exist for -- a fresh engine over the same store starts with the
 * quarantine the previous engine earned, never quarantine-free.
 *
 * @internal
 */
#[CoversClass(DurableDelivery::class)]
#[CoversClass(DurableDrainJournal::class)]
final class DurableDeliveryTest extends TestCase
{
    public function testAQuarantineThresholdBelowOneIsRefused(): void
    {
        try {
            $this->engine(new InMemoryDeliveryStore(), [], 0);
            self::fail('The zero threshold was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                'A quarantine threshold is at least one dead letter, got 0.',
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testDeferringAndDrainingOverTheSpelledStoreAndResolver(): void
    {
        $inMemoryDeliveryStore = new InMemoryDeliveryStore();
        $eventLog = new EventLog();
        $durableRecordingListener = new DurableRecordingListener($eventLog, 'audit', 'app.orders.audit');

        $engine = $this->engine($inMemoryDeliveryStore, [$durableRecordingListener]);
        $engine->defer($this->prepared(['amount' => 12], [$durableRecordingListener]));

        self::assertSame(1, $engine->pendingCount());

        $outcome = $engine->drain(100);

        self::assertSame(1, $outcome->delivered());
        self::assertSame(0, $engine->pendingCount());
        self::assertSame([], $inMemoryDeliveryStore->attributed());
        self::assertSame([], $inMemoryDeliveryStore->quarantined());
        self::assertSame(['audit'], $eventLog->tags());
    }

    public function testAListenersDeathsAreKeptInTheStoresBooks(): void
    {
        $inMemoryDeliveryStore = new InMemoryDeliveryStore();
        $durableFailingListener = new DurableFailingListener('app.orders.audit', 'the audit exploded');

        $engine = $this->engine($inMemoryDeliveryStore, [$durableFailingListener], 1, new RetryPolicy(1, 10));
        $engine->defer($this->prepared(['amount' => 12], [$durableFailingListener]));
        $engine->drain(100);

        $listenerIdentity = new ListenerIdentity('app.orders.audit');

        self::assertSame(1, $engine->deadLettersAttributedTo($listenerIdentity));
        self::assertTrue($engine->isListenerQuarantined($listenerIdentity));
        self::assertSame(
            ['app.orders.audit'],
            array_keys($inMemoryDeliveryStore->quarantined()),
        );
        self::assertSame(
            1,
            $this->deadRecordCount($inMemoryDeliveryStore, 'RuntimeException', 'the audit exploded', 100),
        );
    }

    public function testAFreshEngineOverTheSameStoreStartsWithTheEarnedQuarantine(): void
    {
        $inMemoryDeliveryStore = new InMemoryDeliveryStore();
        $eventLog = new EventLog();
        $durableFailingListener = new DurableFailingListener('app.orders.audit', 'the audit exploded');
        $durableRecordingListener = new DurableRecordingListener($eventLog, 'healthy', 'app.orders.healthy');
        $listenerIdentity = new ListenerIdentity('app.orders.audit');

        $firstEngine = $this->engine(
            $inMemoryDeliveryStore,
            [$durableFailingListener, $durableRecordingListener],
            1,
            new RetryPolicy(1, 10),
        );
        $firstEngine->defer($this->prepared(['amount' => 12], [$durableFailingListener, $durableRecordingListener]));
        $firstEngine->drain(100);

        self::assertSame(1, $firstEngine->deadLettersAttributedTo($listenerIdentity));
        self::assertTrue($firstEngine->isListenerQuarantined($listenerIdentity));

        $freshEngine = $this->engine(
            $inMemoryDeliveryStore,
            [$durableFailingListener, $durableRecordingListener],
            1,
            new RetryPolicy(1, 10),
        );

        self::assertTrue($freshEngine->isListenerQuarantined($listenerIdentity));
        self::assertSame(1, $freshEngine->deadLettersAttributedTo($listenerIdentity));

        $deferralOutcome = $freshEngine->defer(
            $this->prepared(['amount' => 12], [$durableFailingListener, $durableRecordingListener]),
        );

        self::assertSame(1, $deferralOutcome->admitted());
        self::assertSame(1, $deferralOutcome->skippedQuarantined());
        self::assertSame(1, $freshEngine->pendingCount());
        self::assertSame(['healthy'], $eventLog->tags());
    }

    /**
     * How many dead-record actions the store answered carrying the
     * failure's own class, message and dead-at -- the durable story
     * an operator reads, counted over the minted entry identities.
     */
    private function deadRecordCount(
        InMemoryDeliveryStore $inMemoryDeliveryStore,
        string $failureClass,
        string $failureMessage,
        int $deadAt,
    ): int {
        $deadRecordCount = 0;
        $pattern = '/^dead:[0-9a-f]{32}:' . preg_quote($failureClass, '/')
            . ':' . preg_quote($failureMessage, '/') . ':' . $deadAt . '$/';

        foreach ($inMemoryDeliveryStore->actions() as $action) {
            if (preg_match($pattern, $action) !== 1) {
                continue;
            }

            ++$deadRecordCount;
        }

        return $deadRecordCount;
    }

    /**
     * @param list<DurableEventListener> $listeners
     */
    private function engine(
        InMemoryDeliveryStore $inMemoryDeliveryStore,
        array $listeners,
        int $quarantineThreshold = 1,
        ?RetryPolicy $retryPolicy = null,
    ): DurableDelivery {
        $eventBook = new EventBook();
        $eventBook->define(new EventName('order.paid'), new EventShape(['amount' => 'int']));

        $table = [];

        foreach ($listeners as $listener) {
            $table[$listener->listenerIdentity()->spelling()] = $listener;
        }

        return new DurableDelivery(
            $inMemoryDeliveryStore,
            new FixedResolver($table),
            $eventBook,
            $retryPolicy ?? new RetryPolicy(3, 10),
            $quarantineThreshold,
        );
    }

    /**
     * @param array<string, null|array<mixed>|bool|float|int|string> $values
     * @param list<EventListener>                                    $listeners
     */
    private function prepared(array $values, array $listeners): PreparedDelivery
    {
        $pool = [];

        foreach ($listeners as $listener) {
            $pool[] = new ListenerSubscription(
                $listener,
                new ListenerPriority(0),
            );
        }

        return new PreparedDelivery(
            new Event(
                new EventName('order.paid'),
                new EventPayload($values),
            ),
            $pool,
        );
    }
}
