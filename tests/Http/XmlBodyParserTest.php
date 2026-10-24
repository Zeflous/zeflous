<?php

declare(strict_types=1);

namespace Zef\Test\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Http\HttpException;
use Zef\Framework\Http\MalformedBodyException;
use Zef\Framework\Http\MediaTypeSuffix;
use Zef\Framework\Http\XmlBodyParser;

/**
 * The XML strategy, on its own terms: the media-type grammar (the two
 * exact types and the vendor members under the structured `+xml`
 * suffix, case-insensitively, with the `image/` tree outside
 * ownership), the SimpleXMLElement the parsed body carries, the
 * malformed-document refusal carrying libxml's own diagnostic, the
 * fail-closed posture when the runtime lacks the extension, and the
 * XXE posture the engine's own hardening answers.
 *
 * @internal
 */
#[CoversClass(XmlBodyParser::class)]
#[CoversClass(MediaTypeSuffix::class)]
final class XmlBodyParserTest extends TestCase
{
    public function testSupportsTheExactTypesAndVendorMembersCaseInsensitively(): void
    {
        $xmlBodyParser = new XmlBodyParser();

        $supported = [
            'application/xml',
            'TEXT/XML',
            'Application/Xml',
            'application/atom+xml',
            'application/rss+XML',
            'application/vnd.zef.order+xml',
        ];

        foreach ($supported as $mediaType) {
            self::assertTrue($xmlBodyParser->supports($mediaType), $mediaType . ' is owned');
        }
    }

    public function testRefusesTheTypesOutsideItsGrammar(): void
    {
        $xmlBodyParser = new XmlBodyParser();

        $refused = [
            '',
            'application/json',
            'text/plain',
            'image/svg+xml',
            'text/vnd.zef.order+xml',
            'application/+xml',
            'text/xmlx',
            'xml',
        ];

        foreach ($refused as $mediaType) {
            self::assertFalse($xmlBodyParser->supports($mediaType), $mediaType . ' is not owned');
        }
    }

    public function testParsesADocumentIntoTheElementTree(): void
    {
        $document = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n"
            . "<order id=\"7\"><item sku=\"cf-1\">Coffee</item><item sku=\"fl-2\">Filters</item></order>\n";
        $parsed = new XmlBodyParser()->parse($document);

        self::assertSame(
            $document,
            (string) $parsed->asXML(),
            'the whole document -- attributes, both items, the declaration -- round-trips byte-wise',
        );
        self::assertSame('Coffee', (string) $parsed->item, "the first item's text");
        $attributes = $parsed->attributes();
        self::assertNotNull($attributes, 'the root carries its attribute axis');
        self::assertSame('7', (string) $attributes->id, "the root's id attribute");
    }

    public function testAMalformedDocumentIsRefusedWithTheLibxmlDiagnostic(): void
    {
        $xmlBodyParser = new XmlBodyParser();

        try {
            $xmlBodyParser->parse('<order><item>Coffee</order>');
            self::fail('An unbalanced document must refuse.');
        } catch (MalformedBodyException $malformedBodyException) {
            self::assertSame(
                'The request body is not a well-formed XML document:'
                . ' Opening and ending tag mismatch: item line 1 and order',
                $malformedBodyException->getMessage(),
                'the engine diagnostic rides behind the colon, spaced and trimmed',
            );
        }
    }

    public function testAnEmptyDocumentIsRefusedAsMalformed(): void
    {
        $this->expectException(MalformedBodyException::class);
        $this->expectExceptionMessageIsOrContains('not a well-formed XML document');

        new XmlBodyParser()->parse('');
    }

    public function testTheInternalErrorsModeIsRestoredAfterARefusal(): void
    {
        libxml_use_internal_errors(false);

        try {
            new XmlBodyParser()->parse('<order>');
        } catch (MalformedBodyException) {
            // The refusal itself is pinned above; this test pins the
            // process-global diagnostic mode the parse must restore.
        }

        self::assertFalse(libxml_use_internal_errors(), 'the prior mode is restored');
    }

    public function testTheErrorQueueCarriesOnlyThisParsesOwnErrors(): void
    {
        libxml_use_internal_errors(true);
        libxml_clear_errors();
        @simplexml_load_string('<stale>');
        self::assertNotSame([], libxml_get_errors(), 'the stale error is seeded');

        try {
            new XmlBodyParser()->parse('<order>');
            self::fail('A truncated document must refuse.');
        } catch (MalformedBodyException $malformedBodyException) {
            self::assertStringContainsString(
                'tag order',
                $malformedBodyException->getMessage(),
                "the refusal's diagnostic is THIS parse's own, not the stale one",
            );
        }

        $errors = libxml_get_errors();
        libxml_clear_errors();
        libxml_use_internal_errors(false);

        self::assertSame(
            [],
            $errors,
            'the queue is drained on every parse, under the mode the caller left on',
        );
    }

    public function testAnExternalEntityIsNeverLoaded(): void
    {
        $parsed = new XmlBodyParser()->parse(
            '<!DOCTYPE order [<!ENTITY xxe SYSTEM "file:///etc/passwd">]>'
            . '<order>&xxe;</order>',
        );

        self::assertSame('', (string) $parsed, 'the external entity is never loaded into the tree');
    }

    public function testAnEntityAmplificationDocumentIsRefusedByTheEngineCeilings(): void
    {
        $this->expectException(MalformedBodyException::class);

        new XmlBodyParser()->parse(
            '<!DOCTYPE lolz [<!ENTITY lol "lol">'
            . '<!ENTITY lol2 "&lol;&lol;&lol;&lol;&lol;&lol;&lol;&lol;&lol;&lol;">'
            . '<!ENTITY lol3 "&lol2;&lol2;&lol2;&lol2;&lol2;&lol2;&lol2;&lol2;&lol2;&lol2;">'
            . '<!ENTITY lol4 "&lol3;&lol3;&lol3;&lol3;&lol3;&lol3;&lol3;&lol3;&lol3;&lol3;">'
            . ']><lolz>&lol4;</lolz>',
        );
    }

    public function testARuntimeWithoutTheSimpleXmlSeamRefusesFailClosed(): void
    {
        $xmlBodyParser = new XmlBodyParser(false);

        $this->expectException(HttpException::class);
        $this->expectExceptionMessageIsOrContains(
            'The runtime cannot parse XML request bodies: the SimpleXML extension is not available',
        );

        $xmlBodyParser->parse('<order/>');
    }

    #[DataProvider('provideTheGrammarIsOwnedEvenWhenTheSeamIsAbsentCases')]
    public function testTheGrammarIsOwnedEvenWhenTheSeamIsAbsent(string $mediaType): void
    {
        self::assertTrue(new XmlBodyParser(false)->supports($mediaType));
    }

    /** @return iterable<string, list<string>> */
    public static function provideTheGrammarIsOwnedEvenWhenTheSeamIsAbsentCases(): iterable
    {
        yield 'exact type' => ['application/xml'];

        yield 'vendor member' => ['application/atom+xml'];
    }
}
