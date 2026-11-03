<?php

declare(strict_types=1);

namespace Zef\Test\Console;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Config\ConfigException;
use Zef\Framework\Console\ConsoleCommandEntry;
use Zef\Framework\Console\ConsoleCommands;

/**
 * The command registry: the joined table's single judgement -- the
 * honest empty identity, the declaration-order entries, the duplicate
 * refusal, the framework-verb collision refusal, and the schema's
 * fail-closed verdicts.
 *
 * @internal
 */
#[CoversClass(ConsoleCommands::class)]
#[CoversClass(ConfigException::class)]
final class ConsoleCommandsTest extends TestCase
{
    public function testAnAbsentTableAnswersTheHonestEmptyRegistry(): void
    {
        $consoleCommands = new ConsoleCommands(null);

        self::assertSame(0, $consoleCommands->count());
        self::assertSame([], $consoleCommands->entries());
        self::assertNull($consoleCommands->named('shop:ping'));
    }

    public function testTheEntriesRideInTheirDeclarationOrder(): void
    {
        $consoleCommands = new ConsoleCommands([
            ['name' => 'shop:ping', 'description' => 'Ping.', 'service' => 'a'],
            ['name' => 'audit:trail', 'description' => 'Trail.', 'service' => 'b'],
        ]);

        self::assertSame(['shop:ping', 'audit:trail'], array_map(
            static fn (ConsoleCommandEntry $consoleCommandEntry): string => $consoleCommandEntry->name->toString(),
            $consoleCommands->entries(),
        ));
        self::assertSame(2, $consoleCommands->count());
    }

    public function testNamedAnswersTheDeclaredVerbAndNothingElse(): void
    {
        $consoleCommands = new ConsoleCommands([
            ['name' => 'shop:ping', 'description' => 'Ping.', 'service' => 'a'],
        ]);

        self::assertSame('a', $consoleCommands->named('shop:ping')?->service);
        self::assertNull($consoleCommands->named('shop:report'));
        self::assertNull($consoleCommands->named(''));
    }

    public function testATableThatIsNotAnArrayRefuses(): void
    {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessageIsOrContains(
            'The "console.commands" configuration must be a list of command declarations.',
        );

        new ConsoleCommands('nope');
    }

    public function testAnEntryThatFailsTheSchemaRefuses(): void
    {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessageIsOrContains('description');

        new ConsoleCommands([
            ['name' => 'shop:ping', 'service' => 'a'],
        ]);
    }

    public function testAVerbJoinedTwiceRefusesNamingTheVerb(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains(
            "The command 'shop:ping' is declared twice; a verb answers one command.",
        );

        new ConsoleCommands([
            ['name' => 'shop:ping', 'description' => 'Ping.', 'service' => 'a'],
            ['name' => 'shop:ping', 'description' => 'Ping again.', 'service' => 'b'],
        ]);
    }

    public function testAFrameworkVerbCollisionRefusesNamingTheVerb(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains(
            "The command 'route:list' collides with the framework's own verb",
        );

        new ConsoleCommands([
            ['name' => 'route:list', 'description' => 'Shadow.', 'service' => 'a'],
        ]);
    }

    public function testTheCollisionJudgementAnswersOnTheFirstCollidingEntry(): void
    {
        // The collision is judged per entry as the table is walked, so
        // the FIRST framework-verb entry refuses before any later
        // declaration is ever read -- the shadow question never waits
        // for a second spelling of the same lie.
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains(
            "The command 'route:list' collides with the framework's own verb",
        );

        new ConsoleCommands([
            ['name' => 'route:list', 'description' => 'One.', 'service' => 'a'],
            ['name' => 'route:list', 'description' => 'Two.', 'service' => 'b'],
        ]);
    }
}
