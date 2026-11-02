<?php

declare(strict_types=1);

namespace Zef\Test\Database;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Adapters\Database\ConnectionConfig;
use Zef\Framework\Adapters\Database\PdoConnection;
use Zef\Framework\Adapters\Database\PdoUnboundedAudit;
use Zef\Framework\Database\SqlQuery;
use Zef\Test\Support\Log\RecordingLogger;
use Zef\Test\Support\Telemetry\RecordingMeter;

/**
 * Pins the unbounded-statement audit emission: the optional counter
 * and WARN-record seams the adapter's execute verb runs a
 * deliberately unbounded statement through -- the emission's own
 * operation fold (UPDATE-prefixed statements audit as 'update',
 * every other unbounded statement as 'delete'), the series and
 * dimensions the counter carries, the greppable WARN record with
 * the full SQL travelling in its context, and the honest void both
 * null seams spell (no observability, no cost, no failure).
 *
 * @internal
 */
#[CoversClass(PdoUnboundedAudit::class)]
#[CoversClass(PdoConnection::class)]
final class PdoUnboundedAuditTest extends TestCase
{
    private const array SQLITE = ['driver' => 'sqlite', 'dbname' => ':memory:'];

    public function testAnUnboundedUpdateEmitsTheCounterAndTheWarnRecord(): void
    {
        $recordingMeter = new RecordingMeter();
        $recordingLogger = new RecordingLogger();
        $connection = self::auditedConnection($recordingMeter, $recordingLogger);

        $affected = $connection->execute(new SqlQuery('UPDATE audit_rows SET v = ?', ['y'], true));

        self::assertSame(1, $affected);

        self::assertCount(1, $recordingMeter->increments);
        self::assertSame('zef.db.unbounded_statement', $recordingMeter->increments[0]['name']);
        self::assertSame(1, $recordingMeter->increments[0]['value']);
        self::assertSame(['op' => 'update'], $recordingMeter->increments[0]['attributes']);

        self::assertCount(1, $recordingLogger->calls);
        self::assertSame('warning', $recordingLogger->calls[0][0]);
        self::assertSame(
            'Unbounded update executed (allowUnbounded): 1 row(s) affected',
            $recordingLogger->calls[0][1],
        );
        self::assertSame(
            ['sql' => 'UPDATE audit_rows SET v = ?', 'rows' => 1, 'op' => 'update'],
            $recordingLogger->calls[0][2],
        );
    }

    public function testAnUnboundedDeleteAuditsAsTheDeleteOperation(): void
    {
        $recordingMeter = new RecordingMeter();
        $recordingLogger = new RecordingLogger();
        $connection = self::auditedConnection($recordingMeter, $recordingLogger);

        $connection->execute(new SqlQuery('DELETE FROM audit_rows', unbounded: true));

        self::assertSame(['op' => 'delete'], $recordingMeter->increments[0]['attributes']);
        self::assertSame(
            'Unbounded delete executed (allowUnbounded): 1 row(s) affected',
            $recordingLogger->calls[0][1],
        );
    }

    public function testTheOperationFoldReadsTheStatementsOwnHead(): void
    {
        $recordingMeter = new RecordingMeter();
        $recordingLogger = new RecordingLogger();

        PdoUnboundedAudit::emit($recordingMeter, $recordingLogger, new SqlQuery('  update t set a = 1'), 0);
        PdoUnboundedAudit::emit($recordingMeter, $recordingLogger, new SqlQuery('DELETE FROM t'), 2);

        self::assertSame('update', $recordingMeter->increments[0]['attributes']['op']);
        self::assertSame('delete', $recordingMeter->increments[1]['attributes']['op']);
        self::assertSame(
            'Unbounded update executed (allowUnbounded): 0 row(s) affected',
            $recordingLogger->calls[0][1],
        );
        self::assertSame(0, $recordingLogger->calls[0][2]['rows']);
    }

    public function testABoundedStatementEmitsNothing(): void
    {
        $recordingMeter = new RecordingMeter();
        $recordingLogger = new RecordingLogger();
        $connection = self::auditedConnection($recordingMeter, $recordingLogger);

        $connection->execute(new SqlQuery('UPDATE audit_rows SET v = ? WHERE id = ?', ['y', 1]));

        self::assertSame([], $recordingMeter->increments, 'a bounded UPDATE is not an audit event');
        self::assertSame([], $recordingLogger->calls);
    }

    public function testTheEmissionSurvivesItsNullSeams(): void
    {
        $connection = self::auditedConnection(null, null);

        $affected = $connection->execute(new SqlQuery('DELETE FROM audit_rows', unbounded: true));

        self::assertSame(1, $affected, 'the null seams are the honest void, never a failure');
    }

    public function testAFetchVerbNeverEmits(): void
    {
        $recordingMeter = new RecordingMeter();
        $recordingLogger = new RecordingLogger();
        $connection = self::auditedConnection($recordingMeter, $recordingLogger);

        $connection->fetchAll(new SqlQuery('SELECT * FROM audit_rows'));

        self::assertSame([], $recordingMeter->increments);
        self::assertSame([], $recordingLogger->calls);
    }

    private static function auditedConnection(?RecordingMeter $meter, ?RecordingLogger $logger): PdoConnection
    {
        $connection = new PdoConnection(ConnectionConfig::fromArray(self::SQLITE), null, $meter, $logger);
        $connection->execute(new SqlQuery('CREATE TABLE audit_rows (id INTEGER PRIMARY KEY, v TEXT)'));
        $connection->execute(new SqlQuery('INSERT INTO audit_rows (v) VALUES (?)', ['x']));

        return $connection;
    }
}
