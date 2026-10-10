<?php

declare(strict_types=1);

namespace Zef\Test\Config;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Config\Config;
use Zef\Framework\Config\ConfigException;

/**
 * @internal
 */
#[CoversClass(Config::class)]
final class ConfigTest extends TestCase
{
    public function testGetReturnsAFlatValue(): void
    {
        $config = new Config(['app' => 'zef']);

        self::assertSame('zef', $config->get('app'));
    }

    public function testGetReturnsANestedValue(): void
    {
        $config = new Config(['database' => ['mysql' => ['host' => 'localhost', 'port' => 3306]]]);

        self::assertSame('localhost', $config->get('database.mysql.host'));
        self::assertSame(3306, $config->get('database.mysql.port'));
    }

    public function testGetReturnsNullByDefaultForAMissingKey(): void
    {
        self::assertNull(new Config(['app' => 'zef'])->get('missing'));
    }

    public function testGetReturnsTheProvidedDefaultForAMissingKey(): void
    {
        $config = new Config(['app' => 'zef']);

        self::assertSame('fallback', $config->get('missing', 'fallback'));
    }

    public function testGetReturnsTheDefaultWhenAnIntermediateSegmentIsAScalar(): void
    {
        $config = new Config(['database' => 'mysql']);

        self::assertSame('fallback', $config->get('database.host', 'fallback'));
    }

    public function testGetReadsListEntriesByNumericSegment(): void
    {
        $config = new Config(['users' => [['name' => 'ada'], ['name' => 'grace']]]);

        self::assertSame('ada', $config->get('users.0.name'));
        self::assertSame('grace', $config->get('users.1.name'));
    }

    public function testGetReturnsTheDefaultForAnOutOfBoundsListIndex(): void
    {
        $config = new Config(['users' => [['name' => 'ada']]]);

        self::assertSame('fallback', $config->get('users.5.name', 'fallback'));
    }

    public function testGetReturnsAnExplicitNullInsteadOfTheDefault(): void
    {
        // "Explicitly null" is a found value: the default is only for paths
        // that do not resolve. This is what keeps has() meaningful.
        $config = new Config(['debug' => null]);

        self::assertNull($config->get('debug', 'fallback'));
    }

    public function testHasReturnsTrueForSetKeys(): void
    {
        $config = new Config(['database' => ['mysql' => ['host' => 'localhost']]]);

        self::assertTrue($config->has('database.mysql.host'));
    }

    public function testHasReturnsFalseForAMissingKey(): void
    {
        self::assertFalse(new Config(['app' => 'zef'])->has('missing'));
    }

    public function testHasReturnsFalseWhenAnIntermediateSegmentIsAScalar(): void
    {
        self::assertFalse(new Config(['database' => 'mysql'])->has('database.host'));
    }

    public function testHasReturnsTrueForAListIndex(): void
    {
        $config = new Config(['users' => [['name' => 'ada']]]);

        self::assertTrue($config->has('users.0.name'));
        self::assertFalse($config->has('users.5.name'));
    }

    public function testHasDetectsAnExplicitNullValue(): void
    {
        self::assertTrue(new Config(['debug' => null])->has('debug'));
    }

    public function testGetRejectsAMalformedKey(): void
    {
        $config = new Config(['app' => 'zef']);

        try {
            $config->get('app..name');
            self::fail('A malformed key must raise a ConfigException.');
        } catch (ConfigException $configException) {
            self::assertSame(
                'Configuration key "app..name" is malformed: empty segment.',
                $configException->getMessage(),
            );
        }
    }

    public function testHasRejectsAMalformedKey(): void
    {
        $config = new Config(['app' => 'zef']);

        try {
            $config->has('app.');
            self::fail('A malformed key must raise a ConfigException.');
        } catch (ConfigException $configException) {
            self::assertSame(
                'Configuration key "app." is malformed: empty segment.',
                $configException->getMessage(),
            );
        }
    }

    public function testSplitReturnsAScopedRepository(): void
    {
        $config = new Config(['database' => ['mysql' => ['host' => 'localhost'], 'driver' => 'mysql']]);

        $database = $config->split('database');

        self::assertSame('localhost', $database->get('mysql.host'));
        self::assertSame('mysql', $database->get('driver'));
        self::assertTrue($database->has('mysql.host'));
        self::assertFalse($database->has('redis.host'));
    }

    public function testSplitKeepsTheSubArrayIntact(): void
    {
        $mysql = ['host' => 'localhost', 'port' => 3306];
        $config = new Config(['database' => ['mysql' => $mysql]]);

        self::assertSame($mysql, $config->split('database.mysql')->toArray());
    }

