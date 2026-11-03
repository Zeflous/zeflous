<?php

declare(strict_types=1);

namespace Zef\Test;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Config\Config;
use Zef\Framework\Config\ConfigException;
use Zef\Framework\ConsoleFamilies;

/**
 * The `console` map's family census: the known list, the unknown-sibling
 * refusal, and the not-a-map refusal.
 *
 * @internal
 */
#[CoversClass(ConsoleFamilies::class)]
#[CoversClass(ConfigException::class)]
final class ConsoleFamiliesTest extends TestCase
{
    public function testAnAbsentMapAnswersTheSilentNull(): void
    {
        self::assertNull(ConsoleFamilies::subtree(null, 'commands'));
    }

    public function testTheCommandsFamilyIsReadOutOfTheMap(): void
    {
        $table = [['name' => 'shop:ping', 'description' => 'd', 'service' => 's']];

        self::assertSame(
            $table,
            ConsoleFamilies::subtree(['commands' => $table], 'commands'),
        );
    }

    public function testAFamilyTheMapDoesNotCarryAnswersNull(): void
    {
        self::assertNull(ConsoleFamilies::subtree([], 'commands'));
    }

    public function testAnUnknownFamilyRefusesNamingItAndTheKnownList(): void
    {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessageIsOrContains(
            "The console configuration carries the unknown knob 'command' (known knobs: commands)",
        );

        ConsoleFamilies::subtree(['command' => []], 'commands');
    }

    public function testAMapThatIsNotAMapRefuses(): void
    {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessageIsOrContains(
            'The console configuration must be a map, string given.',
        );

        ConsoleFamilies::subtree('nope', 'commands');
    }

    public function testAConfigWithoutTheConsoleKeyAnswersNull(): void
    {
        self::assertNull(ConsoleFamilies::subtree(new Config()->get('console'), 'commands'));
    }
}
