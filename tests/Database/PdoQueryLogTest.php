<?php

declare(strict_types=1);

namespace Zef\Test\Database;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Adapters\Database\ConnectionConfig;
use Zef\Framework\Adapters\Database\PdoConnection;
use Zef\Framework\Adapters\Database\PdoQueryLog;
use Zef\Framework\Adapters\Database\PdoQueryLogArming;
use Zef\Framework\Adapters\Database\PdoQueryOperation;
use Zef\Framework\Adapters\Database\PdoQueryRun;
use Zef\Framework\Adapters\Database\PdoQuerySignals;
use Zef\Framework\Database\QueryException;
use Zef\Framework\Database\QueryLogPolicy;
use Zef\Framework\Database\SqlQuery;
use Zef\Test\Support\Log\RecordingLogger;
use Zef\Test\Support\Log\ThrowingLogger;
use Zef\Test\Support\Telemetry\RecordingMeter;
use Zef\Test\Support\Telemetry\SteppingTelemetryClock;

/**
 * Pins the query-log family's emission over real SQLite: the per-query
 * DEBUG record and the duration observation every measured statement
 * answers (the SQL travelling in the record's context, the bound
 * values deliberately absent -- the privacy posture the family's
 * records carry), the slow verdict's WARN record and counter at the
 * exact boundary (a span that MEETS the bar answers no slow record, a
 * span one nanosecond over it answers both records and the counter),
 * the operation fold's five-word vocabulary, the throw path the
 * finally seam shares (a failing statement records its span and then
 * propagates its own exception untouched), the containment a failing
 * sink answers (neither a failed query nor a masked exception), the
 * backwards clock seam's fabricated nothing, and the honest void the
 * null seam spells.
 *
 * @internal
 */
#[CoversClass(PdoQueryLog::class)]
#[CoversClass(PdoQuerySignals::class)]
#[CoversClass(PdoQueryOperation::class)]
#[CoversClass(PdoQueryRun::class)]
#[CoversClass(PdoQueryLogArming::class)]
#[CoversClass(PdoConnection::class)]
final class PdoQueryLogTest extends TestCase
{
    private const array SQLITE = ['driver' => 'sqlite', 'dbname' => ':memory:'];

    public function testAMeasuredStatementAnswersThePerQueryRecordAndTheDurationObservation(): void
    {
        $recordingMeter = new RecordingMeter();
        $recordingLogger = new RecordingLogger();
        $connection = $this->loggedConnection($recordingMeter, $recordingLogger, 100);

        $affected = $connection->execute(new SqlQuery('INSERT INTO logged (v) VALUES (?)', ['hello']));

        self::assertSame(1, $affected, 'the measured statement still answers its own verb');

        self::assertCount(1, $recordingMeter->observations);
        self::assertSame('zef.db.query.duration_seconds', $recordingMeter->observations[0]['name']);
        self::assertGreaterThan(0.0, $recordingMeter->observations[0]['value']);
        self::assertLessThan(0.1, $recordingMeter->observations[0]['value']);
        self::assertSame(
            ['db.statement.operation' => 'insert'],
            $recordingMeter->observations[0]['attributes'],
        );

        self::assertCount(1, $recordingLogger->calls);
        self::assertSame('debug', $recordingLogger->calls[0][0]);
        self::assertSame('Query executed', $recordingLogger->calls[0][1]);
        self::assertSame('INSERT INTO logged (v) VALUES (?)', $recordingLogger->calls[0][2]['sql']);
        self::assertIsFloat($recordingLogger->calls[0][2]['elapsed_ms']);
        self::assertSame('insert', $recordingLogger->calls[0][2]['op']);
        self::assertArrayNotHasKey(
            'params',
            $recordingLogger->calls[0][2],
            'the bound values are payload, and payload never travels in records',
        );
    }

    public function testAMeasuredFetchAnswersItsOwnRecord(): void
    {
        $recordingMeter = new RecordingMeter();
        $recordingLogger = new RecordingLogger();
        $connection = $this->loggedConnection($recordingMeter, $recordingLogger, 100);

        $rows = $connection->fetchAll(new SqlQuery('SELECT v FROM logged'));

        self::assertSame([['v' => 'seeded']], $rows);

        self::assertCount(1, $recordingMeter->observations);
        self::assertSame(
            ['db.statement.operation' => 'select'],
            $recordingMeter->observations[0]['attributes'],
        );
        self::assertSame('select', $recordingLogger->calls[0][2]['op']);
        self::assertGreaterThan(0.0, $recordingMeter->observations[0]['value']);
        self::assertLessThan(
            1.0,
            $recordingMeter->observations[0]['value'],
            'the window measured the statement, not the clock epoch: a span that answers the\n'
                . ' uptime-scale magnitude is a bracket whose opening reading was lost',
        );
        self::assertLessThan(
            1000.0,
            $recordingLogger->calls[0][2]['elapsed_ms'],
            "the per-query record carries the statement's own span, never the clock epoch",
        );
    }

