<?php

declare(strict_types=1);

namespace Zef\Test;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Message\Message;

/**
 * @internal
 */
#[CoversClass(Message::class)]
final class MessageTest extends TestCase
{
    public function testExposesItsName(): void
    {
        self::assertSame('order.create', new Message('order.create')->name());
    }

    public function testPayloadDefaultsToAnEmptyArray(): void
    {
        self::assertSame([], new Message('m')->payload());
    }

    public function testExposesThePayload(): void
    {
        self::assertSame(['id' => 42], new Message('m', ['id' => 42])->payload());
    }

    public function testGetReturnsTheStoredValue(): void
    {
        self::assertSame(42, new Message('m', ['id' => 42])->get('id'));
    }

    public function testGetReturnsTheDefaultWhenTheKeyIsAbsent(): void
    {
        self::assertSame('fallback', new Message('m')->get('id', 'fallback'));
    }

    public function testGetReturnsNullWhenAbsentAndNoDefaultIsGiven(): void
    {
        self::assertNull(new Message('m')->get('id'));
    }

    public function testHasReflectsPresence(): void
    {
        $message = new Message('m', ['id' => 42]);

        self::assertTrue($message->has('id'));
        self::assertFalse($message->has('missing'));
    }

    public function testHasIsTrueForANullPayloadValue(): void
    {
        self::assertTrue(new Message('m', ['id' => null])->has('id'));
    }
}
