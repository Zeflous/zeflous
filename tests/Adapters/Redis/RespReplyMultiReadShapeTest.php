<?php

declare(strict_types=1);

namespace Zef\Test\Adapters\Redis;

use InvalidArgumentException;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Adapters\Redis\RespReplyMultiReadShape;
use Zef\Framework\Adapters\Redis\RespReplyRefusal;

/**
 * Pins the multi-read reply narrowing: one element per key asked,
 * each a bulk string or the null bulk -- each happy arm and each
 * refusal arm, both shapes named on every refusal. The range and
 * scalar spellings' own narrowing are their own folds' own tests.
 *
 * @internal
 */
#[CoversClass(RespReplyMultiReadShape::class)]
#[CoversClass(RespReplyRefusal::class)]
final class RespReplyMultiReadShapeTest extends TestCase
{
    private RespReplyMultiReadShape $respReplyMultiReadShape;

    #[Override]
    protected function setUp(): void
    {
        $this->respReplyMultiReadShape = new RespReplyMultiReadShape();
    }

    public function testABulkOrNullListReplyIsNarrowedToItsElements(): void
    {
        self::assertSame(
            ['record', null],
            $this->respReplyMultiReadShape->bulkOrNullList(['record', null], 'MGET'),
        );
    }

    public function testABulkOrNullListWithAnIntegerElementIsRefused(): void
    {
        try {
            $this->respReplyMultiReadShape->bulkOrNullList(['record', 4], 'MGET');
            self::fail('The integer element was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                "The Redis reply for 'MGET' is refused: the command answers an array of bulk strings and"
                . ' null bulks, the reply decoded into an array of 2 elements.',
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testANullMultiReadReplyIsRefused(): void
    {
        try {
            $this->respReplyMultiReadShape->bulkOrNullList(null, 'MGET');
            self::fail('The null multi-read reply was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                "The Redis reply for 'MGET' is refused: the command answers an array of bulk strings and"
                . ' null bulks, the reply decoded into the null bulk.',
                $invalidArgumentException->getMessage(),
            );
        }
    }
}
