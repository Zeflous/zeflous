<?php

declare(strict_types=1);

namespace Zef\Test;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Config\Config;
use Zef\Framework\Config\ConfigException;
use Zef\Framework\Database\QueryLogException;
use Zef\Framework\DatabaseConnectionKnobs;
use Zef\Framework\DatabaseConnections;
use Zef\Framework\DatabaseDeclaration;
use Zef\Framework\DatabaseKnobs;
use Zef\Framework\DatabaseQueryLogKnobs;
use Zef\Framework\FrameworkDefaults;

/**
 * Pins the database family's declaration fold: the strict census of
 * the one `database` knob block (the silent posture an absent block
 * answers, the named halts a typo'd or wrong-typed knob earns, the
 * connections map's own grammar, the per-purpose name's one-way
 * fallback), and the drift pin that holds the framework profile's
 * own declaration to the silent posture -- the two cannot disagree
 * without a test naming it. The covers list names the whole reading
 * family this class exercises -- the declaration and the two folds
 * its grammar reads through (`DatabaseKnobs`, `DatabaseConnections`)
 * plus the Default layer the drift pin reads: the suite runs with
 * requireCoverageMetadata, so coverage of a fold whose home does not
 * name it is filtered out of the clover report -- the
 * covers-attribution doctrine the events family's own declaration
 * test spelled first.
 *
 * @internal
 */
#[CoversClass(DatabaseDeclaration::class)]
#[CoversClass(DatabaseKnobs::class)]
#[CoversClass(DatabaseConnections::class)]
#[CoversClass(DatabaseConnectionKnobs::class)]
#[CoversClass(DatabaseQueryLogKnobs::class)]
#[CoversClass(FrameworkDefaults::class)]
final class DatabaseDeclarationTest extends TestCase
{
    public function testAnAbsentDatabaseBlockAnswersTheSilentPosture(): void
    {
        $declaration = DatabaseDeclaration::fromConfig(new Config([]));

        self::assertSame('', $declaration->default);
        self::assertSame([], $declaration->connections);
        self::assertSame('', $declaration->migrations);
        self::assertFalse($declaration->wantsWiredConnection(), 'an absent family declares no wired default');
        self::assertSame('', $declaration->migrationsName(), 'an absent family names no migrations purpose either');
    }

    public function testAnAbsentKnobAnswersTheSilentPostureForThatKnob(): void
    {
        $declaration = DatabaseDeclaration::fromConfig(new Config([
            'database' => [
                'connections' => ['app' => ['driver' => 'sqlite', 'dbname' => ':memory:']],
            ],
        ]));

        self::assertSame('', $declaration->default);
        self::assertSame(['app' => ['driver' => 'sqlite', 'dbname' => ':memory:']], $declaration->connections);
        self::assertSame('', $declaration->migrations);
        self::assertFalse($declaration->wantsWiredConnection(), 'a connections map alone declares no default');
    }

    public function testAMalformedDatabaseBlockHalts(): void
    {
        $configException = $this->readMalformed(['database' => 'nope']);

        self::assertSame('The database configuration must be a map, string given.', $configException->getMessage());
    }

    public function testAnUnknownKnobHaltsWithTheKnownListSpelled(): void
    {
        $configException = $this->readMalformed(['database' => ['conections' => []]]);

        self::assertSame(
            "The database configuration carries the unknown knob 'conections'"
                . ' (known knobs: default, connections, migrations, query_log).',
            $configException->getMessage(),
        );
    }

    public function testAnUnknownKnobBehindAKnownOneStillHalts(): void
    {
        $configException = $this->readMalformed([
            'database' => [
                'default' => 'app',
                'connections' => [],
                'migrations' => '',
                'conections' => [],
            ],
        ]);

        self::assertSame(
            "The database configuration carries the unknown knob 'conections'"
                . ' (known knobs: default, connections, migrations, query_log).',
            $configException->getMessage(),
            'the census walks every child: a known knob never hides an unknown sibling',
        );
    }

    public function testAWrongTypedDefaultNameHalts(): void
    {
        $configException = $this->readMalformed(['database' => ['default' => 7]]);

        self::assertSame('database.default must be a connection name string.', $configException->getMessage());
    }

    public function testAWrongTypedMigrationsNameHalts(): void
    {
        $configException = $this->readMalformed(['database' => ['migrations' => false]]);

        self::assertSame('database.migrations must be a connection name string.', $configException->getMessage());
    }

