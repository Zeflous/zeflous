<?php

declare(strict_types=1);

namespace Zef\Test\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Http\WorkerQuery;

/**
 * The query bag fold, on its own terms: the engine's own decode, the
 * numeric-key fold the superglobal boundary already claims, and the
 * absence an empty query component answers.
 *
 * @internal
 */
#[CoversClass(WorkerQuery::class)]
final class WorkerQueryTest extends TestCase
{
    public function testAnEmptyQueryComponentAnswersTheEmptyBag(): void
    {
        self::assertSame(
            [],
            WorkerQuery::bag(''),
            'absence answers absence, never a bag with a decoded empty string',
        );
    }

    public function testThePairsDecodeIntoTheBag(): void
    {
        self::assertSame(
            ['page' => '2', 'sort' => 'name'],
            WorkerQuery::bag('page=2&sort=name'),
            "the plain pairs land as the SAPI's own decode would fill them",
        );
    }

    public function testTheNumericKeyFoldsToItsEngineForm(): void
    {
        $bag = WorkerQuery::bag('0=first&1=second');

        self::assertSame(
            [0 => 'first', 1 => 'second'],
            $bag,
            'the KDD-H10 claim replayed: the numeric-string key is the integer key it folds into',
        );
    }

    public function testBracketedNamesNestIntoArrays(): void
    {
        self::assertSame(
            ['filter' => ['status' => 'active', 'tag' => ['a', 'b']]],
            WorkerQuery::bag('filter[status]=active&filter[tag][]=a&filter[tag][]=b'),
            "the engine's nesting decode is the bag the SAPI twin would carry",
        );
    }

    public function testPercentEncodedValuesDecode(): void
    {
        self::assertSame(
            ['q' => 'zef framework', 'next' => '/users?page=3'],
            WorkerQuery::bag('q=zef+framework&next=%2Fusers%3Fpage%3D3'),
            "the wire's encoding decodes exactly once",
        );
    }

    public function testABareTokenAnswersTheEmptyStringValue(): void
    {
        self::assertSame(
            ['flag' => ''],
            WorkerQuery::bag('flag'),
            "the engine's own reading of a valueless pair",
        );
    }
}
