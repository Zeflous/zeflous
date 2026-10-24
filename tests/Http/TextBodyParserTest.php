<?php

declare(strict_types=1);

namespace Zef\Test\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Http\TextBody;
use Zef\Framework\Http\TextBodyParser;

/**
 * The plain-text strategy, on its own terms: the exact media type,
 * and the value object the text rides -- the PSR contract's parsed
 * body is array, object or null, so a scalar document rides the only
 * legal shape it has.
 *
 * @internal
 */
#[CoversClass(TextBodyParser::class)]
#[CoversClass(TextBody::class)]
final class TextBodyParserTest extends TestCase
{
    public function testSupportsTheExactMediaTypeCaseInsensitively(): void
    {
        $textBodyParser = new TextBodyParser();

        self::assertTrue($textBodyParser->supports('text/plain'));
        self::assertTrue($textBodyParser->supports('TEXT/PLAIN'));
        self::assertFalse($textBodyParser->supports('text/html'));
        self::assertFalse($textBodyParser->supports('application/json'));
        self::assertFalse($textBodyParser->supports(''));
    }

    public function testParsesTheBodyIntoTheTextValueObject(): void
    {
        $parsed = new TextBodyParser()->parse("brew notes:\n- 22g coffee\n- 300g water");

        self::assertSame("brew notes:\n- 22g coffee\n- 300g water", $parsed->text);
    }
}
