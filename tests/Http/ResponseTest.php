<?php

declare(strict_types=1);

namespace Zef\Test\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Http\HeaderAppender;
use Zef\Framework\Http\HeaderGrammar;
use Zef\Framework\Http\HeaderIndex;
use Zef\Framework\Http\HeaderStore;
use Zef\Framework\Http\HeaderTable;
use Zef\Framework\Http\HttpException;
use Zef\Framework\Http\HttpReasonPhrases;
use Zef\Framework\Http\HttpStatusLine;
use Zef\Framework\Http\HttpVersion;
use Zef\Framework\Http\Message;
use Zef\Framework\Http\Response;
use Zef\Framework\Http\Stream;

/**
 * @internal
 */
#[CoversClass(Response::class)]
#[CoversClass(Message::class)]
#[CoversClass(HeaderStore::class)]
#[CoversClass(HeaderTable::class)]
#[CoversClass(HeaderAppender::class)]
#[CoversClass(HeaderIndex::class)]
#[CoversClass(HeaderGrammar::class)]
#[CoversClass(HttpReasonPhrases::class)]
#[CoversClass(HttpStatusLine::class)]
#[CoversClass(HttpVersion::class)]
final class ResponseTest extends TestCase
{
    public function testDefaultsToA200OkEmptyResponse(): void
    {
        $response = new Response();

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('OK', $response->getReasonPhrase());
        self::assertSame('1.1', $response->getProtocolVersion());
        self::assertSame([], $response->getHeaders());
        self::assertSame('', $response->getBody()->getContents());
    }

    public function testResolvesRegisteredReasonPhrases(): void
    {
        self::assertSame('Not Found', new Response(404)->getReasonPhrase());
        self::assertSame('No Content', new Response(204)->getReasonPhrase());
        self::assertSame("I'm a teapot", new Response(418)->getReasonPhrase());
    }

    public function testUnknownCodesAnswerAnEmptyReasonPhrase(): void
    {
        self::assertSame('', new Response(599)->getReasonPhrase());
    }

    public function testCustomReasonPhrasesWinOverTheRegistry(): void
    {
        $response = new Response(200, [], '', 'All Good');

        self::assertSame('All Good', $response->getReasonPhrase());
        self::assertSame('All Good', new Response()->withStatus(200, 'All Good')->getReasonPhrase());
        self::assertSame('Moved Permanently', new Response(301, [], '', 'Moved Permanently')->getReasonPhrase());
    }

    public function testWithStatusChangesStatusAndResolvesThePhrase(): void
    {
        $response = new Response(200)->withStatus(404);

        self::assertSame(404, $response->getStatusCode());
        self::assertSame('Not Found', $response->getReasonPhrase());
        self::assertSame('Tea Time', new Response(200)->withStatus(418, 'Tea Time')->getReasonPhrase());
    }

    public function testRejectsStatusCodesOutsideTheGrammar(): void
    {
        $this->assertThrows('Invalid HTTP status code: 99.', static function (): void {
            new Response(99);
        });
        $this->assertThrows('Invalid HTTP status code: 600.', static function (): void {
            new Response(600);
        });
        $this->assertThrows('Invalid HTTP status code: 600.', static function (): void {
            new Response()->withStatus(600);
        });
    }

    public function testAcceptsTheStatusBoundaries(): void
    {
        self::assertSame(100, new Response(100)->getStatusCode());
        self::assertSame(599, new Response(599)->getStatusCode());
    }

    public function testRejectsReasonPhrasesThatCorruptStatusLines(): void
    {
        $this->assertThrows('Invalid reason phrase.', static function (): void {
            new Response(200, [], '', "All\rGood");
        });
        $this->assertThrows('Invalid reason phrase.', static function (): void {
            new Response(200, [], '', "All\nGood");
        });
        $this->assertThrows('Invalid reason phrase.', static function (): void {
            new Response()->withStatus(200, "All\0Good");
        });
    }

