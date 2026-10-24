<?php

declare(strict_types=1);

namespace Zef\Test\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Zef\Framework\Exception\CarriesHttpStatus;
use Zef\Framework\Http\ErrorStatusMap;
use Zef\Framework\Http\HttpException;
use Zef\Framework\Http\MalformedBodyException;

/**
 * @internal
 *
 * The hierarchy pins go through reflection: the analyser already knows the
 * static types, so the facts worth pinning -- the leaf stays final, the
 * layer root admits subclasses, the refusal really sits under the fold's
 * catchability root, and the coarse-tier status resolution the refusal
 * deliberately joins -- are asserted where static analysis cannot fold
 * them
 */
#[CoversClass(MalformedBodyException::class)]
final class MalformedBodyExceptionTest extends TestCase
{
    public function testTheRefusalCarriesItsThrowSitesOwnSentence(): void
    {
        self::assertSame(
            'The request body is not a well-formed JSON document.',
            new MalformedBodyException('The request body is not a well-formed JSON document.')->getMessage(),
        );
    }

    /**
     * The refusal deliberately does NOT join the typed tier: the 400 it
     * must reach is the coarse status the error map's second tier
     * already answers for every {@see HttpException} -- the typed tier
     * exists for the statuses that differ from the coarse default
     * ({@see \Zef\Framework\Http\PayloadTooLargeException} 413).
     */
    public function testTheRefusalJoinsTheCoarseTierNotTheTypedOne(): void
    {
        self::assertFalse(
            new ReflectionClass(MalformedBodyException::class)->implementsInterface(CarriesHttpStatus::class),
            'the coarse tier answers the 400, not a typed override',
        );
        self::assertSame(
            400,
            ErrorStatusMap::statusFor(new MalformedBodyException('malformed')),
            'the error map answers the coarse 400 for the refusal',
        );
    }

    /**
     * The catchability contract the fold promised: every existing
     * `catch (HttpException)` keeps catching the refusal, asserted through
     * reflection so the pin survives the analyser's own knowledge.
     */
    public function testTheRefusalStaysUnderTheLayerRoot(): void
    {
        self::assertTrue(
            new ReflectionClass(MalformedBodyException::class)->isSubclassOf(HttpException::class),
        );
    }

    /**
     * The layer root admits typed signals beneath it (the family-root
     * shape), while every leaf signal stays final -- the contract the
     * fold's catchability and the family's discipline both rest on.
     */
    public function testTheSignalIsAFinalLeafUnderTheExtensibleLayerRoot(): void
    {
        self::assertTrue(new ReflectionClass(MalformedBodyException::class)->isFinal());
        self::assertFalse(new ReflectionClass(HttpException::class)->isFinal());
    }
}
