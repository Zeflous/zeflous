<?php

declare(strict_types=1);

namespace Zef\Test\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Http\HealthContentNegotiator;

/**
 * Pins the health surface's own negotiation arithmetic: which media
 * ranges count for the exposition side, which count for the JSON side,
 * the universal wildcard both fold to, and the strict comparison that
 * separates a tie from a preference. The metrics middleware composes
 * this answer for every health poll; this battery pins the arithmetic
 * itself -- the default the JSON stays, the coalescing order that
 * keeps a specific spelling's quality above its own wildcard's, and
 * the greater-than a tie must fail.
 *
 * @internal
 */
#[CoversClass(HealthContentNegotiator::class)]
final class HealthContentNegotiatorTest extends TestCase
{
    public function testAnEmptyAcceptLineIsNoPreference(): void
    {
        self::assertFalse(HealthContentNegotiator::prefersExposition(''));
    }

    public function testADirectTextPlainRangeIsAPreference(): void
    {
        self::assertTrue(HealthContentNegotiator::prefersExposition('text/plain'));
    }

    /**
     * The exposition side's own wildcard must count: a header that
     * names text/* alone still prefers the gauges over the JSON, the
     * same wildcard read the error negotiator's HTML side carries.
     */
    public function testTheTextWildcardAloneIsAPreference(): void
    {
        self::assertTrue(HealthContentNegotiator::prefersExposition('text/*'));
    }

    public function testTheJsonVocabularyAloneIsNotAPreference(): void
    {
        self::assertFalse(HealthContentNegotiator::prefersExposition('application/json'));
        self::assertFalse(HealthContentNegotiator::prefersExposition('application/*'));
        self::assertFalse(HealthContentNegotiator::prefersExposition('application/problem+json'));
    }

    /**
     * The load balancer's poll: a header that accepts anything carries
     * no preference at all, because the universal wildcard is the
     * shared floor both sides fold to -- 1.0 against 1.0 is a tie, and
     * the tie answers the default representation.
     */
    public function testTheUniversalWildcardAloneIsATieNotAPreference(): void
    {
        self::assertFalse(HealthContentNegotiator::prefersExposition('*/*'));
    }

    /**
     * The collector's own Accept line, verbatim: the text spelling at
     * 0.5 outranks the universal floor at 0.1, so a Prometheus
     * scraping the health surface reads the gauges.
     */
    public function testThePrometheusAcceptLineIsAPreference(): void
    {
        self::assertTrue(
            HealthContentNegotiator::prefersExposition(
                'text/plain;version=0.0.4;q=0.5,*/*;q=0.1',
            ),
        );
    }

    public function testAMediaTypeTheParserSkipsCarriesNoVoice(): void
    {
        self::assertFalse(HealthContentNegotiator::prefersExposition('!!!!'));
        self::assertFalse(HealthContentNegotiator::prefersExposition(',garbage,'));
        self::assertFalse(HealthContentNegotiator::prefersExposition('text/plain-please'));
    }

    public function testTheCaseFoldReadsBothSpellings(): void
    {
        self::assertTrue(HealthContentNegotiator::prefersExposition('TEXT/PLAIN'));
        self::assertFalse(HealthContentNegotiator::prefersExposition('APPLICATION/JSON'));
    }

    public function testATieAnswersTheDefault(): void
    {
        self::assertFalse(
            HealthContentNegotiator::prefersExposition('text/plain, application/json'),
        );
    }

    public function testTheHigherExpositionQualityWins(): void
    {
        self::assertTrue(
            HealthContentNegotiator::prefersExposition('text/plain;q=0.9, application/json;q=0.8'),
        );
        self::assertFalse(
            HealthContentNegotiator::prefersExposition('text/plain;q=0.8, application/json;q=0.9'),
        );
    }

    /**
     * A spelling refused outright (q=0) is a quality, not an absence:
     * the text side reads zero, the tie answers the JSON.
     */
    public function testARefusedTextRangeIsNotAPreference(): void
    {
        self::assertFalse(HealthContentNegotiator::prefersExposition('text/plain;q=0'));
    }