    public function testAcceptsTabsSpacesAndObsTextInReasonPhrases(): void
    {
        $response = new Response(200, [], '', "All\tGood\xc3\xa9");

        self::assertSame("All\tGood\xc3\xa9", $response->getReasonPhrase());
    }

    public function testRejectsInvalidProtocolVersions(): void
    {
        $this->assertThrows('Invalid HTTP protocol version.', static function (): void {
            new Response(200, [], '', '', '1.2.3');
        });
        $this->assertThrows('Invalid HTTP protocol version.', static function (): void {
            new Response()->withProtocolVersion('v2');
        });
    }

    public function testAcceptsEveryValidProtocolVersionSpelling(): void
    {
        foreach (['1.0', '1.1', '2', '2.0', '3'] as $version) {
            $response = new Response()->withProtocolVersion($version);

            self::assertSame($version, $response->getProtocolVersion());
        }
    }

    public function testConstructsHeadersWithReplacementSemantics(): void
    {
        $response = new Response(200, ['X-Foo' => 'a', 'x-foo' => 'b', 'X-Bar' => ['c', 'd']]);

        self::assertSame(['x-foo' => ['b'], 'X-Bar' => ['c', 'd']], $response->getHeaders());
    }

    public function testLookupsAreCaseInsensitiveOverTheStoredSpelling(): void
    {
        $response = new Response(200, ['X-Foo' => 'a']);

        self::assertTrue($response->hasHeader('x-foo'));
        self::assertTrue($response->hasHeader('X-FOO'));
        self::assertSame(['a'], $response->getHeader('X-FOO'));
        self::assertSame(['X-Foo' => ['a']], $response->getHeaders());
    }

    public function testAbsentHeadersAnswerEmpty(): void
    {
        $response = new Response();

        self::assertFalse($response->hasHeader('X-Foo'));
        self::assertSame([], $response->getHeader('X-Foo'));
        self::assertSame('', $response->getHeaderLine('X-Foo'));
    }

    public function testWithHeaderReplacesUnderTheNewSpelling(): void
    {
        $response = new Response(200, ['X-Foo' => 'a'])->withHeader('x-foo', 'b');

        self::assertSame(['x-foo' => ['b']], $response->getHeaders());
        self::assertSame(['b'], $response->getHeader('X-FOO'));
    }

    public function testWithAddedHeaderAppendsToTheStoredSpelling(): void
    {
        $response = new Response(200, ['X-Foo' => 'a'])
            ->withAddedHeader('x-foo', 'b')
            ->withAddedHeader('X-Foo', ['c', 'd'])
        ;

        self::assertSame(['X-Foo' => ['a', 'b', 'c', 'd']], $response->getHeaders());
        self::assertSame('a, b, c, d', $response->getHeaderLine('x-FOO'));
    }

    public function testWithAddedHeaderCreatesAbsentHeaders(): void
    {
        $response = new Response()->withAddedHeader('X-Foo', 'a');

        self::assertSame(['X-Foo' => ['a']], $response->getHeaders());
    }

    public function testAppendsAgainstATableOfSeveralHeaders(): void
    {
        $response = new Response(200, ['X-A' => 'a', 'X-B' => 'b']);

        $created = $response->withAddedHeader('X-C', 'c');

        self::assertSame(['X-A' => ['a'], 'X-B' => ['b'], 'X-C' => ['c']], $created->getHeaders());

        $extended = $response->withAddedHeader('X-A', 'a2');

        self::assertSame(['a', 'a2'], $extended->getHeader('X-A'));
        self::assertSame(['X-A' => ['a', 'a2'], 'X-B' => ['b']], $extended->getHeaders());
    }

    public function testWithoutHeaderRemovesCaseInsensitivelyAndToleratesAbsence(): void
    {
        $response = new Response(200, ['X-Foo' => 'a', 'X-Bar' => 'b']);

        self::assertSame(['X-Bar' => ['b']], $response->withoutHeader('x-foo')->getHeaders());
        self::assertSame(['X-Foo' => ['a'], 'X-Bar' => ['b']], $response->withoutHeader('X-Baz')->getHeaders());
    }

