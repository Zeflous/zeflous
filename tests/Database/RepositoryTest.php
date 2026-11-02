<?php

declare(strict_types=1);

namespace Zef\Test\Database;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Adapters\Database\ConnectionConfig;
use Zef\Framework\Adapters\Database\PdoConnection;
use Zef\Framework\Database\QueryException;
use Zef\Framework\Database\Repository;
use Zef\Framework\Database\RepositoryAggregate;
use Zef\Framework\Database\RepositoryCriteria;
use Zef\Framework\Database\RepositoryPage;
use Zef\Framework\Database\SqlQuery;
use Zef\Test\Support\Database\ScriptedConnection;
use Zef\Test\Support\Database\UsersRepository;

/**
 * The table-data-gateway base (real SQLite in-memory + a scripted
 * connection for the aggregate decode): CRUD round-trips, criteria null
 * semantics, ordering/pagination with the offset-without-limit guard,
 * the unbounded-write guards, and the construction-time table grammar.
 *
 * @internal
 */
#[CoversClass(Repository::class)]
#[CoversClass(RepositoryCriteria::class)]
#[CoversClass(RepositoryAggregate::class)]
#[CoversClass(RepositoryPage::class)]
#[CoversClass(QueryException::class)]
final class RepositoryTest extends TestCase
{
    private PdoConnection $pdoConnection;

    private Repository $repository;

    #[Override]
    protected function setUp(): void
    {
        $this->pdoConnection = new PdoConnection(ConnectionConfig::fromArray([
            'driver' => 'sqlite',
            'dbname' => ':memory:',
        ]));
        $this->pdoConnection->execute(SqlQuery::raw(
            'CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, age INTEGER, email TEXT)',
        ));
        $this->repository = new UsersRepository($this->pdoConnection);
    }

    public function testInsertFindUpdateDeleteRoundtrip(): void
    {
        self::assertSame(1, $this->repository->insert(['name' => 'Ari', 'age' => 30, 'email' => null]));
        self::assertSame(1, $this->repository->insert(['name' => 'Budi', 'age' => 17, 'email' => 'b@x.id']));

        $ari = $this->repository->find(1);
        self::assertSame(['id' => 1, 'name' => 'Ari', 'age' => 30, 'email' => null], $ari);

        self::assertNull($this->repository->find(999));

        self::assertSame(1, $this->repository->update(['age' => 31], ['name' => 'Ari']));
        $after = $this->repository->find(1);
        self::assertNotNull($after);
        self::assertSame(31, $after['age']);

        self::assertSame(1, $this->repository->delete(['name' => 'Budi']));
        self::assertNull($this->repository->find(2));
        self::assertSame(1, $this->repository->count());
    }

    public function testFindUsesTheDeclaredPrimaryKey(): void
    {
        $this->repository->insert(['name' => 'Ari', 'age' => 30, 'email' => 'a@x.id']);

        $byCustomKey = $this->repository->find('Ari', 'name');
        self::assertNotNull($byCustomKey);
        self::assertSame('Ari', $byCustomKey['name']);
        self::assertNull($this->repository->find('Nobody', 'name'));
    }

    public function testFindOneByAndNullCriterionBecomesIsNull(): void
    {
        $this->repository->insert(['name' => 'A', 'age' => 1, 'email' => 'a@x.id']);
        $this->repository->insert(['name' => 'B', 'age' => 1, 'email' => null]);

        $hit = $this->repository->findOneBy(['age' => 1, 'email' => 'a@x.id']);
        self::assertNotNull($hit);
        self::assertSame('A', $hit['name']);
        $nullHit = $this->repository->findOneBy(['email' => null]);
        self::assertNotNull($nullHit);
        self::assertSame('B', $nullHit['name']);
        self::assertNull($this->repository->findOneBy(['age' => 42]));
    }

    public function testFindByOrderLimitOffset(): void
    {
        foreach ([['C', 3], ['A', 1], ['B', 2], ['D', 4]] as [$n, $a]) {
            $this->repository->insert(['name' => $n, 'age' => $a, 'email' => null]);
        }

        $names = array_column($this->repository->findBy([], ['age' => 'ASC']), 'name');
        self::assertSame(['A', 'B', 'C', 'D'], $names);

        $desc = array_column($this->repository->findBy([], ['age' => 'DESC']), 'name');
        self::assertSame(['D', 'C', 'B', 'A'], $desc);

        $page = array_column($this->repository->findBy([], ['age' => 'ASC'], 2, 1), 'name');
        self::assertSame(['B', 'C'], $page);

        $zeroOffset = array_column($this->repository->findBy([], ['age' => 'ASC'], 2, 0), 'name');
        self::assertSame(['A', 'B'], $zeroOffset, 'a zero offset pages from the top');

        self::assertSame(1, $this->repository->count(['age' => 1]));
        self::assertSame(4, $this->repository->count());
        self::assertFalse($this->repository->exists(['name' => 'zzz']));
        self::assertTrue($this->repository->exists(['name' => 'A']));
    }

