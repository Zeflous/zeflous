<?php

declare(strict_types=1);

namespace Zef\Test;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Adapters\Database\PdoConnection;
use Zef\Framework\ApplicationBootstrapper;
use Zef\Framework\Config\Config;
use Zef\Framework\Config\ConfigException;
use Zef\Framework\Database\ConnectionException;
use Zef\Framework\Database\ConnectionInterface;
use Zef\Framework\Database\Migrator;
use Zef\Framework\Database\SqlQuery;
use Zef\Framework\Database\TableGateway;
use Zef\Framework\Database\TransactionManager;
use Zef\Framework\Database\UnitOfWork;
use Zef\Framework\DatabaseConnections;
use Zef\Framework\DatabaseDeclaration;
use Zef\Framework\DatabaseKnobs;
use Zef\Framework\DatabaseWiring;
use Zef\Framework\Kernel;
use Zef\Test\Support\Database\ScriptedMigration;
use Zef\Test\Support\Telemetry\RecordingMeter;

/**
 * Pins the database family's wiring: the composition folds that
 * answer the family's entry points from the frozen repository (the
 * silent refusal with the knob grammar spelled, the default
 * connection's happy path over a real SQLite database, the
 * per-purpose migrations connection and its standalone posture, the
 * transaction manager over the handed connection, the generic
 * gateway) and -- the rule-17 integration the slice exists for --
 * the boot-level end-to-end path from declaration to read: the
 * silent profile boots without ever running the wiring's factories
 * (the lazy proxies the singleton warmer answers are the
 * never-at-the-boot discipline), the silent wired default refuses
 * only at the host's own first use, and a declared connection
 * answers through the container as ONE shared instance whose
 * queries, migrator, transaction manager and audit records all ride
 * the same socket.
 *
 * @internal
 */
#[CoversClass(DatabaseWiring::class)]
#[CoversClass(DatabaseDeclaration::class)]
#[CoversClass(DatabaseKnobs::class)]
#[CoversClass(DatabaseConnections::class)]
final class DatabaseWiringTest extends TestCase
{
    private ?string $temporaryConfigs = null;

    private ?string $temporaryDatabase = null;

    protected function tearDown(): void
    {
        foreach ([$this->temporaryConfigs, $this->temporaryDatabase] as $path) {
            if ($path === null || !str_starts_with($path, sys_get_temp_dir()) || !is_file($path)) {
                continue;
            }

            unlink($path);
        }

        if ($this->temporaryConfigs !== null && is_dir(\dirname($this->temporaryConfigs))) {
            rmdir(\dirname($this->temporaryConfigs));
        }

        $this->temporaryConfigs = null;
        $this->temporaryDatabase = null;
    }

    public function testTheSilentDeclarationRefusesWithTheKnobGrammarSpelled(): void
    {
        $configException = $this->refusingConfig(new Config([]));
        self::assertInstanceOf(ConfigException::class, $configException);

        self::assertSame(
            'The database.connection wired default is not declared: spell a connection'
                . ' (database.connections plus database.default) or compose the family over'
                . ' your own ConnectionInterface.',
            $configException->getMessage(),
        );
    }

    public function testASilentFamilyWithConnectionsDeclaredStillRefuses(): void
    {
        $configException = $this->refusingConfig(new Config([
            'database' => ['connections' => ['app' => ['driver' => 'sqlite', 'dbname' => ':memory:']]],
        ]));

        self::assertNotNull($configException, 'a connections map without a default name wires nothing');
    }

    public function testTheDefaultConnectionComposesOverTheAdaptersOwnFolds(): void
    {
        $connection = DatabaseWiring::connection($this->declaredConfig());

        $connection->execute(new SqlQuery('CREATE TABLE wired (id INTEGER PRIMARY KEY, v TEXT)'));
        $connection->execute(new SqlQuery('INSERT INTO wired (v) VALUES (?)', ['hello']));

        self::assertSame(
            ['id' => 1, 'v' => 'hello'],
            $connection->fetchOne(new SqlQuery('SELECT * FROM wired WHERE id = 1')),
        );
    }

    public function testTheAuditCounterSeamRidesTheWiredConnection(): void
    {
        $recordingMeter = new RecordingMeter();
        $connection = DatabaseWiring::connection($this->declaredConfig(), $recordingMeter);

        $connection->execute(new SqlQuery('CREATE TABLE audited (id INTEGER PRIMARY KEY, v TEXT)'));
        $connection->execute(new SqlQuery('INSERT INTO audited (v) VALUES (?)', ['x']));

        $affected = $connection->execute(new SqlQuery('UPDATE audited SET v = ?', ['y'], true));

        self::assertSame(1, $affected);
        self::assertCount(1, $recordingMeter->increments);
        self::assertSame('zef.db.unbounded_statement', $recordingMeter->increments[0]['name']);
    }

