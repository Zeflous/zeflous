<?php

declare(strict_types=1);

namespace Zef\Test\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Event\Event;
use Zef\Framework\Event\EventName;
use Zef\Framework\Event\EventPayload;

/**
 * Pins the event envelope itself: the name and the validated
 * payload it freezes at construction are the name and payload
 * every listener reads -- identity, not a copy or a re-derivation.
 *
 * @internal
 */
#[CoversClass(Event::class)]
final class EventTest extends TestCase
{
    public function testTheNameAndPayloadAreCarriedVerbatim(): void
    {
        $eventName = new EventName('user.created');
        $eventPayload = new EventPayload(['userId' => 7]);

        $event = new Event($eventName, $eventPayload);

        self::assertSame($eventName, $event->name());
        self::assertSame($eventPayload, $event->payload());
        self::assertSame('user.created', $event->name()->spelling());
        self::assertSame(7, $event->payload()->value('userId'));
    }
}
