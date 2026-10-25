<?php

declare(strict_types=1);

namespace Zef\Test\Event;

use InvalidArgumentException;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Zef\Framework\Event\DispatchOutcome;
use Zef\Framework\Event\Event;
use Zef\Framework\Event\EventDispatcher;
use Zef\Framework\Event\EventListener;
use Zef\Framework\Event\EventName;
use Zef\Framework\Event\EventShape;
use Zef\Framework\Event\ListenerPriority;
use Zef\Framework\Event\ListenerRegistry;
use Zef\Framework\Event\ListenerSubscription;

/**
 * Pins the dispatcher's whole public vocabulary end-to-end: the
 * define-before-subscribe-and-dispatch discipline, the payload
 * boundary that refuses a mis-shaped producer before a single
 * listener runs, the synchronous delivery order (priority first,
 * registration order among equals), the unsubscribe verb's honest
 * boolean, and the bubbling failure regime -- a listener's
 * exception reaches the dispatch caller and stops the remaining
 * deliveries of that dispatch.
 *
 * @internal
 */
#[CoversClass(EventDispatcher::class)]
#[CoversClass(ListenerRegistry::class)]
#[CoversClass(ListenerSubscription::class)]
#[CoversClass(DispatchOutcome::class)]
final class EventDispatcherTest extends TestCase
{
    public function testADefinedEventDispatchesToItsListenersInOrder(): void
    {
        $eventDispatcher = new EventDispatcher();
        $eventLog = new EventLog();
        $eventName = new EventName('user.created');

        $eventDispatcher->define($eventName, new EventShape(['userId' => 'int']));

        $eventDispatcher->subscribe(
            $eventName,
            new RecordingListener($eventLog, 'late'),
            new ListenerPriority(10),
        );
        $eventDispatcher->subscribe(
            $eventName,
            new RecordingListener($eventLog, 'early'),
            new ListenerPriority(20),
        );

        $outcome = $eventDispatcher->dispatch($eventName, ['userId' => 7]);

        self::assertSame(['early', 'late'], $eventLog->tags());
        self::assertSame('user.created', $outcome->name()->spelling());
        self::assertSame(2, $outcome->deliveredTo());
    }

    public function testEveryListenerReadsTheSameValidatedEvent(): void
    {
        $eventDispatcher = new EventDispatcher();
        $eventName = new EventName('order.paid');
        $first = new RecordingListener(new EventLog(), 'first');
        $second = new RecordingListener(new EventLog(), 'second');

        $eventDispatcher->define($eventName, new EventShape(['orderId' => 'int', 'amount' => '?float']));

        $eventDispatcher->subscribe($eventName, $first, new ListenerPriority(0));
        $eventDispatcher->subscribe($eventName, $second, new ListenerPriority(0));

        $eventDispatcher->dispatch($eventName, ['orderId' => 3, 'amount' => null]);

        self::assertCount(1, $first->received());
        self::assertCount(1, $second->received());
        self::assertSame($first->received()[0], $second->received()[0]);
        self::assertSame('order.paid', $first->received()[0]->name()->spelling());
        self::assertSame(3, $first->received()[0]->payload()->value('orderId'));
        self::assertNull($first->received()[0]->payload()->value('amount'));
    }

    public function testADispatchWithoutListenersAnswersZeroDeliveries(): void
    {
        $eventDispatcher = new EventDispatcher();
        $eventName = new EventName('user.created');

        $eventDispatcher->define($eventName, new EventShape(['userId' => 'int']));

        $outcome = $eventDispatcher->dispatch($eventName, ['userId' => 7]);

        self::assertSame(0, $outcome->deliveredTo());
    }