    public function testAnUndeclaredDefaultNameHaltsWithTheKnownList(): void
    {
        $configException = $this->refusingConfig(new Config([
            'database' => [
                'default' => 'app',
                'connections' => ['ops' => ['driver' => 'sqlite', 'dbname' => ':memory:']],
            ],
        ]));
        self::assertInstanceOf(ConfigException::class, $configException);

        self::assertSame(
            "The database.default connection 'app' is not declared (database.connections: ops).",
            $configException->getMessage(),
        );
    }

    public function testAConnectionFieldRefusalCarriesTheAdaptersOwnTypedMessage(): void
    {
        $this->expectException(ConnectionException::class);
        $this->expectExceptionMessageIsOrContains(
            "Database name (dbname) must be a non-empty string for driver 'sqlite'.",
        );

        DatabaseWiring::connection(new Config([
            'database' => [
                'default' => 'app',
                'connections' => ['app' => ['driver' => 'sqlite']],
            ],
        ]));
    }

    public function testTheMigratorRidesThePerPurposeConnection(): void
    {
        $migrator = DatabaseWiring::migrator($this->declaredConfig());

        self::assertInstanceOf(Migrator::class, $migrator);

        $migrator->register(new ScriptedMigration('20261102000001', 'create'));

        self::assertSame(['20261102000001'], $migrator->plan());
    }

    public function testAPurposeSpelledWithoutADefaultStillWires(): void
    {
        $migrator = DatabaseWiring::migrator(new Config([
            'database' => [
                'connections' => [
                    'ops' => ['driver' => 'sqlite', 'dbname' => ':memory:'],
                ],
                'migrations' => 'ops',
            ],
        ]));

        self::assertNotNull($migrator, 'the migrations purpose stands alone: no default required');
    }

    public function testTheSilentMigratorRefusesWithItsOwnGrammar(): void
    {
        $configException = $this->refusingMigrator(new Config([]));
        self::assertInstanceOf(ConfigException::class, $configException);

        self::assertSame(
            'The database.migrator wired default is not declared: spell the migration'
                . " runner's connection (database.migrations or database.default over"
                . ' database.connections) or compose the runner over your own ConnectionInterface.',
            $configException->getMessage(),
        );
    }

    public function testAnUndeclaredPurposeNameHaltsNamingThePurposeKnob(): void
    {
        $configException = $this->refusingMigrator(new Config([
            'database' => [
                'default' => 'app',
                'connections' => ['app' => ['driver' => 'sqlite', 'dbname' => ':memory:']],
                'migrations' => 'ops',
            ],
        ]));
        self::assertInstanceOf(ConfigException::class, $configException);

        self::assertSame(
            "The database.migrations connection 'ops' is not declared (database.connections: app).",
            $configException->getMessage(),
        );
    }

    public function testTheTransactionsFoldAnswersTheManagerOverTheHandedConnection(): void
    {
        $connection = DatabaseWiring::connection($this->declaredConfig());
        $connection->execute(new SqlQuery('CREATE TABLE scoped (id INTEGER PRIMARY KEY, v TEXT)'));

        $transactionManager = DatabaseWiring::transactions($connection);

        $transactionManager->withTransaction(
            static function (ConnectionInterface $connection): void {
                $connection->execute(new SqlQuery('INSERT INTO scoped (v) VALUES (?)', ['in-scope']));
            },
        );

        self::assertSame(
            ['id' => 1, 'v' => 'in-scope'],
            $connection->fetchOne(new SqlQuery('SELECT * FROM scoped WHERE id = 1')),
            'the outermost commit applied the scoped write',
        );
    }

    public function testTheRepositoryFoldAnswersTheGenericGateway(): void
    {
        $connection = DatabaseWiring::connection($this->declaredConfig());
        $gateway = DatabaseWiring::repository($connection, 'things');

        self::assertInstanceOf(TableGateway::class, $gateway);

        $connection->execute(new SqlQuery('CREATE TABLE things (id INTEGER PRIMARY KEY, v TEXT)'));
        self::assertSame(1, $gateway->insert(['v' => 'a-thing']));
        self::assertSame(['id' => 1, 'v' => 'a-thing'], $gateway->find(1));
    }

