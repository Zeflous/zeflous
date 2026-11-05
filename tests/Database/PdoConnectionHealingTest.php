<?php

declare(strict_types=1);

namespace Zef\Test\Database;

use PDOException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Zef\Framework\Adapters\Database\ConnectionConfig;
use Zef\Framework\Adapters\Database\PdoConnection;
use Zef\Framework\Adapters\Database\PdoHandle;
use Zef\Framework\Adapters\Database\PdoHealing;
use Zef\Framework\Adapters\Database\PdoTransactionDepth;
use Zef\Framework\Adapters\Database\PdoTransactions;
use Zef\Framework\Database\ConnectionInterface;
use Zef\Framework\Database\QueryException;
use Zef\Framework\Database\SqlQuery;
use Zef\Framework\Database\TransactionException;
use Zef\Test\Support\Database\RecordingPdo;

/**
 * The healing fold's boundary over the connection port: a classified
 * lost-connection failure drops the lazy handle and resets the
 * savepoint depth with it (the next ask re-materialising the socket,
 * a new beginTransaction answering level 1 -- never a phantom), the
 * classified exception propagating untouched -- the SAME instance the
 * adapter saw, mapped or raw. The boundary's other edge is pinned too:
 * a deadlock is deliberately NOT a lost connection (the socket stays,
 * the retry verdict owns those), and the closure's own domain
 * throwables never heal anything.
 *
 * @internal
 */
#[CoversClass(PdoConnection::class)]
#[CoversClass(PdoHealing::class)]
#[CoversClass(PdoHandle::class)]
#[CoversClass(PdoTransactions::class)]
#[CoversClass(PdoTransactionDepth::class)]
final class PdoConnectionHealingTest extends TestCase
{
    /**
     * The code lane alone classifies: a driver failure spelling no
     * pattern the message lane knows, answered by the code the
     * lost-connection family owns (2006, the server-gone spelling).
     */
    public function testTheCodeLaneHealsAndTheNextAskLandsOnAFreshSocket(): void
    {
        $recordingPdo = new RecordingPdo();
        $recordingPdo->deadSocket = new PDOException('server has terminated the link', 2006);

        $pdoConnection = $this->healingConnection($recordingPdo);

        try {
            $pdoConnection->execute(SqlQuery::raw('SELECT 1'));
            self::fail('the dead socket must refuse the first ask');
        } catch (QueryException $queryException) {
            self::assertSame($recordingPdo->deadSocket, $queryException->getPrevious());
        }

        // The double's arm is sticky: a success here can only land on
        // the re-materialised socket the heal dropped onto.
        self::assertSame(0, $pdoConnection->execute(SqlQuery::raw('SELECT 1')));
        self::assertSame([['one' => 1]], $pdoConnection->fetchAll(SqlQuery::raw('SELECT 1 AS one')));
    }

    /**
     * The 1040 arm of the code lane: the server shedding the connection
     * under load heals exactly like the server-gone spelling.
     */
    public function testTheConnectionCountCodeHealsToo(): void
    {
        $recordingPdo = new RecordingPdo();
        $recordingPdo->deadSocket = new PDOException('Too many connections', 1040);

        $pdoConnection = $this->healingConnection($recordingPdo);

        try {
            $pdoConnection->fetchAll(SqlQuery::raw('SELECT 1'));
            self::fail('the dead socket must refuse the first ask');
        } catch (QueryException $queryException) {
            self::assertSame($recordingPdo->deadSocket, $queryException->getPrevious());
        }

        self::assertSame([['one' => 1]], $pdoConnection->fetchAll(SqlQuery::raw('SELECT 1 AS one')));
    }

    /**
     * The message lane classifies the spellings no informative code
     * accompanies (the libpq family's own wording, code 0).
     */
    public function testTheMessageLaneHealsTheCodelessSpelling(): void
    {
        $recordingPdo = new RecordingPdo();
        $recordingPdo->deadSocket = new PDOException('server closed the connection unexpectedly', 0);

        $pdoConnection = $this->healingConnection($recordingPdo);

        try {
            $pdoConnection->fetchOne(SqlQuery::raw('SELECT 1'));
            self::fail('the dead socket must refuse the first ask');
        } catch (QueryException $queryException) {
            self::assertSame($recordingPdo->deadSocket, $queryException->getPrevious());
        }

        self::assertSame([['one' => 1]], $pdoConnection->fetchAll(SqlQuery::raw('SELECT 1 AS one')));
    }

