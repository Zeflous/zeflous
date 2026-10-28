<?php

declare(strict_types=1);

namespace Zef\Test\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Http\WorkerCookies;

/**
 * The cookie bag fold, on its own terms: the reference's own pair
 * grammar, the shadow order a repeated name resolves, and the
 * nameless pairs that contribute nothing.
 *
 * @internal
 */
#[CoversClass(WorkerCookies::class)]
final class WorkerCookiesTest extends TestCase
{
    public function testThePairsSplitAndDecode(): void
    {
        self::assertSame(
            ['session' => 'abc', 'theme' => 'dark'],
            WorkerCookies::bag(['session=abc; theme=dark']),
            "one Cookie value's pairs land as the engine's own bag build would fill them",
        );
    }

    public function testAValueCarryingItsOwnEqualsSurvivesTheFirstSplit(): void
    {
        self::assertSame(
            ['token' => 'b=c'],
            WorkerCookies::bag(['token=b=c']),
            'the pair splits on the first = only -- values may themselves carry =',
        );
    }

    public function testABareTokenAnswersTheEmptyStringValue(): void
    {
        self::assertSame(
            ['flag' => ''],
            WorkerCookies::bag(['flag']),
            'a valueless cookie is distinct from an absent one',
        );
    }

    public function testWhitespaceAroundThePairAndValueIsTrimmed(): void
    {
        self::assertSame(
            ['session' => 'abc'],
            WorkerCookies::bag(['  session= abc  ']),
            "the reference's own trims: the pair before the split, the value after",
        );
    }

    public function testTheNameKeepsItsOwnBytes(): void
    {
        $bag = WorkerCookies::bag(['a b = 1']);

        self::assertSame(
            ['a b ' => '1'],
            $bag,
            'the bag never invents a cleaner name than the sender spelled '
            . '-- the bytes before the separator ride verbatim',
        );
    }

    public function testARepeatedNameAnswersItsLastOccurrence(): void
    {
        self::assertSame(
            ['session' => 'fresh'],
            WorkerCookies::bag(['session=stale; session=fresh']),
            "the fresh value shadows the stale one, the wire's own left-to-right order",
        );
    }

    public function testNamelessPairsContributeNothing(): void
    {
        self::assertSame(
            ['session' => 'abc'],
            WorkerCookies::bag(['; session=abc ;']),
            'the empty pair is not a cookie -- the trailing separators describe none',
        );
    }

    public function testANamelessValuedPairIsSkipped(): void
    {
        self::assertSame(
            [],
            WorkerCookies::bag(['=orphan']),
            'a pair whose name is empty describes no cookie',
        );
    }

    public function testMultipleCookieValuesMergeInWireOrder(): void
    {
        self::assertSame(
            ['session' => 'abc', 'theme' => 'dark'],
            WorkerCookies::bag(['session=abc', 'theme=dark']),
            'several Cookie values merge pair-wise, never through a comma join',
        );
    }

    public function testARepeatedNameAcrossValuesStillAnswersTheLast(): void
    {
        self::assertSame(
            ['session' => 'fresh'],
            WorkerCookies::bag(['session=stale', 'session=fresh']),
            'the shadow order holds across values exactly as it holds within one',
        );
    }

    public function testAnEmptyValueListAnswersTheEmptyBag(): void
    {
        self::assertSame(
            [],
            WorkerCookies::bag([]),
            'no Cookie header values means no cookies',
        );
    }

    public function testAnEmptyCookieHeaderValueAnswersTheEmptyBag(): void
    {
        self::assertSame(
            [],
            WorkerCookies::bag(['']),
            'an empty Cookie header carries no pairs',
        );
    }
}
