<?php

declare(strict_types=1);

namespace Zef\Test;

use InvalidArgumentException;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass;
use Zef\Framework\Config\Config;
use Zef\Framework\Config\ConfigException;
use Zef\Framework\Config\Schema\EventSchema;
use Zef\Framework\Container\Container;
use Zef\Framework\Container\NotFoundException;
use Zef\Framework\Event\EventDispatcher;
use Zef\Framework\Event\EventName;
use Zef\Framework\EventFamilies;
use Zef\Framework\EventWiring;
use Zef\Test\Modules\Fixtures\EventsModule;
use Zef\Test\Modules\Fixtures\FixtureLog;
use Zef\Test\Modules\Fixtures\OrderPaidListener;

/**
 * The events family's listener wiring: the `listen` table's own reader --
 * the definitions and subscriptions it answers, the declaration-order
 * standing, the honest no-family posture and every refusal's sentence.
 *
 * @internal
 */
#[CoversClass(EventWiring::class)]
#[CoversClass(EventFamilies::class)]
#[CoversClass(EventDispatcher::class)]
#[CoversClass(EventSchema::class)]
#[CoversClass(ConfigException::class)]
#[CoversClass(NotFoundException::class)]
final class EventWiringTest extends TestCase
{
    #[Override]
    protected function setUp(): void
    {
        FixtureLog::reset();
    }

    public function testARepositoryWithoutTheEventsMapAnswersABareDispatcher(): void
    {
        $eventDispatcher = EventWiring::dispatcher(new Config(), new Container());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains("'shop.order.paid'");

        $eventDispatcher->dispatch(new EventName('shop.order.paid'), ['orderId' => 1]);
    }

    public function testARepositoryWithTheDeferredFamilyAloneAnswersABareDispatcher(): void
    {
        $config = new Config([
            'events' => ['deferred' => ['store' => '', 'path' => '', 'capacity' => 1024]],
        ]);

        $eventDispatcher = EventWiring::dispatcher($config, new Container());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains("'host.pinged'");

        $eventDispatcher->dispatch(new EventName('host.pinged'), ['count' => 1]);
    }

    public function testADeclarationOnlyEventIsDefinedAndDispatchableToNobody(): void
    {
        $config = new Config([
            'events' => ['listen' => [
                ['name' => 'shop.order.dispatched', 'fields' => ['carrier' => 'string']],
            ]],
        ]);

        $eventDispatcher = EventWiring::dispatcher($config, new Container());

        $outcome = $eventDispatcher->dispatch(new EventName('shop.order.dispatched'), ['carrier' => 'DHL']);

        self::assertSame(0, $outcome->deliveredTo());
    }

    public function testADeclaredListenerIsResolvedAndSubscribed(): void
    {
        $container = new Container();
        $container->set(
            EventsModule::LISTENER_SERVICE,
            static fn (): OrderPaidListener => new OrderPaidListener(),
        );

        $config = new Config([
            'events' => ['listen' => [
                [
                    'name' => 'shop.order.paid',
                    'fields' => ['orderId' => 'int', 'amount' => 'int'],
                    'listeners' => [EventsModule::LISTENER_SERVICE],
                ],
            ]],
        ]);

        $eventDispatcher = EventWiring::dispatcher($config, $container);
        $outcome = $eventDispatcher->dispatch(
            new EventName('shop.order.paid'),
            ['orderId' => 7, 'amount' => 1250],
        );

        self::assertSame(1, $outcome->deliveredTo());
        self::assertSame(
            ['listener:shop.order.paid@{"orderId":7,"amount":1250}'],
            FixtureLog::events(),
        );
    }

    public function testAMisshapedPayloadRefusesAtTheDispatchBoundary(): void
    {
        $container = new Container();
        $container->set(
            EventsModule::LISTENER_SERVICE,
            static fn (): OrderPaidListener => new OrderPaidListener(),
        );

        $config = new Config([
            'events' => ['listen' => [
                [
                    'name' => 'shop.order.paid',
                    'fields' => ['orderId' => 'int'],
                    'listeners' => [EventsModule::LISTENER_SERVICE],
                ],
            ]],
        ]);

        $eventDispatcher = EventWiring::dispatcher($config, $container);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('orderId');

        $eventDispatcher->dispatch(new EventName('shop.order.paid'), ['orderId' => 'seven']);
    }