    public function testOffsetWithoutLimitRejected(): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionMessageIs('offset() requires a limit.');
        $this->repository->findBy([], null, null, 5);
    }

    public function testAZeroOffsetWithoutALimitAnswersEveryRow(): void
    {
        $this->repository->insert(['name' => 'A', 'age' => 1, 'email' => null]);

        $rows = $this->repository->findBy([], null, null, 0);

        self::assertCount(1, $rows, 'a zero offset is no offset at all');
    }

    public function testUpdateDeleteWithoutCriteriaRejected(): void
    {
        try {
            $this->repository->update(['age' => 1], []);
            self::fail('update without criteria must be rejected');
        } catch (QueryException $queryException) {
            self::assertSame('update() requires at least one criterion.', $queryException->getMessage());
        }

        $this->expectException(QueryException::class);
        $this->expectExceptionMessageIs('delete() requires at least one criterion.');
        $this->repository->delete([]);
    }

    public function testInvalidTableNameRejectedAtConstruction(): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionMessageIsOrContains("Invalid table 'users; DROP'.");
        new UsersRepository($this->pdoConnection, 'users; DROP');
    }

    public function testFindSpellsTheSingleRowSelectExactly(): void
    {
        $scriptedConnection = new ScriptedConnection();
        $scriptedConnection->fetchOneScript = [null];

        $usersRepository = new UsersRepository($scriptedConnection);

        self::assertNull($usersRepository->find(1));
        self::assertSame(
            ['SELECT * FROM "users" WHERE "id" = ? LIMIT 1'],
            $scriptedConnection->executed,
            'the primary-key lookup pins its single-row limit',
        );
    }

    public function testFindOneBySpellsTheSingleRowSelectExactly(): void
    {
        $scriptedConnection = new ScriptedConnection();
        $scriptedConnection->fetchOneScript = [null];

        $usersRepository = new UsersRepository($scriptedConnection);

        self::assertNull($usersRepository->findOneBy(['email' => 'a@x.id']));
        self::assertSame(
            ['SELECT * FROM "users" WHERE "email" = ? LIMIT 1'],
            $scriptedConnection->executed,
            'the criteria lookup pins its single-row limit',
        );
    }

    public function testTheTableBuilderFoldAnswersTheBoundTable(): void
    {
        $usersRepository = new UsersRepository($this->pdoConnection);

        self::assertSame('SELECT * FROM "users"', $usersRepository->tableSql());
    }

    public function testCountOnEmptyTable(): void
    {
        self::assertSame(0, $this->repository->count());
    }

    /**
     * The aggregate decode: the driver's COUNT answer arrives as an int,
     * a numeric string or a float, and each folds to the integer count;
     * anything else is a contract break the gateway halts on.
     */
    public function testCountFoldsEveryNumericAggregateSpelling(): void
    {
        $intAnswer = new ScriptedConnection();
        $intAnswer->fetchOneScript = [['aggregate' => 3]];
        self::assertSame(3, new UsersRepository($intAnswer, 'users')->count());

        $stringAnswer = new ScriptedConnection();
        $stringAnswer->fetchOneScript = [['aggregate' => '7']];
        self::assertSame(7, new UsersRepository($stringAnswer, 'users')->count());

        $floatAnswer = new ScriptedConnection();
        $floatAnswer->fetchOneScript = [['aggregate' => 2.7]];
        self::assertSame(2, new UsersRepository($floatAnswer, 'users')->count());
    }

    public function testCountRefusesAMissingAggregate(): void
    {
        $scriptedConnection = new ScriptedConnection();
        $scriptedConnection->fetchOneScript = [[]];

        $this->expectException(QueryException::class);
        $this->expectExceptionMessageIs('count() returned no aggregate value.');
        new UsersRepository($scriptedConnection, 'users')->count();
    }

    public function testCountRefusesANonNumericAggregate(): void
    {
        $scriptedConnection = new ScriptedConnection();
        $scriptedConnection->fetchOneScript = [['aggregate' => null]];

        $this->expectException(QueryException::class);
        $this->expectExceptionMessageIs('count() returned no aggregate value.');
        new UsersRepository($scriptedConnection, 'users')->count();
    }
}
