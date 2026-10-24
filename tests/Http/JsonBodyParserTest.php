<?php

declare(strict_types=1);

namespace Zef\Test\Http;

use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Http\JsonBodyParser;
use Zef\Framework\Http\MalformedBodyException;
use Zef\Framework\Http\MediaTypeSuffix;

/**
 * The JSON strategy, on its own terms: the media-type grammar (the
 * exact type, its vendor members under the structured `+json`
 * suffix, both case-insensitively), the associative decode the PSR
 * contract's parsed body carries, and the two honest refusals -- a
 * malformed document, and a well-formed document whose top level is a
 * scalar the parsed body cannot legally carry.
 *
 * @internal
 */
#[CoversClass(JsonBodyParser::class)]
#[CoversClass(MediaTypeSuffix::class)]
final class JsonBodyParserTest extends TestCase
{
    public function testSupportsTheExactTypeAndItsVendorMembersCaseInsensitively(): void
    {
        $jsonBodyParser = new JsonBodyParser();

        $supported = [
            'application/json',
            'APPLICATION/JSON',
            'Application/Json',
            'application/vnd.api+json',
            'application/hal+JSON',
            'APPLICATION/problem+json',
        ];

        foreach ($supported as $mediaType) {
            self::assertTrue($jsonBodyParser->supports($mediaType), $mediaType . ' is owned');
        }
    }

    public function testRefusesTheTypesOutsideItsGrammar(): void
    {
        $jsonBodyParser = new JsonBodyParser();

        $refused = [
            '',
            'text/plain',
            'text/json',
            'application/xml',
            'application/+json',
            'text/vnd.api+json',
            'image/svg+json',
            'application/jsonx',
            'json',
        ];

        foreach ($refused as $mediaType) {
            self::assertFalse($jsonBodyParser->supports($mediaType), $mediaType . ' is not owned');
        }
    }

    public function testDecodesAnObjectDocumentIntoTheAssociativeArray(): void
    {
        $parsed = new JsonBodyParser()->parse('{"order": 7, "items": ["coffee", "filters"]}');

        self::assertSame(['order' => 7, 'items' => ['coffee', 'filters']], $parsed);
    }

    public function testDecodesAnArrayDocumentIntoTheList(): void
    {
        $parsed = new JsonBodyParser()->parse('[1, 2, 3]');

        self::assertSame([1, 2, 3], $parsed);
    }

    public function testANullDocumentAnswersNull(): void
    {
        self::assertNull(new JsonBodyParser()->parse('null'));
    }

    #[DataProvider('provideAScalarTopLevelIsRefusedBecauseTheParsedBodyCannotCarryItCases')]
    public function testAScalarTopLevelIsRefusedBecauseTheParsedBodyCannotCarryIt(
        string $document,
        string $spelled,
    ): void {
        $jsonBodyParser = new JsonBodyParser();

        $this->expectException(MalformedBodyException::class);
        $this->expectExceptionMessageIsOrContains(
            "The JSON document's top level is a scalar, which the parsed body"
            . ' cannot carry: ' . $spelled . ' given.',
        );

        $jsonBodyParser->parse($document);
    }

    /** @return iterable<string, array{string, string}> */
    public static function provideAScalarTopLevelIsRefusedBecauseTheParsedBodyCannotCarryItCases(): iterable
    {
        yield 'integer' => ['5', 'int'];

        yield 'float' => ['5.25', 'float'];

        yield 'string' => ['"coffee"', 'string'];

        yield 'boolean' => ['true', 'bool'];
    }

    public function testAMalformedDocumentIsRefusedWithTheEngineDiagnosticRidingAsPrevious(): void
    {
        $jsonBodyParser = new JsonBodyParser();

        try {
            $jsonBodyParser->parse('{"order": ');
            self::fail('A truncated document must refuse.');
        } catch (MalformedBodyException $malformedBodyException) {
            self::assertStringStartsWith(
                'The request body is not a well-formed JSON document:',
                $malformedBodyException->getMessage(),
            );
            self::assertSame(0, $malformedBodyException->getCode(), 'no engine code is claimed');
            self::assertNotNull($malformedBodyException->getPrevious(), 'the engine diagnostic rides along');
            self::assertInstanceOf(JsonException::class, $malformedBodyException->getPrevious());
        }
    }

    public function testAnEmptyDocumentIsRefusedAsMalformed(): void
    {
        $this->expectException(MalformedBodyException::class);
        $this->expectExceptionMessageIsOrContains('not a well-formed JSON document');

        new JsonBodyParser()->parse('');
    }

    public function testADocumentNestedPastTheDepthCeilingIsRefused(): void
    {
        $jsonBodyParser = new JsonBodyParser(2);

        $this->expectException(MalformedBodyException::class);
        $this->expectExceptionMessageIsOrContains('Maximum stack depth exceeded');

        $jsonBodyParser->parse('{"a": {"b": {"c": 1}}}');
    }

    public function testADocumentAtTheDepthCeilingDecodes(): void
    {
        $jsonBodyParser = new JsonBodyParser(3);

        self::assertSame(['a' => ['b' => 1]], $jsonBodyParser->parse('{"a": {"b": 1}}'));
    }

    public function testTheConstructorDefaultIsTheErrorSystemsOwnThirtyTwoLevelCeiling(): void
    {
        $thirtyOneLevels = str_repeat('{"a":', 31) . '1' . str_repeat('}', 31);

        self::assertNotNull(new JsonBodyParser()->parse($thirtyOneLevels));
    }

    public function testTheConstructorDefaultRefusesTheThirtySecondLevel(): void
    {
        $thirtyTwoLevels = str_repeat('{"a":', 32) . '1' . str_repeat('}', 32);

        $this->expectException(MalformedBodyException::class);
        $this->expectExceptionMessageIsOrContains('Maximum stack depth exceeded');

        new JsonBodyParser()->parse($thirtyTwoLevels);
    }

    public function testTheSuffixLengthIsMeasuredInCharacters(): void
    {
        self::assertTrue(
            MediaTypeSuffix::underApplication('application/vnd.api+jsön', '+jsön'),
            'a multibyte suffix is sliced by its own character count',
        );
        self::assertTrue(
            MediaTypeSuffix::underApplication('application/vnd.äpi+json', '+json'),
            'a multibyte subtype never disturbs the ASCII prefix and suffix walk',
        );
    }
}
