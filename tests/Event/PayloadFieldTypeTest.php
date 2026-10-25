<?php

declare(strict_types=1);

namespace Zef\Test\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Event\PayloadFieldType;

/**
 * Pins the payload kind vocabulary: the five spellings the shape
 * grammar accepts and, for every kind, the exact set of values
 * that answer it. The cross-product is spelled out on purpose --
 * a kind that accepted a value its spelling forbids (or refused
 * one it names) would move the payload boundary from the dispatch
 * call site into a listener's body, exactly where the roadmap
 * refuses to let it live.
 *
 * @internal
 */
#[CoversClass(PayloadFieldType::class)]
final class PayloadFieldTypeTest extends TestCase
{
    public function testTheVocabularySpellsFiveKinds(): void
    {
        self::assertSame(
            ['string', 'int', 'float', 'bool', 'list'],
            array_map(
                static fn (PayloadFieldType $payloadFieldType): string => $payloadFieldType->spelling(),
                PayloadFieldType::cases(),
            ),
        );
    }

    public function testStringAcceptsAStringAndRefusesTheRest(): void
    {
        $type = PayloadFieldType::String;

        self::assertTrue($type->accepts('hello'));
        self::assertFalse($type->accepts(0));
        self::assertFalse($type->accepts(0.5));
        self::assertFalse($type->accepts(true));
        self::assertFalse($type->accepts([]));
        self::assertFalse($type->accepts(null));
    }

    public function testIntegerAcceptsAnIntAndRefusesTheRest(): void
    {
        $type = PayloadFieldType::Integer;

        self::assertTrue($type->accepts(7));
        self::assertTrue($type->accepts(-7));
        self::assertTrue($type->accepts(0));
        self::assertFalse($type->accepts('7'));
        self::assertFalse($type->accepts(7.0));
        self::assertFalse($type->accepts(false));
        self::assertFalse($type->accepts([]));
        self::assertFalse($type->accepts(null));
    }

    public function testFloatAcceptsAFloatAndRefusesTheRest(): void
    {
        $type = PayloadFieldType::Float;

        self::assertTrue($type->accepts(0.5));
        self::assertFalse($type->accepts(5));
        self::assertFalse($type->accepts('0.5'));
        self::assertFalse($type->accepts(true));
        self::assertFalse($type->accepts([]));
        self::assertFalse($type->accepts(null));
    }

    public function testBooleanAcceptsBothBooleansAndRefusesTheRest(): void
    {
        $type = PayloadFieldType::Boolean;

        self::assertTrue($type->accepts(true));
        self::assertTrue($type->accepts(false));
        self::assertFalse($type->accepts(1));
        self::assertFalse($type->accepts(0));
        self::assertFalse($type->accepts('true'));
        self::assertFalse($type->accepts([]));
        self::assertFalse($type->accepts(null));
    }

    public function testListAcceptsAnArrayAndRefusesTheRest(): void
    {
        $type = PayloadFieldType::List;

        self::assertTrue($type->accepts([]));
        self::assertTrue($type->accepts([1, 2, 3]));
        self::assertFalse($type->accepts('list'));
        self::assertFalse($type->accepts(0));
        self::assertFalse($type->accepts(0.5));
        self::assertFalse($type->accepts(true));
        self::assertFalse($type->accepts(null));
    }
}
