<?php

declare(strict_types=1);

namespace Zef\Test\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Http\HeaderGrammar;
use Zef\Framework\Http\WorkerResponseHeaders;

/**
 * The worker answer's header map, on its own terms: the outbound fold
 * that re-judges a foreign response's fields and skips the
 * unacceptable ones -- never a refusal, never a coercion, the
 * well-formed neighbours always surviving.
 *
 * @internal
 */
#[CoversClass(WorkerResponseHeaders::class)]
#[CoversClass(HeaderGrammar::class)]
final class WorkerResponseHeadersTest extends TestCase
{
    public function testABareStringMemberAnswersTheSingleValueList(): void
    {
        self::assertSame(
            ['content-type' => ['application/json']],
            WorkerResponseHeaders::acceptable(['Content-Type' => 'application/json']),
            'a bare string value answers the one-value list the canonical form carries',
        );
    }

    public function testAListSpellingStaysTheListItCarries(): void
    {
        self::assertSame(
            ['accept' => ['text/html', 'application/json']],
            WorkerResponseHeaders::acceptable(['Accept' => ['text/html', 'application/json']]),
            'the list spelling is already the canonical value form',
        );
    }

    public function testAnIntegerKeyFoldsToItsDecimalName(): void
    {
        self::assertSame(
            ['123' => ['ok']],
            WorkerResponseHeaders::acceptable([123 => 'ok']),
            'a numeric name is a legal token the grammar accepts, decimal-spelled',
        );
    }

    public function testAnEmptyMapAnswersTheEmptyCanonicalForm(): void
    {
        self::assertSame([], WorkerResponseHeaders::acceptable([]), 'no headers is no headers');
    }

    public function testTwoSpellingsOfOneNameMergeInEncounterOrder(): void
    {
        self::assertSame(
            ['x-double' => ['first', 'second']],
            WorkerResponseHeaders::acceptable(['X-Double' => 'first', 'x-double' => 'second']),
            'the map form of two header lines of one field carries both values, in order',
        );
    }

    public function testANameOutsideTheTokenGrammarIsSkippedNotRefused(): void
    {
        self::assertSame(
            ['x-good' => ['ok']],
            WorkerResponseHeaders::acceptable(['bad name' => 'v', 'X-Good' => 'ok']),
            'the unacceptable field is skipped and the well-formed neighbour survives',
        );
    }

    public function testANonStringMemberIsDroppedInsteadOfCoerced(): void
    {
        self::assertSame(
            [],
            WorkerResponseHeaders::acceptable(['X-Count' => 5, 'X-Null' => [null]]),
            'a non-string member drops exactly like an invalid name or value',
        );
    }

    public function testAControlByteInTheMemberIsSkippedAndItsSiblingsSurvive(): void
    {
        self::assertSame(
            ['x-multi' => ['second']],
            WorkerResponseHeaders::acceptable(['X-Multi' => ["first\nbad", 'second']]),
            'only the unacceptable member of the listed header is skipped',
        );
    }

    public function testTheNameFoldsThroughTheFullUnicodeCaseMap(): void
    {
        self::assertSame(
            ['key' => ['v']],
            WorkerResponseHeaders::acceptable(["\u{212A}EY" => 'v']),
            'U+212A KELVIN SIGN lower-cases to the ASCII k under the Unicode case map; '
            . 'a byte-wise fold would leave the raw bytes behind and the name grammar would skip them',
        );
    }
}