    public function testASpanThatExactlyMeetsTheBarAnswersNoSlowRecord(): void
    {
        $recordingMeter = new RecordingMeter();
        $recordingLogger = new RecordingLogger();

        PdoQuerySignals::emit(
            $recordingMeter,
            $recordingLogger,
            new SqlQuery('SELECT 1'),
            100 * 1000000,
            new QueryLogPolicy(thresholdMs: 100),
        );

        self::assertSame([], $recordingMeter->increments, 'a span that meets the bar is not slow');
        self::assertCount(1, $recordingLogger->calls, 'the per-query record still answers');
        self::assertSame('debug', $recordingLogger->calls[0][0]);
    }

    public function testASpanOneNanosecondOverTheBarAnswersBothRecordsAndTheCounter(): void
    {
        $recordingMeter = new RecordingMeter();
        $recordingLogger = new RecordingLogger();

        PdoQuerySignals::emit(
            $recordingMeter,
            $recordingLogger,
            new SqlQuery('UPDATE logged SET v = ?', ['x']),
            100 * 1000000 + 1,
            new QueryLogPolicy(thresholdMs: 100),
        );

        self::assertCount(1, $recordingMeter->increments);
        self::assertSame('zef.db.slow_query.total', $recordingMeter->increments[0]['name']);
        self::assertSame(1, $recordingMeter->increments[0]['value']);
        self::assertSame(
            ['db.statement.operation' => 'update'],
            $recordingMeter->increments[0]['attributes'],
        );

        self::assertCount(2, $recordingLogger->calls, 'the per-query record and the slow record both answer');
        self::assertSame('debug', $recordingLogger->calls[0][0]);
        self::assertSame(100.0, $recordingLogger->calls[0][2]['elapsed_ms']);
        self::assertSame('warning', $recordingLogger->calls[1][0]);
        self::assertSame('Slow query', $recordingLogger->calls[1][1]);
        self::assertSame('UPDATE logged SET v = ?', $recordingLogger->calls[1][2]['sql']);
        self::assertSame(100.0, $recordingLogger->calls[1][2]['elapsed_ms']);
        self::assertSame(100, $recordingLogger->calls[1][2]['threshold_ms']);
        self::assertSame('update', $recordingLogger->calls[1][2]['op']);
    }

    public function testTheOperationFoldReadsTheStatementsOwnHead(): void
    {
        self::assertSame('select', PdoQueryOperation::word('SELECT * FROM t'));
        self::assertSame('insert', PdoQueryOperation::word('  insert into t values (1)'));
        self::assertSame('update', PdoQueryOperation::word('UPDATE t SET a = 1'));
        self::assertSame('delete', PdoQueryOperation::word('delete from t'));
        self::assertSame('other', PdoQueryOperation::word('CREATE TABLE t (id INTEGER)'));
        self::assertSame('other', PdoQueryOperation::word('EXPLAIN SELECT 1'));
        self::assertSame('other', PdoQueryOperation::word('WITH c AS (SELECT 1) SELECT * FROM c'));
        self::assertSame(
            'select',
            PdoQueryOperation::word("\u{017F}ELECT 1"),
            'the Unicode case map folds the long s into SELECT: the head word the mb fold answers',
        );
    }

    public function testAFailingStatementRecordsItsSpanAndPropagatesItsOwnException(): void
    {
        $recordingMeter = new RecordingMeter();
        $recordingLogger = new RecordingLogger();
        $connection = $this->loggedConnection($recordingMeter, $recordingLogger, 100);

        $queryException = null;

        try {
            $connection->execute(new SqlQuery('INSERT INTO no_such_table (v) VALUES (?)', ['x']));
        } catch (QueryException $queryException) {
            self::assertStringContainsString('no_such_table', $queryException->getMessage());
        }

        self::assertNotNull($queryException, "the statement's own exception must propagate untouched");

        self::assertCount(1, $recordingLogger->calls, 'the throw path records its span: the finally seam');
        self::assertSame('debug', $recordingLogger->calls[0][0]);
        self::assertSame(
            'INSERT INTO no_such_table (v) VALUES (?)',
            $recordingLogger->calls[0][2]['sql'],
        );
        self::assertCount(1, $recordingMeter->observations);
    }