    public function testAcceptsTabsAndObsTextInHeaderValues(): void
    {
        $response = new Response()->withHeader('X-Foo', "value\tcaf\xc3\xa9");

        self::assertSame(["value\tcaf\xc3\xa9"], $response->getHeader('X-Foo'));
    }

    public function testRejectsInvalidHeaderNames(): void
    {
        $this->assertThrows("Invalid header name 'X Foo'.", static function (): void {
            new Response()->withHeader('X Foo', 'v');
        });
        $this->assertThrows("Invalid header name ''.", static function (): void {
            new Response()->withAddedHeader('', 'v');
        });
        $this->assertThrows("Invalid header name 'X-Foo:'.", static function (): void {
            new Response(200, ['X-Foo:' => 'v']);
        });
    }

    public function testRejectsHeaderValuesWithForbiddenControlCharacters(): void
    {
        $this->assertThrows("Invalid header value for 'X-Foo'.", static function (): void {
            new Response()->withHeader('X-Foo', "va\nlue");
        });
        $this->assertThrows("Invalid header value for 'X-Foo'.", static function (): void {
            new Response()->withAddedHeader('X-Foo', ["va\rlue"]);
        });
    }

    public function testRejectsNonStringHeaderValuesInsteadOfCoercing(): void
    {
        $this->assertThrows('Header value must be a string.', static function (): void {
            new Response()->withHeader('X-Foo', [123]);
        });
        $this->assertThrows('Header value must be a string.', static function (): void {
            new Response()->withAddedHeader('X-Foo', [null]);
        });
    }

    public function testResolvesNumericHeaderNamesThroughTheEngineFoldedKey(): void
    {
        $response = new Response()->withHeader('123', 'v');

        self::assertTrue($response->hasHeader('123'));
        self::assertSame(['v'], $response->getHeader('123'));
        self::assertSame('v', $response->getHeaderLine('123'));
        self::assertSame([], $response->withoutHeader('123')->getHeaders());
    }

    public function testBuildsABodyFromAStringAndAcceptsAStream(): void
    {
        $stream = Stream::fromString('stream body');

        self::assertSame('string body', new Response(200, [], 'string body')->getBody()->getContents());
        self::assertSame('stream body', new Response(200, [], $stream)->getBody()->getContents());
    }

    public function testWithBodySwapsTheBody(): void
    {
        $response = new Response(200, [], 'original')->withBody(Stream::fromString('swapped'));

        self::assertSame('swapped', $response->getBody()->getContents());
    }

    public function testEveryWitherLeavesTheOriginalUntouched(): void
    {
        $response = new Response(200, ['X-Foo' => 'a'], 'body');

        self::assertSame('1.0', $response->withProtocolVersion('1.0')->getProtocolVersion());
        self::assertSame('1.1', $response->getProtocolVersion());
        self::assertSame(['b'], $response->withHeader('X-Foo', 'b')->getHeader('X-Foo'));
        self::assertSame(['a'], $response->getHeader('X-Foo'));
        self::assertSame(['a', 'b'], $response->withAddedHeader('X-Foo', 'b')->getHeader('X-Foo'));
        self::assertSame(['a'], $response->getHeader('X-Foo'));
        self::assertSame([], $response->withoutHeader('X-Foo')->getHeader('X-Foo'));
        self::assertSame(['a'], $response->getHeader('X-Foo'));
        self::assertSame('swapped', $response->withBody(Stream::fromString('swapped'))->getBody()->getContents());
        self::assertSame('body', $response->getBody()->getContents());
        self::assertSame(404, $response->withStatus(404)->getStatusCode());
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('OK', $response->getReasonPhrase());
    }

    /**
     * @param callable(): void $operation
     */
    private function assertThrows(string $message, callable $operation): void
    {
        try {
            $operation();
            self::fail('HttpException expected.');
        } catch (HttpException $httpException) {
            self::assertSame($message, $httpException->getMessage());
        }
    }
}