    public function testSplitOfAnEmptyArrayYieldsAnEmptyRepository(): void
    {
        $split = new Config(['tags' => []])->split('tags');

        self::assertSame([], $split->toArray());
        self::assertFalse($split->has('anything'));
    }

    public function testSplitRejectsAMissingKey(): void
    {
        $config = new Config(['app' => 'zef']);

        try {
            $config->split('missing');
            self::fail('Splitting a missing key must raise a ConfigException.');
        } catch (ConfigException $configException) {
            self::assertSame(
                'Cannot split configuration key "missing": it is not set.',
                $configException->getMessage(),
            );
        }
    }

    public function testSplitRejectsAScalarValue(): void
    {
        $config = new Config(['database' => 'mysql']);

        try {
            $config->split('database');
            self::fail('Splitting a scalar must raise a ConfigException.');
        } catch (ConfigException $configException) {
            self::assertSame(
                'Cannot split configuration key "database": it is not an array.',
                $configException->getMessage(),
            );
        }
    }

    public function testToArrayReturnsTheDataIdentically(): void
    {
        $data = ['app' => ['name' => 'zef'], 'users' => [['name' => 'ada']]];

        self::assertSame($data, new Config($data)->toArray());
    }

    public function testAnEmptyConfigAnswersEveryLookupWithTheDefault(): void
    {
        $config = new Config();

        self::assertNull($config->get('anything'));
        self::assertSame('fallback', $config->get('anything.nested', 'fallback'));
        self::assertFalse($config->has('anything'));
        self::assertSame([], $config->toArray());
    }

    public function testReadingDoesNotMutateTheRepositoryData(): void
    {
        $data = ['app' => ['name' => 'zef']];
        $config = new Config($data);

        $config->get('app.name');
        $config->has('app.name');
        $config->split('app');

        self::assertSame($data, $config->toArray());
    }

    public function testWithSetReturnsANewInstance(): void
    {
        $config = new Config(['app' => 'zef']);

        self::assertNotSame($config, $config->withSet('app', 'new'));
    }

    public function testWithSetLeavesTheOriginalRepositoryUntouched(): void
    {
        $data = ['app' => 'zef', 'database' => ['host' => 'localhost']];
        $config = new Config($data);

        $mutated = $config->withSet('database.port', 3306);

        self::assertSame($data, $config->toArray());
        self::assertSame(
            ['app' => 'zef', 'database' => ['host' => 'localhost', 'port' => 3306]],
            $mutated->toArray(),
        );
    }

    public function testWithSetWritesAFlatKey(): void
    {
        $config = new Config(['app' => 'zef']);

        self::assertSame('beta', $config->withSet('app', 'beta')->get('app'));
    }

    public function testWithSetCreatesMissingIntermediateArrays(): void
    {
        $config = new Config();

        $mutated = $config->withSet('database.mysql.host', 'localhost');

        self::assertSame(
            ['database' => ['mysql' => ['host' => 'localhost']]],
            $mutated->toArray(),
        );
        self::assertSame('localhost', $mutated->get('database.mysql.host'));
        self::assertTrue($mutated->has('database.mysql.host'));
    }

    public function testWithSetReplacesAScalarIntermediateWithAnArray(): void
    {
        $config = new Config(['database' => 'mysql']);

        self::assertSame(
            ['database' => ['host' => 'localhost']],
            $config->withSet('database.host', 'localhost')->toArray(),
        );
    }

    public function testWithSetOverwritesAScalarLeafWithAnArray(): void
    {
        $config = new Config(['app' => 'scalar']);

        self::assertSame(
            ['app' => ['name' => 'zef']],
            $config->withSet('app', ['name' => 'zef'])->toArray(),
        );
    }

    public function testWithSetOverwritesAnArrayLeafWithAScalar(): void
    {
        $config = new Config(['app' => ['name' => 'zef']]);

        self::assertSame(['app' => 'scalar'], $config->withSet('app', 'scalar')->toArray());
    }

    public function testWithSetCreatesAMissingNumericOffsetWithoutReindexing(): void
    {
        $config = new Config(['users' => [['name' => 'ada']]]);

        $mutated = $config->withSet('users.2.name', 'bob');

        self::assertSame(
            ['users' => [0 => ['name' => 'ada'], 2 => ['name' => 'bob']]],
            $mutated->toArray(),
        );
        self::assertSame('bob', $mutated->get('users.2.name'));
    }

