<?php

declare(strict_types=1);

namespace Zef\Test\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Http\ErrorContentNegotiator;

/**
 * Pins the content negotiator's own preference arithmetic: which media
 * ranges count for the HTML side, which count for the JSON side, and the
 * strict comparison that separates a tie from a preference. The problem
 * document and the page renderer compose this answer; this battery pins
 * the arithmetic itself -- the fallback order through the wildcards and
 * the greater-than that a tie must fail.
 *
 * @internal
 */
#[CoversClass(ErrorContentNegotiator::class)]
final class ErrorContentNegotiatorTest extends TestCase
{
    public function testAnEmptyAcceptHeaderDoesNotPreferHtml(): void
    {
        self::assertFalse(ErrorContentNegotiator::prefersHtml(''));
    }

    public function testADirectHtmlRangeIsAPreference(): void
    {
        self::assertTrue(ErrorContentNegotiator::prefersHtml('text/html'));
    }

    /**
     * The HTML side's own wildcard must count: the fallback list is the
     * whole vocabulary of the read, not its first entry -- a header that
     * names text/* alone still prefers the page over the problem document.
     */
    public function testTheHtmlWildcardAloneIsAPreference(): void
    {
        self::assertTrue(ErrorContentNegotiator::prefersHtml('text/*'));
    }

    public function testTheJsonVocabularyAloneIsNotAPreference(): void
    {
        self::assertFalse(ErrorContentNegotiator::prefersHtml('application/json'));
        self::assertFalse(ErrorContentNegotiator::prefersHtml('application/problem+json'));
        self::assertFalse(ErrorContentNegotiator::prefersHtml('application/*'));
    }

    public function testTheUniversalWildcardAloneIsATieNotAPreference(): void
    {
        self::assertFalse(ErrorContentNegotiator::prefersHtml('*/*'));
    }

    public function testTheHigherHtmlQualityWins(): void
    {
        self::assertTrue(
            ErrorContentNegotiator::prefersHtml('text/html;q=0.9, application/problem+json;q=0.8'),
        );
    }

    /**
     * A JSON range that outranks the HTML range must keep the answer false
     * even though the HTML side found its own range: the comparison is
     * against the JSON side's best read, not against zero alone.
     */
    public function testAHigherJsonQualityRefusesThePreference(): void
    {
        self::assertFalse(
            ErrorContentNegotiator::prefersHtml('application/json;q=1.0, text/html;q=0.9'),
        );
    }

    public function testAnExplicitZeroHtmlQualityIsNotAPreference(): void
    {
        self::assertFalse(
            ErrorContentNegotiator::prefersHtml('text/html;q=0, application/problem+json;q=0'),
        );
    }

    public function testTheHtmlWildcardOutranksAWeakerUniversalWildcard(): void
    {
        self::assertTrue(
            ErrorContentNegotiator::prefersHtml('text/*;q=0.9, */*;q=0.8'),
        );
    }
}
