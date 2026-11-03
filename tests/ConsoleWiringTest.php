<?php

declare(strict_types=1);

namespace Zef\Test;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass;
use Zef\Framework\Config\Config;
use Zef\Framework\Config\ConfigException;
use Zef\Framework\Console\ConsoleCommandEntry;
use Zef\Framework\Console\ConsoleCommands;
use Zef\Framework\ConsoleWiring;
use Zef\Framework\Container\Container;
use Zef\Test\Modules\Fixtures\PingHandler;

/**
 * The console family's wiring fold: the registry the frozen repository
 * answers, and the handler resolution's fail-closed judgements.
 *
 * @internal
 */
#[CoversClass(ConsoleWiring::class)]
#[CoversClass(ConsoleCommands::class)]
#[CoversClass(ConfigException::class)]
final class ConsoleWiringTest extends TestCase
{
    public function testARepositoryWithoutTheConsoleMapAnswersTheEmptyRegistry(): void
    {
        $commands = ConsoleWiring::commands(new Config());

        self::assertSame(0, $commands->count());
        self::assertNull($commands->named('shop:ping'));
    }

    public function testTheWiredRegistryAnswersTheDeclaredTable(): void
    {
        $config = new Config([
            'console' => [
                'commands' => [
                    ['name' => 'shop:ping', 'description' => 'Ping.', 'service' => 'test/shop/ping.handler'],
                ],
            ],
        ]);

        $commands = ConsoleWiring::commands($config);

        self::assertSame(1, $commands->count());
        self::assertSame(
            'test/shop/ping.handler',
            $commands->named('shop:ping')?->service,
        );
    }

    public function testAnUnknownConsoleFamilyRefusesTheRead(): void
    {
        $config = new Config(['console' => ['commands' => [], 'verbs' => []]]);

        $this->expectException(ConfigException::class);
        $this->expectExceptionMessageIsOrContains(
            "The console configuration carries the unknown knob 'verbs'",
        );

        ConsoleWiring::commands($config);
    }

    public function testACommandsValueThatIsNotAListRefusesTheRead(): void
    {
        $config = new Config(['console' => ['commands' => 'nope']]);

        $this->expectException(ConfigException::class);
        $this->expectExceptionMessageIsOrContains(
            'The "console.commands" configuration must be a list of command declarations.',
        );

        ConsoleWiring::commands($config);
    }

    public function testTheHandlerResolutionAnswersTheDeclaredService(): void
    {
        $container = new Container();
        $container->set('test/shop/ping.handler', static fn (): PingHandler => new PingHandler());

        $entry = ConsoleWiring::commands(new Config([
            'console' => ['commands' => [
                ['name' => 'shop:ping', 'description' => 'Ping.', 'service' => 'test/shop/ping.handler'],
            ]],
        ]))->named('shop:ping');

        self::assertInstanceOf(ConsoleCommandEntry::class, $entry);
        self::assertInstanceOf(PingHandler::class, ConsoleWiring::handler($container, $entry));
    }

    public function testAServiceThatDoesNotAnswerTheContractRefusesNamingTheCommand(): void
    {
        $container = new Container();
        $container->set('test/shop/not-a-handler', static fn (): stdClass => new stdClass());

        $entry = ConsoleWiring::commands(new Config([
            'console' => ['commands' => [
                ['name' => 'shop:ping', 'description' => 'Ping.', 'service' => 'test/shop/not-a-handler'],
            ]],
        ]))->named('shop:ping');

        self::assertInstanceOf(ConsoleCommandEntry::class, $entry);

        $this->expectException(ConfigException::class);
        $this->expectExceptionMessageIsOrContains(
            "The 'shop:ping' command declaration names the service 'test/shop/not-a-handler'"
            . ' that does not implement CommandHandler.',
        );

        ConsoleWiring::handler($container, $entry);
    }
}
