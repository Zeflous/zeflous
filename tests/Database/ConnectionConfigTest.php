<?php

declare(strict_types=1);

namespace Zef\Test\Database;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Adapters\Database\ConnectionConfig;
use Zef\Framework\Database\ConnectionException;

/**
 * The connection configuration's refusal matrix: every misconfiguration
 * surfaces at construction time with an exact message (driver, dbname,
 * host shape and length, port range in both spellings, user/password/
 * charset types, the options whitelist) and the three drivers' DSN
 * spellings are pinned -- including the MySQL default charset, the
 * PostgreSQL client-encoding options clause and the SQLite path
 * boundary.
 *
 * @internal
 */
#[CoversClass(ConnectionConfig::class)]
#[CoversClass(ConnectionException::class)]
final class ConnectionConfigTest extends TestCase
{
    // ------------------------------------------------------------------
    // DSN spelling
    // ------------------------------------------------------------------

    public function testMysqlDsnCarriesHostPortDbnameAndDefaultCharset(): void
    {
        $config = ConnectionConfig::fromArray([
            'driver' => 'mysql',
            'host' => 'db.local',
            'dbname' => 'app',
            'user' => 'app',
            'password' => 'secret',
        ]);

        self::assertSame('mysql', $config->driver);
        self::assertSame('db.local', $config->host);
        self::assertSame(3306, $config->port);
        self::assertSame('utf8mb4', $config->charset);
        self::assertSame('mysql:host=db.local;port=3306;dbname=app;charset=utf8mb4', $config->dsn());
    }

    public function testPgsqlDsnCarriesTheClientEncodingClause(): void
    {
        $config = ConnectionConfig::fromArray([
            'driver' => 'pgsql',
            'host' => 'db.local',
            'port' => '5433',
            'dbname' => 'app',
            'charset' => 'UTF8',
        ]);

        self::assertSame(5433, $config->port);
        self::assertSame("pgsql:host=db.local;port=5433;dbname=app;options='--client_encoding=UTF8'", $config->dsn());
    }

    public function testSqliteDsnIsThePathAlone(): void
    {
        $memory = ConnectionConfig::fromArray(['driver' => 'sqlite', 'dbname' => ':memory:']);
        $file = ConnectionConfig::fromArray(['driver' => 'sqlite', 'dbname' => '/var/data/app.sqlite']);

        self::assertSame('sqlite::memory:', $memory->dsn());
        self::assertSame('sqlite:/var/data/app.sqlite', $file->dsn());
        self::assertNull($memory->host);
        self::assertNull($memory->port);
    }

    // ------------------------------------------------------------------
    // Driver refusal
    // ------------------------------------------------------------------

    public function testUnknownDriverRejected(): void
    {
        $this->expectException(ConnectionException::class);
        $this->expectExceptionMessageIsOrContains("Unknown database driver 'oracle' (allowed: mysql, pgsql, sqlite).");

        ConnectionConfig::fromArray(['driver' => 'oracle', 'dbname' => 'x']);
    }

    public function testNonStringDriverRejected(): void
    {
        $this->expectException(ConnectionException::class);
        $this->expectExceptionMessageIsOrContains("Unknown database driver '5' (allowed: mysql, pgsql, sqlite).");

        ConnectionConfig::fromArray(['driver' => 5, 'dbname' => 'x']);
    }

    public function testMissingDriverRejected(): void
    {
        $this->expectException(ConnectionException::class);
        $this->expectExceptionMessageIsOrContains('Unknown database driver');

        ConnectionConfig::fromArray(['dbname' => 'x']);
    }

    // ------------------------------------------------------------------
    // Dbname refusal
    // ------------------------------------------------------------------

    public function testEmptyDbnameRejected(): void
    {
        $this->expectException(ConnectionException::class);
        $this->expectExceptionMessageIsOrContains(
            "Database name (dbname) must be a non-empty string for driver 'mysql'.",
        );

        ConnectionConfig::fromArray(['driver' => 'mysql', 'host' => 'h', 'dbname' => '']);
    }

    public function testNonStringDbnameRejected(): void
    {
        $this->expectException(ConnectionException::class);
        $this->expectExceptionMessageIsOrContains(
            "Database name (dbname) must be a non-empty string for driver 'mysql'.",
        );

        ConnectionConfig::fromArray(['driver' => 'mysql', 'host' => 'h', 'dbname' => 42]);
    }