    public function testAListenValueThatIsNotAnArrayRefuses(): void
    {
        $config = new Config(['events' => ['listen' => 'nope']]);

        $this->expectException(ConfigException::class);
        $this->expectExceptionMessageIsOrContains(
            'The events.listen configuration must be a list of event declarations, string given.',
        );

        EventWiring::dispatcher($config, new Container());
    }

    public function testAnEventsMapThatIsNotAMapRefuses(): void
    {
        $config = new Config(['events' => 'nope']);

        $this->expectException(ConfigException::class);
        $this->expectExceptionMessageIsOrContains('The events configuration must be a map, string given.');

        EventWiring::dispatcher($config, new Container());
    }

    public function testAnUnknownEventsFamilyRefusesWithTheKnownListSpelled(): void
    {
        $config = new Config(['events' => ['deffered' => []]]);

        $this->expectException(ConfigException::class);
        $this->expectExceptionMessageIsOrContains(
            "The events configuration carries the unknown knob 'deffered' (known knobs: deferred, listen).",
        );

        EventWiring::dispatcher($config, new Container());
    }

    public function testANameOutsideTheEventGrammarRefusesAtTheReader(): void
    {
        $config = new Config([
            'events' => ['listen' => [
                ['name' => 'shop order paid', 'fields' => ['orderId' => 'int']],
            ]],
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('shop order paid');

        EventWiring::dispatcher($config, new Container());
    }

    public function testAFieldKindOutsideTheVocabularyRefusesAtTheReader(): void
    {
        $config = new Config([
            'events' => ['listen' => [
                ['name' => 'shop.order.paid', 'fields' => ['orderId' => 'integerish']],
            ]],
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('integerish');

        EventWiring::dispatcher($config, new Container());
    }

    public function testANameJoinedTwiceRefusesThroughTheBook(): void
    {
        $config = new Config([
            'events' => ['listen' => [
                ['name' => 'shop.order.paid', 'fields' => ['orderId' => 'int']],
                ['name' => 'shop.order.paid', 'fields' => ['amount' => 'int']],
            ]],
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('shop.order.paid');

        EventWiring::dispatcher($config, new Container());
    }

    public function testAnUnregisteredListenerServiceRefusesThroughTheContainer(): void
    {
        $config = new Config([
            'events' => ['listen' => [
                [
                    'name' => 'shop.order.paid',
                    'fields' => ['orderId' => 'int'],
                    'listeners' => ['test/shop/never-registered'],
                ],
            ]],
        ]);

        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessageIsOrContains('test/shop/never-registered');

        EventWiring::dispatcher($config, new Container());
    }

    public function testAListenerServiceThatDoesNotAnswerTheContractRefuses(): void
    {
        $container = new Container();
        $container->set('test/shop/plain', static fn (): stdClass => new stdClass());

        $config = new Config([
            'events' => ['listen' => [
                [
                    'name' => 'shop.order.paid',
                    'fields' => ['orderId' => 'int'],
                    'listeners' => ['test/shop/plain'],
                ],
            ]],
        ]);

        $this->expectException(ConfigException::class);
        $this->expectExceptionMessageIsOrContains(
            "The 'shop.order.paid' event declaration names the listener service "
            . "'test/shop/plain' that does not implement EventListener.",
        );

        EventWiring::dispatcher($config, $container);
    }

    public function testAScalarListenEntryRefusesThroughTheSchema(): void
    {
        $config = new Config([
            'events' => ['listen' => ['shop.order.paid']],
        ]);

        $this->expectException(ConfigException::class);
        $this->expectExceptionMessageIsOrContains('Configuration failed schema validation');

        EventWiring::dispatcher($config, new Container());
    }
}