    /**
     * The depth-reset pin: a socket lost at depth 2 heals, and a new
     * beginTransaction answers level 1 -- not a phantom 3 the engine
     * would refuse to justify (a commit releasing a savepoint that
     * never existed, a guard vouching for a level no socket carries).
     */
    public function testTheHealedDepthAnswersANewBeginTransactionLevelOneNotAPhantomThree(): void
    {
        $recordingPdo = new RecordingPdo();
        $pdoConnection = $this->healingConnection($recordingPdo);

        $pdoConnection->beginTransaction();
        $pdoConnection->beginTransaction();
        self::assertSame(2, $pdoConnection->transactionLevel());
        $recordingPdo->deadSocket = new PDOException('MySQL server has gone away', 2006);

        try {
            $pdoConnection->execute(SqlQuery::raw('SELECT 1'));
            self::fail('the dead socket must refuse the ask');
        } catch (QueryException $queryException) {
            self::assertSame($recordingPdo->deadSocket, $queryException->getPrevious());
        }

        self::assertSame(0, $pdoConnection->transactionLevel());

        // The double's arm is sticky: a BEGIN that lands can only land
        // on the re-materialised socket, and it answers level 1.
        $pdoConnection->beginTransaction();
        self::assertSame(1, $pdoConnection->transactionLevel());
        $pdoConnection->commit();
        self::assertSame(0, $pdoConnection->transactionLevel());
    }

    /**
     * The propagate-untouched pin over the RAW escape: the identity
     * verb's driver failure is the one PDOException the adapter never
     * maps -- the instance the caller catches is the instance the
     * socket threw, and the heal still lands around it.
     */
    public function testTheRawIdentityEscapePropagatesUntouchedAndHeals(): void
    {
        $recordingPdo = new RecordingPdo();
        $recordingPdo->deadSocket = new PDOException('MySQL server has gone away', 2006);

        $pdoConnection = $this->healingConnection($recordingPdo);

        try {
            $pdoConnection->lastInsertId();
            self::fail('the dead socket must refuse the identity ask');
        } catch (PDOException $pdoException) {
            self::assertSame($recordingPdo->deadSocket, $pdoException);
        }

        // The re-materialised socket carries no insert: the identity
        // fold answers the null the port promises.
        self::assertNull($pdoConnection->lastInsertId());
    }

    /**
     * The boundary's refused half: a deadlock is NOT a lost connection
     * -- the socket stays alive, the depth stays owned by the caller's
     * rollback story, and the next ask still lands on the SAME handle
     * (the evidence the fold did not drop it).
     */
    public function testADeadlockIsNotALostConnectionTheHandleStays(): void
    {
        $recordingPdo = new RecordingPdo();
        $pdoConnection = $this->healingConnection($recordingPdo);

        $pdoConnection->beginTransaction();
        $pdoConnection->beginTransaction();
        self::assertSame(2, $pdoConnection->transactionLevel());
        $recordingPdo->deadSocket = new PDOException(
            'Deadlock found when trying to get lock; try restarting transaction',
            1213,
        );

        try {
            $pdoConnection->execute(SqlQuery::raw('SELECT 1'));
            self::fail('the failing socket must refuse the ask');
        } catch (QueryException $queryException) {
            self::assertSame($recordingPdo->deadSocket, $queryException->getPrevious());
        }

        // No heal: the depth the caller's rollback still owns, and the
        // next ask still reaching the very same handle.
        self::assertSame(2, $pdoConnection->transactionLevel());

        try {
            $pdoConnection->execute(SqlQuery::raw('SELECT 1'));
            self::fail('the same handle must still refuse the next ask');
        } catch (QueryException $queryException) {
            self::assertSame($recordingPdo->deadSocket, $queryException->getPrevious());
        }
    }

