<?php

declare(strict_types=1);

namespace Zef\Test\Adapters\Event;

use InvalidArgumentException;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use RuntimeException;
use Zef\Framework\Adapters\Database\ConnectionConfig;
use Zef\Framework\Adapters\Database\PdoConnection;
use Zef\Framework\Adapters\Event\PdoDeliveryStore;
use Zef\Framework\Adapters\Event\PdoOutboxAdmission;
use Zef\Framework\Adapters\Event\PdoOutboxBoundary;
use Zef\Framework\Adapters\Event\PdoOutboxDialect;
use Zef\Framework\Adapters\Event\PdoOutboxEntries;
use Zef\Framework\Adapters\Event\PdoOutboxPayload;
use Zef\Framework\Adapters\Event\PdoOutboxPending;
use Zef\Framework\Adapters\Event\PdoOutboxRecords;
use Zef\Framework\Adapters\Event\PdoOutboxRelay;
use Zef\Framework\Adapters\Event\PdoOutboxSchema;
use Zef\Framework\Adapters\Event\PdoOutboxStore;
use Zef\Framework\Adapters\Event\PdoOutboxTables;
use Zef\Framework\Database\ConnectionInterface;
use Zef\Framework\Database\SqlQuery;
use Zef\Framework\Database\TransactionManager;
use Zef\Framework\Event\EventDispatcher;
use Zef\Framework\Event\EventName;
use Zef\Framework\Event\EventShape;
use Zef\Framework\Event\ListenerPriority;
use Zef\Framework\Event\OutboxMessage;
use Zef\Framework\Event\OutboxMessageId;
use Zef\Framework\Event\RetryPolicy;
use Zef\Test\Event\DurableRecordingListener;
use Zef\Test\Event\EventLog;
use Zef\Test\Event\FixedResolver;
use Zef\Test\Support\Adapters\Event\ScriptedDeliveryConnection;

/**
 * Pins the outbox store's database answer to the transactional
 * arm's persistence port, over the real SQLite in-memory engine
 * the database family's own battery rides: the table the
 * construction ensures (idempotently), the append riding the
 * caller's own transaction (the business write and the outbox row
 * landing together or neither -- the same-transaction write the
 * whole lane is named for), the append's capacity arithmetic with
 * its counts spelled, the read order (appended-at then identity),
 * and the relay pass as one unit -- the read, the handoff's
 * delivery writes and the removals both-or-neither, observed over
 * the real delivery store on the SAME connection the engine
 * spells, exactly the composition demand the port's doctrine
 * owns. The scripted tier pins the two foreign dialect spellings
 * and the boundary's translated refusals.
 *
 * @internal
 */
#[CoversClass(PdoOutboxStore::class)]
#[CoversClass(PdoOutboxAdmission::class)]
#[CoversClass(PdoOutboxBoundary::class)]
#[CoversClass(PdoOutboxDialect::class)]
#[CoversClass(PdoOutboxEntries::class)]
#[CoversClass(PdoOutboxPending::class)]
#[CoversClass(PdoOutboxPayload::class)]
#[CoversClass(PdoOutboxRecords::class)]
#[CoversClass(PdoOutboxRelay::class)]
#[CoversClass(PdoOutboxSchema::class)]
#[CoversClass(PdoOutboxTables::class)]
#[CoversClass(OutboxMessage::class)]
#[CoversClass(OutboxMessageId::class)]
final class PdoOutboxStoreTest extends TestCase
{
    private ConnectionInterface $connection;

    #[Override]
    protected function setUp(): void
    {
        $this->connection = new PdoConnection(ConnectionConfig::fromArray([
            'driver' => 'sqlite',
            'dbname' => ':memory:',
        ]));
    }

