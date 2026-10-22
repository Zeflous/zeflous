<?php

declare(strict_types=1);

namespace Zef\Test\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Http\EtagCandidates;

/**
 * @internal
 */
#[CoversClass(EtagCandidates::class)]
final class EtagCandidatesTest extends TestCase
{
    public function testABareListSplitsOnEveryComma(): void
    {
        self::assertSame(['"a"', ' "b"', '"c"'], EtagCandidates::split('"a", "b","c"'));
    }

    /**
     * RFC 9110 §8.8.3: `etagc` admits 0x2C, so a comma inside a quoted
     * opaque validator is part of the validator, never a separator.
     */
    public function testACommaInsideAQuotedValidatorIsNotASeparator(): void
    {
        self::assertSame(['"a,b"', ' "c"'], EtagCandidates::split('"a,b", "c"'));
        self::assertSame(['W/"x,y"'], EtagCandidates::split('W/"x,y"'));
    }

    public function testEveryDQuoteTogglesTheQuotingState(): void
    {
        self::assertSame(['"a"', '"b"', ' "c"'], EtagCandidates::split('"a","b", "c"'));
    }

    /**
     * Byte offsets, not character counts: a header may carry raw
     * obs-text bytes that are not valid UTF-8, and the walk must reach
     * the final byte.
     */
    public function testRawObsTextBytesWalkToTheFinalByte(): void
    {
        $value = "\"\xB1\xAF\", \"b\"";

        self::assertSame(['"' . "\xB1\xAF" . '"', ' "b"'], EtagCandidates::split($value));
    }

    public function testASingleCandidateAnswersAOneEntryList(): void
    {
        self::assertSame(['"a"'], EtagCandidates::split('"a"'));
    }

    public function testAnEmptyValueAnswersTheEmptySingleCandidate(): void
    {
        self::assertSame([''], EtagCandidates::split(''));
    }

    public function testATrailingSeparatorAnswersTheEmptyTrailingCandidate(): void
    {
        self::assertSame(['"a"', ''], EtagCandidates::split('"a",'));
    }
}
