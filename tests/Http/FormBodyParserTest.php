<?php

declare(strict_types=1);

namespace Zef\Test\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Http\FormBodyParser;

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
}
