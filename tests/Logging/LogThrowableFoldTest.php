<?php

declare(strict_types=1);

namespace Zef\Test\Logging;

use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Zef\Framework\Logging\LogThrowableFold;

/**
 * @internal
 */
#[CoversClass(LogThrowableFold::class)]
final class LogThrowableFoldTest extends TestCase
{
    public function testTheFoldCarriesTheExceptionsOwnThreeFacts(): void
    {
        $fold = LogThrowableFold::fold(new LogicException('the wrapper', 7));

        self::assertSame(LogicException::class, $fold['class']);
        self::assertSame('the wrapper', $fold['message']);
        self::assertSame(7, $fold['code']);
        self::assertArrayNotHasKey('previous', $fold, 'an unwrapped failure has no chain');
    }

    /**
     * The previous link is the root cause a wrapped diagnostic exists
     * to name: the fold keeps it, nested the way the wrap itself nests.
     */
    public function testTheWrappedChainRidesTheFold(): void
    {
        $runtimeException = new RuntimeException('the root cause');
        $logicException = new LogicException('the wrapper', 0, $runtimeException);

        $fold = LogThrowableFold::fold($logicException);

        $previous = $fold['previous'];
        self::assertIsArray($previous);
        self::assertSame(RuntimeException::class, $previous['class']);
        self::assertSame('the root cause', $previous['message']);
        self::assertArrayNotHasKey('previous', $previous, 'the chain ends where the wrap ends');
    }

    /**
     * Every link of the chain carries the same three facts the wrapper
     * carries -- its own code included: a root cause that names an
     * error code a record cannot read is a causeless failure.
     */
    public function testEveryChainLinkCarriesItsOwnCode(): void
    {
        $runtimeException = new RuntimeException('the root cause', 42);
        $logicException = new LogicException('the wrapper', 7, $runtimeException);

        $fold = LogThrowableFold::fold($logicException);

        self::assertSame(7, $fold['code']);
        $previous = $fold['previous'];
        self::assertIsArray($previous);
        self::assertSame(42, $previous['code']);
    }

    /**
     * A pathological wrap chain stops at the budget's marker, never at
     * the stack.
     */
    public function testAPathologicalChainStopsAtTheMarker(): void
    {
        $throwable = new RuntimeException('the outermost');
        for ($depth = 0; $depth < 12; ++$depth) {
            $throwable = new RuntimeException('link ' . $depth, 0, $throwable);
        }

        $link = LogThrowableFold::fold($throwable);

        for ($depth = 0; $depth < 10; ++$depth) {
            $link = $link['previous'];
            self::assertIsArray($link);
        }

        self::assertArrayHasKey('_truncated', $link, 'the chain budget names its own end');
        self::assertTrue($link['_truncated']);
        self::assertArrayNotHasKey('previous', $link);
    }

    public function testAMessageCarryingACredentialIsRedactedInTheFold(): void
    {
        $fold = LogThrowableFold::fold(new RuntimeException('Bearer eyJhbGciOiJIUzI1NiJ9.e30.abc123'));

        self::assertSame('Bearer [REDACTED]', $fold['message']);
    }
}
