<?php

declare(strict_types=1);

namespace Zef\Test\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;
use Zef\Framework\Http\ErrorRequestContext;
use Zef\Framework\Http\ServerRequest;
use Zef\Framework\Http\Uri;

/**
 * @internal
 */
#[CoversClass(ErrorRequestContext::class)]
final class ErrorRequestContextTest extends TestCase
{
    public function testDatabaseAdapterCanIncrementOnlyTheActiveRequestCounter(): void
    {
        $serverRequest = new ServerRequest('GET', new Uri('https://app.example/users'));
        $errorRequestContext = new ErrorRequestContext('request-123');
        $requestWithContext = $serverRequest->withAttribute(ErrorRequestContext::ATTRIBUTE, $errorRequestContext);

        ErrorRequestContext::recordQueryFor($requestWithContext);
        ErrorRequestContext::recordQueryFor($requestWithContext);
        ErrorRequestContext::recordQueryFor($serverRequest);

        self::assertSame(2, $errorRequestContext->queryCount());
    }

    public function testATraceIdIsOnlyReplacedByACompleteNonZeroHexToken(): void
    {
        $errorRequestContext = new ErrorRequestContext('request-123');

        $errorRequestContext->setTraceId('g' . str_repeat('4', 32));
        self::assertSame('request-123', $errorRequestContext->traceId(), 'a 33-char token cannot match');

        $errorRequestContext->setTraceId(str_repeat('4', 32) . 'g');
        self::assertSame('request-123', $errorRequestContext->traceId(), 'a trailing suffix cannot match');

        $errorRequestContext->setTraceId(str_repeat('4', 32) . "\n");
        self::assertSame('request-123', $errorRequestContext->traceId(), 'a trailing newline cannot match');

        $errorRequestContext->setTraceId(str_repeat('0', 32));
        self::assertSame('request-123', $errorRequestContext->traceId(), 'an all-zero trace id is refused');

        $errorRequestContext->setTraceId(str_repeat('A', 32));
        self::assertSame(str_repeat('a', 32), $errorRequestContext->traceId(), 'a valid id is stored lowercase');
    }

    public function testTheQueryCounterSaturatesAtOneMillion(): void
    {
        $errorRequestContext = new ErrorRequestContext('request-123');

        for ($i = 0; $i < 1_000_001; ++$i) {
            $errorRequestContext->recordQuery();
        }

        self::assertSame(1_000_000, $errorRequestContext->queryCount());
    }

    public function testTheExceptionCounterCountsUpAndSaturatesAtOneHundred(): void
    {
        $errorRequestContext = new ErrorRequestContext('request-123');

        $errorRequestContext->recordException();
        self::assertSame(1, $errorRequestContext->exceptionCount());

        for ($i = 0; $i < 100; ++$i) {
            $errorRequestContext->recordException();
        }

        self::assertSame(100, $errorRequestContext->exceptionCount());
    }

    public function testTheDurationAnswersTheElapsedMillisecondsSinceConstruction(): void
    {
        // The start stamp is an unreadable readonly property fed by hrtime,
        // so the one-hour-ago stamp is injected the only way PHP allows:
        // reflection initialization of an instance that skipped its
        // constructor. A full hour of "elapsed" time makes the divisor and
        // operand observable: any off-by-one changes the answer by whole
        // milliseconds, far beyond the microseconds of real call jitter.
        $reflection = new ReflectionClass(ErrorRequestContext::class);
        $errorRequestContext = $reflection->newInstanceWithoutConstructor();
        $startedAt = new ReflectionProperty(ErrorRequestContext::class, 'startedAt');
        $startedAt->setValue($errorRequestContext, hrtime(true) - 3_600_000_000_000);

        $milliseconds = $errorRequestContext->durationMilliseconds();

        self::assertGreaterThan(3_599_999.0, $milliseconds);
        self::assertLessThan(3_600_001.0, $milliseconds);
    }

    public function testAReplayIdIsOnlyAcceptedAsACompleteLowercaseHexToken(): void
    {
        $errorRequestContext = new ErrorRequestContext('request-123');

        self::assertNull($errorRequestContext->replayId());

        $errorRequestContext->setReplayId('g' . str_repeat('f', 32));
        self::assertNull($errorRequestContext->replayId(), 'a leading non-hex byte refuses the id');

        $errorRequestContext->setReplayId(str_repeat('f', 32) . 'g');
        self::assertNull($errorRequestContext->replayId(), 'a trailing non-hex byte refuses the id');

        $errorRequestContext->setReplayId(str_repeat('f', 32) . "\n");
        self::assertNull($errorRequestContext->replayId(), 'a trailing newline refuses the id');

        $errorRequestContext->setReplayId(str_repeat('f', 32));
        self::assertSame(str_repeat('f', 32), $errorRequestContext->replayId());
    }
}