    public function testWithSetRejectsAMalformedKey(): void
    {
        $config = new Config(['app' => 'zef']);

        try {
            $config->withSet('app..name', 'value');
            self::fail('A malformed key must raise a ConfigException.');
        } catch (ConfigException $configException) {
            self::assertSame(
                'Configuration key "app..name" is malformed: empty segment.',
                $configException->getMessage(),
            );
        }
    }

    public function testWithUnsetRemovesTheLeafAndKeepsTheIntermediates(): void
    {
        $config = new Config(['database' => ['host' => 'localhost', 'port' => 3306]]);

        $mutated = $config->withUnset('database.host');

        self::assertSame(['database' => ['port' => 3306]], $mutated->toArray());
        self::assertFalse($mutated->has('database.host'));
        self::assertTrue($mutated->has('database.port'));
    }

    public function testWithUnsetRemovesAWholeBranch(): void
    {
        $config = new Config(['app' => 'zef', 'database' => ['host' => 'localhost']]);

        self::assertSame(
            ['app' => 'zef'],
            $config->withUnset('database')->toArray(),
        );
    }

    public function testWithUnsetOfAnAbsentKeyYieldsANewInstanceWithTheSameData(): void
    {
        $config = new Config(['app' => 'zef']);

        $mutated = $config->withUnset('missing');

        self::assertNotSame($config, $mutated);
        self::assertSame(['app' => 'zef'], $mutated->toArray());
        self::assertSame(['app' => 'zef'], $config->toArray());
    }

    public function testWithUnsetOfADeepAbsentPathIsANoOp(): void
    {
        $data = ['database' => ['host' => 'localhost']];
        $config = new Config($data);

        self::assertSame($data, $config->withUnset('database.redis.host')->toArray());
    }

    public function testWithUnsetLeavesAScalarIntermediateUntouched(): void
    {
        $config = new Config(['database' => 'mysql']);

        self::assertSame(
            ['database' => 'mysql'],
            $config->withUnset('database.host.port')->toArray(),
        );
    }

    public function testWithUnsetAllowsAKeyToBeRebuiltAfterwards(): void
    {
        $config = new Config(['app' => ['name' => 'zef']]);

        $mutated = $config->withUnset('app')->withSet('app', ['name' => 'zef-2']);

        self::assertSame(['app' => ['name' => 'zef-2']], $mutated->toArray());
        self::assertSame(['app' => ['name' => 'zef']], $config->toArray());
    }

    public function testWithUnsetRejectsAMalformedKey(): void
    {
        $config = new Config(['app' => 'zef']);

        try {
            $config->withUnset('.app');
            self::fail('A malformed key must raise a ConfigException.');
        } catch (ConfigException $configException) {
            self::assertSame(
                'Configuration key ".app" is malformed: empty segment.',
                $configException->getMessage(),
            );
        }
    }

    public function testWithAppendAddsTheValueToTheEndOfAList(): void
    {
        $config = new Config(['middleware' => ['auth', 'cors']]);

        $mutated = $config->withAppend('middleware', 'gzip');

        self::assertSame(['auth', 'cors', 'gzip'], $mutated->get('middleware'));
        self::assertSame(['middleware' => ['auth', 'cors']], $config->toArray());
    }

    public function testWithAppendToAnEmptyList(): void
    {
        $config = new Config(['middleware' => []]);

        self::assertSame(['gzip'], $config->withAppend('middleware', 'gzip')->get('middleware'));
    }

    public function testWithAppendToANestedList(): void
    {
        $config = new Config(['users' => [['roles' => ['admin']]]]);

        $mutated = $config->withAppend('users.0.roles', 'dev');

        self::assertSame(['admin', 'dev'], $mutated->get('users.0.roles'));
        self::assertSame(['users' => [['roles' => ['admin']]]], $config->toArray());
    }

    public function testWithAppendKeepsTheOriginalRepositoryIntact(): void
    {
        $data = ['middleware' => ['auth']];
        $config = new Config($data);

        $config->withAppend('middleware', 'gzip');

        self::assertSame($data, $config->toArray());
    }

    public function testWithAppendRejectsAMissingKey(): void
    {
        $config = new Config(['app' => 'zef']);

        try {
            $config->withAppend('middleware', 'gzip');
            self::fail('Appending to a missing key must raise a ConfigException.');
        } catch (ConfigException $configException) {
            self::assertSame(
                'Cannot append to configuration key "middleware": it is not set.',
                $configException->getMessage(),
            );
        }
    }

    public function testWithAppendRejectsAScalarTarget(): void
    {
        $config = new Config(['app' => 'zef']);

        try {
            $config->withAppend('app', 'value');
            self::fail('Appending to a scalar must raise a ConfigException.');
        } catch (ConfigException $configException) {
            self::assertSame(
                'Cannot append to configuration key "app": it is not a list.',
                $configException->getMessage(),
            );
        }
    }