    public function testSqliteNonPrintablePathRejected(): void
    {
        $this->expectException(ConnectionException::class);
        $this->expectExceptionMessageIsOrContains(
            "SQLite database path must be ':memory:' or printable ASCII (got non-printable input).",
        );

        ConnectionConfig::fromArray(['driver' => 'sqlite', 'dbname' => "/var/data/a\tb"]);
    }

    public function testSqlitePathBoundaryLengthIsAccepted(): void
    {
        $config = ConnectionConfig::fromArray(['driver' => 'sqlite', 'dbname' => '/' . str_repeat('a', 4095)]);

        self::assertSame('sqlite:/' . str_repeat('a', 4095), $config->dsn());
    }

    // ------------------------------------------------------------------
    // Host & port refusal
    // ------------------------------------------------------------------

    public function testMissingHostRejectedForNonSqliteDrivers(): void
    {
        $this->expectException(ConnectionException::class);
        $this->expectExceptionMessageIsOrContains(
            "Host must be a non-empty string without whitespace for driver 'mysql'.",
        );

        ConnectionConfig::fromArray(['driver' => 'mysql', 'dbname' => 'app']);
    }

    public function testHostWhitespaceRejected(): void
    {
        $this->expectException(ConnectionException::class);
        $this->expectExceptionMessageIsOrContains(
            "Host must be a non-empty string without whitespace for driver 'pgsql'.",
        );

        ConnectionConfig::fromArray(['driver' => 'pgsql', 'host' => 'db local', 'dbname' => 'app']);
    }

    public function testHostExactly255BytesAccepted(): void
    {
        $config = ConnectionConfig::fromArray(['driver' => 'mysql', 'host' => str_repeat('h', 255), 'dbname' => 'app']);

        self::assertSame(str_repeat('h', 255), $config->host);
    }

    public function testHostBeyond255BytesRejected(): void
    {
        $this->expectException(ConnectionException::class);
        $this->expectExceptionMessageIsOrContains('Host must be a non-empty string without whitespace');

        ConnectionConfig::fromArray(['driver' => 'mysql', 'host' => str_repeat('h', 256), 'dbname' => 'app']);
    }

    public function testPortNumericStringBoundaries(): void
    {
        $low = ConnectionConfig::fromArray(['driver' => 'mysql', 'host' => 'h', 'dbname' => 'app', 'port' => '1']);
        $high = ConnectionConfig::fromArray(['driver' => 'pgsql', 'host' => 'h', 'dbname' => 'app', 'port' => '65535']);

        self::assertSame(1, $low->port);
        self::assertSame(65535, $high->port);
    }

    public function testPortExtremeValuesAccepted(): void
    {
        $config = ConnectionConfig::fromArray(['driver' => 'mysql', 'host' => 'h', 'dbname' => 'app', 'port' => 65535]);

        self::assertSame(65535, $config->port);
    }

    public function testPortBeyondRangeRejected(): void
    {
        $this->expectException(ConnectionException::class);
        $this->expectExceptionMessageIsOrContains('Port 65536 is out of range (1-65535).');

        ConnectionConfig::fromArray(['driver' => 'mysql', 'host' => 'h', 'dbname' => 'app', 'port' => 65536]);
    }

    public function testPortZeroRejected(): void
    {
        $this->expectException(ConnectionException::class);
        $this->expectExceptionMessageIsOrContains('Port 0 is out of range (1-65535).');

        ConnectionConfig::fromArray(['driver' => 'mysql', 'host' => 'h', 'dbname' => 'app', 'port' => 0]);
    }

    public function testNonNumericPortStringRejected(): void
    {
        $this->expectException(ConnectionException::class);
        $this->expectExceptionMessageIsOrContains('Port must be an int or a numeric string (1-65535).');

        ConnectionConfig::fromArray(['driver' => 'mysql', 'host' => 'h', 'dbname' => 'app', 'port' => 'http']);
    }

    // ------------------------------------------------------------------
    // Credentials, charset, options
    // ------------------------------------------------------------------