    /**
     * The specific spelling's own quality decides its side, never the
     * wildcard beneath it (RFC 7231 specificity): a text/plain spelled
     * q=0.3 stays 0.3 under a text/* spelled q=0.9 -- against a JSON
     * vocabulary between the two, the specific reading is the verdict
     * (the coalesce order the mutation round reads directly).
     */
    public function testTheSpecificTextRangeBeatsItsOwnWildcard(): void
    {
        self::assertTrue(
            HealthContentNegotiator::prefersExposition('text/plain;q=0.3, text/*;q=0.9'),
        );
        self::assertFalse(
            HealthContentNegotiator::prefersExposition(
                'text/plain;q=0.3, text/*;q=0.9, application/json;q=0.5',
            ),
        );
    }

    /**
     * The JSON side's own specificity, the mirror pin: an
     * application/json spelled q=0.5 stays 0.5 under an application/*
     * spelled q=0.9, so the text spelling between the two decides
     * against the wildcard's larger grant.
     */
    public function testTheSpecificJsonRangeBeatsItsOwnWildcard(): void
    {
        self::assertTrue(
            HealthContentNegotiator::prefersExposition(
                'text/plain;q=0.7, application/json;q=0.5, application/*;q=0.9',
            ),
        );
    }

    /**
     * The universal floor outranking the text spelling is the client
     * preferring everything else over the gauges: the JSON answers,
     * whatever the anything-but-text fold grants it.
     */
    public function testAUniversalFloorAboveTheTextRangeAnswersTheDefault(): void
    {
        self::assertFalse(
            HealthContentNegotiator::prefersExposition('text/plain;q=0.3, */*;q=0.9'),
        );
        self::assertTrue(
            HealthContentNegotiator::prefersExposition('text/plain;q=0.9, */*;q=0.3'),
        );
    }

    /**
     * The mirror fold: a universal floor outranking the JSON
     * vocabulary is the client preferring everything over JSON, and
     * the gauges are a member of everything -- the arithmetic answers
     * them, the client's own ranking the only voice.
     */
    public function testAUniversalFloorAboveTheJsonVocabularyAnswersTheGauges(): void
    {
        self::assertTrue(
            HealthContentNegotiator::prefersExposition('application/json;q=0.5, */*;q=0.9'),
        );
    }

    /**
     * The text side's family wildcard outranks the universal floor it
     * coalesces over (the coalesce order the mutation round reads
     * directly): a text/* spelled above the floor it falls back to
     * must win the side's reading, never the floor beneath it.
     */
    public function testTheTextFamilyWildcardBeatsTheUniversalFloorItCoalescesOver(): void
    {
        self::assertTrue(
            HealthContentNegotiator::prefersExposition('text/*;q=0.7, */*;q=0.1, application/json;q=0.4'),
        );
    }

    /**
     * The JSON side's family wildcard, the mirror pin: an
     * application/* spelled above the universal floor must win its
     * side's reading, never the floor beneath it.
     */
    public function testTheJsonFamilyWildcardBeatsTheUniversalFloorItCoalescesOver(): void
    {
        self::assertFalse(
            HealthContentNegotiator::prefersExposition('text/plain;q=0.5, application/*;q=0.9, */*;q=0.3'),
        );
    }

    /**
     * The browser's own line (HTML the page, everything else at a
     * lower quality) never selects the gauges: no text/plain and no
     * text/* spelling, so the exposition side reads the universal
     * floor and the tie answers the JSON.
     */
    public function testTheBrowsersLineAnswersTheJson(): void
    {
        self::assertFalse(
            HealthContentNegotiator::prefersExposition(
                'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            ),
        );
        self::assertFalse(HealthContentNegotiator::prefersExposition('text/html'));
    }

    public function testTheJsonFamilyWildcardCompetesForTheDefault(): void
    {
        self::assertTrue(
            HealthContentNegotiator::prefersExposition('text/plain;q=0.9, application/*;q=0.5'),
        );
        self::assertFalse(
            HealthContentNegotiator::prefersExposition('text/*;q=0.5, application/*;q=0.9'),
        );
    }
}