    public function testTheSilentProfileBootsWithoutRunningTheWiringFactories(): void
    {
        $kernel = new ApplicationBootstrapper()->boot(__DIR__ . '/../app/configs');

        $connection = $kernel->container()->get('database.connection');

        self::assertInstanceOf(
            PdoConnection::class,
            $connection,
            'the silent profile still answers the identity: a lazy proxy, the factory never run',
        );
    }

    public function testTheSilentWiredDefaultRefusesAtTheHostsOwnFirstUse(): void
    {
        $kernel = new ApplicationBootstrapper()->boot(__DIR__ . '/../app/configs');
        $connection = $kernel->container()->get('database.connection');

        $this->expectException(ConfigException::class);
        $this->expectExceptionMessageIsOrContains('The database.connection wired default is not declared');

        $connection->execute(new SqlQuery('SELECT 1'));
    }

    public function testADeclaredConnectionAnswersThroughTheBootAsOneSharedInstance(): void
    {
        $kernel = self::bootDeclared();

        $connection = $kernel->container()->get('database.connection');

        $connection->execute(new SqlQuery('CREATE TABLE booted (id INTEGER PRIMARY KEY, v TEXT)'));
        $connection->execute(new SqlQuery('INSERT INTO booted (v) VALUES (?)', ['booted']));

        self::assertSame(
            ['id' => 1, 'v' => 'booted'],
            $connection->fetchOne(new SqlQuery('SELECT * FROM booted WHERE id = 1')),
        );
        self::assertSame(
            $kernel->container()->get('database.connection'),
            $connection,
            'a lazy singleton materialises once: every later ask answers the one instance',
        );
    }

    public function testTheBootedMigratorUnitOfWorkAndTransactionsAnswerTheirOwnIdentities(): void
    {
        $kernel = self::bootDeclared();

        self::assertInstanceOf(
            Migrator::class,
            $kernel->container()->get('database.migrator'),
        );
        self::assertInstanceOf(
            TransactionManager::class,
            $kernel->container()->get('database.transactions'),
        );

        $unitOfWork = $kernel->container()->get('database.unit_of_work');
        self::assertInstanceOf(UnitOfWork::class, $unitOfWork);
        self::assertNotSame(
            $unitOfWork,
            $kernel->container()->get('database.unit_of_work'),
            'the deferred-write queue is a per-ask artifact: a fresh queue every resolution',
        );
    }

    public function testTheBootLevelAuditEmissonCountsOnTheSharedMeter(): void
    {
        $kernel = self::bootDeclared();
        $connection = $kernel->container()->get('database.connection');

        $connection->execute(new SqlQuery('CREATE TABLE metered (id INTEGER PRIMARY KEY, v TEXT)'));
        $connection->execute(new SqlQuery('INSERT INTO metered (v) VALUES (?)', ['x']));
        $connection->execute(new SqlQuery('UPDATE metered SET v = ?', ['y'], true));

        $series = $kernel->container()->get('observability.meter')->snapshot();

        self::assertArrayHasKey('zef.db.unbounded_statement|{"op":"update"}', $series);
    }

    private function declaredConfig(): Config
    {
        return new Config([
            'database' => [
                'default' => 'app',
                'connections' => [
                    'app' => ['driver' => 'sqlite', 'dbname' => ':memory:'],
                    'ops' => ['driver' => 'sqlite', 'dbname' => ':memory:'],
                ],
                'migrations' => 'ops',
            ],
        ]);
    }

    private function refusingConfig(Config $config): ?ConfigException
    {
        try {
            DatabaseWiring::connection($config);
        } catch (ConfigException $configException) {
            return $configException;
        }

        return null;
    }

    private function refusingMigrator(Config $config): ?ConfigException
    {
        try {
            DatabaseWiring::migrator($config);
        } catch (ConfigException $configException) {
            return $configException;
        }

        return null;
    }

    private function bootDeclared(): Kernel
    {
        $directory = sys_get_temp_dir() . '/zef-database-wiring-' . uniqid();
        $this->temporaryConfigs = $directory . '/database.php';
        $this->temporaryDatabase = $directory . '/wiring.sqlite';

        mkdir($directory, 0o777, true);

        file_put_contents($this->temporaryConfigs, '<?php return [' . \PHP_EOL
            . "    'default' => 'app'," . \PHP_EOL
            . "    'connections' => ['app' => ['driver' => 'sqlite', 'dbname' => " . var_export(
                $this->temporaryDatabase,
                true,
            ) . ']],' . \PHP_EOL
            . '];');

        return new ApplicationBootstrapper()->boot($directory);
    }
}
