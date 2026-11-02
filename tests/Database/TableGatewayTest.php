<?php

declare(strict_types=1);

namespace Zef\Test\Database;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Adapters\Database\ConnectionConfig;
use Zef\Framework\Adapters\Database\PdoConnection;
use Zef\Framework\Database\QueryException;
use Zef\Framework\Database\SqlQuery;
use Zef\Framework\Database\TableGateway;

/**
 * Pins the family's generic table-data gateway: the constructible
 * spelling of the abstract Repository base -- the base verbs a
 * table needs (insert, find, the criteria walk, update, delete,
 * count) answering over one real connection, and the eager table
 * grammar the base's own constructor runs.
 *
 * @internal
 */
#[CoversClass(TableGateway::class)]
final class TableGatewayTest extends TestCase
{
    private PdoConnection $connection;

    protected function setUp(): void
    {
        $this->connection = new PdoConnection(
            ConnectionConfig::fromArray(['driver' => 'sqlite', 'dbname' => ':memory:']),
        );
        $this->connection->execute(
            new SqlQuery('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, age INTEGER)'),
        );
    }

    public function testTheGenericGatewayCarriesTheBaseVerbs(): void
    {
        $users = new TableGateway($this->connection, 'users');

        self::assertSame(1, $users->insert(['name' => 'Ari', 'age' => 30]));
        self::assertSame(1, $users->insert(['name' => 'Budi', 'age' => 17]));

        $ari = $users->find(1);
        self::assertSame(['id' => 1, 'name' => 'Ari', 'age' => 30], $ari);

        self::assertNull($users->find(999), 'an absent row answers null, never a failure');

        self::assertSame(1, $users->update(['age' => 31], ['name' => 'Ari']));
        self::assertSame(31, $users->find(1)['age']);

        self::assertSame(1, $users->delete(['name' => 'Budi']));
        self::assertNull($users->find(2));

        self::assertSame(1, $users->count());
        self::assertTrue($users->exists(['name' => 'Ari']));
    }

    public function testTheBaseConstructorRunsEagerlyForTheGenericToo(): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionMessageIsOrContains("Invalid table 'users; DROP'.");

        new TableGateway($this->connection, 'users; DROP');
    }
}
