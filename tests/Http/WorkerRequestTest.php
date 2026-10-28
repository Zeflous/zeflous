<?php

declare(strict_types=1);

namespace Zef\Test\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Http\HttpException;
use Zef\Framework\Http\HttpMethod;
use Zef\Framework\Http\HttpVersion;
use Zef\Framework\Http\Uri;
use Zef\Framework\Http\WorkerConnection;
use Zef\Framework\Http\WorkerHeaders;
use Zef\Framework\Http\WorkerRequest;

/**
 * The worker payload vocabulary, on its own terms: the wire shape with
 * every field judged at construction -- the method token's fold, the
 * URI's own parse, the header map's canonical form, the peer pair's
 * grammars -- so a payload that cannot become a request never leaves
 * its constructor.
 *
 * @internal
 */
#[CoversClass(WorkerRequest::class)]
#[CoversClass(WorkerHeaders::class)]
#[CoversClass(WorkerConnection::class)]
#[CoversClass(HttpMethod::class)]
#[CoversClass(HttpVersion::class)]
#[CoversClass(Uri::class)]
final class WorkerRequestTest extends TestCase
{
    public function testTheMethodTokenFoldsThroughTheFullUnicodeCaseMap(): void
    {
        $workerRequest = new WorkerRequest("\u{017F}", '/status');

        // U+017F LATIN SMALL LETTER LONG S upper-cases to the ASCII "S"
        // under the Unicode case map; a byte-wise fold would leave the
        // raw bytes behind and the method grammar would reject them.
        self::assertSame('S', $workerRequest->method);
    }

    public function testTheWireShapeLandsValidatedAndNormalised(): void
    {
        $workerRequest = new WorkerRequest(
            'get',
            '/v1/users?page=2',
            ['Host' => 'api.example.com', 'Accept' => ['application/json', 'text/html']],
            '{"page":2}',
            '2.0',
            '[2001:db8::1]',
            54123,
        );

        self::assertSame(
            'GET',
            $workerRequest->method,
            'the method token is uppercased at this single ingress boundary',
        );
        self::assertSame(
            '/v1/users?page=2',
            (string) $workerRequest->uri,
            "the URI string parses through the layer's own fold",
        );
        self::assertSame(
            ['host' => ['api.example.com'], 'accept' => ['application/json', 'text/html']],
            $workerRequest->headers,
            'the header map answers in the canonical form the folds read',
        );
        self::assertSame('{"page":2}', $workerRequest->body, 'the payload rides binary-safe');
        self::assertSame('2.0', $workerRequest->protocolVersion, 'the version rides its PSR-7 spelling');
        self::assertSame('2001:db8::1', $workerRequest->remoteAddress, 'the bracketed literal normalises to bare');
        self::assertSame(54123, $workerRequest->remotePort);
    }

    public function testTheDefaultsAnswerTheMinimalWire(): void
    {
        $workerRequest = new WorkerRequest('GET', '/status');

        self::assertSame([], $workerRequest->headers);
        self::assertSame('', $workerRequest->body);
        self::assertSame('1.1', $workerRequest->protocolVersion);
        self::assertNull($workerRequest->remoteAddress, 'an unnamed peer stays unnamed');
        self::assertNull($workerRequest->remotePort);
    }

    public function testTheEmptyAddressSpellingIsTheAbsenceItAnswers(): void
    {
        $workerRequest = new WorkerRequest('GET', '/status', [], '', '1.1', '');

        self::assertNull($workerRequest->remoteAddress, 'a runtime that cannot name its peer hands the empty spelling');
    }

    #[DataProvider('provideAMethodOutsideTheTokenGrammarIsRefusedCases')]
    public function testAMethodOutsideTheTokenGrammarIsRefused(string $method): void
    {
        $this->expectException(HttpException::class);

        new WorkerRequest($method, '/status');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideAMethodOutsideTheTokenGrammarIsRefusedCases(): iterable
    {
        yield 'an empty token' => [''];

        yield 'a separator in the token' => ['GET /'];

        yield 'a control byte in the token' => ["GET\x0B"];
    }

    public function testAUriWithAControlByteIsRefusedAtConstruction(): void
    {
        $this->expectException(HttpException::class);

        new WorkerRequest('GET', "/status\x0Aprobe=live");
    }

    public function testAVersionOutsideTheGrammarIsRefused(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionMessageIsOrContains('Invalid HTTP protocol version.');

        new WorkerRequest('GET', '/status', [], '', 'HTTP/1.1');
    }

    public function testAGarbagePeerAddressIsRefused(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionMessageIsOrContains('Invalid worker remote address');

        new WorkerRequest('GET', '/status', [], '', '1.1', 'worker.internal');
    }

    #[DataProvider('provideAPortOutsideItsCorridorIsRefusedCases')]
    public function testAPortOutsideItsCorridorIsRefused(int $port): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionMessageIsOrContains('Invalid worker remote port');

        new WorkerRequest('GET', '/status', [], '', '1.1', '192.0.2.10', $port);
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function provideAPortOutsideItsCorridorIsRefusedCases(): iterable
    {
        yield 'the zero port' => [0];

        yield 'one past the ceiling' => [65536];
    }

    public function testAPortWithoutItsAddressIsRefused(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionMessageIsOrContains('without its address describes no peer');

        new WorkerRequest('GET', '/status', [], '', '1.1', null, 54123);
    }

    public function testAPortUnderTheEmptyAddressSpellingIsStillPortless(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionMessageIsOrContains('without its address describes no peer');

        new WorkerRequest('GET', '/status', [], '', '1.1', '', 54123);
    }

    public function testAnAddressWithoutAPortIsTheNamedPeer(): void
    {
        $workerRequest = new WorkerRequest('GET', '/status', [], '', '1.1', '192.0.2.10');

        self::assertSame('192.0.2.10', $workerRequest->remoteAddress);
        self::assertNull($workerRequest->remotePort, 'a runtime that cannot name the port still names the peer');
    }

    public function testAHeaderValueOutsideTheFieldGrammarIsRefusedAtConstruction(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionMessageIsOrContains('Invalid header value');

        new WorkerRequest('GET', '/status', ['X-Injected' => "value\r\nSet-Cookie: stolen=1"]);
    }
}
