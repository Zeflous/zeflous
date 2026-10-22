<?php

declare(strict_types=1);

namespace Zef\Test\Tooling;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Test\Support\Tooling\ReviewThreadPageInfo;
use Zef\Test\Support\Tooling\ToolingException;

/**
 * The pagination answer's own battery: the continuation pair a
 * well-shaped page carries, and the refusal every malformed shape
 * answers -- a pageInfo whose cursor is not a string and whose
 * hasNextPage is a real boolean, a payload that does not decode to a
 * map at all, and a walk that loses a level. A silently missing page
 * answer would let the resolver believe a blocked pull request has
 * nothing left beyond its first page, so every miss refuses.
 *
 * @internal
 */
#[CoversClass(ReviewThreadPageInfo::class)]
final class ReviewThreadPageInfoTest extends TestCase
{
    private const string VALID_PAGE = '{"data":{"repository":{"pullRequest":{"reviewThreads":{"pageInfo":'
        . '{"hasNextPage":true,"endCursor":"cursor-2"}}}}}}';

    public function testAWellShapedPageAnswersItsContinuationPair(): void
    {
        $page = new ReviewThreadPageInfo(self::VALID_PAGE)->page();

        self::assertSame(['hasNextPage' => true, 'endCursor' => 'cursor-2'], $page);
    }

    public function testAFinalPageAnswersItsNullCursor(): void
    {
        $payload = '{"data":{"repository":{"pullRequest":{"reviewThreads":{"pageInfo":'
            . '{"hasNextPage":false,"endCursor":null}}}}}}';

        self::assertSame(['hasNextPage' => false, 'endCursor' => null], new ReviewThreadPageInfo($payload)->page());
    }

    /**
     * The shape census reads the cursor as a whole: a boolean
     * hasNextPage with an integer cursor is not a page answer, and the
     * walk refuses it -- a shape that only checks "a cursor exists"
     * would let an integer cursor ride the walk and the resolver would
     * loop on a cursor that names no page.
     */
    public function testAnIntegerCursorRefusesEvenWithABooleanHasNextPage(): void
    {
        $payload = '{"data":{"repository":{"pullRequest":{"reviewThreads":{"pageInfo":'
            . '{"hasNextPage":true,"endCursor":5}}}}}}';

        try {
            new ReviewThreadPageInfo($payload)->page();
            self::fail('An integer cursor is not a page answer and must refuse.');
        } catch (ToolingException $toolingException) {
            self::assertSame(
                'Review-thread payload is missing reviewThreads.pageInfo.',
                $toolingException->getMessage(),
            );
        }
    }

    public function testAStringHasNextPageRefuses(): void
    {
        $payload = '{"data":{"repository":{"pullRequest":{"reviewThreads":{"pageInfo":'
            . '{"hasNextPage":"yes","endCursor":"cursor-2"}}}}}}';

        $this->expectException(ToolingException::class);

        new ReviewThreadPageInfo($payload)->page();
    }

    public function testAPayloadThatDoesNotDecodeToAMapRefuses(): void
    {
        $reviewThreadPageInfo = new ReviewThreadPageInfo('"a plain string"');

        try {
            $reviewThreadPageInfo->page();
            self::fail('A payload that does not decode to a map must refuse.');
        } catch (ToolingException $toolingException) {
            self::assertSame(
                'Review-thread payload is missing reviewThreads.pageInfo.',
                $toolingException->getMessage(),
            );
        }
    }

    public function testAPayloadThatLosesAWalkLevelRefuses(): void
    {
        $payload = '{"data":{"repository":{"reviewThreads":{"pageInfo":'
            . '{"hasNextPage":false,"endCursor":null}}}}}';

        $this->expectException(ToolingException::class);

        new ReviewThreadPageInfo($payload)->page();
    }
}
