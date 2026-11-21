<?php

declare(strict_types=1);

namespace Zef\Test\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Http\FormBodyParser;
use Zef\Framework\Http\MalformedBodyException;

/**
 * The url-encoded form strategy, on its own terms: the exact media
 * type, and the engine's own form decode into the array the parsed
 * body carries -- the same decode the replay lane reads over the same
 * budget-bounded bodies.
 *
 * @internal
 */
#[CoversClass(FormBodyParser::class)]
final class FormBodyParserTest extends TestCase
{
    public function testSupportsTheExactMediaTypeCaseInsensitively(): void
    {
        $formBodyParser = new FormBodyParser();

        self::assertTrue($formBodyParser->supports('application/x-www-form-urlencoded'));
        self::assertTrue($formBodyParser->supports('APPLICATION/X-WWW-FORM-URLENCODED'));
        self::assertFalse($formBodyParser->supports('multipart/form-data'));
        self::assertFalse($formBodyParser->supports('text/plain'));
        self::assertFalse($formBodyParser->supports(''));
    }

    public function testDecodesThePairsIntoTheFlatArray(): void
    {
        self::assertSame(
            ['order' => '7', 'item' => 'coffee filters'],
            new FormBodyParser()->parse('order=7&item=coffee%20filters'),
        );
    }

    public function testDecodesThePlusAsTheEncodedSpace(): void
    {
        self::assertSame(
            ['item' => 'coffee filters'],
            new FormBodyParser()->parse('item=coffee+filters'),
        );
    }

    public function testDecodesTheBracketGrammarIntoTheNestedArray(): void
    {
        self::assertSame(
            ['order' => ['id' => '7', 'items' => ['coffee', 'filters']]],
            new FormBodyParser()->parse('order[id]=7&order[items][]=coffee&order[items][]=filters'),
        );
    }

    public function testABareTokenDecodesToTheEmptyStringValue(): void
    {
        self::assertSame(['flag' => ''], new FormBodyParser()->parse('flag'));
    }

    public function testThePairBoundRefusesTheAmpersandStorm(): void
    {
        // Issue #801's finding 3: the userland decode owns the pair bound
        // the SAPI decoder would have enforced (max_input_vars' own
        // default) -- an ampersand storm inside the two-mebibyte budget
        // is the memory-amplification vector the bound refuses before
        // any decode allocates.
        $amplified = 'a=1' . str_repeat('&x=1', FormBodyParser::PAIR_LIMIT);

        try {
            new FormBodyParser()->parse($amplified);
            self::fail('Expected a MalformedBodyException to be thrown.');
        } catch (MalformedBodyException $malformedBodyException) {
            self::assertSame(
                'The url-encoded body carries more than 1000 pairs (the max_input_vars posture '
                . 'the SAPI decoder would have enforced), the userland decode refusing the '
                . 'amplification instead of paying it.',
                $malformedBodyException->getMessage(),
            );
        }
    }

    public function testThePairBoundCountsRawAmpersandsBeforeAnyDecodeAllocates(): void
    {
        // The census itself never pays the amplification: the count is
        // the engine's own byte scan, the bound judged before a single
        // member array is built.
        self::assertSame(5, FormBodyParser::pairCount('a=1&b=2&c=3&d=4&e=5'));
        self::assertSame(1, FormBodyParser::pairCount('flag'));
        self::assertSame(
            FormBodyParser::PAIR_LIMIT + 1,
            FormBodyParser::pairCount('a=1' . str_repeat('&x=1', FormBodyParser::PAIR_LIMIT)),
            'a body of exactly the bound plus one raw ampersand over the line refuses',
        );
    }

    public function testAStormOfEmptyMembersRefusesEarlyInTheConservativeDirection(): void
    {
        // The count is conservative: raw ampersands overcount the real
        // pair set, so a junk body of empty members refuses a little
        // early -- the fail-closed direction the family keeps.
        $junk = 'a=1' . str_repeat('&', FormBodyParser::PAIR_LIMIT);

        try {
            new FormBodyParser()->parse($junk);
            self::fail('Expected a MalformedBodyException to be thrown.');
        } catch (MalformedBodyException $malformedBodyException) {
            self::assertSame(
                'The url-encoded body carries more than 1000 pairs (the max_input_vars posture '
                . 'the SAPI decoder would have enforced), the userland decode refusing the '
                . 'amplification instead of paying it.',
                $malformedBodyException->getMessage(),
            );
        }
    }

    public function testABodyExactlyAtThePairBoundParses(): void
    {
        // The bound's own edge: a body of exactly the thousand pairs the
        // posture permits parses whole -- the refusal starts one pair
        // beyond, never at the line itself. The pairs carry distinct keys
        // so the decoded bag's own census answers the raw one.
        $atBound = 'a=1&' . implode('&', array_map(
            static fn (int $index): string => "k{$index}=1",
            range(1, FormBodyParser::PAIR_LIMIT - 1),
        ));

        $decoded = new FormBodyParser()->parse($atBound);

        self::assertSame('1', $decoded['a']);
        self::assertSame('1', $decoded['k1']);
        self::assertCount(FormBodyParser::PAIR_LIMIT, $decoded, 'every pair the bound permits rides the decode');
    }
}
