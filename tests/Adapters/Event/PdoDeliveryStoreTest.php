<?php

declare(strict_types=1);

namespace Zef\Test\Adapters\Event;

use InvalidArgumentException;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Adapters\Database\ConnectionConfig;
use Zef\Framework\Adapters\Database\PdoConnection;
use Zef\Framework\Adapters\Event\PdoDeliveryAdmission;
use Zef\Framework\Adapters\Event\PdoDeliveryBooks;
use Zef\Framework\Adapters\Event\PdoDeliveryBoundary;
use Zef\Framework\Adapters\Event\PdoDeliveryClaim;
use Zef\Framework\Adapters\Event\PdoDeliveryDeadLane;
use Zef\Framework\Adapters\Event\PdoDeliveryDialect;
use Zef\Framework\Adapters\Event\PdoDeliveryDueScan;
use Zef\Framework\Adapters\Event\PdoDeliveryEntries;
use Zef\Framework\Adapters\Event\PdoDeliveryRecords;
use Zef\Framework\Adapters\Event\PdoDeliverySchema;
use Zef\Framework\Adapters\Event\PdoDeliveryStore;
use Zef\Framework\Adapters\Event\PdoDeliveryTables;
use Zef\Framework\Adapters\Event\PdoDeliveryUpsert;
use Zef\Framework\Database\ConnectionInterface;
use Zef\Framework\Database\QueryBuilder;
use Zef\Framework\Database\QueryException;
use Zef\Framework\Database\SqlQuery;
use Zef\Framework\Database\TableName;
use Zef\Framework\Event\DeliveryEntryId;
use Zef\Framework\Event\DeliveryEnvelope;
use Zef\Framework\Event\DeliveryEnvelopeDecoder;
use Zef\Framework\Event\DeliveryEnvelopeEncoder;
use Zef\Framework\Event\DrainOutcome;
use Zef\Framework\Event\DrainTrace;
use Zef\Framework\Event\DrainTraceEncoder;
use Zef\Framework\Event\EventName;
use Zef\Framework\Event\ListenerIdentity;
use Zef\Test\Support\Adapters\Event\ScriptedDeliveryConnection;

/**
 * Pins the database answer to the persistence port, over the real
 * SQLite in-memory engine the database family's own battery rides:
 * the five tables the construction ensures (idempotently), the
 * batch admission with its capacity counts spelled and its
 * one-statement all-or-nothing, the requeue that is never an
 * admission, the due selection's ready-at-then-identity order with
 * its exactly-due boundary, the claim semantics the conditional
 * UPDATE owns (a foreign live claim respected, this drainer's own
 * claim re-taken, an expired claim handed to the next drainer),
 * the stale settle's vanished row skipped, the idempotent settle,
 * the dead lane's one rewritten row, the books' insert-once rows
 * and the journal's one row per completed pass -- and the port's
 * own family held at the boundary: every engine refusal translated
 * into the {@see InvalidArgumentException} the port's docblocks
 * declare, the corrupt record refused by the entry's own identity.
 * The mutation round's pins ride the same battery: the visibility
 * timeout's exactly-one-second bound, the drain's foreign-claim-first
 * order and the records read's vanished-row-first order (the loop
 * seams the early-exit doctrine owns, both spelled in the mixed
 * order the continue-versus-break mutant names), the hostile cells'
 * and the corrupt record's full refusal messages with their zero
 * codes, and the conflict-tail seam's composed INSERT spelling.
 * The dialect's MySQL and PostgreSQL spellings and the narrowing
 * folds' hostile-row refusals ride the scripted tier
 * ({@see ScriptedDeliveryConnection}), the SQLite-alone-socket
 * posture the schema-reflection dialect itself carries.
 *
 * @internal
 */
