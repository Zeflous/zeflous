<?php

declare(strict_types=1);

namespace Zef\Test\Adapters\Redis;

use InvalidArgumentException;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Adapters\Redis\RespReplyRefusal;
use Zef\Framework\Adapters\Redis\RespReplyShape;

/**
 * Pins the scalar reply narrowing: every command's answer is
 * exactly one shape of the reply grammar, and a reply that decodes
 * into the wrong shape is refused with both shapes named -- the
 * status, the integer, and the bulk with its null spelling, each
 * happy arm and each refusal arm. The array spellings' own narrowing
 * is the list shape fold's own test.
 *
 * @internal
 */
#[CoversClass(RespReplyRefusal::class)]
#[CoversClass(RespReplyShape::class)]
final class RespReplyShapeTest extends TestCase
{
    private RespReplyShape $respReplyShape;

    #[Override]
    protected function setUp(): void
    {
        $this->respReplyShape = new RespReplyShape();
    }

    public function testAStatusReplyPassesItsGuardAndAWrongShapeIsRefused(): void
    {
        $this->respReplyShape->status('OK', 'SET');

        try {
            $this->respReplyShape->status(1, 'SET');
            self::fail('The integer status reply was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                "The Redis reply for 'SET' is refused: the command answers a status string,"
                . ' the reply decoded into the integer 1.',
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testAnIntegerReplyIsNarrowedToItsInteger(): void
    {
        self::assertSame(3, $this->respReplyShape->integer(3, 'ZCARD'));
    }

    public function testANonIntegerCounterReplyIsRefusedWithBothShapesNamed(): void
    {
        try {
            $this->respReplyShape->integer('OK', 'ZCARD');
            self::fail('The string counter reply was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                "The Redis reply for 'ZCARD' is refused: the command answers an integer,"
                . " the reply decoded into the string 'OK'.",
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testABulkReplyIsNarrowedToItsString(): void
    {
        self::assertSame('record', $this->respReplyShape->bulk('record', 'GET'));
    }

    public function testTheNullBulkIsNarrowedToNull(): void
    {
        self::assertNull($this->respReplyShape->bulk(null, 'GET'));
    }

    public function testAnIntegerBulkReplyIsRefused(): void
    {
        try {
            $this->respReplyShape->bulk(7, 'GET');
            self::fail('The integer bulk reply was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                "The Redis reply for 'GET' is refused: the command answers a bulk string or the null"
                . ' bulk, the reply decoded into the integer 7.',
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testAReplyOfATypeTheGrammarNeverAnswersIsNamedByItsOwnType(): void
    {
        try {
            $this->respReplyShape->status(true, 'SET');
            self::fail('The boolean status reply was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                "The Redis reply for 'SET' is refused: the command answers a status string,"
                . ' the reply decoded into bool.',
                $invalidArgumentException->getMessage(),
            );
        }
    }
}
