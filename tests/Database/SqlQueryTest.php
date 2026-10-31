<?php

declare(strict_types=1);

namespace Zef\Test\Database;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Database\ConnectionException;
use Zef\Framework\Database\DatabaseException;
use Zef\Framework\Database\IsolationLevel;
use Zef\Framework\Database\QueryException;
use Zef\Framework\Database\SqlExpression;
use Zef\Framework\Database\SqlQuery;
use Zef\Framework\Database\TransactionException;
use Zef\Framework\Exception\ZefException;

/**
 * The value vocabulary's own guards: the sql/params/unbounded record
 * refuses the empty statement and the named-parameter map, the raw
 * factory answers the parameterless DDL shape, the expression wrapper
 * refuses whitespace-only input, and every isolation level's string
 * value is the exact SQL token the SET TRANSACTION statement spells.
 *
 * @internal
 */
#[CoversClass(SqlQuery::class)]
#[CoversClass(SqlExpression::class)]
#[CoversClass(IsolationLevel::class)]
#[CoversClass(QueryException::class)]
#[CoversClass(DatabaseException::class)]
final class SqlQueryTest extends TestCase
{
    public function testTheRecordCarriesItsThreeReadings(): void
    {
        $sqlQuery = new SqlQuery('SELECT 1', [1, 'a'], true);

        self::assertSame('SELECT 1', $sqlQuery->sql);
        self::assertSame([1, 'a'], $sqlQuery->params);
        self::assertTrue($sqlQuery->unbounded);
    }

    public function testDefaultsAreTheParameterlessBoundedShape(): void
    {
        $sqlQuery = new SqlQuery('SELECT 1');

        self::assertSame([], $sqlQuery->params);
        self::assertFalse($sqlQuery->unbounded);
    }

    public function testTheEmptyStatementIsRefused(): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionMessageIsOrContains('SQL statement must not be empty.');

        new SqlQuery('');
    }

    public function testNamedParameterMapsAreRefused(): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionMessageIsOrContains('SqlQuery params must be a list of positional values.');

        new SqlQuery('SELECT 1', ['named' => 'value']);
    }

    public function testTheRawFactoryAnswersTheDdlShape(): void
    {
        $query = SqlQuery::raw('CREATE TABLE t (id INTEGER)');

        self::assertSame('CREATE TABLE t (id INTEGER)', $query->sql);
        self::assertSame([], $query->params);
        self::assertFalse($query->unbounded);
    }

    public function testTheExpressionAnswersItsOwnString(): void
    {
        $sqlExpression = new SqlExpression('NOW()');

        self::assertSame('NOW()', $sqlExpression->expression);
        self::assertSame('NOW()', (string) $sqlExpression);
    }

    #[DataProvider('provideEveryIsolationLevelSpellsItsSqlTokenCases')]
    public function testEveryIsolationLevelSpellsItsSqlToken(IsolationLevel $isolationLevel, string $token): void
    {
        self::assertSame($token, $isolationLevel->value);
    }

    /**
     * @return iterable<non-empty-string, array{IsolationLevel, non-empty-string}>
     */
    public static function provideEveryIsolationLevelSpellsItsSqlTokenCases(): iterable
    {
        yield 'read uncommitted' => [IsolationLevel::ReadUncommitted, 'READ UNCOMMITTED'];

        yield 'read committed' => [IsolationLevel::ReadCommitted, 'READ COMMITTED'];

        yield 'repeatable read' => [IsolationLevel::RepeatableRead, 'REPEATABLE READ'];

        yield 'serializable' => [IsolationLevel::Serializable, 'SERIALIZABLE'];
    }

    public function testTheWhitespaceOnlyExpressionIsRefused(): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionMessageIsOrContains('SqlExpression must not be empty.');

        new SqlExpression('   ');
    }

    #[DataProvider('provideEveryMemberIsTypedOverTheFrameworkRootCases')]
    public function testEveryMemberIsTypedOverTheFrameworkRoot(ZefException $zefException): void
    {
        self::assertInstanceOf(DatabaseException::class, $zefException);
        self::assertSame('the message', $zefException->getMessage());
    }

    /**
     * The family's three concrete members, typed as the framework root
     * so membership itself is pinned by the provider's signature.
     *
     * @return iterable<non-empty-string, array{ZefException}>
     */
    public static function provideEveryMemberIsTypedOverTheFrameworkRootCases(): iterable
    {
        yield 'query' => [new QueryException('the message')];

        yield 'connection' => [new ConnectionException('the message')];

        yield 'transaction' => [new TransactionException('the message')];
    }

    public function testTheFamilyErrorCodesAreDistinct(): void
    {
        $codes = [
            new QueryException('m')->getErrorCode(),
            new ConnectionException('m')->getErrorCode(),
            new TransactionException('m')->getErrorCode(),
        ];

        self::assertSame($codes, array_values(array_unique($codes)));
    }
}
