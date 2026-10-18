<?php

declare(strict_types=1);

namespace Zef\Test\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Zef\Framework\Exception\CarriesHttpStatus;
use Zef\Framework\Http\HttpException;
use Zef\Framework\Http\PayloadTooLargeException;

/**
 * @internal
 *
 * The hierarchy pins go through reflection: the analyser already knows the
 * static types, so the facts worth pinning -- the leaf stays final, the
 * layer root admits subclasses, the refusal really sits under the fold's
 * catchability root -- are asserted where static analysis cannot fold
 * them
 */
#[CoversClass(PayloadTooLargeException::class)]
final class PayloadTooLargeExceptionTest extends TestCase
{
    public function testTheRefusalMessageStaysByteIdenticalToTheIngressText(): void
    {
        self::assertSame(
            'Request body exceeds configured size limit.',
            new PayloadTooLargeException()->getMessage(),
        );
    }

    public function testTheRefusalAnswersTheTypedBudgetStatus(): void
    {
        self::assertSame(413, new PayloadTooLargeException()->httpStatus());
    }

    /**
     * The catchability contract the fold promised: every existing
     * `catch (HttpException)` keeps catching the refusal, asserted through
     * reflection so the pin survives the analyser's own knowledge.
     */
    public function testTheRefusalStaysUnderTheLayerRoot(): void
    {
        self::assertTrue(
            new ReflectionClass(PayloadTooLargeException::class)->isSubclassOf(HttpException::class),
        );
        self::assertTrue(
            new ReflectionClass(PayloadTooLargeException::class)->implementsInterface(CarriesHttpStatus::class),
        );
    }

    /**
     * The layer root admits typed signals beneath it (the family-root
     * shape), while every leaf signal stays final -- the contract the
     * fold's catchability and the family's discipline both rest on.
     */
    public function testTheSignalIsAFinalLeafUnderTheExtensibleLayerRoot(): void
    {
        self::assertTrue(new ReflectionClass(PayloadTooLargeException::class)->isFinal());
        self::assertFalse(new ReflectionClass(HttpException::class)->isFinal());
    }
}
