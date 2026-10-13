<?php

declare(strict_types=1);

namespace Zef\Test\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Http\IfNoneMatch;

/**
 * @internal
 */
#[CoversClass(IfNoneMatch::class)]
final class IfNoneMatchTest extends TestCase
{
    public function testTheStarFormMatchesAnyValidator(): void
    {
        self::assertTrue(IfNoneMatch::matches('*', '"anything"'));
    }

    public function testAStrongCandidateMatchesItsValidator(): void
    {
        self::assertTrue(IfNoneMatch::matches('"a"', '"a"'));
    }

    public function testANonMatchingCandidateDoesNotMatch(): void
    {
        self::assertFalse(IfNoneMatch::matches('"b"', '"a"'));
    }

    /**
     * Weak comparison (RFC 9110 §13.1.2): the weakness marker and its
     * casing are not part of the comparison, on either side.
     */
    public function testAWeakCandidateMatchesAStrongValidator(): void
    {
        self::assertTrue(IfNoneMatch::matches('W/"a"', '"a"'));
        self::assertTrue(IfNoneMatch::matches('w/"a"', '"a"'));
        self::assertTrue(IfNoneMatch::matches('W/"a"', 'W/"a"'));
    }

    public function testSurroundingWhitespaceIsNotPartOfTheComparison(): void
    {
        self::assertTrue(IfNoneMatch::matches('  "a"  ', '"a"'));
        self::assertTrue(IfNoneMatch::matches('  "a"  ', '  "a"  '));
    }

    public function testAnyListMemberMayMatch(): void
    {
        self::assertTrue(IfNoneMatch::matches('"zzz", "a"', '"a"'));
        self::assertTrue(IfNoneMatch::matches('"zzz", W/"a", "q"', '"a"'));
    }

    public function testEmptyListMembersAreSkipped(): void
    {
        self::assertTrue(IfNoneMatch::matches(',  , "a" ,', '"a"'));
    }

    public function testTheWeaknessStripRemovesExactlyTheTwoMarkerCharacters(): void
    {
        self::assertTrue(IfNoneMatch::matches('W/abc', 'abc'));
        self::assertFalse(IfNoneMatch::matches('W/bc', 'abc'));
    }

    /**
     * The weakness strip folds through mbstring's character semantics: a
     * validator body of raw obs-text bytes that is not valid UTF-8 does
     * not survive the fold (mbstring substitutes it), so it cannot match
     * even itself -- while a valid multi-byte validator matches fine.
     * RFC 9110 §8.8.3 allows obs-text bytes in an opaque validator.
     */
    public function testTheWeakStripFoldsThroughCharacterSemantics(): void
    {
        self::assertTrue(IfNoneMatch::matches('W/étag', 'étag'));
        self::assertFalse(IfNoneMatch::matches("W/\xA9tag", "\xA9tag"));
    }

    public function testAnEmptyValueHasNoMatchingCandidate(): void
    {
        self::assertFalse(IfNoneMatch::matches('', '"a"'));
    }

    public function testTheStarFormMatchesWithoutAComputedValidator(): void
    {
        self::assertTrue(IfNoneMatch::matches('*', '"a"'));
    }
}