    public function testAMalformedConnectionsMapHalts(): void
    {
        $configException = $this->readMalformed(['database' => ['connections' => 'app']]);

        self::assertSame(
            'The database.connections configuration must be a map, string given.',
            $configException->getMessage(),
        );
    }

    public function testANonStringConnectionNameHalts(): void
    {
        $configException = $this->readMalformed(['database' => ['connections' => [0 => []]]]);

        self::assertSame(
            'The database.connections names must be non-empty strings, int given.',
            $configException->getMessage(),
        );
    }

    public function testAnEmptyConnectionNameHalts(): void
    {
        $configException = $this->readMalformed(['database' => ['connections' => ['' => []]]]);

        self::assertSame(
            'The database.connections names must be non-empty strings, an empty string given.',
            $configException->getMessage(),
        );
    }

    public function testANonMapConnectionDeclarationHalts(): void
    {
        $configException = $this->readMalformed(['database' => ['connections' => ['app' => 'sqlite']]]);

        self::assertSame(
            'The database.connections.app declaration must be a map, string given.',
            $configException->getMessage(),
        );
    }

    public function testANonWordKeyInsideAConnectionDeclarationHalts(): void
    {
        $configException = $this->readMalformed(['database' => ['connections' => ['app' => [0 => 'sqlite']]]]);

        self::assertSame(
            'The database.connections.app knobs must be word keys, int given.',
            $configException->getMessage(),
        );
    }

    public function testThePerPurposeNameFallsBackToTheDefaultOneWay(): void
    {
        $ownPurpose = DatabaseDeclaration::fromConfig(new Config([
            'database' => [
                'default' => 'app',
                'connections' => [
                    'app' => ['driver' => 'sqlite', 'dbname' => ':memory:'],
                    'ops' => ['driver' => 'sqlite', 'dbname' => ':memory:'],
                ],
                'migrations' => 'ops',
            ],
        ]));

        self::assertTrue($ownPurpose->wantsWiredConnection());
        self::assertSame('ops', $ownPurpose->migrationsName(), 'a spelled purpose keeps its own connection');

        $fallback = DatabaseDeclaration::fromConfig(new Config([
            'database' => [
                'default' => 'app',
                'connections' => ['app' => ['driver' => 'sqlite', 'dbname' => ':memory:']],
            ],
        ]));

        self::assertSame('app', $fallback->migrationsName(), 'a silent purpose rides the default connection');
    }

    public function testASilentDefaultAndPurposeSpellNoPurposeConnection(): void
    {
        $declaration = DatabaseDeclaration::fromConfig(new Config([
            'database' => [
                'connections' => ['ops' => ['driver' => 'sqlite', 'dbname' => ':memory:']],
            ],
        ]));

        self::assertSame('', $declaration->migrationsName(), 'no default and no purpose spell no effective name');
    }

    public function testOneAnswersTheNamedConnectionsDeclaration(): void
    {
        $connections = [
            'app' => ['driver' => 'sqlite', 'dbname' => ':memory:'],
            'ops' => ['driver' => 'sqlite', 'dbname' => ':memory:'],
        ];

        self::assertSame(
            ['driver' => 'sqlite', 'dbname' => ':memory:'],
            DatabaseConnections::one($connections, 'ops', 'migrations'),
        );
    }

    public function testOneRefusesAnUndeclaredNameWithTheAskingKnobAndKnownList(): void
    {
        $connections = ['app' => ['driver' => 'sqlite', 'dbname' => ':memory:']];
        $configException = $this->haltOn($connections, 'ops', 'migrations');

        self::assertSame(
            "The database.migrations connection 'ops' is not declared (database.connections: app).",
            $configException->getMessage(),
        );
    }

    public function testOneRefusesWithNoneDeclaredWhenTheMapIsEmpty(): void
    {
        $configException = $this->haltOn([], 'app', 'default');

        self::assertSame(
            "The database.default connection 'app' is not declared (database.connections: none declared).",
            $configException->getMessage(),
        );
    }

    public function testTheFrameworkProfileShipsTheSilentDatabasePosture(): void
    {
        $profile = FrameworkDefaults::profile();

        self::assertSame(
            ['default' => '', 'connections' => [], 'migrations' => '', 'query_log' => null],
            $profile['database'],
            'the framework ships no database of its own: the knob block lands silent,'
                . ' and a host declaration is the only way one arrives -- the query-log'
                . ' block included, its silent posture the absent block',
        );
    }