    public function testDefiningANameTwiceIsRefused(): void
    {
        $eventDispatcher = new EventDispatcher();
        $eventName = new EventName('user.created');

        $eventDispatcher->define($eventName, new EventShape(['userId' => 'int']));

        try {
            $eventDispatcher->define($eventName, new EventShape(['userId' => 'string']));
            self::fail('The second definition was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                "The event 'user.created' is already defined; a second"
                . ' definition would silently re-spell the contract its'
                . ' existing subscribers reasoned about.',
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testSubscribingToAnUndefinedEventIsRefused(): void
    {
        $eventDispatcher = new EventDispatcher();

        try {
            $eventDispatcher->subscribe(
                new EventName('user.created'),
                new RecordingListener(new EventLog(), 'x'),
                new ListenerPriority(0),
            );
            self::fail('The undefined-name subscription was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                "The event 'user.created' is not defined; subscribe and"
                . ' unsubscribe both refuse an undefined name so a typo'
                . ' cannot silently register a listener nobody dispatches to.',
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testUnsubscribingFromAnUndefinedEventIsRefused(): void
    {
        $eventDispatcher = new EventDispatcher();

        try {
            $eventDispatcher->unsubscribe(new EventName('user.created'), new RecordingListener(new EventLog(), 'x'));
            self::fail('The undefined-name unsubscribe was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                "The event 'user.created' is not defined; subscribe and"
                . ' unsubscribe both refuse an undefined name so a typo'
                . ' cannot silently register a listener nobody dispatches to.',
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testUnsubscribeAnswersWhetherAStoodListenerWasRemoved(): void
    {
        $eventDispatcher = new EventDispatcher();
        $eventName = new EventName('user.created');
        $recordingListener = new RecordingListener(new EventLog(), 'x');

        $eventDispatcher->define($eventName, new EventShape(['userId' => 'int']));
        $eventDispatcher->subscribe($eventName, $recordingListener, new ListenerPriority(0));

        self::assertTrue($eventDispatcher->unsubscribe($eventName, $recordingListener));
        self::assertFalse($eventDispatcher->unsubscribe($eventName, $recordingListener));
        self::assertSame(0, $eventDispatcher->dispatch($eventName, ['userId' => 7])->deliveredTo());
    }

    public function testASubscribedListenerMayNotSubscribeTwice(): void
    {
        $eventDispatcher = new EventDispatcher();
        $eventName = new EventName('user.created');
        $recordingListener = new RecordingListener(new EventLog(), 'x');

        $eventDispatcher->define($eventName, new EventShape(['userId' => 'int']));
        $eventDispatcher->subscribe($eventName, $recordingListener, new ListenerPriority(0));

        try {
            $eventDispatcher->subscribe($eventName, $recordingListener, new ListenerPriority(10));
            self::fail('The second standing was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                'The listener is already subscribed to this event; a'
                . ' second standing for the same object would deliver the'
                . ' event twice -- unsubscribe first, then re-subscribe.',
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testARemovedListenerMayReSubscribeAtANewPriority(): void
    {
        $eventDispatcher = new EventDispatcher();
        $eventLog = new EventLog();
        $eventName = new EventName('user.created');
        $recordingListener = new RecordingListener($eventLog, 'moved');

        $eventDispatcher->define($eventName, new EventShape(['userId' => 'int']));
        $eventDispatcher->subscribe($eventName, $recordingListener, new ListenerPriority(0));
        $eventDispatcher->unsubscribe($eventName, $recordingListener);
        $eventDispatcher->subscribe($eventName, $recordingListener, new ListenerPriority(50));

        $outcome = $eventDispatcher->dispatch($eventName, ['userId' => 7]);

        self::assertSame(['moved'], $eventLog->tags());
        self::assertSame(1, $outcome->deliveredTo());
    }

    public function testDispatchingAnUndefinedEventIsRefused(): void
    {
        $eventDispatcher = new EventDispatcher();

        try {
            $eventDispatcher->dispatch(new EventName('user.created'), ['userId' => 7]);
            self::fail('The undefined-name dispatch was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                "The event 'user.created' is not defined; dispatch refuses an"
                . ' undefined name so a typo cannot silently deliver nothing.',
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testAMisShapedPayloadIsRefusedBeforeAnyListenerRuns(): void
    {
        $eventDispatcher = new EventDispatcher();
        $eventLog = new EventLog();
        $eventName = new EventName('user.created');

        $eventDispatcher->define($eventName, new EventShape(['userId' => 'int']));
        $eventDispatcher->subscribe($eventName, new RecordingListener($eventLog, 'never'), new ListenerPriority(0));

        try {
            $eventDispatcher->dispatch($eventName, ['userId' => 'seven']);
            self::fail('The mis-shaped payload was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                "The payload field 'userId' must answer int, got string.",
                $invalidArgumentException->getMessage(),
            );
        }

        self::assertSame([], $eventLog->tags());
    }

    public function testAnUnknownPayloadFieldIsRefusedBeforeAnyListenerRuns(): void
    {
        $eventDispatcher = new EventDispatcher();
        $eventLog = new EventLog();
        $eventName = new EventName('user.created');

        $eventDispatcher->define($eventName, new EventShape(['userId' => 'int']));
        $eventDispatcher->subscribe($eventName, new RecordingListener($eventLog, 'never'), new ListenerPriority(0));

        try {
            $eventDispatcher->dispatch($eventName, ['userId' => 7, 'email' => 'a@b.c']);
            self::fail('The unknown payload field was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                "The payload carries a field the event shape never demanded: 'email'.",
                $invalidArgumentException->getMessage(),
            );
        }

        self::assertSame([], $eventLog->tags());
    }

    public function testAListenerExceptionBubblesAndStopsTheRemainingDeliveries(): void
    {
        $eventDispatcher = new EventDispatcher();
        $eventLog = new EventLog();
        $eventName = new EventName('user.created');

        $eventDispatcher->define($eventName, new EventShape(['userId' => 'int']));
        $eventDispatcher->subscribe($eventName, new RecordingListener($eventLog, 'first'), new ListenerPriority(20));
        $eventDispatcher->subscribe($eventName, $this->throwingListener(), new ListenerPriority(10));
        $eventDispatcher->subscribe($eventName, new RecordingListener($eventLog, 'never'), new ListenerPriority(0));

        try {
            $eventDispatcher->dispatch($eventName, ['userId' => 7]);
            self::fail('The listener exception did not bubble.');
        } catch (RuntimeException $runtimeException) {
            self::assertSame('the listener refused to continue', $runtimeException->getMessage());
            self::assertSame(['first'], $eventLog->tags());
        }
    }

    private function throwingListener(): EventListener
    {
        return new class implements EventListener {
            #[Override]
            public function handle(Event $event): void
            {
                throw new RuntimeException('the listener refused to continue');
            }
        };
    }
}