    public function testNonStringUserRejected(): void
    {
        $this->expectException(ConnectionException::class);
        $this->expectExceptionMessageIsOrContains('User must be a string or null.');

        ConnectionConfig::fromArray(['driver' => 'mysql', 'host' => 'h', 'dbname' => 'app', 'user' => 7]);
    }

    public function testNonStringPasswordRejected(): void
    {
        $this->expectException(ConnectionException::class);
        $this->expectExceptionMessageIsOrContains('Password must be a string or null.');

        ConnectionConfig::fromArray(['driver' => 'mysql', 'host' => 'h', 'dbname' => 'app', 'password' => false]);
    }

    public function testNonStringCharsetRejected(): void
    {
        $this->expectException(ConnectionException::class);
        $this->expectExceptionMessageIsOrContains('Charset must be a string or null.');

        ConnectionConfig::fromArray(['driver' => 'mysql', 'host' => 'h', 'dbname' => 'app', 'charset' => 8]);
    }

    public function testNonArrayOptionsRejected(): void
    {
        $this->expectException(ConnectionException::class);
        $this->expectExceptionMessageIsOrContains('Options must be an array.');

        ConnectionConfig::fromArray(['driver' => 'mysql', 'host' => 'h', 'dbname' => 'app', 'options' => 'yes']);
    }

    public function testUnknownOptionKeyRejected(): void
    {
        $this->expectException(ConnectionException::class);
        $this->expectExceptionMessageIsOrContains(
            "Unknown connection option 'pooling' (allowed: persistent, timeout).",
        );

        ConnectionConfig::fromArray([
            'driver' => 'mysql',
            'host' => 'h',
            'dbname' => 'app',
            'options' => ['pooling' => true],
        ]);
    }

    public function testNonIntOptionKeyRejected(): void
    {
        $this->expectException(ConnectionException::class);
        $this->expectExceptionMessageIsOrContains("Unknown connection option 'int' (allowed: persistent, timeout).");

        ConnectionConfig::fromArray(['driver' => 'mysql', 'host' => 'h', 'dbname' => 'app', 'options' => [0 => true]]);
    }

    public function testNonBoolPersistentRejected(): void
    {
        $this->expectException(ConnectionException::class);
        $this->expectExceptionMessageIsOrContains('Option persistent must be a bool.');

        ConnectionConfig::fromArray([
            'driver' => 'mysql',
            'host' => 'h',
            'dbname' => 'app',
            'options' => ['persistent' => 'yes'],
        ]);
    }

    public function testNonNumericTimeoutRejected(): void
    {
        $this->expectException(ConnectionException::class);
        $this->expectExceptionMessageIsOrContains('Option timeout must be an int or float.');

        ConnectionConfig::fromArray([
            'driver' => 'mysql',
            'host' => 'h',
            'dbname' => 'app',
            'options' => ['timeout' => 'soon'],
        ]);
    }

    public function testNonPositiveTimeoutRejected(): void
    {
        $this->expectException(ConnectionException::class);
        $this->expectExceptionMessageIsOrContains('Option timeout must be greater than zero.');

        ConnectionConfig::fromArray([
            'driver' => 'mysql',
            'host' => 'h',
            'dbname' => 'app',
            'options' => ['timeout' => 0],
        ]);
    }

    public function testFloatTimeoutBoundaryAccepted(): void
    {
        $config = ConnectionConfig::fromArray([
            'driver' => 'mysql',
            'host' => 'h',
            'dbname' => 'app',
            'options' => ['timeout' => 0.5, 'persistent' => false],
        ]);

        self::assertSame(['persistent' => false, 'timeout' => 0.5], $config->options);
    }

    public function testTheDefaultCharsetBelongsToMysqlAlone(): void
    {
        // PostgreSQL without a charset keeps null: the default belongs
        // to the mysql driver alone, and an explicit spelling always
        // wins verbatim.
        $pgsql = ConnectionConfig::fromArray(['driver' => 'pgsql', 'host' => 'h', 'dbname' => 'app']);
        self::assertNull($pgsql->charset);
        self::assertStringNotContainsString('client_encoding', $pgsql->dsn());

        $explicit = ConnectionConfig::fromArray([
            'driver' => 'mysql',
            'host' => 'h',
            'dbname' => 'app',
            'charset' => 'latin1',
        ]);
        self::assertSame('latin1', $explicit->charset);
    }
}