    public function testWithAppendRejectsAnAssociativeMapTarget(): void
    {
        $config = new Config(['driver' => ['primary' => 'mysql']]);

        try {
            $config->withAppend('driver', 'secondary');
            self::fail('Appending to an associative map must raise a ConfigException.');
        } catch (ConfigException $configException) {
            self::assertSame(
                'Cannot append to configuration key "driver": it is not a list.',
                $configException->getMessage(),
            );
        }
    }

    public function testWithAppendRejectsAMalformedKey(): void
    {
        $config = new Config(['app' => 'zef']);

        try {
            $config->withAppend('app..name', 'value');
            self::fail('A malformed key must raise a ConfigException.');
        } catch (ConfigException $configException) {
            self::assertSame(
                'Configuration key "app..name" is malformed: empty segment.',
                $configException->getMessage(),
            );
        }
    }

    public function testWithPrependAddsTheValueToTheFrontOfAList(): void
    {
        $config = new Config(['middleware' => ['auth', 'cors']]);

        $mutated = $config->withPrepend('middleware', 'gzip');

        self::assertSame(['gzip', 'auth', 'cors'], $mutated->get('middleware'));
        self::assertSame(['middleware' => ['auth', 'cors']], $config->toArray());
    }

    public function testWithPrependToAnEmptyList(): void
    {
        $config = new Config(['middleware' => []]);

        self::assertSame(['gzip'], $config->withPrepend('middleware', 'gzip')->get('middleware'));
    }

    public function testWithPrependKeepsTheOriginalRepositoryIntact(): void
    {
        $data = ['middleware' => ['auth']];
        $config = new Config($data);

        $config->withPrepend('middleware', 'gzip');

        self::assertSame($data, $config->toArray());
    }

    public function testWithPrependRejectsAMissingKey(): void
    {
        $config = new Config(['app' => 'zef']);

        try {
            $config->withPrepend('middleware', 'gzip');
            self::fail('Prepending to a missing key must raise a ConfigException.');
        } catch (ConfigException $configException) {
            self::assertSame(
                'Cannot prepend to configuration key "middleware": it is not set.',
                $configException->getMessage(),
            );
        }
    }

    public function testWithPrependRejectsAScalarTarget(): void
    {
        $config = new Config(['app' => 'zef']);

        try {
            $config->withPrepend('app', 'value');
            self::fail('Prepending to a scalar must raise a ConfigException.');
        } catch (ConfigException $configException) {
            self::assertSame(
                'Cannot prepend to configuration key "app": it is not a list.',
                $configException->getMessage(),
            );
        }
    }

    public function testWithPrependRejectsAnAssociativeMapTarget(): void
    {
        $config = new Config(['driver' => ['primary' => 'mysql']]);

        try {
            $config->withPrepend('driver', 'secondary');
            self::fail('Prepending to an associative map must raise a ConfigException.');
        } catch (ConfigException $configException) {
            self::assertSame(
                'Cannot prepend to configuration key "driver": it is not a list.',
                $configException->getMessage(),
            );
        }
    }

    public function testWithPrependRejectsAMalformedKey(): void
    {
        $config = new Config(['app' => 'zef']);

        try {
            $config->withPrepend('app..name', 'value');
            self::fail('A malformed key must raise a ConfigException.');
        } catch (ConfigException $configException) {
            self::assertSame(
                'Configuration key "app..name" is malformed: empty segment.',
                $configException->getMessage(),
            );
        }
    }

    public function testMutationsChainIntoABuiltConfiguration(): void
    {
        $config = new Config(['app' => ['name' => 'zef']]);

        $built = $config
            ->withSet('database.host', 'localhost')
            ->withSet('middleware', [])
            ->withAppend('middleware', 'auth')
            ->withPrepend('middleware', 'gzip')
        ;

        self::assertSame('localhost', $built->get('database.host'));
        self::assertSame(['gzip', 'auth'], $built->get('middleware'));
        self::assertSame(['app' => ['name' => 'zef']], $config->toArray());
    }

    public function testASplitRepositoryMutatesIndependentlyOfItsParent(): void
    {
        $config = new Config(['database' => ['host' => 'localhost']]);
        $database = $config->split('database');

        $mutated = $database->withSet('port', 3306);

        self::assertSame(['host' => 'localhost', 'port' => 3306], $mutated->toArray());
        self::assertSame(['host' => 'localhost'], $database->toArray());
        self::assertSame(['database' => ['host' => 'localhost']], $config->toArray());
    }
}
