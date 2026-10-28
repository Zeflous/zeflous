<?php

declare(strict_types=1);

namespace Zef\Test\Adapters\Redis;

use InvalidArgumentException;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Adapters\Redis\RespReplyListShape;
use Zef\Framework\Adapters\Redis\RespReplyMultiReadShape;
use Zef\Framework\Adapters\Redis\RespReplyRefusal;

/**
 * Pins the range reply narrowing: the range commands the array of
 * bulk strings -- each happy arm and each refusal arm, both shapes
 * named on every refusal. The multi-read spelling's own narrowing
 * and the scalar spellings' own narrowing are their own folds' own
 * tests.
 *
 * @internal
 */
#[CoversClass(RespReplyListShape::class)]
#[CoversClass(RespReplyMultiReadShape::class)]
#[CoversClass(RespReplyRefusal::class)]
final class RespReplyListShapeTest extends TestCase
{
    private RespReplyListShape $respReplyListShape;

    #[Override]
    protected function setUp(): void
    {
        $this->respReplyListShape = new RespReplyListShape();
    }

    public function testABulkListReplyIsNarrowedToItsStrings(): void
    {
        self::assertSame(
            ['e1', 'e2'],
            $this->respReplyListShape->bulkList(['e1', 'e2'], 'ZRANGEBYSCORE'),
        );
    }

    public function testAnEmptyBulkListIsTheEmptyRange(): void
    {
        self::assertSame([], $this->respReplyListShape->bulkList([], 'ZRANGEBYSCORE'));
    }

    public function testABulkListWithANonStringElementIsRefused(): void
    {
        try {
            $this->respReplyListShape->bulkList(['e1', null], 'ZRANGEBYSCORE');
            self::fail('The null element was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                "The Redis reply for 'ZRANGEBYSCORE' is refused: the command answers an array of bulk"
                . ' strings, the reply decoded into an array of 2 elements.',
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testANonArrayRangeReplyIsRefused(): void
    {
        try {
            $this->respReplyListShape->bulkList('e1', 'ZRANGEBYSCORE');
            self::fail('The string range reply was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                "The Redis reply for 'ZRANGEBYSCORE' is refused: the command answers an array of bulk"
                . " strings, the reply decoded into the string 'e1'.",
                $invalidArgumentException->getMessage(),
            );
        }
    }
}