    public function testAnAbsentQueryLogBlockAnswersTheSilentPosture(): void
    {
        $declaration = DatabaseDeclaration::fromConfig(new Config([
            'database' => ['default' => 'app'],
        ]));

        self::assertNull(
            $declaration->queryLogPolicy,
            'an absent query_log block arms no query log: the honest void, no emission object at all',
        );
    }

    public function testASpelledQueryLogBlockArmsWithItsOwnDefaults(): void
    {
        $declaration = DatabaseDeclaration::fromConfig(new Config([
            'database' => ['query_log' => []],
        ]));

        $queryLogPolicy = $declaration->queryLogPolicy;
        self::assertNotNull($queryLogPolicy, 'a spelled block arms the family');
        self::assertTrue($queryLogPolicy->enabled);
        self::assertSame(100, $queryLogPolicy->thresholdMs);
    }

    public function testASpelledThresholdReadsStrictly(): void
    {
        $declaration = DatabaseDeclaration::fromConfig(new Config([
            'database' => ['query_log' => ['enabled' => false, 'threshold_ms' => 250]],
        ]));

        $queryLogPolicy = $declaration->queryLogPolicy;
        self::assertNotNull($queryLogPolicy, 'a spelled block arms the family');
        self::assertFalse($queryLogPolicy->enabled);
        self::assertSame(250, $queryLogPolicy->thresholdMs);
    }

    public function testAMalformedQueryLogBlockHalts(): void
    {
        $configException = $this->readMalformed(['database' => ['query_log' => 'nope']]);

        self::assertSame(
            'The database.query_log configuration must be a map, string given.',
            $configException->getMessage(),
        );
    }

    public function testAnUnknownQueryLogKnobHaltsWithTheKnownListSpelled(): void
    {
        $configException = $this->readMalformed(['database' => ['query_log' => ['threshold' => 5]]]);

        self::assertSame(
            "The database.query_log configuration carries the unknown knob 'threshold'"
                . ' (known knobs: enabled, threshold_ms).',
            $configException->getMessage(),
        );
    }

    public function testAnUnknownQueryLogKnobBehindAKnownOneStillHalts(): void
    {
        $configException = $this->readMalformed([
            'database' => ['query_log' => ['enabled' => true, 'threshold' => 5]],
        ]);

        self::assertSame(
            "The database.query_log configuration carries the unknown knob 'threshold'"
                . ' (known knobs: enabled, threshold_ms).',
            $configException->getMessage(),
            'the sub-map census walks every child: a known knob never hides an unknown sibling',
        );
    }

    public function testAWrongTypedArmKnobHalts(): void
    {
        $configException = $this->readMalformed(['database' => ['query_log' => ['enabled' => 'maybe']]]);

        self::assertSame(
            'database.query_log enabled must be a boolean.',
            $configException->getMessage(),
        );
    }

    public function testAWrongTypedThresholdKnobHalts(): void
    {
        $configException = $this->readMalformed(['database' => ['query_log' => ['threshold_ms' => '100']]]);

        self::assertSame(
            'database.query_log threshold_ms must be an integer.',
            $configException->getMessage(),
        );
    }

    public function testAnOutOfCorridorThresholdHaltsThroughThePolicy(): void
    {
        $queryLogException = null;

        try {
            DatabaseDeclaration::fromConfig(new Config([
                'database' => ['query_log' => ['threshold_ms' => 0]],
            ]));
        } catch (QueryLogException $queryLogException) {
            self::assertSame(
                'The database.query_log threshold_ms knob must be between 1 and 3600000, 0 given.',
                $queryLogException->getMessage(),
            );
        }

        self::assertNotNull($queryLogException, 'the out-of-corridor threshold must halt the read');
    }

    /**
     * @param array<string, mixed> $config
     */
    private function readMalformed(array $config): ConfigException
    {
        try {
            DatabaseDeclaration::fromConfig(new Config($config));
        } catch (ConfigException $configException) {
            return $configException;
        }

        self::fail('The malformed database declaration must halt.');
    }

    /**
     * @param array<string, array<string, mixed>> $connections
     */
    private function haltOn(array $connections, string $name, string $knob): ConfigException
    {
        try {
            DatabaseConnections::one($connections, $name, $knob);
        } catch (ConfigException $configException) {
            return $configException;
        }

        self::fail('The undeclared connection name must halt.');
    }
}