    public function testACapacityBelowOneIsRefused(): void
    {
        try {
            new PdoOutboxStore($this->connection, 0, 'zef_');
            self::fail('The zero capacity was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                "An outbox store's capacity must be at least one, got 0.",
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testAnEmptyTablePrefixIsRefused(): void
    {
        try {
            new PdoOutboxStore($this->connection, 8, '');
            self::fail('The empty prefix was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                "A PDO outbox store's table prefix must not be empty -- the"
                . " un-prefixed vocabulary is the host schema's own, never the"
                . " store's to claim.",
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testAPrefixThatComposesAnInvalidTableNameIsRefused(): void
    {
        try {
            new PdoOutboxStore($this->connection, 8, 'zef-');
            self::fail('The prefix that composes an invalid table name was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertStringContainsString(
                'zef-outbox',
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testTheConstructionEnsuresTheOutboxTableAndTheIndex(): void
    {
        new PdoOutboxStore($this->connection, 8, 'zef_');

        $schemaObjectNames = $this->schemaObjectNames();

        self::assertContains('zef_outbox', $schemaObjectNames);
        self::assertContains('zef_outbox_appended_at', $schemaObjectNames);
    }

    public function testASecondStoreOverTheSameSchemaIsTheNoOpTheTableAnswers(): void
    {
        new PdoOutboxStore($this->connection, 8, 'zef_');
        new PdoOutboxStore($this->connection, 8, 'zef_');

        self::assertCount(1, array_keys($this->schemaObjectNames(), 'zef_outbox', true));
    }

    public function testTheAppendRidesTheCallersTransactionAndLandsWithItsCommit(): void
    {
        $pdoOutboxStore = new PdoOutboxStore($this->connection, 8, 'zef_');
        $transactionManager = new TransactionManager($this->connection);
        $this->businessTable();

        $transactionManager->withTransaction(
            function (ConnectionInterface $connection) use ($pdoOutboxStore): void {
                $this->businessWrite($connection);
                $pdoOutboxStore->append($this->message(100));
            },
        );

        self::assertSame(1, $pdoOutboxStore->pendingCount());
        self::assertSame(1, $this->businessRowCount());
    }

    public function testTheCallersRollbackDiscardsTheOutboxRowWithTheBusinessWrite(): void
    {
        $pdoOutboxStore = new PdoOutboxStore($this->connection, 8, 'zef_');
        $transactionManager = new TransactionManager($this->connection);
        $this->businessTable();

        try {
            $transactionManager->withTransaction(
                function (ConnectionInterface $connection) use ($pdoOutboxStore): never {
                    $this->businessWrite($connection);
                    $pdoOutboxStore->append($this->message(100));

                    throw new RuntimeException('the command refuses');
                },
            );
            // @phpstan-ignore-next-line (the scope always throws on this path)
            self::fail('The throwing body must propagate.');
        } catch (RuntimeException) {
        }

        self::assertSame(0, $pdoOutboxStore->pendingCount());
        self::assertSame(0, $this->businessRowCount());
    }

    public function testTheAppendOutsideAnyScopeLandsOnItsOwn(): void
    {
        $pdoOutboxStore = new PdoOutboxStore($this->connection, 8, 'zef_');

        $pdoOutboxStore->append($this->message(100));

        self::assertSame(1, $pdoOutboxStore->pendingCount());
    }

    public function testAnEmptyAppendIsTheNoOp(): void
    {
        $pdoOutboxStore = new PdoOutboxStore($this->connection, 8, 'zef_');

        $pdoOutboxStore->append();

        self::assertSame(0, $pdoOutboxStore->pendingCount());
    }

    public function testTheCapacityRefusalSpellsTheCounts(): void
    {
        $pdoOutboxStore = new PdoOutboxStore($this->connection, 2, 'zef_');

        $pdoOutboxStore->append($this->message(100), $this->message(101));

        try {
            $pdoOutboxStore->append($this->message(102), $this->message(103));
            self::fail('The over-capacity batch was not refused whole.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                'The outbox store is full: capacity 2, occupied 2, demanded 2'
                . ' -- relay before deferring more.',
                $invalidArgumentException->getMessage(),
            );
        }

        self::assertSame(2, $pdoOutboxStore->pendingCount());
    }

    public function testABatchLandingExactlyAtCapacityIsAdmitted(): void
    {
        $pdoOutboxStore = new PdoOutboxStore($this->connection, 2, 'zef_');

        $pdoOutboxStore->append($this->message(100), $this->message(101));

        self::assertSame(2, $pdoOutboxStore->pendingCount());
    }

    public function testThePendingReadOrdersByAppendedAtThenIdentity(): void
    {
        $pdoOutboxStore = new PdoOutboxStore($this->connection, 8, 'zef_');

        $early = $this->message(100);
        $late = $this->message(200);
        $lateTie = $this->message(200);

        $pdoOutboxStore->append($late, $early, $lateTie);

        $ties = [$late->messageId()->spelling(), $lateTie->messageId()->spelling()];
        sort($ties);

        $relayedIdentities = [];
        $pdoOutboxStore->relay(8, static function (OutboxMessage ...$outboxMessages) use (&$relayedIdentities): void {
            foreach ($outboxMessages as $outboxMessage) {
                $relayedIdentities[] = $outboxMessage->messageId()->spelling();
            }
        });

        self::assertSame(
            [$early->messageId()->spelling(), $ties[0], $ties[1]],
            $relayedIdentities,
        );
    }

    public function testTheRelayRemovesTheRowsItRelayed(): void
    {
        $pdoOutboxStore = new PdoOutboxStore($this->connection, 8, 'zef_');

        $pdoOutboxStore->append($this->message(100), $this->message(101));

        $relayed = $pdoOutboxStore->relay(8, static function (): void {
        });

        self::assertSame(2, $relayed);
        self::assertSame(0, $pdoOutboxStore->pendingCount());
    }

    public function testAnEmptyOutboxRelaysNothing(): void
    {
        $pdoOutboxStore = new PdoOutboxStore($this->connection, 8, 'zef_');

        $relayed = $pdoOutboxStore->relay(8, static function (): never {
            self::fail('An empty outbox must never hand the handoff any rows.');
        });

        self::assertSame(0, $relayed);
    }

    public function testARelayBelowOneRowRefuses(): void
    {
        $pdoOutboxStore = new PdoOutboxStore($this->connection, 8, 'zef_');

        try {
            $pdoOutboxStore->relay(0, static function (): void {
            });
            self::fail('The zero limit was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                'A relay pass relays at least one row, got 0.',
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testARefusedHandoffRollsTheWholePassBack(): void
    {
        $pdoOutboxStore = new PdoOutboxStore($this->connection, 8, 'zef_');
        $pdoDeliveryStore = new PdoDeliveryStore($this->connection, 1, 60, 'zef_');

        $pdoOutboxStore->append($this->message(100));

        try {
            $pdoOutboxStore->relay(
                8,
                static function (): never {
                    throw new InvalidArgumentException('the handoff refuses');
                },
            );
            self::fail('The refused handoff must roll the whole pass back.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame('the handoff refuses', $invalidArgumentException->getMessage());
        }

        self::assertSame(1, $pdoOutboxStore->pendingCount());
        self::assertSame(0, $pdoDeliveryStore->pendingCount());
    }

    public function testTheRelayFeedsTheDeliveryStoreOverTheSameConnection(): void
    {
        $pdoOutboxStore = new PdoOutboxStore($this->connection, 8, 'zef_');
        $pdoDeliveryStore = new PdoDeliveryStore($this->connection, 8, 60, 'zef_');

        $pdoOutboxStore->append($this->message(100), $this->message(101));

        $relayed = $pdoOutboxStore->relay(
            8,
            function (OutboxMessage ...$outboxMessages) use ($pdoDeliveryStore): void {
                foreach ($outboxMessages as $outboxMessage) {
                    $pdoDeliveryStore->save($this->envelopeFor($outboxMessage));
                }
            },
        );

        self::assertSame(2, $relayed);
        self::assertSame(0, $pdoOutboxStore->pendingCount());
        self::assertSame(2, $pdoDeliveryStore->pendingCount());
    }

    public function testTheRelaysRefusedHandoffKeepsEveryRowPendingAndNothingDelivered(): void
    {
        $pdoOutboxStore = new PdoOutboxStore($this->connection, 8, 'zef_');
        $pdoDeliveryStore = new PdoDeliveryStore($this->connection, 8, 60, 'zef_');

        $pdoOutboxStore->append($this->message(100));

        try {
            $pdoOutboxStore->relay(
                8,
                function (OutboxMessage ...$outboxMessages) use ($pdoDeliveryStore): void {
                    foreach ($outboxMessages as $outboxMessage) {
                        $pdoDeliveryStore->save($this->envelopeFor($outboxMessage));
                    }

                    throw new InvalidArgumentException('the handoff refuses after its writes');
                },
            );
            self::fail('The refused handoff must refuse the whole pass.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame('the handoff refuses after its writes', $invalidArgumentException->getMessage());
        }

        self::assertSame(1, $pdoOutboxStore->pendingCount());
        self::assertSame(0, $pdoDeliveryStore->pendingCount());
    }

    public function testTheTransactionFacadeAnswersTheWholeLaneOverOneEngine(): void
    {
        $pdoOutboxStore = new PdoOutboxStore($this->connection, 8, 'zef_');
        $pdoDeliveryStore = new PdoDeliveryStore($this->connection, 8, 60, 'zef_');
        $transactionManager = new TransactionManager($this->connection);
        $eventLog = new EventLog();
        $durableRecordingListener = new DurableRecordingListener($eventLog, 'audit', 'app.orders.audit');

        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->define(new EventName('order.paid'), new EventShape(['amount' => 'int']));
        $eventDispatcher->subscribe(new EventName('order.paid'), $durableRecordingListener, new ListenerPriority(0));

        $durableEventDispatcher = $eventDispatcher->durableDelivery(
            $pdoDeliveryStore,
            new FixedResolver(['app.orders.audit' => $durableRecordingListener]),
            new RetryPolicy(3, 10),
            1,
        );
        $transactional = $eventDispatcher->transactionalDelivery($pdoOutboxStore, $durableEventDispatcher);
        $this->businessTable();

        $transactionManager->withTransaction(
            function (ConnectionInterface $connection) use ($transactional): void {
                $this->businessWrite($connection);
                $transactional->defer(new EventName('order.paid'), ['amount' => 12], 100);
            },
        );

        self::assertSame(1, $pdoOutboxStore->pendingCount());
        self::assertSame(0, $pdoDeliveryStore->pendingCount());

        $outboxRelayOutcome = $transactional->relay(8);

        self::assertSame(1, $outboxRelayOutcome->relayed());
        self::assertSame(1, $outboxRelayOutcome->admitted());
        self::assertSame(0, $outboxRelayOutcome->skippedQuarantined());
        self::assertSame(0, $pdoOutboxStore->pendingCount());
        self::assertSame(1, $pdoDeliveryStore->pendingCount());

        $durableEventDispatcher->drain(200);

        self::assertSame(['audit'], $eventLog->tags());
        self::assertSame(1, $this->businessRowCount());
    }

    public function testTheEngineRefusalIsAnsweredInThePortsFamily(): void
    {
        $pdoOutboxStore = new PdoOutboxStore($this->connection, 8, 'zef_');

        $this->connection->execute(SqlQuery::raw('DROP TABLE zef_outbox'));

        try {
            $pdoOutboxStore->append($this->message(100));
            self::fail("The vanished table must refuse in the port's family.");
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertStringStartsWith(
                'The outbox store refused:',
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testACorruptPayloadRefusesNamingTheRow(): void
    {
        $pdoOutboxStore = new PdoOutboxStore($this->connection, 8, 'zef_');

        $outboxMessage = $this->message(100);
        $pdoOutboxStore->append($outboxMessage);

        $this->connection->execute(
            SqlQuery::raw(
                "UPDATE zef_outbox SET payload = '{not json' WHERE message_id = '"
                . $outboxMessage->messageId()->spelling() . "'",
            ),
        );

        try {
            $pdoOutboxStore->relay(8, static function (): void {
            });
            self::fail('The corrupt payload must refuse naming the row.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertStringContainsString(
                $outboxMessage->messageId()->spelling(),
                $invalidArgumentException->getMessage(),
            );
            self::assertStringContainsString('refuses to decode', $invalidArgumentException->getMessage());
        }
    }

    public function testAHostileCellRefuses(): void
    {
        $scriptedDeliveryConnection = new ScriptedDeliveryConnection(
            'sqlite',
            [],
            [[['message_id' => 7, 'event_name' => 'order.paid', 'payload' => '{}', 'appended_at' => 1]]],
        );
        $pdoOutboxStore = new PdoOutboxStore($scriptedDeliveryConnection, 8, 'zef_');

        try {
            $pdoOutboxStore->relay(8, static function (): void {
            });
            self::fail('The hostile cell must refuse.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                'The pending read refused its answer: the message_id cell is'
                . ' not the string its column spells.',
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testAHostileAppendedAtCellRefusesNamingTheRow(): void
    {
        $pdoOutboxStore = new PdoOutboxStore($this->connection, 8, 'zef_');

        $outboxMessage = $this->message(100);
        $pdoOutboxStore->append($outboxMessage);

        $this->connection->execute(
            SqlQuery::raw(
                "UPDATE zef_outbox SET appended_at = 'soon' WHERE message_id = '"
                . $outboxMessage->messageId()->spelling() . "'",
            ),
        );

        try {
            $pdoOutboxStore->relay(8, static function (): void {
            });
            self::fail('The hostile appended-at cell must refuse.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertStringContainsString(
                $outboxMessage->messageId()->spelling(),
                $invalidArgumentException->getMessage(),
            );
            self::assertStringContainsString(
                'an appended-at cell that is not the integer its column spells',
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testThePendingCountRefusesAShapeTheContractDoesNotSpell(): void
    {
        $scriptedDeliveryConnection = new ScriptedDeliveryConnection('sqlite', [null]);
        $pdoOutboxBoundary = new PdoOutboxBoundary($scriptedDeliveryConnection);
        $pdoOutboxTables = new PdoOutboxTables('zef_');

        try {
            $pdoOutboxAdmission = new PdoOutboxAdmission(
                $pdoOutboxBoundary,
                $pdoOutboxTables,
                new PdoOutboxEntries($pdoOutboxBoundary, $pdoOutboxTables),
                8,
            );
            $reflectionMethod = new ReflectionMethod($pdoOutboxAdmission, 'pendingCount');
            $reflectionMethod->invoke($pdoOutboxAdmission);
            self::fail('The missing aggregate must refuse.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                'The pending count returned no aggregate value.',
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testTheStandardSchemaSpellsTheIndexStatement(): void
    {
        $scriptedDeliveryConnection = new ScriptedDeliveryConnection('pgsql');
        $pdoOutboxDialect = new PdoOutboxDialect($scriptedDeliveryConnection->driver());

        self::assertSame(
            'CREATE TABLE IF NOT EXISTS zef_outbox (message_id CHAR(32) NOT NULL'
            . ' PRIMARY KEY, event_name VARCHAR(64) NOT NULL, payload TEXT NOT'
            . ' NULL, appended_at BIGINT NOT NULL)',
            $pdoOutboxDialect->outboxTableDdl(new \Zef\Framework\Database\TableName('zef_outbox')),
        );
        self::assertSame(
            'CREATE INDEX IF NOT EXISTS zef_outbox_appended_at ON zef_outbox'
            . ' (appended_at, message_id)',
            $pdoOutboxDialect->outboxIndexDdl(new \Zef\Framework\Database\TableName('zef_outbox')),
        );
    }

    public function testTheMySqlSchemaCarriesTheIndexInlineAndSpellsNoIndexStatement(): void
    {
        $scriptedDeliveryConnection = new ScriptedDeliveryConnection('mysql');
        $pdoOutboxDialect = new PdoOutboxDialect($scriptedDeliveryConnection->driver());

        self::assertSame(
            'CREATE TABLE IF NOT EXISTS zef_outbox (message_id CHAR(32) NOT NULL'
            . ' PRIMARY KEY, event_name VARCHAR(64) NOT NULL, payload TEXT NOT'
            . ' NULL, appended_at BIGINT NOT NULL, KEY zef_outbox_appended_at'
            . ' (appended_at, message_id))',
            $pdoOutboxDialect->outboxTableDdl(new \Zef\Framework\Database\TableName('zef_outbox')),
        );
        self::assertNull(
            $pdoOutboxDialect->outboxIndexDdl(new \Zef\Framework\Database\TableName('zef_outbox')),
        );
    }

    public function testAnUnknownDriverOwnsNoDialect(): void
    {
        try {
            new PdoOutboxDialect('sqlsrv');
            self::fail('The unknown driver was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                'The PDO outbox store answers the three drivers the DSN grammar'
                . " accepts (mysql, pgsql, sqlite), got 'sqlsrv'.",
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testTheBoundaryCarriesTheAnswersThroughUntouched(): void
    {
        $scriptedDeliveryConnection = new ScriptedDeliveryConnection(
            'sqlite',
            [['message_id' => 'x', 'appended_at' => 1]],
            [[['message_id' => 'x', 'appended_at' => 1]]],
        );

        $pdoOutboxBoundary = new PdoOutboxBoundary($scriptedDeliveryConnection);

        self::assertSame('sqlite', $pdoOutboxBoundary->driver());
        self::assertSame(1, $pdoOutboxBoundary->execute(SqlQuery::raw('INSERT INTO t VALUES (1)')));
        self::assertSame(
            [['message_id' => 'x', 'appended_at' => 1]],
            $pdoOutboxBoundary->fetchAll(SqlQuery::raw('SELECT * FROM t')),
        );
        self::assertSame(['message_id' => 'x', 'appended_at' => 1], $pdoOutboxBoundary->fetchOne(SqlQuery::raw('SELECT * FROM t')));
        self::assertNull($scriptedDeliveryConnection->lastInsertId());
    }

    public function testTheBoundaryAnswersEveryEngineRefusalInThePortsFamily(): void
    {
        $refusingConnection = new class implements ConnectionInterface {
            #[Override]
            public function driver(): string
            {
                return 'sqlite';
            }

            #[Override]
            public function execute(SqlQuery $sqlQuery): int
            {
                throw $this->refusal();
            }

            #[Override]
            public function fetchAll(SqlQuery $sqlQuery): array
            {
                throw $this->refusal();
            }

            #[Override]
            public function fetchOne(SqlQuery $sqlQuery): ?array
            {
                throw $this->refusal();
            }

            #[Override]
            public function lastInsertId(): ?string
            {
                throw $this->refusal();
            }

            #[Override]
            public function beginTransaction(?\Zef\Framework\Database\IsolationLevel $isolationLevel = null): void
            {
                throw $this->refusal();
            }

            #[Override]
            public function commit(): void
            {
                throw $this->refusal();
            }

            #[Override]
            public function rollBack(): void
            {
                throw $this->refusal();
            }

            #[Override]
            public function transactionLevel(): int
            {
                throw $this->refusal();
            }

            #[Override]
            public function transaction(callable $fn, ?\Zef\Framework\Database\IsolationLevel $isolationLevel = null): mixed
            {
                throw $this->refusal();
            }

            private function refusal(): \Zef\Framework\Database\QueryException
            {
                return new \Zef\Framework\Database\QueryException('the engine refuses');
            }
        };

        $pdoOutboxBoundary = new PdoOutboxBoundary($refusingConnection);

        foreach ([
            static fn (): int => $pdoOutboxBoundary->execute(SqlQuery::raw('SELECT 1')),
            static fn (): array => $pdoOutboxBoundary->fetchAll(SqlQuery::raw('SELECT 1')),
            static fn (): ?array => $pdoOutboxBoundary->fetchOne(SqlQuery::raw('SELECT 1')),
            $pdoOutboxBoundary->lastInsertId(...),
            static fn (): null => $pdoOutboxBoundary->beginTransaction(),
            $pdoOutboxBoundary->commit(...),
            $pdoOutboxBoundary->rollBack(...),
            $pdoOutboxBoundary->transactionLevel(...),
            static fn (): mixed => $pdoOutboxBoundary->transaction(static fn (): null => null),
        ] as $verb) {
            try {
                $verb();
                self::fail('The engine refusal was not translated.');
            } catch (InvalidArgumentException $invalidArgumentException) {
                self::assertSame(
                    'The outbox store refused: the engine refuses',
                    $invalidArgumentException->getMessage(),
                );
            }
        }
    }

    private function message(int $appendedAt, ?string $messageId = null): OutboxMessage
    {
        return new OutboxMessage(
            $messageId === null ? OutboxMessageId::mint() : new OutboxMessageId($messageId),
            new EventName('order.paid'),
            ['amount' => 12],
            $appendedAt,
        );
    }

    /**
     * The delivery envelope one relayed row's handoff writes into
     * the delivery store over the same connection.
     */
    private function envelopeFor(OutboxMessage $outboxMessage): \Zef\Framework\Event\DeliveryEnvelope
    {
        return new \Zef\Framework\Event\DeliveryEnvelope(
            \Zef\Framework\Event\DeliveryEntryId::mint(),
            $outboxMessage->name(),
            $outboxMessage->values(),
            new \Zef\Framework\Event\ListenerIdentity('app.orders.audit'),
            1,
            0,
        );
    }

    /**
     * The host's own business table, the write the outbox row
     * rides -- a plain table the same transaction owns.
     */
    private function businessTable(): void
    {
        $this->connection->execute(SqlQuery::raw('CREATE TABLE IF NOT EXISTS business (id INTEGER PRIMARY KEY, amount INT)'));
    }

    private function businessWrite(ConnectionInterface $connection): void
    {
        $connection->execute(SqlQuery::raw('INSERT INTO business (amount) VALUES (12)'));
    }

    private function businessRowCount(): int
    {
        $row = $this->connection->fetchOne(SqlQuery::raw('SELECT COUNT(*) AS rows FROM business'));

        $count = $row['rows'] ?? null;

        if (!\is_int($count) && !\is_string($count) && !\is_float($count)) {
            throw new InvalidArgumentException('The business count returned no aggregate value.');
        }

        return (int) $count;
    }

    /**
     * @return list<string> the schema's own object names, the table
     *                      and its index alike
     */
    private function schemaObjectNames(): array
    {
        $names = [];

        foreach (
            $this->connection->fetchAll(
                SqlQuery::raw("SELECT name FROM sqlite_master WHERE name NOT LIKE 'sqlite_%'"),
            ) as $row
        ) {
            $name = $row['name'] ?? null;

            if (!\is_string($name)) {
                throw new InvalidArgumentException(
                    'The schema read refused its answer: a name cell is not'
                    . ' the string its column spells.',
                );
            }

            $names[] = $name;
        }

        return $names;
    }
}