    public function testAFailingSinkNeverConvertsASuccessfulStatementIntoAFailure(): void
    {
        $connection = $this->loggedConnection(new RecordingMeter(), new ThrowingLogger(), 100);

        $affected = $connection->execute(new SqlQuery('INSERT INTO logged (v) VALUES (?)', ['x']));

        self::assertSame(1, $affected, "the observation is never worth the query's own answer");
    }

    public function testAFailingSinkNeverMasksTheStatementsOwnException(): void
    {
        $connection = $this->loggedConnection(new RecordingMeter(), new ThrowingLogger(), 100);

        $queryException = null;

        try {
            $connection->execute(new SqlQuery('INSERT INTO no_such_table (v) VALUES (?)', ['x']));
        } catch (QueryException $queryException) {
            self::assertStringContainsString('no_such_table', $queryException->getMessage());
        }

        self::assertNotNull(
            $queryException,
            "the statement's own exception must win the finally seam over the sink's own failure",
        );
    }

    public function testABackwardsClockSeamFabricatesNothing(): void
    {
        $recordingMeter = new RecordingMeter();
        $recordingLogger = new RecordingLogger();
        $steppingTelemetryClock = new SteppingTelemetryClock(monotonicEpoch: 2_000_000, monotonicStep: -500_000);
        $pdoConnection = new PdoConnection(
            ConnectionConfig::fromArray(self::SQLITE),
            null,
            null,
            null,
            new PdoQueryLog(
                new QueryLogPolicy(thresholdMs: 1),
                $recordingMeter,
                $recordingLogger,
                static fn (): int => $steppingTelemetryClock->monotonicNs(),
            ),
        );

        $pdoConnection->execute(new SqlQuery('CREATE TABLE logged (id INTEGER PRIMARY KEY, v TEXT)'));

        $affected = $pdoConnection->execute(new SqlQuery('INSERT INTO logged (v) VALUES (?)', ['x']));

        self::assertSame(1, $affected, 'the backwards clock never failed the statement');

        self::assertCount(2, $recordingLogger->calls, 'both statements still answered their records');
        foreach ($recordingLogger->calls as $call) {
            self::assertSame(-0.5, $call[2]['elapsed_ms'], 'the negative span renders as it answered');
        }

        self::assertSame(
            [],
            $recordingMeter->increments,
            'a negative span exceeds no positive bar: a broken measurement fabricates nothing',
        );
    }

    public function testTheUnarmedConnectionAnswersItsVerbsWithNoRecordsAtAll(): void
    {
        $recordingMeter = new RecordingMeter();
        $recordingLogger = new RecordingLogger();
        $pdoConnection = new PdoConnection(
            ConnectionConfig::fromArray(self::SQLITE),
            null,
            $recordingMeter,
            $recordingLogger,
        );

        $pdoConnection->execute(new SqlQuery('CREATE TABLE logged (id INTEGER PRIMARY KEY, v TEXT)'));

        $affected = $pdoConnection->execute(new SqlQuery('INSERT INTO logged (v) VALUES (?)', ['x']));
        $rows = $pdoConnection->fetchAll(new SqlQuery('SELECT v FROM logged'));

        self::assertSame(1, $affected);
        self::assertSame([['v' => 'x']], $rows);
        self::assertSame([], $recordingMeter->observations, 'no query log, no measurement');
        self::assertSame([], $recordingLogger->calls, 'no query log, no records');
    }

    public function testTheNullMeterSeamAnswersTheRecordsWithoutTheSignals(): void
    {
        $recordingLogger = new RecordingLogger();

        PdoQuerySignals::emit(
            null,
            $recordingLogger,
            new SqlQuery('SELECT 1'),
            150 * 1000000 + 1,
            new QueryLogPolicy(thresholdMs: 100),
        );

        self::assertCount(2, $recordingLogger->calls, 'both records answer without a meter');
        self::assertSame('debug', $recordingLogger->calls[0][0]);
        self::assertSame('warning', $recordingLogger->calls[1][0], "the slow verdict's record included");
    }