#[CoversClass(PdoDeliveryStore::class)]
#[CoversClass(PdoDeliveryAdmission::class)]
#[CoversClass(PdoDeliveryBoundary::class)]
#[CoversClass(PdoDeliveryBooks::class)]
#[CoversClass(PdoDeliveryClaim::class)]
#[CoversClass(PdoDeliveryDeadLane::class)]
#[CoversClass(PdoDeliveryDialect::class)]
#[CoversClass(PdoDeliveryDueScan::class)]
#[CoversClass(PdoDeliveryEntries::class)]
#[CoversClass(PdoDeliveryRecords::class)]
#[CoversClass(PdoDeliverySchema::class)]
#[CoversClass(PdoDeliveryTables::class)]
#[CoversClass(PdoDeliveryUpsert::class)]
final class PdoDeliveryStoreTest extends TestCase
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
            new PdoDeliveryStore($this->connection, 0, 60, 'zef_');
            self::fail('The zero capacity was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                "A delivery store's capacity must be at least one, got 0.",
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testAVisibilityTimeoutBelowOneIsRefused(): void
    {
        try {
            new PdoDeliveryStore($this->connection, 8, 0, 'zef_');
            self::fail('The zero visibility timeout was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                "A delivery claim's visibility timeout must be at least one"
                . ' second, got 0.',
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testAVisibilityTimeoutOfExactlyOneSecondIsTheAcceptedBound(): void
    {
        $pdoDeliveryStore = new PdoDeliveryStore($this->connection, 8, 1, 'zef_');
        $pdoDeliveryStore->save(
            $this->envelope('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', ['amount' => 12]),
        );

        $taken = $pdoDeliveryStore->takeDue(0);

        self::assertSame(
            ['aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'],
            $this->identitiesOf($taken),
        );
    }

    public function testAnEmptyTablePrefixIsRefused(): void
    {
        try {
            new PdoDeliveryStore($this->connection, 8, 60, '');
            self::fail('The empty prefix was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                "A PDO delivery store's table prefix must not be empty -- the"
                . " un-prefixed vocabulary is the host schema's own, never the"
                . " store's to claim.",
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testAPrefixThatComposesAnInvalidTableNameIsRefused(): void
    {
        try {
            new PdoDeliveryStore($this->connection, 8, 60, '9bad');
            self::fail('The digit-first prefix was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                "A PDO delivery store's table prefix must compose valid table"
                . ' names (letters, digits and underscores, a letter or an'
                . " underscore first, at most sixty-four bytes): '9baddelivery'"
                . ' refuses.',
                $invalidArgumentException->getMessage(),
            );
            self::assertSame(0, $invalidArgumentException->getCode());
            self::assertInstanceOf(
                QueryException::class,
                $invalidArgumentException->getPrevious(),
            );
        }
    }

    public function testTheConstructionEnsuresTheFiveTablesAndTheIndex(): void
    {
        new PdoDeliveryStore($this->connection, 8, 60, 'zef_');

        $names = $this->schemaObjectNames();

        self::assertContains('zef_delivery', $names);
        self::assertContains('zef_delivery_dead', $names);
        self::assertContains('zef_delivery_attributed', $names);
        self::assertContains('zef_delivery_quarantine', $names);
        self::assertContains('zef_delivery_journal', $names);
        self::assertContains('zef_delivery_ready_at', $names);
    }

    public function testASecondStoreOverTheSameSchemaIsTheNoOpTheTablesAnswer(): void
    {
        new PdoDeliveryStore($this->connection, 8, 60, 'zef_');
        new PdoDeliveryStore($this->connection, 8, 60, 'zef_');

        $names = $this->schemaObjectNames();

        self::assertContains('zef_delivery', $names);
        self::assertCount(6, $names);
    }

    public function testTheClaimColumnsRideTheDeliveryRow(): void
    {
        new PdoDeliveryStore($this->connection, 8, 60, 'zef_');

        $columns = $this->connection->fetchAll(
            SqlQuery::raw('PRAGMA table_info(zef_delivery)'),
        );

        self::assertSame(
            ['entry_id', 'record', 'ready_at', 'claim_token', 'claim_expires_at'],
            array_column($columns, 'name'),
        );
    }

    public function testASavedBatchIsAdmittedWholeAndPending(): void
    {
        $pdoDeliveryStore = new PdoDeliveryStore($this->connection, 8, 60, 'zef_');
        $envelopes = [
            $this->envelope('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', ['amount' => 12]),
            $this->envelope('bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb', ['amount' => 13]),
        ];

        $pdoDeliveryStore->save(...$envelopes);

        self::assertSame(2, $pdoDeliveryStore->pendingCount());

        $taken = $pdoDeliveryStore->takeDue(0);

        self::assertCount(2, $taken);
        $this->assertEnvelopeMatches($envelopes[0], $taken[0]);
        $this->assertEnvelopeMatches($envelopes[1], $taken[1]);
    }

    public function testAnEmptyBatchIsTheNoOp(): void
    {
        $pdoDeliveryStore = new PdoDeliveryStore($this->connection, 8, 60, 'zef_');

        $pdoDeliveryStore->save();

        self::assertSame(0, $pdoDeliveryStore->pendingCount());
    }

    public function testABatchLandingExactlyAtCapacityIsAdmitted(): void
    {
        $pdoDeliveryStore = new PdoDeliveryStore($this->connection, 2, 60, 'zef_');

        $pdoDeliveryStore->save(
            $this->envelope('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', ['amount' => 12]),
            $this->envelope('bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb', ['amount' => 13]),
        );

        self::assertSame(2, $pdoDeliveryStore->pendingCount());
    }

    public function testTheCapacityRefusalSpellsTheCounts(): void
    {
        $pdoDeliveryStore = new PdoDeliveryStore($this->connection, 1, 60, 'zef_');
        $pdoDeliveryStore->save(
            $this->envelope('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', ['amount' => 12]),
        );

        try {
            $pdoDeliveryStore->save(
                $this->envelope('bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb', ['amount' => 13]),
                $this->envelope('cccccccccccccccccccccccccccccccc', ['amount' => 14]),
            );
            self::fail('The over-capacity batch was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                'The delivery store is full: capacity 1, occupied 1, demanded 2'
                . ' -- drain before deferring more.',
                $invalidArgumentException->getMessage(),
            );
        }

        self::assertSame(1, $pdoDeliveryStore->pendingCount());
    }

    public function testABatchTheTableCannotHoldWholeIsRefusedWhole(): void
    {
        $pdoDeliveryStore = new PdoDeliveryStore($this->connection, 8, 60, 'zef_');
        $held = $this->envelope('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', ['amount' => 12]);
        $pdoDeliveryStore->save($held);

        try {
            $pdoDeliveryStore->save(
                $held,
                $this->envelope('bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb', ['amount' => 13]),
            );
            self::fail('The duplicate-identity batch was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertStringStartsWith(
                'The delivery store refused: ',
                $invalidArgumentException->getMessage(),
            );
            self::assertInstanceOf(
                QueryException::class,
                $invalidArgumentException->getPrevious(),
            );
        }

        self::assertSame(1, $pdoDeliveryStore->pendingCount());
    }

    public function testARequeueWritesTheRetryUnderItsFreshIdentity(): void
    {
        $pdoDeliveryStore = new PdoDeliveryStore($this->connection, 8, 60, 'zef_');
        $retry = $this->envelope(
            'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb',
            ['amount' => 12],
            2,
            50,
        );

        $pdoDeliveryStore->requeue($retry);

        self::assertSame(1, $pdoDeliveryStore->pendingCount());
        self::assertSame([], $pdoDeliveryStore->takeDue(49));

        $taken = $pdoDeliveryStore->takeDue(50);

        self::assertCount(1, $taken);
        $this->assertEnvelopeMatches($retry, $taken[0]);
    }

    public function testARequeueIsNeverAnAdmission(): void
    {
        $pdoDeliveryStore = new PdoDeliveryStore($this->connection, 1, 60, 'zef_');
        $pdoDeliveryStore->save(
            $this->envelope('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', ['amount' => 12]),
        );

        $pdoDeliveryStore->requeue(
            $this->envelope('bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb', ['amount' => 13], 2, 50),
        );

        self::assertSame(2, $pdoDeliveryStore->pendingCount());
    }

    public function testTheDueSelectionOrdersByReadyAtThenIdentity(): void
    {
        $pdoDeliveryStore = new PdoDeliveryStore($this->connection, 8, 60, 'zef_');
        $pdoDeliveryStore->save(
            $this->envelope('bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb', ['amount' => 12], 1, 50),
            $this->envelope('cccccccccccccccccccccccccccccccc', ['amount' => 13], 1, 10),
            $this->envelope('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', ['amount' => 14], 1, 10),
        );

        $taken = $pdoDeliveryStore->takeDue(100);

        self::assertSame(
            [
                'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
                'cccccccccccccccccccccccccccccccc',
                'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb',
            ],
            $this->identitiesOf($taken),
        );
    }

    public function testNotYetDueEntriesAreNotTaken(): void
    {
        $pdoDeliveryStore = new PdoDeliveryStore($this->connection, 8, 60, 'zef_');
        $pdoDeliveryStore->save(
            $this->envelope('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', ['amount' => 12], 1, 200),
        );

        self::assertSame([], $pdoDeliveryStore->takeDue(100));
    }

    public function testTheExactlyDueBoundaryIsIncluded(): void
    {
        $pdoDeliveryStore = new PdoDeliveryStore($this->connection, 8, 60, 'zef_');
        $pdoDeliveryStore->save(
            $this->envelope('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', ['amount' => 12], 1, 100),
        );

        $taken = $pdoDeliveryStore->takeDue(100);

        self::assertCount(1, $taken);
    }

    public function testASecondDrainerRespectsALiveClaim(): void
    {
        $storeA = new PdoDeliveryStore($this->connection, 8, 60, 'zef_');
        $storeB = new PdoDeliveryStore($this->connection, 8, 60, 'zef_');
        $envelope = $this->envelope('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', ['amount' => 12]);
        $storeA->save($envelope);

        $takenByA = $storeA->takeDue(100);

        self::assertCount(1, $takenByA);
        self::assertSame([], $storeB->takeDue(100));
    }

    public function testAForeignLiveClaimOnTheFirstDueEntryDoesNotStopTheDrain(): void
    {
        $pdoDeliveryStore = new PdoDeliveryStore($this->connection, 8, 60, 'zef_');
        $pdoDeliveryStore->save(
            $this->envelope('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', ['amount' => 12]),
        );
        $pdoDeliveryStore->save(
            $this->envelope('bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb', ['amount' => 14]),
        );

        $this->connection->execute(SqlQuery::raw(
            "UPDATE zef_delivery SET claim_token = 'eeeeeeeeeeeeeeeeeeeeeeeeeeeeeeee',"
            . " claim_expires_at = 160 WHERE entry_id = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'",
        ));

        $secondDrainer = new PdoDeliveryStore($this->connection, 8, 60, 'zef_');

        $takenByTheSecondDrainer = $secondDrainer->takeDue(100);

        self::assertSame(
            ['bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb'],
            $this->identitiesOf($takenByTheSecondDrainer),
        );
    }

    public function testThisDrainersOwnClaimIsReTakenByTheNextPass(): void
    {
        $pdoDeliveryStore = new PdoDeliveryStore($this->connection, 8, 60, 'zef_');
        $envelope = $this->envelope('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', ['amount' => 12]);
        $pdoDeliveryStore->save($envelope);

        $pdoDeliveryStore->takeDue(100);

        $reTakenByA = $pdoDeliveryStore->takeDue(100);

        self::assertCount(1, $reTakenByA);
        $this->assertEnvelopeMatches($envelope, $reTakenByA[0]);
    }

    public function testAnExpiredClaimIsHandedToTheNextDrainer(): void
    {
        $storeA = new PdoDeliveryStore($this->connection, 8, 60, 'zef_');
        $storeB = new PdoDeliveryStore($this->connection, 8, 60, 'zef_');
        $storeA->save(
            $this->envelope('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', ['amount' => 12]),
        );

        $storeA->takeDue(100);

        $takenByB = $storeB->takeDue(161);

        self::assertCount(1, $takenByB);
    }

    public function testASettledEntryIsNotRetaken(): void
    {
        $pdoDeliveryStore = new PdoDeliveryStore($this->connection, 8, 60, 'zef_');
        $envelope = $this->envelope('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', ['amount' => 12]);
        $pdoDeliveryStore->save($envelope);

        $taken = $pdoDeliveryStore->takeDue(0);
        $pdoDeliveryStore->settle(...$taken);

        self::assertSame(0, $pdoDeliveryStore->pendingCount());
        self::assertSame([], $pdoDeliveryStore->takeDue(0));
    }

    public function testSettleIsIdempotent(): void
    {
        $pdoDeliveryStore = new PdoDeliveryStore($this->connection, 8, 60, 'zef_');
        $envelope = $this->envelope('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', ['amount' => 12]);
        $pdoDeliveryStore->save($envelope);
        $taken = $pdoDeliveryStore->takeDue(0);

        $pdoDeliveryStore->settle(...$taken);
        $pdoDeliveryStore->settle(...$taken);

        self::assertSame(0, $pdoDeliveryStore->pendingCount());
    }

    public function testSettlingAnEntryTheStoreNeverHeldIsTheNoOp(): void
    {
        $pdoDeliveryStore = new PdoDeliveryStore($this->connection, 8, 60, 'zef_');

        $pdoDeliveryStore->settle(
            $this->envelope('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', ['amount' => 12]),
        );

        self::assertSame(0, $pdoDeliveryStore->pendingCount());
    }

    public function testTheStaleSettlesVanishedRowIsSkippedNeverRefused(): void
    {
        $pdoDeliveryStore = new PdoDeliveryStore($this->connection, 8, 60, 'zef_');
        $envelope = $this->envelope('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', ['amount' => 12]);
        $pdoDeliveryStore->save($envelope);

        $pdoDeliveryClaim = new PdoDeliveryClaim(
            $this->connection,
            new PdoDeliveryTables('zef_'),
            new PdoDeliveryDueScan($this->connection, new PdoDeliveryTables('zef_')),
            'dddddddddddddddddddddddddddddddd',
            60,
        );
        $won = $pdoDeliveryClaim->claimDue(0);

        self::assertSame(['aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'], $won);

        $pdoDeliveryStore->settle($envelope);

        $answered = new PdoDeliveryRecords(
            new PdoDeliveryEntries($this->connection, new PdoDeliveryTables('zef_')),
            new DeliveryEnvelopeDecoder(),
        )->answeredDeliveries($won);

        self::assertSame([], $answered);
    }

    public function testAVanishedRowAheadOfALiveClaimedEntryIsSkippedNotStoppingTheDecode(): void
    {
        $pdoDeliveryStore = new PdoDeliveryStore($this->connection, 8, 60, 'zef_');
        $first = $this->envelope('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', ['amount' => 12]);
        $second = $this->envelope('bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb', ['amount' => 14]);
        $pdoDeliveryStore->save($first);
        $pdoDeliveryStore->save($second);

        $won = new PdoDeliveryClaim(
            $this->connection,
            new PdoDeliveryTables('zef_'),
            new PdoDeliveryDueScan($this->connection, new PdoDeliveryTables('zef_')),
            'dddddddddddddddddddddddddddddddd',
            60,
        )->claimDue(0);

        self::assertSame(
            ['aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb'],
            $won,
        );

        $this->connection->execute(SqlQuery::raw(
            "DELETE FROM zef_delivery WHERE entry_id = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'",
        ));

        $answered = new PdoDeliveryRecords(
            new PdoDeliveryEntries($this->connection, new PdoDeliveryTables('zef_')),
            new DeliveryEnvelopeDecoder(),
        )->answeredDeliveries($won);

        self::assertCount(1, $answered);
        $this->assertEnvelopeMatches($second, $answered[0]);
    }

    public function testACorruptRecordRefusesNamingTheEntry(): void
    {
        $pdoDeliveryStore = new PdoDeliveryStore($this->connection, 8, 60, 'zef_');
        $this->connection->execute(SqlQuery::raw(
            'INSERT INTO zef_delivery (entry_id, record, ready_at)'
            . " VALUES ('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', 'not-json', 0)",
        ));

        try {
            $pdoDeliveryStore->takeDue(0);
            self::fail('The corrupt record was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                "The delivery entry record 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa' is refused:"
                . ' The durable delivery record is not valid JSON: Syntax error.',
                $invalidArgumentException->getMessage(),
            );
            self::assertSame(0, $invalidArgumentException->getCode());
            self::assertInstanceOf(
                InvalidArgumentException::class,
                $invalidArgumentException->getPrevious(),
            );
        }
    }

    public function testAnEngineRefusalIsAnsweredInThePortsFamily(): void
    {
        $pdoDeliveryStore = new PdoDeliveryStore($this->connection, 8, 60, 'zef_');
        $this->connection->execute(SqlQuery::raw('DROP TABLE zef_delivery'));

        try {
            $pdoDeliveryStore->takeDue(0);
            self::fail('The missing table was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertStringStartsWith(
                'The delivery store refused: ',
                $invalidArgumentException->getMessage(),
            );
            self::assertInstanceOf(
                QueryException::class,
                $invalidArgumentException->getPrevious(),
            );
        }
    }

    public function testTheDeadLaneCarriesOneRowRewritten(): void
    {
        $pdoDeliveryStore = new PdoDeliveryStore($this->connection, 8, 60, 'zef_');
        $envelope = $this->envelope('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', ['amount' => 12]);

        $pdoDeliveryStore->deadLetter($envelope, 'RuntimeException', 'first death', 100);
        $pdoDeliveryStore->deadLetter($envelope, 'LogicException', 'second death', 200);

        $rows = $this->connection->fetchAll(
            QueryBuilder::table('zef_delivery_dead')->select('entry_id', 'record')->build(),
        );

        self::assertCount(1, $rows);
        self::assertSame(
            new DeliveryEnvelopeEncoder()->encodeDeadLetter(
                $envelope,
                'LogicException',
                'second death',
                200,
            ),
            $rows[0]['record'],
        );
    }

    public function testTheAttributionIsIdempotentPerEntryAndCountsPerIdentity(): void
    {
        $pdoDeliveryStore = new PdoDeliveryStore($this->connection, 8, 60, 'zef_');
        $listenerIdentity = new ListenerIdentity('app.orders.audit');
        $firstEntry = new DeliveryEntryId('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa');
        $secondEntry = new DeliveryEntryId('bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb');

        $pdoDeliveryStore->recordAttributedDeath($firstEntry, $listenerIdentity, 100);
        $pdoDeliveryStore->recordAttributedDeath($firstEntry, $listenerIdentity, 200);
        $pdoDeliveryStore->recordAttributedDeath($secondEntry, $listenerIdentity, 300);

        self::assertSame(
            2,
            $pdoDeliveryStore->deadLettersAttributedTo($listenerIdentity),
        );
    }

    public function testTheAttributionCountAnswersZeroForAnUnknownIdentity(): void
    {
        $pdoDeliveryStore = new PdoDeliveryStore($this->connection, 8, 60, 'zef_');

        self::assertSame(
            0,
            $pdoDeliveryStore->deadLettersAttributedTo(new ListenerIdentity('app.unknown')),
        );
    }

    public function testTheQuarantineIsIdempotentMembership(): void
    {
        $pdoDeliveryStore = new PdoDeliveryStore($this->connection, 8, 60, 'zef_');
        $listenerIdentity = new ListenerIdentity('app.orders.audit');

        self::assertFalse($pdoDeliveryStore->isListenerQuarantined($listenerIdentity));

        $pdoDeliveryStore->quarantineListener($listenerIdentity, 100);
        $pdoDeliveryStore->quarantineListener($listenerIdentity, 200);

        self::assertTrue($pdoDeliveryStore->isListenerQuarantined($listenerIdentity));
        self::assertCount(
            1,
            $this->connection->fetchAll(
                QueryBuilder::table('zef_delivery_quarantine')
                    ->select('listener_identity')
                    ->build(),
            ),
        );
    }

    public function testTheJournalAppendsOneRowPerCompletedPass(): void
    {
        $pdoDeliveryStore = new PdoDeliveryStore($this->connection, 8, 60, 'zef_');
        $drainTraceEncoder = new DrainTraceEncoder();

        $pdoDeliveryStore->recordDrainOutcome(
            DrainTrace::fromOutcome(100, new DrainOutcome(1, 0, 0, 0, 0)),
        );
        $pdoDeliveryStore->recordDrainOutcome(
            DrainTrace::fromOutcome(200, new DrainOutcome(0, 1, 2, 3, 4)),
        );

        $rows = $this->connection->fetchAll(
            QueryBuilder::table('zef_delivery_journal')
                ->select('record_id', 'record')
                ->orderBy('record')
                ->build(),
        );

        self::assertCount(2, $rows);
        self::assertNotSame($rows[0]['record_id'], $rows[1]['record_id']);
        self::assertSame(
            $drainTraceEncoder->encode(DrainTrace::fromOutcome(100, new DrainOutcome(1, 0, 0, 0, 0))),
            $rows[0]['record'],
        );
        self::assertSame(
            $drainTraceEncoder->encode(DrainTrace::fromOutcome(200, new DrainOutcome(0, 1, 2, 3, 4))),
            $rows[1]['record'],
        );
    }

    public function testTheStandardDriversSpellTheStandardConflictTails(): void
    {
        foreach (['sqlite', 'pgsql'] as $driver) {
            $pdoDeliveryDialect = new PdoDeliveryDialect($driver);

            self::assertSame(
                'ON CONFLICT (entry_id) DO NOTHING',
                $pdoDeliveryDialect->insertOnceTail('entry_id'),
                $driver,
            );
            self::assertSame(
                'ON CONFLICT (entry_id) DO UPDATE SET record = excluded.record',
                $pdoDeliveryDialect->insertRewriteTail('entry_id', 'record'),
                $driver,
            );
        }
    }

    public function testMySqlSpellsItsOwnConflictTails(): void
    {
        $pdoDeliveryDialect = new PdoDeliveryDialect('mysql');

        self::assertSame(
            'ON DUPLICATE KEY UPDATE entry_id = entry_id',
            $pdoDeliveryDialect->insertOnceTail('entry_id'),
        );
        self::assertSame(
            'ON DUPLICATE KEY UPDATE record = VALUES(record)',
            $pdoDeliveryDialect->insertRewriteTail('entry_id', 'record'),
        );
    }

    public function testTheConflictTailSeamSpellsTheBuildersInsertWithTheTailAppended(): void
    {
        $scriptedDeliveryConnection = new ScriptedDeliveryConnection('sqlite');
        $pdoDeliveryUpsert = new PdoDeliveryUpsert(
            $scriptedDeliveryConnection,
            new PdoDeliveryDialect('sqlite'),
        );

        $pdoDeliveryUpsert->insertOnce(
            new TableName('zef_delivery_attributed'),
            [
                'entry_id' => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
                'listener_identity' => 'app.orders.audit',
            ],
            'entry_id',
        );
        $pdoDeliveryUpsert->insertRewrite(
            new TableName('zef_delivery_dead'),
            [
                'entry_id' => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
                'record' => '{}',
            ],
            'entry_id',
            'record',
        );

        self::assertSame(
            [
                'INSERT INTO "zef_delivery_attributed" ("entry_id", "listener_identity")'
                . ' VALUES (?, ?) ON CONFLICT (entry_id) DO NOTHING',
                'INSERT INTO "zef_delivery_dead" ("entry_id", "record")'
                . ' VALUES (?, ?) ON CONFLICT (entry_id) DO UPDATE SET record = excluded.record',
            ],
            $scriptedDeliveryConnection->executedStatements,
        );
    }

    public function testAnUnknownDriverOwnsNoDialect(): void
    {
        try {
            new PdoDeliveryDialect('oracle');
            self::fail('The unknown driver was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                'The PDO delivery store answers the three drivers the DSN'
                . " grammar accepts (mysql, pgsql, sqlite), got 'oracle'.",
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testTheMySqlSchemaCarriesTheIndexInlineAndSpellsNoIndexStatement(): void
    {
        $scriptedDeliveryConnection = new ScriptedDeliveryConnection('mysql');

        new PdoDeliverySchema(
            $scriptedDeliveryConnection,
            new PdoDeliveryDialect('mysql'),
            new PdoDeliveryTables('zef_'),
        )->ensure();

        self::assertSame(
            [
                'CREATE TABLE IF NOT EXISTS zef_delivery ('
                . 'entry_id CHAR(32) NOT NULL PRIMARY KEY, '
                . 'record TEXT NOT NULL, '
                . 'ready_at BIGINT NOT NULL, '
                . 'claim_token CHAR(32) NULL, '
                . 'claim_expires_at BIGINT NULL, '
                . 'KEY zef_delivery_ready_at (ready_at, entry_id)'
                . ')',
                'CREATE TABLE IF NOT EXISTS zef_delivery_dead'
                . ' (entry_id CHAR(32) NOT NULL PRIMARY KEY, record TEXT NOT NULL)',
                'CREATE TABLE IF NOT EXISTS zef_delivery_attributed'
                . ' (entry_id CHAR(32) NOT NULL PRIMARY KEY,'
                . ' listener_identity VARCHAR(64) NOT NULL)',
                'CREATE TABLE IF NOT EXISTS zef_delivery_quarantine'
                . ' (listener_identity VARCHAR(64) NOT NULL PRIMARY KEY)',
                'CREATE TABLE IF NOT EXISTS zef_delivery_journal'
                . ' (record_id CHAR(32) NOT NULL PRIMARY KEY, record TEXT NOT NULL)',
            ],
            $scriptedDeliveryConnection->executedStatements,
        );
    }

    public function testTheStandardSchemaSpellsTheIndexStatement(): void
    {
        $scriptedDeliveryConnection = new ScriptedDeliveryConnection('pgsql');

        new PdoDeliverySchema(
            $scriptedDeliveryConnection,
            new PdoDeliveryDialect('pgsql'),
            new PdoDeliveryTables('zef_'),
        )->ensure();

        self::assertSame(
            'CREATE INDEX IF NOT EXISTS zef_delivery_ready_at ON zef_delivery'
            . ' (ready_at, entry_id)',
            $scriptedDeliveryConnection->executedStatements[5],
        );
        self::assertCount(6, $scriptedDeliveryConnection->executedStatements);
    }

    public function testTheDueSelectionRefusesANonStringIdentityCell(): void
    {
        $scriptedDeliveryConnection = new ScriptedDeliveryConnection(
            'sqlite',
            [],
            [[['entry_id' => 404]]],
        );

        try {
            new PdoDeliveryDueScan(
                $scriptedDeliveryConnection,
                new PdoDeliveryTables('zef_'),
            )->dueIdentities(0);
            self::fail('The non-string identity cell was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                'The due selection refused its answer: an entry identity cell is'
                . ' not the string the primary key spells.',
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testTheClaimedRecordsReadRefusesHostileCells(): void
    {
        $hostileReads = [
            [
                'rows' => [[['entry_id' => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', 'record' => 404]]],
                'column' => 'record',
            ],
            [
                'rows' => [[['entry_id' => 404, 'record' => '{}']]],
                'column' => 'entry_id',
            ],
        ];

        foreach ($hostileReads as $hostileRead) {
            $scriptedDeliveryConnection = new ScriptedDeliveryConnection(
                'sqlite',
                [],
                $hostileRead['rows'],
            );

            try {
                new PdoDeliveryRecords(
                    new PdoDeliveryEntries(
                        $scriptedDeliveryConnection,
                        new PdoDeliveryTables('zef_'),
                    ),
                    new DeliveryEnvelopeDecoder(),
                )->answeredDeliveries(['aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa']);
                self::fail('The hostile cell was not refused.');
            } catch (InvalidArgumentException $invalidArgumentException) {
                self::assertSame(
                    'The claimed records read refused its answer: the '
                    . $hostileRead['column']
                    . ' cell is not the string its column spells.',
                    $invalidArgumentException->getMessage(),
                );
            }
        }
    }

    public function testTheClaimedRecordsReadAnswersNothingWhenNothingWasWon(): void
    {
        $scriptedDeliveryConnection = new ScriptedDeliveryConnection('sqlite');

        self::assertSame(
            [],
            new PdoDeliveryRecords(
                new PdoDeliveryEntries(
                    $scriptedDeliveryConnection,
                    new PdoDeliveryTables('zef_'),
                ),
                new DeliveryEnvelopeDecoder(),
            )->answeredDeliveries([]),
        );
        self::assertSame([], $scriptedDeliveryConnection->executedStatements);
    }

    public function testThePendingCountRefusesAShapeTheContractDoesNotSpell(): void
    {
        $pdoDeliveryTables = new PdoDeliveryTables('zef_');

        foreach ([null, ['pending' => '7']] as $fetchOneScriptEntry) {
            $scriptedDeliveryConnection = new ScriptedDeliveryConnection(
                'sqlite',
                [$fetchOneScriptEntry],
            );
            $pdoDeliveryAdmission = new PdoDeliveryAdmission(
                $scriptedDeliveryConnection,
                $pdoDeliveryTables,
                new PdoDeliveryEntries($scriptedDeliveryConnection, $pdoDeliveryTables),
                new DeliveryEnvelopeEncoder(),
                8,
            );

            if ($fetchOneScriptEntry === null) {
                try {
                    $pdoDeliveryAdmission->pendingCount();
                    self::fail('The missing aggregate was not refused.');
                } catch (InvalidArgumentException $invalidArgumentException) {
                    self::assertSame(
                        'The pending count returned no aggregate value.',
                        $invalidArgumentException->getMessage(),
                    );
                }

                continue;
            }

            self::assertSame(7, $pdoDeliveryAdmission->pendingCount());
        }
    }

    public function testTheAttributedCountRefusesAShapeTheContractDoesNotSpell(): void
    {
        $scriptedDeliveryConnection = new ScriptedDeliveryConnection('sqlite', [null]);
        $pdoDeliveryUpsert = new PdoDeliveryUpsert(
            $scriptedDeliveryConnection,
            new PdoDeliveryDialect('sqlite'),
        );
        $pdoDeliveryBooks = new PdoDeliveryBooks(
            $scriptedDeliveryConnection,
            new PdoDeliveryTables('zef_'),
            $pdoDeliveryUpsert,
        );

        try {
            $pdoDeliveryBooks->deadLettersAttributedTo(new ListenerIdentity('app.orders.audit'));
            self::fail('The missing aggregate was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                'The attributed count returned no aggregate value.',
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testTheBoundaryCarriesTheAnswersThroughUntouched(): void
    {
        $scriptedDeliveryConnection = new ScriptedDeliveryConnection(
            'sqlite',
            [['pending' => 7]],
            [[['entry_id' => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', 'record' => '{}']]],
        );
        $pdoDeliveryBoundary = new PdoDeliveryBoundary($scriptedDeliveryConnection);
        $statement = SqlQuery::raw('SELECT 1');

        self::assertSame(1, $pdoDeliveryBoundary->execute($statement));
        self::assertSame(
            [['entry_id' => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', 'record' => '{}']],
            $pdoDeliveryBoundary->fetchAll($statement),
        );
        self::assertSame(['pending' => 7], $pdoDeliveryBoundary->fetchOne($statement));
        self::assertNull($pdoDeliveryBoundary->lastInsertId());
        self::assertSame(0, $pdoDeliveryBoundary->transactionLevel());
        $pdoDeliveryBoundary->beginTransaction();
        $pdoDeliveryBoundary->commit();
        $pdoDeliveryBoundary->rollBack();

        $receivedConnection = null;
        $pdoDeliveryBoundary->transaction(
            static function (ConnectionInterface $connection) use (&$receivedConnection): null {
                $receivedConnection = $connection;

                return null;
            },
        );

        self::assertSame($receivedConnection, $scriptedDeliveryConnection);

        $carried = [];

        foreach ($scriptedDeliveryConnection->executed as $executed) {
            $carried[] = $executed->sql;
        }

        self::assertSame(['SELECT 1'], $carried);
    }

    public function testTheBoundaryAnswersEveryEngineRefusalInThePortsFamily(): void
    {
        $pdoDeliveryBoundary = new PdoDeliveryBoundary(
            new ScriptedDeliveryConnection('sqlite', [], [], true),
        );

        self::assertSame('sqlite', $pdoDeliveryBoundary->driver());

        $operations = [
            'execute' => static fn (): int => $pdoDeliveryBoundary->execute(
                SqlQuery::raw('SELECT 1'),
            ),
            'fetchAll' => static fn (): array => $pdoDeliveryBoundary->fetchAll(
                SqlQuery::raw('SELECT 1'),
            ),
            'fetchOne' => static fn (): ?array => $pdoDeliveryBoundary->fetchOne(
                SqlQuery::raw('SELECT 1'),
            ),
            'lastInsertId' => $pdoDeliveryBoundary->lastInsertId(...),
            'beginTransaction' => static fn (): null => $pdoDeliveryBoundary->beginTransaction(),
            'commit' => $pdoDeliveryBoundary->commit(...),
            'rollBack' => $pdoDeliveryBoundary->rollBack(...),
            'transactionLevel' => $pdoDeliveryBoundary->transactionLevel(...),
            'transaction' => static fn (): null => $pdoDeliveryBoundary->transaction(
                static fn (): null => null,
            ),
        ];

        foreach ($operations as $name => $operation) {
            try {
                $operation();
                self::fail("The {$name} refusal was not answered in the port's family.");
            } catch (InvalidArgumentException $invalidArgumentException) {
                self::assertSame(
                    'The delivery store refused: The scripted connection refuses.',
                    $invalidArgumentException->getMessage(),
                    $name,
                );
                self::assertSame(0, $invalidArgumentException->getCode(), $name);
                self::assertInstanceOf(
                    QueryException::class,
                    $invalidArgumentException->getPrevious(),
                    $name,
                );
            }
        }
    }

    /**
     * @return list<string> the schema object names the store ensured
     */
    private function schemaObjectNames(): array
    {
        $names = [];

        foreach (
            $this->connection->fetchAll(
                SqlQuery::raw(
                    "SELECT name FROM sqlite_master WHERE name LIKE 'zef_%'"
                    . " AND name NOT LIKE 'sqlite_%'",
                ),
            ) as $row
        ) {
            $name = $row['name'] ?? null;
            self::assertIsString($name);
            $names[] = $name;
        }

        return $names;
    }

    /**
     * @param array<string, null|array<mixed>|bool|float|int|string> $values
     */
    private function envelope(
        string $entryId,
        array $values,
        int $attempt = 1,
        int $readyAt = 0,
    ): DeliveryEnvelope {
        return new DeliveryEnvelope(
            new DeliveryEntryId($entryId),
            new EventName('order.paid'),
            $values,
            new ListenerIdentity('app.orders.audit'),
            $attempt,
            $readyAt,
        );
    }

    /**
     * @param list<DeliveryEnvelope> $envelopes
     *
     * @return list<string>
     */
    private function identitiesOf(array $envelopes): array
    {
        $identities = [];

        foreach ($envelopes as $envelope) {
            $identities[] = $envelope->entryId()->spelling();
        }

        return $identities;
    }

    private function assertEnvelopeMatches(
        DeliveryEnvelope $expected,
        DeliveryEnvelope $actual,
    ): void {
        self::assertSame($expected->entryId()->spelling(), $actual->entryId()->spelling());
        self::assertSame($expected->readyAt(), $actual->readyAt());
        self::assertSame($expected->attempt(), $actual->attempt());
        self::assertSame(
            $expected->listenerIdentity()->spelling(),
            $actual->listenerIdentity()->spelling(),
        );
        self::assertSame($expected->values(), $actual->values());
    }
}
