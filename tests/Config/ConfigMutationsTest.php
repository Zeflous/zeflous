<?php

declare(strict_types=1);

namespace Zef\Test\Config;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Config\Config;
use Zef\Framework\Config\ConfigException;

/**
 * The mutation protocol of the configuration repository, covered as its own
 * unit: every `with*` answers a NEW repository (rule 3's immutability, the
 * persistent worker's sharing contract), the traversal semantics live in the
 * dot collaborators, and the refusals are the list protocol's own.
 *
 * The trait is covered through its only consumer: PHPUnit 13 refuses a trait
 * as a coverage target, while a class's coverage includes the traits it
 * composes -- the same attribution ConfigTest already rides.
 *
 * @internal
 */
#[CoversClass(Config::class)]
final class ConfigMutationsTest extends TestCase
{
    public function testWithSetCreatesTheMissingIntermediateArrays(): void
    {
        $config = new Config(['app' => 'zef']);

        $mutated = $config->withSet('database.mysql.host', 'localhost');

        self::assertNotSame($config, $mutated);
        self::assertSame('localhost', $mutated->get('database.mysql.host'));
        self::assertSame('zef', $mutated->get('app'));
        self::assertNull($config->get('database.mysql.host'));
    }

    public function testWithSetOverwritesAnExistingLeaf(): void
    {
        $config = new Config(['app' => 'zef']);

        $mutated = $config->withSet('app', 'new');

        self::assertSame('new', $mutated->get('app'));
        self::assertSame('zef', $config->get('app'));
    }

    public function testWithUnsetRemovesTheAddressedLeaf(): void
    {
        $config = new Config(['database' => ['mysql' => ['host' => 'localhost']]]);

        $mutated = $config->withUnset('database.mysql.host');

        self::assertNull($mutated->get('database.mysql.host'));
        self::assertSame('localhost', $config->get('database.mysql.host'));
    }

    public function testWithUnsetOnAnAbsentPathYieldsANewInstanceAndKeepsTheData(): void
    {
        // The documented no-op: an absent path still answers a new instance,
        // so sharing never observes an in-place mutation.
        $config = new Config(['app' => 'zef']);

        $mutated = $config->withUnset('missing.path');

        self::assertNotSame($config, $mutated);
        self::assertSame('zef', $mutated->get('app'));
    }

    public function testWithAppendAddsToTheEndOfTheAddressedList(): void
    {
        $config = new Config(['users' => ['ada', 'grace']]);

        $mutated = $config->withAppend('users', 'linus');

        self::assertSame(['ada', 'grace', 'linus'], $mutated->get('users'));
        self::assertSame(['ada', 'grace'], $config->get('users'));
    }

    public function testWithPrependAddsToTheStartOfTheAddressedList(): void
    {
        $config = new Config(['users' => ['ada', 'grace']]);

        $mutated = $config->withPrepend('users', 'linus');

        self::assertSame(['linus', 'ada', 'grace'], $mutated->get('users'));
        self::assertSame(['ada', 'grace'], $config->get('users'));
    }

    public function testTheListProtocolRefusesAMissingTarget(): void
    {
        $config = new Config(['app' => 'zef']);

        $this->expectException(ConfigException::class);

        $config->withAppend('missing.list', 'value');
    }

    public function testTheListProtocolRefusesANonListTarget(): void
    {
        $config = new Config(['app' => 'zef']);

        $this->expectException(ConfigException::class);

        $config->withPrepend('app', 'value');
    }
}