    /**
     * The managed commit death: a socket that dies between the
     * closure's end and the commit heals inside the transaction()
     * bracket -- the mapped TransactionException propagating with the
     * original chained, the depth reset to zero (never a phantom 1 the
     * next begin would climb from), the next begin landing fresh.
     */
    public function testTheManagedCommitDeathHealsTheDepthAndTheNextBeginLandsFresh(): void
    {
        $recordingPdo = new RecordingPdo();
        $recordingPdo->lastInsertIdAnswer = '777';

        $deadSocket = new PDOException('MySQL server has gone away', 2006);
        $pdoConnection = $this->healingConnection($recordingPdo);

        try {
            $pdoConnection->transaction(
                static function (ConnectionInterface $connection) use ($recordingPdo, $deadSocket): int {
                    $recordingPdo->deadSocket = $deadSocket;

                    return 1;
                },
            );
            self::fail('the commit must fail on the dead socket');
        } catch (TransactionException $transactionException) {
            self::assertSame($deadSocket, $transactionException->getPrevious());
            self::assertStringContainsString(
                'Failed to commit transaction: MySQL server has gone away',
                $transactionException->getMessage(),
            );
        }

        self::assertSame(0, $pdoConnection->transactionLevel());

        // The double is dead-sticky and its identity answer is steered
        // to '777': a begin that lands and an identity that answers
        // null can only come from the re-materialised socket.
        $pdoConnection->beginTransaction();
        self::assertSame(1, $pdoConnection->transactionLevel());
        self::assertNull($pdoConnection->lastInsertId());
    }

    /**
     * The closure's own throwable owns the connection: a domain
     * failure carrying no driver evidence never heals -- the handle
     * stays held (the steered identity answer proves it), the
     * transaction() unwind rolling it back on the LIVE socket first.
     */
    public function testTheClosureDomainThrowableNeverHealsTheHandle(): void
    {
        $recordingPdo = new RecordingPdo();
        $recordingPdo->lastInsertIdAnswer = '777';

        $pdoConnection = $this->healingConnection($recordingPdo);
        $domain = new RuntimeException('domain refusal');

        try {
            $pdoConnection->transaction(
                static function (ConnectionInterface $connection) use ($domain): never {
                    $connection->execute(SqlQuery::raw('SELECT 1'));

                    throw $domain;
                },
            );
        } catch (RuntimeException $runtimeException) {
            self::assertSame($domain, $runtimeException);
        }

        self::assertSame(['BEGIN', 'ROLLBACK'], $recordingPdo->verbs);
        self::assertSame('777', $pdoConnection->lastInsertId());
    }

    /**
     * The mid-flight edge inside the managed closure, pinned exactly:
     * the ask heals (drop and reset), and the unwind's rollback then
     * answers the level-zero guard -- the pre-existing replacement
     * shape the unbalanced closure always answered, now reached
     * honestly because the transaction the engine already ended is
     * gone. The connection afterwards is healed: level zero, the next
     * begin landing fresh.
     */
    public function testTheHealedMidFlightClosureLeavesTheUnwindAtTheLevelZeroGuard(): void
    {
        $recordingPdo = new RecordingPdo();
        $pdoConnection = $this->healingConnection($recordingPdo);
        $deadSocket = new PDOException('MySQL server has gone away', 2006);

        try {
            $pdoConnection->transaction(
                static function (ConnectionInterface $connection) use ($recordingPdo, $deadSocket): int {
                    $recordingPdo->deadSocket = $deadSocket;

                    return $connection->execute(SqlQuery::raw('SELECT 1'));
                },
            );
            self::fail("the dead socket must refuse the closure's ask");
        } catch (TransactionException $transactionException) {
            self::assertSame(
                'rollBack() called outside a transaction.',
                $transactionException->getMessage(),
            );
        }

        self::assertSame(0, $pdoConnection->transactionLevel());
        $pdoConnection->beginTransaction();
        self::assertSame(1, $pdoConnection->transactionLevel());
        $pdoConnection->commit();
    }

    /**
     * One connection over the preset dead-socket double, its
     * configuration the real in-memory SQLite the healed handle
     * re-materialises over.
     */
    private function healingConnection(RecordingPdo $recordingPdo): PdoConnection
    {
        return new PdoConnection(
            ConnectionConfig::fromArray(['driver' => 'sqlite', 'dbname' => ':memory:']),
            $recordingPdo,
        );
    }
}
