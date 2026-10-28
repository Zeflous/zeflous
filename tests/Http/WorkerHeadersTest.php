<?php

declare(strict_types=1);

namespace Zef\Test\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Http\HeaderGrammar;
use Zef\Framework\Http\HttpException;
use Zef\Framework\Http\WorkerHeaders;

/**
 * The worker header map's canonical form, on its own terms: the case
 * fold, the integer-key cast, the grammar's own judgments -- and the
 * collision the map form can carry that the SAPI twin never sees.
 *
 * @internal
 */
#[CoversClass(WorkerHeaders::class)]
#[CoversClass(HeaderGrammar::class)]
final class WorkerHeadersTest extends TestCase
{
    public function testAStringValuesSpellingBecomesTheSingleValueList(): void
    {
        self::assertSame(
            ['content-type' => ['application/json']],
            WorkerHeaders::normalize(['Content-Type' => 'application/json']),
            'a bare string value answers the one-value list the canonical form carries',
        );
    }

    public function testAListValuesSpellingStaysTheListItCarries(): void
    {
        self::assertSame(
            ['accept' => ['text/html', 'application/json']],
            WorkerHeaders::normalize(['Accept' => ['text/html', 'application/json']]),
            'the list spelling is already the canonical value form',
        );
    }

    public function testAnIntegerKeyFoldsToItsDecimalName(): void
    {
        self::assertSame(
            ['123' => ['ok']],
            WorkerHeaders::normalize([123 => 'ok']),
            'a numeric name is a legal token the grammar accepts, decimal-spelled',
        );
    }

    public function testAnEmptyMapAnswersTheEmptyCanonicalForm(): void
    {
        self::assertSame([], WorkerHeaders::normalize([]), 'no headers is no headers');
    }

    public function testTwoSpellingsOfOneNameMergeInEncounterOrder(): void
    {
        self::assertSame(
            ['x-double' => ['first', 'second']],
            WorkerHeaders::normalize(['X-Double' => 'first', 'x-double' => 'second']),
            'the map form of two header lines of one field carries both values, in order',
        );
    }

    #[DataProvider('provideANameOutsideTheTokenGrammarIsRefusedCases')]
    public function testANameOutsideTheTokenGrammarIsRefused(string $name): void
    {
        $this->expectException(HttpException::class);

        WorkerHeaders::normalize([$name => 'value']);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideANameOutsideTheTokenGrammarIsRefusedCases(): iterable
    {
        yield 'a space in the name' => ['bad name'];

        yield 'an empty name' => [''];
    }

    public function testANonStringValueIsRejectedRatherThanCoerced(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionMessageIsOrContains('Header value must be a string.');

        WorkerHeaders::normalize(['Content-Length' => 2048]);
    }

    public function testANonStringListMemberIsRejected(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionMessageIsOrContains('Header value must be a string.');

        WorkerHeaders::normalize(['Accept' => ['text/html', 42]]);
    }

    public function testAControlByteInTheValueIsRefused(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionMessageIsOrContains('Invalid header value');

        WorkerHeaders::normalize(['X-Injected' => "value\r\nSet-Cookie: stolen=1"]);
    }

    public function testTheNameFoldsThroughTheFullUnicodeCaseMap(): void
    {
        $headers = WorkerHeaders::normalize(["\u{212A}EY" => 'v']);

        self::assertSame(
            ['key' => ['v']],
            $headers,
            'U+212A KELVIN SIGN lower-cases to the ASCII k under the Unicode case map; '
            . 'a byte-wise fold would leave the raw bytes behind and the name grammar would reject them',
        );
    }

    public function testTheValuesLookupAnswersTheWireOrderList(): void
    {
        $headers = WorkerHeaders::normalize(['Cookie' => 'a=1', 'cookie' => 'b=2']);

        self::assertSame(
            ['a=1', 'b=2'],
            WorkerHeaders::values($headers, 'cookie'),
            'the lookup answers the merged wire order under the canonical name',
        );
    }

    public function testTheValuesLookupAnswersEmptyForAnAbsentName(): void
    {
        self::assertSame(
            [],
            WorkerHeaders::values(WorkerHeaders::normalize([]), 'cookie'),
            'an absent name answers no values, never an error',
        );
    }

    public function testTheValuesLookupAnswersUnderAnySpellingTheCallerHolds(): void
    {
        $headers = WorkerHeaders::normalize(['HOST' => 'api.example.com']);

        self::assertSame(
            ['api.example.com'],
            WorkerHeaders::values($headers, 'host'),
            'the canonical map is keyed lowercase, so the lookup never re-folds',
        );
    }
}