    public function testTheNullLoggerSeamAnswersTheSignalsWithoutTheRecords(): void
    {
        $recordingMeter = new RecordingMeter();

        PdoQuerySignals::emit(
            $recordingMeter,
            null,
            new SqlQuery('SELECT 1'),
            150 * 1000000 + 1,
            new QueryLogPolicy(thresholdMs: 100),
        );

        self::assertCount(1, $recordingMeter->observations, 'the duration observation answers without a logger');
        self::assertCount(1, $recordingMeter->increments, 'the slow counter included');
    }

    public function testTheElapsedFactsPinTheExactTwoDecimalRendition(): void
    {
        $recordingMeter = new RecordingMeter();
        $recordingLogger = new RecordingLogger();
        $queryLogPolicy = new QueryLogPolicy(thresholdMs: 1);

        PdoQuerySignals::emit($recordingMeter, $recordingLogger, new SqlQuery('SELECT 1'), 100254999, $queryLogPolicy);
        PdoQuerySignals::emit($recordingMeter, $recordingLogger, new SqlQuery('SELECT 2'), 100255001, $queryLogPolicy);
        PdoQuerySignals::emit($recordingMeter, $recordingLogger, new SqlQuery('SELECT 3'), 150512345, $queryLogPolicy);

        self::assertCount(6, $recordingLogger->calls, 'three query records, three slow records');

        self::assertSame(100.25, $recordingLogger->calls[0][2]['elapsed_ms']);
        self::assertSame(
            100.25,
            $recordingLogger->calls[1][2]['elapsed_ms'],
            'the slow record carries the same rendition',
        );
        self::assertSame(100.26, $recordingLogger->calls[2][2]['elapsed_ms']);
        self::assertSame(100.26, $recordingLogger->calls[3][2]['elapsed_ms']);
        self::assertSame(150.51, $recordingLogger->calls[4][2]['elapsed_ms']);
        self::assertSame(150.51, $recordingLogger->calls[5][2]['elapsed_ms']);
    }

    public function testTheArmingFoldAnswersTheBracketOnlyForAnArmedReaderedFamily(): void
    {
        $queryLogPolicy = new QueryLogPolicy();
        $recordingMeter = new RecordingMeter();
        $recordingLogger = new RecordingLogger();

        self::assertNotNull(PdoQueryLogArming::bracket($queryLogPolicy, $recordingMeter, $recordingLogger));
        self::assertNotNull(
            PdoQueryLogArming::bracket($queryLogPolicy, null, $recordingLogger),
            'a record reader alone arms the bracket',
        );
        self::assertNotNull(
            PdoQueryLogArming::bracket($queryLogPolicy, $recordingMeter, null),
            'a metrics reader alone arms the bracket',
        );
        self::assertNull(
            PdoQueryLogArming::bracket($queryLogPolicy, null, null),
            'the readerless armed family is the honest void',
        );
        self::assertNull(
            PdoQueryLogArming::bracket(new QueryLogPolicy(enabled: false), $recordingMeter, $recordingLogger),
            'the disarmed family answers no bracket',
        );
        self::assertNull(
            PdoQueryLogArming::bracket(null, $recordingMeter, $recordingLogger),
            'the absent family answers no bracket',
        );
    }

    /**
     * An armed connection over real SQLite: the table seeded through
     * the armed connection itself, then the tapes cleared, so every
     * test below asserts exactly the statements IT spelled.
     */
    private function loggedConnection(
        RecordingMeter $recordingMeter,
        RecordingLogger|ThrowingLogger $logger,
        int $thresholdMs,
    ): PdoConnection {
        $pdoConnection = new PdoConnection(
            ConnectionConfig::fromArray(self::SQLITE),
            null,
            null,
            null,
            new PdoQueryLog(
                new QueryLogPolicy(thresholdMs: $thresholdMs),
                $recordingMeter,
                $logger,
            ),
        );
        $pdoConnection->execute(new SqlQuery('CREATE TABLE logged (id INTEGER PRIMARY KEY, v TEXT)'));
        $pdoConnection->execute(new SqlQuery('INSERT INTO logged (v) VALUES (?)', ['seeded']));

        $recordingMeter->observations = [];
        $recordingMeter->increments = [];

        if ($logger instanceof RecordingLogger) {
            $logger->calls = [];
        }

        return $pdoConnection;
    }
}
