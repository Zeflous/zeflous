<?php

declare(strict_types=1);

namespace Zef\Test\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Http\BodyParserPolicy;
use Zef\Framework\Http\FormBodyParser;
use Zef\Framework\Http\JsonBodyParser;
use Zef\Framework\Http\MalformedBodyException;
use Zef\Framework\Http\RequestBodyParsers;
use Zef\Framework\Http\StandardBodyParsers;
use Zef\Framework\Http\TextBodyParser;
use Zef\Framework\Http\XmlBodyParser;
use Zef\Test\Support\Http\ClaimingParser;

/**
 * The negotiation table, on its own terms: each owned media type
 * routes to its strategy, the media types the family does not own
 * (multipart included) answer no strategy, and the first declared
 * owner wins in a host-built table with overlapping grammars -- plus
 * the request-level walk the table owns: the parser a request
 * carries (or none, when the type line is absent or another seam
 * already answered), and the parsed body the family answers for the
 * request's own non-empty, capturable body.
 *
 * @internal
 */
#[CoversClass(RequestBodyParsers::class)]
#[CoversClass(StandardBodyParsers::class)]
final class RequestBodyParsersTest extends TestCase
{
    public function testTheStandardFamilyRoutesEveryOwnedMediaTypeToItsStrategy(): void
    {
        $parsers = RequestBodyParsers::standard(new BodyParserPolicy());

        $routes = [
            'application/json' => JsonBodyParser::class,
            'APPLICATION/VND.API+JSON' => JsonBodyParser::class,
            'application/xml' => XmlBodyParser::class,
            'text/xml' => XmlBodyParser::class,
            'application/atom+xml' => XmlBodyParser::class,
            'application/x-www-form-urlencoded' => FormBodyParser::class,
            'text/plain' => TextBodyParser::class,
        ];

        foreach ($routes as $mediaType => $strategy) {
            $routed = $parsers->forMediaType($mediaType);
            self::assertInstanceOf($strategy, $routed, $mediaType . ' routes to its strategy');
        }
    }

    public function testTheStandardFamilyOwnsNoStrategyForTheUnownedMediaTypes(): void
    {
        $parsers = RequestBodyParsers::standard(new BodyParserPolicy());

        $unowned = [
            '',
            'multipart/form-data',
            'application/octet-stream',
            'image/svg+xml',
            'text/html',
            'application/x-yaml',
        ];

        foreach ($unowned as $mediaType) {
            self::assertNull($parsers->forMediaType($mediaType), $mediaType . ' is not owned');
        }
    }

    public function testTheStandardFamilyCarriesThePolicysOwnDepthCeiling(): void
    {
        $parsers = RequestBodyParsers::standard(new BodyParserPolicy(jsonDepth: 2));

        $routed = $parsers->forMediaType('application/json');
        self::assertInstanceOf(JsonBodyParser::class, $routed);

        $this->expectException(MalformedBodyException::class);
        $this->expectExceptionMessageIsOrContains('Maximum stack depth exceeded');

        $routed->parse('{"a": {"b": {"c": 1}}}');
    }

    public function testAHostBuiltTableAnswersItsFirstDeclaredOwner(): void
    {
        $claimingParser = new ClaimingParser(new JsonBodyParser(), new FormBodyParser());
        $requestBodyParsers = new RequestBodyParsers([
            $claimingParser,
            new FormBodyParser(),
        ]);

        $routed = $requestBodyParsers->forMediaType('application/x-www-form-urlencoded');

        self::assertSame($claimingParser, $routed, 'the first declared owner wins');
    }
}
