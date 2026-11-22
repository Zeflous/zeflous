<?php

declare(strict_types=1);

namespace Zef\Test\Database;

use Closure;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use Zef\Framework\Adapters\Database\ConnectionConfig;
use Zef\Framework\Adapters\Database\PdoConnection;
use Zef\Framework\Adapters\Database\PdoFetchOne;
use Zef\Framework\Adapters\Database\PdoQueryCount;
use Zef\Framework\Adapters\Database\PdoQueryRun;
use Zef\Framework\Database\QueryException;
use Zef\Framework\Database\SqlQuery;
use Zef\Framework\Http\ErrorRequestContext;
use Zef\Framework\Http\ServerRequest;
use Zef\Framework\Http\Uri;

/**
 * Pins the request-scoped query counter's feeder over real SQLite: the
 * vendored adapter's own call to the hook the toolbar waited on
 * (`ErrorRequestContext::recordQueryFor()`), answered once per executed
 * statement through the composition's request provider (the null answer
 * of a console or warm-up run feeding nothing), the finally seam the
 * feed shares with the measured window's closing edge (a statement that
 * threw still counts, then propagates its throw untouched), the
 * containment a throwing provider answers (the query's own answer is
 * never worth replacing), the boundary-less request that carries no
 * context to feed, and the honest void the providerless composition
 * spells.
 *
 * @internal
 */
#[CoversClass(PdoQueryCount::class)]
#[CoversClass(PdoConnection::class)]
#[CoversClass(PdoQueryRun::class)]
#[CoversClass(PdoFetchOne::class)]
final class PdoQueryCountTest extends TestCase
{
    private const array SQLITE = ['driver' => 'sqlite', 'dbname' => ':memory:'];

    public function testEveryStatementVerbFeedsTheActiveRequestCounter(): void
    {
        $errorRequestContext = new ErrorRequestContext('request-123');
        $serverRequest = $this->contextCarryingRequest($errorRequestContext);
        $connection = $this->countedConnection(static fn (): ServerRequestInterface => $serverRequest);

        $connection->execute(new SqlQuery('CREATE TABLE counted (id INTEGER PRIMARY KEY, v TEXT)'));
        $connection->execute(new SqlQuery('INSERT INTO counted (v) VALUES (?)', ['x']));
        $connection->fetchAll(new SqlQuery('SELECT id FROM counted'));
        $connection->fetchOne(new SqlQuery('SELECT v FROM counted'));

        self::assertSame(
            4,
            $errorRequestContext->queryCount(),
            'every executed statement fed the request-scoped counter once, whichever verb ran it',
        );
    }

    public function testAFailingStatementStillFeedsTheCounterThenPropagatesItsThrow(): void
    {
        $errorRequestContext = new ErrorRequestContext('request-123');
        $serverRequest = $this->contextCarryingRequest($errorRequestContext);
        $connection = $this->countedConnection(static fn (): ServerRequestInterface => $serverRequest);

        try {
            $connection->execute(new SqlQuery('INSERT INTO nowhere (v) VALUES (?)', ['x']));
            self::fail('the failing statement still propagates its own refusal');
        } catch (QueryException $queryException) {
            self::assertStringContainsString('nowhere', $queryException->getMessage());
        }

        self::assertSame(
            1,
            $errorRequestContext->queryCount(),
            'a statement that threw is a query the request paid: the finally seam feeds it too',
        );
    }

    public function testARequestWithoutTheBoundaryContextFeedsNoCounter(): void
    {
        $serverRequest = new ServerRequest('GET', new Uri('https://app.example/orders'));
        $connection = $this->countedConnection(static fn (): ServerRequest => $serverRequest);

        $connection->execute(new SqlQuery('SELECT 1 AS one'));

        self::assertSame(
            [['one' => 1]],
            $connection->fetchAll(new SqlQuery('SELECT 1 AS one')),
            'the statement answered its own verb: the count is decoration, never a dependency',
        );
    }

    public function testAProviderAnsweringNoActiveRequestCountsNothing(): void
    {
        $errorRequestContext = new ErrorRequestContext('request-123');
        $serverRequest = $this->contextCarryingRequest($errorRequestContext);
        $connection = $this->countedConnection(static fn (): ?ServerRequestInterface => null);

        $connection->execute(new SqlQuery('SELECT 1 AS one'));

        self::assertSame(
            0,
            $errorRequestContext->queryCount(),
            'a console run or warm-up walk answers no request, and no request means no counter',
        );
        self::assertSame(
            'GET',
            $serverRequest->getMethod(),
            'the provider that answers no request leaves the boundary-carried one untouched',
        );
    }

    public function testAThrowingProviderCannotReplaceTheStatementAnswer(): void
    {
        $connection = $this->countedConnection(static function (): ServerRequest {
            throw new RuntimeException("the composition's provider failed");
        });

        $rows = $connection->fetchAll(new SqlQuery('SELECT 1 AS one'));

        self::assertSame(
            [['one' => 1]],
            $rows,
            'the observation is never worth the query: a failing provider answers containment',
        );
    }

    public function testTheProviderlessCompositionIsTheHonestVoid(): void
    {
        self::assertNull(
            PdoQueryCount::whenCarried(null),
            'no provider armed means no feeder held, no question asked, no cost paid',
        );

        $pdoConnection = new PdoConnection(ConnectionConfig::fromArray(self::SQLITE));
        self::assertSame(
            [['one' => 1]],
            $pdoConnection->fetchAll(new SqlQuery('SELECT 1 AS one')),
            'the providerless connection answers its verbs exactly as it always did',
        );
    }

    public function testTheCountedFetchOneStillAnswersItsOwnVerbUntouched(): void
    {
        $errorRequestContext = new ErrorRequestContext('request-123');
        $serverRequest = $this->contextCarryingRequest($errorRequestContext);
        $pdoConnection = new PdoConnection(
            ConnectionConfig::fromArray(self::SQLITE),
            null,
            null,
            null,
            null,
            PdoQueryCount::whenCarried(static fn (): ServerRequestInterface => $serverRequest),
        );

        $rows = $pdoConnection->fetchOne(new SqlQuery('SELECT 1 AS one'));

        self::assertSame(['one' => 1], $rows, 'the count is decoration, never a dependency');
        self::assertSame(1, $errorRequestContext->queryCount());
    }

    /**
     * @param Closure(): (null|ServerRequestInterface) $requestProvider the composition's request seam
     */
    private function countedConnection(Closure $requestProvider): PdoConnection
    {
        return new PdoConnection(
            ConnectionConfig::fromArray(self::SQLITE),
            null,
            null,
            null,
            null,
            PdoQueryCount::whenCarried($requestProvider),
        );
    }

    private function contextCarryingRequest(ErrorRequestContext $errorRequestContext): ServerRequestInterface
    {
        $serverRequest = new ServerRequest('GET', new Uri('https://app.example/orders'));

        return $serverRequest->withAttribute(ErrorRequestContext::ATTRIBUTE, $errorRequestContext);
    }
}
