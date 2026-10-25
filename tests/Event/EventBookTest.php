<?php

declare(strict_types=1);

namespace Zef\Test\Event;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Event\EventBook;
use Zef\Framework\Event\EventName;
use Zef\Framework\Event\EventShape;
use Zef\Framework\Event\ListenerRegistry;

/**
 * Pins the book of defined events: the entry a definition writes
 * (the shape and a fresh listener registry), and the two
 * undefined-name refusals whose arms the dispatch and the
 * subscribe/unsubscribe verbs each spell -- the book is the one
 * authority that knows whether a name was defined, so both arms
 * are refused here, named, and never reach a listener.
 *
 * @internal
 */
#[CoversClass(EventBook::class)]
#[CoversClass(ListenerRegistry::class)]
final class EventBookTest extends TestCase
{
    public function testADefinitionOpensAShapeAndAFreshRegistry(): void
    {
        $eventBook = new EventBook();
        $eventName = new EventName('user.created');
        $eventShape = new EventShape(['userId' => 'int']);

        $eventBook->define($eventName, $eventShape);

        self::assertSame($eventShape, $eventBook->shapeFor($eventName));
        self::assertTrue($eventBook->registryFor($eventName)->isEmpty());
    }

    public function testWritingANameTwiceIsRefused(): void
    {
        $eventBook = new EventBook();
        $eventName = new EventName('user.created');

        $eventBook->define($eventName, new EventShape(['userId' => 'int']));

        try {
            $eventBook->define($eventName, new EventShape(['userId' => 'string']));
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

    public function testTheShapeArmRefusesAnUndefinedName(): void
    {
        $eventBook = new EventBook();

        try {
            $eventBook->shapeFor(new EventName('user.created'));
            self::fail('The undefined-name shape lookup was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                "The event 'user.created' is not defined; dispatch refuses an"
                . ' undefined name so a typo cannot silently deliver nothing.',
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testTheRegistryArmRefusesAnUndefinedName(): void
    {
        $eventBook = new EventBook();

        try {
            $eventBook->registryFor(new EventName('user.created'));
            self::fail('The undefined-name registry lookup was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                "The event 'user.created' is not defined; subscribe and"
                . ' unsubscribe both refuse an undefined name so a typo'
                . ' cannot silently register a listener nobody dispatches to.',
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testTwoEventsKeepTheirOwnEntries(): void
    {
        $eventBook = new EventBook();
        $created = new EventName('user.created');
        $removed = new EventName('user.removed');

        $eventBook->define($created, new EventShape(['userId' => 'int']));
        $eventBook->define($removed, new EventShape(['userId' => 'int', 'reason' => '?string']));

        self::assertNotSame(
            $eventBook->shapeFor($created),
            $eventBook->shapeFor($removed),
        );
        self::assertNotSame(
            $eventBook->registryFor($created),
            $eventBook->registryFor($removed),
        );
    }
}
