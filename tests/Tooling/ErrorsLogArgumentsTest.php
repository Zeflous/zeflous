<?php

declare(strict_types=1);

namespace Zef\Test\Tooling;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Test\Support\Tooling\ErrorsDisplayText;
use Zef\Test\Support\Tooling\ErrorsLogArguments;
use Zef\Test\Support\Tooling\ToolingException;

/**
 * The errors family's small folds: the shared argument parse and the
 * display-text discipline, each pinned over the grammar they own.
 *
 * @internal
 */
#[CoversClass(ErrorsLogArguments::class)]
#[CoversClass(ErrorsDisplayText::class)]
final class ErrorsLogArgumentsTest extends TestCase
{
    public function testNoArgumentsAnswerNoFilterAndNoPositionalPath(): void
    {
        self::assertSame([null, null], ErrorsLogArguments::parsed([]));
    }

    public function testAValidRequestIdFilterIsAnswered(): void
    {
        self::assertSame(['req-a', null], ErrorsLogArguments::parsed(['--request-id=req-a']));
    }

    public function testTheFullFilterCorridorIsAnswered(): void
    {
        self::assertSame(
            ['abcDEF012._:-', null],
            ErrorsLogArguments::parsed(['--request-id=abcDEF012._:-']),
        );
    }

    public function testAnEmptyRequestIdFilterIsAUsageDefect(): void
    {
        $this->expectException(ToolingException::class);
        $this->expectExceptionMessageIsOrContains('Invalid request ID filter.');

        ErrorsLogArguments::parsed(['--request-id=']);
    }

    public function testAnOffCorridorRequestIdFilterIsAUsageDefect(): void
    {
        $this->expectException(ToolingException::class);
        $this->expectExceptionMessageIsOrContains('Invalid request ID filter.');

        ErrorsLogArguments::parsed(['--request-id=not valid!']);
    }

    public function testAnOverlongRequestIdFilterIsAUsageDefect(): void
    {
        $this->expectException(ToolingException::class);

        ErrorsLogArguments::parsed(['--request-id=' . str_repeat('a', 129)]);
    }

    public function testTheConfirmMarkerIsNeverTakenForThePositionalPath(): void
    {
        self::assertSame([null, null], ErrorsLogArguments::parsed(['--confirm']));
    }

    public function testAPositionalPathIsAnswered(): void
    {
        self::assertSame([null, '/tmp/zef-errors.jsonl'], ErrorsLogArguments::parsed(['/tmp/zef-errors.jsonl']));
    }

    public function testTheLastPositionalPathWins(): void
    {
        self::assertSame(
            [null, '/second.jsonl'],
            ErrorsLogArguments::parsed(['/first.jsonl', '/second.jsonl']),
        );
    }

    public function testTheFilterAndThePositionalPathRideTogether(): void
    {
        self::assertSame(
            ['req-b', '/tmp/log.jsonl'],
            ErrorsLogArguments::parsed(['/tmp/log.jsonl', '--request-id=req-b']),
        );
    }

    public function testATrailingNewlineInTheFilterIsAUsageDefect(): void
    {
        $this->expectException(ToolingException::class);
        $this->expectExceptionMessageIsOrContains('Invalid request ID filter.');

        ErrorsLogArguments::parsed(["--request-id=ab\n"]);
    }

    public function testTheFilterRidesBeforeThePositionalPath(): void
    {
        self::assertSame(
            ['req-b', '/tmp/log.jsonl'],
            ErrorsLogArguments::parsed(['--request-id=req-b', '/tmp/log.jsonl']),
        );
    }

    public function testTheConfirmMarkerRidesBeforeThePositionalPath(): void
    {
        self::assertSame(
            [null, '/tmp/log.jsonl'],
            ErrorsLogArguments::parsed(['--confirm', '/tmp/log.jsonl']),
        );
    }

    public function testDisplayTextStripsControlBytes(): void
    {
        self::assertSame('er[2Jror', ErrorsDisplayText::of("er\x1b[2Jror"));
        self::assertSame('error', ErrorsDisplayText::of("e\x01rror"));
    }

    public function testDisplayTextStripsCarriageReturnsAndBells(): void
    {
        self::assertSame('ab]0;pwned', ErrorsDisplayText::of("a\r\nb\x1b]0;pwned\x07"));
    }

    public function testDisplayTextAnswersUnknownWhenNothingDisplayableRemains(): void
    {
        self::assertSame('unknown', ErrorsDisplayText::of("\x1b\x07"));
    }

    public function testDisplayTextKeepsThePlainSpellingVerbatim(): void
    {
        self::assertSame('ZEF-DB-42', ErrorsDisplayText::of('ZEF-DB-42'));
    }
}
