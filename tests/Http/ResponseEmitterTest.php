<?php

declare(strict_types=1);

namespace Zef\Test\Http;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Zef\Framework\Http\ContentLength;
use Zef\Framework\Http\EmittedBody;
use Zef\Framework\Http\EmittedHeaders;
use Zef\Framework\Http\HeaderGrammar;
use Zef\Framework\Http\HttpException;
use Zef\Framework\Http\Response;
use Zef\Framework\Http\ResponseEmitter;
use Zef\Framework\Http\SapiTransmitter;
use Zef\Framework\Http\Stream;
use Zef\Framework\Http\StreamOpener;
use Zef\Test\Support\Http\CursoredStream;
use Zef\Test\Support\Http\ForeignResponse;
use Zef\Test\Support\Http\LyingEofStreamWrapper;
use Zef\Test\Support\Http\RecordingStream;
use Zef\Test\Support\Http\TellGuardedStream;
use Zef\Test\Support\Http\WriteOnlySeekableStreamWrapper;

/**
 * @internal
 */
#[CoversClass(ResponseEmitter::class)]
#[CoversClass(SapiTransmitter::class)]
#[CoversClass(EmittedHeaders::class)]
#[CoversClass(EmittedBody::class)]
#[CoversClass(ContentLength::class)]
#[CoversClass(HeaderGrammar::class)]
#[CoversClass(Response::class)]
#[CoversClass(Stream::class)]
final class ResponseEmitterTest extends TestCase
{
    private const string LYING_EOF_PROTOCOL = 'zef-emitter-lying-eof';

    private const string WRITE_ONLY_SEEKABLE_PROTOCOL = 'zef-emitter-write-only-seekable';

    #[Override]
    protected function setUp(): void
    {
        stream_wrapper_register(self::LYING_EOF_PROTOCOL, LyingEofStreamWrapper::class);
        stream_wrapper_register(
            self::WRITE_ONLY_SEEKABLE_PROTOCOL,
            WriteOnlySeekableStreamWrapper::class,
        );
    }

    #[Override]
    protected function tearDown(): void
    {
        stream_wrapper_unregister(self::LYING_EOF_PROTOCOL);
        stream_wrapper_unregister(self::WRITE_ONLY_SEEKABLE_PROTOCOL);
    }

    public function testEmitsTheFullBodyInFixedSizeChunks(): void
    {
        $payload = str_repeat('x', 20000);
        $recordingStream = new RecordingStream(Stream::fromString($payload));
        $fields = [];
        $statuses = [];
        $emitter = $this->recordingEmitter($fields, $statuses);

        $output = $this->emits($emitter, new Response(200, [], $recordingStream));

        self::assertSame($payload, $output);
        self::assertSame([8192, 8192, 8192], $recordingStream->readLengths);
        self::assertSame([200], $statuses);
    }

    public function testEmitsWithTheDefaultFlags(): void
    {
        $body = Stream::fromString('payload');
        $responseEmitter = new ResponseEmitter();

        ob_start();

        try {
            $responseEmitter->emit(new Response(200, [], $body));
        } finally {
            $output = (string) ob_get_clean();
        }

        self::assertSame('payload', $output);
        self::assertTrue($body->isReadable());
        self::assertTrue($body->isSeekable());
    }

    public function testRewindsADrainedSeekableBodyBeforeEmitting(): void
    {
        $body = Stream::fromString('hello');
        self::assertSame('hello', (string) $body);
        self::assertTrue($body->eof());

        $fields = [];
        $statuses = [];
        $emitter = $this->recordingEmitter($fields, $statuses);

        $output = $this->emits($emitter, new Response(200, ['Content-Length' => '5'], $body));

        self::assertSame('hello', $output);
        self::assertSame(['Content-Length: 5'], $this->fieldLines($fields));
    }

    public function testLeavesANonSeekableBodyUntouched(): void
    {
        $tellGuardedStream = new TellGuardedStream(3);
        $fields = [];
        $statuses = [];
        $emitter = $this->recordingEmitter($fields, $statuses);

        $output = $this->emits($emitter, new ForeignResponse(200, [], $tellGuardedStream));

        self::assertSame('', $output);
        self::assertSame([200], $statuses);
    }

    public function testSuppressesTheBodyWhenAsked(): void
    {
        $fields = [];
        $statuses = [];
        $emitter = $this->recordingEmitter($fields, $statuses);

        $output = $this->emits($emitter, new Response(200, ['X-Framework' => 'zef'], 'hello'), false, true);

        self::assertSame('', $output);
        self::assertSame([200], $statuses);
        self::assertSame(['X-Framework: zef'], $this->fieldLines($fields));
    }

    public function testEmitsNoBodyForTheBodylessStatuses(): void
    {
        $fields = [];
        $statuses = [];
        $emitter = $this->recordingEmitter($fields, $statuses);

        foreach ([100, 101, 204, 205, 304] as $status) {
            $output = $this->emits($emitter, new Response($status, [], 'must-not-appear'));

            self::assertSame('', $output, "status {$status} must not carry a body");
        }

        foreach ([200, 201, 404] as $status) {
            $output = $this->emits($emitter, new Response($status, [], 'payload'));

            self::assertSame('payload', $output, "status {$status} must carry its body");
        }
    }

    /**
     * RFC 9110 section 8.6: a 1xx or 204 answer MUST NOT carry a
     * Content-Length at all -- even a truthful `0` against an empty
     * body, which the reconciliation would otherwise let ship.
     */
    public function testDropsTheContentLengthOnTheStatusesThatMustNotCarryIt(): void
    {
        $fields = [];
        $statuses = [];
        $emitter = $this->recordingEmitter($fields, $statuses);

        foreach ([100, 101, 204] as $status) {
            $before = \count($fields);
            $this->emits($emitter, new Response($status, ['Content-Length' => '0', 'X-Framework' => 'zef'], ''));

            $emitted = \array_slice($this->fieldLines($fields), $before);
            self::assertSame(['X-Framework: zef'], $emitted, "status {$status} must not carry a Content-Length");
        }
    }

    /**
     * The same section's second paragraph: a 304 may keep a truthful
     * Content-Length, and the reconciliation still owns its honesty.
     */
    public function testKeepsATruthfulContentLengthOnNotModified(): void
    {
        $fields = [];
        $statuses = [];
        $emitter = $this->recordingEmitter($fields, $statuses);

        $this->emits($emitter, new Response(304, ['Content-Length' => '0'], ''));

        self::assertSame(['Content-Length: 0'], $this->fieldLines($fields));
    }

    public function testCloseBodyClosesTheStreamAfterwards(): void
    {
        $body = Stream::fromString('hello');
        $fields = [];
        $statuses = [];
        $emitter = $this->recordingEmitter($fields, $statuses);

        $output = $this->emits($emitter, new Response(200, [], $body), true);

        self::assertSame('hello', $output);
        self::assertFalse($body->isReadable());
        self::assertFalse($body->isSeekable());
    }

    public function testCloseBodyClosesEvenWhenTheBodyIsUnreadable(): void
    {
        $stream = new Stream(StreamOpener::open(self::WRITE_ONLY_SEEKABLE_PROTOCOL . '://resource', 'wb'));
        $fields = [];
        $statuses = [];
        $emitter = $this->recordingEmitter($fields, $statuses);

        try {
            $this->emits($emitter, new Response(200, [], $stream), true);
            self::fail('HttpException expected.');
        } catch (HttpException $httpException) {
            self::assertSame('Response body stream is not readable.', $httpException->getMessage());
        }

        self::assertFalse($stream->isReadable());
        self::assertFalse($stream->isSeekable());
    }

    public function testUnreadableBodyFailsClosed(): void
    {
        $stream = new Stream(StreamOpener::open(self::WRITE_ONLY_SEEKABLE_PROTOCOL . '://resource', 'wb'));
        $fields = [];
        $statuses = [];
        $emitter = $this->recordingEmitter($fields, $statuses);

        $this->assertThrows('Response body stream is not readable.', static function () use ($emitter, $stream): void {
            $emitter->emit(new Response(200, [], $stream));
        });
    }

    public function testStopsAtAnEmptyReadBelowTheEndOfStream(): void
    {
        $stream = new Stream(StreamOpener::open(self::LYING_EOF_PROTOCOL . '://resource', 'r'));
        $fields = [];
        $statuses = [];
        $emitter = $this->recordingEmitter($fields, $statuses);

        $output = $this->emits($emitter, new ForeignResponse(200, [], $stream));

        self::assertSame('AB', $output);
        self::assertFalse($stream->eof());
    }

    public function testEmitsTheBodyEvenWhenHeadersAreAlreadySent(): void
    {
        $fields = [];
        $statuses = [];
        $emitter = $this->recordingEmitter($fields, $statuses, true);

        $output = $this->emits($emitter, new Response(200, ['X-Framework' => 'zef'], 'hello'));

        self::assertSame('hello', $output);
        self::assertSame([], $fields);
        self::assertSame([], $statuses);
    }

    public function testTransmitsTheStatusCode(): void
    {
        foreach ([200, 201, 404, 599, 100] as $status) {
            $fields = [];
            $statuses = [];
            $emitter = $this->recordingEmitter($fields, $statuses);

            $this->emits($emitter, new Response($status, [], 'x'), false, true);

            self::assertSame([$status], $statuses, "status {$status} must be transmitted");
        }
    }

    public function testSkipsTheStatusForForeignStatusCodes(): void
    {
        foreach ([999, 0, 700] as $status) {
            $fields = [];
            $statuses = [];
            $emitter = $this->recordingEmitter($fields, $statuses);

            $this->emits($emitter, new ForeignResponse($status, [], Stream::fromString('x')));

            self::assertSame([], $statuses, "foreign status {$status} must not be transmitted");
        }
    }

    public function testTransmitsValidatedFieldsWithAppendSemantics(): void
    {
        $fields = [];
        $statuses = [];
        $emitter = $this->recordingEmitter($fields, $statuses);

        $this->emits(
            $emitter,
            new Response(200, ['X-Framework' => 'zef', 'X-Multi' => ['a', 'b']], 'hello'),
            false,
            true,
        );

        self::assertSame(
            [['X-Framework: zef', false], ['X-Multi: a', false], ['X-Multi: b', false]],
            $fields,
        );
    }

    public function testSkipsInvalidFieldsAndKeepsTheValidNeighbours(): void
    {
        $response = new ForeignResponse(
            200,
            ['Bad Name' => ['v'], 'X-Good' => ['ok'], 'X-Bad-Value' => ["line\nbreak"]],
            Stream::fromString('x'),
        )
            ->withHeader('X-Also', 'fine')
            ->withHeader('X-Count', 5)
        ;
        $fields = [];
        $statuses = [];
        $emitter = $this->recordingEmitter($fields, $statuses);

        $this->emits($emitter, $response, false, true);

        self::assertSame([['X-Also: fine', false], ['X-Good: ok', false]], $fields);
    }

    public function testDropsNonStringMembersInsteadOfCoercingThem(): void
    {
        $response = new ForeignResponse(200, [], Stream::fromString('x'))
            ->withHeader('X-Count', 5)
            ->withHeader('X-Null', [null])
        ;
        $fields = [];
        $statuses = [];
        $emitter = $this->recordingEmitter($fields, $statuses);

        $this->emits($emitter, $response, false, true);

        self::assertSame([], $fields);
    }

    public function testToleratesAScalarFieldValueList(): void
    {
        $response = new ForeignResponse(200, [], Stream::fromString('x'))
            ->withHeader('X-Solo', 'bare')
        ;
        $fields = [];
        $statuses = [];
        $emitter = $this->recordingEmitter($fields, $statuses);

        $this->emits($emitter, $response, false, true);

        self::assertSame([['X-Solo: bare', false]], $fields);
    }

    public function testEmitsAFieldWhoseNameIsAnEngineIntegerKey(): void
    {
        $mutated = new Response(200, [], 'x')
            ->withHeader('123', 'v')
        ;
        self::assertInstanceOf(Response::class, $mutated);
        $fields = [];
        $statuses = [];
        $emitter = $this->recordingEmitter($fields, $statuses);

        $this->emits($emitter, $mutated, false, true);

        self::assertSame([['123: v', false]], $fields);
    }

    public function testSkipsOnlyTheUnacceptableMemberOfAListedHeader(): void
    {
        $foreignResponse = new ForeignResponse(
            200,
            ['X-Multi' => ["first\nbad", 'second']],
            Stream::fromString('x'),
        );
        $fields = [];
        $statuses = [];
        $emitter = $this->recordingEmitter($fields, $statuses);

        $this->emits($emitter, $foreignResponse, false, true);

        self::assertSame([['X-Multi: second', false]], $fields);
    }

    public function testKeepsAnHonestContentLength(): void
    {
        $fields = [];
        $statuses = [];
        $emitter = $this->recordingEmitter($fields, $statuses);

        $this->emits($emitter, new Response(200, ['Content-Length' => '3'], 'abc'), false, true);

        self::assertContains('Content-Length: 3', $this->fieldLines($fields));
    }

    public function testDropsALyingContentLengthAndNeverMutatesTheResponse(): void
    {
        $response = new Response(200, ['Content-Length' => '999'], 'abc');
        $fields = [];
        $statuses = [];
        $emitter = $this->recordingEmitter($fields, $statuses);

        $this->emits($emitter, $response, false, true);

        self::assertNotContains('Content-Length: 999', $this->fieldLines($fields));
        self::assertSame('999', $response->getHeaderLine('Content-Length'));
    }

    public function testDropsANonNumericContentLength(): void
    {
        $foreignResponse = new ForeignResponse(200, ['Content-Length' => ['abc']], Stream::fromString('x'));
        $fields = [];
        $statuses = [];
        $emitter = $this->recordingEmitter($fields, $statuses);

        $this->emits($emitter, $foreignResponse, false, true);

        self::assertNotContains('Content-Length: abc', $this->fieldLines($fields));
    }

    public function testDropsTheContentLengthWhenTheSizeIsUnknown(): void
    {
        $foreignResponse = new ForeignResponse(200, ['Content-Length' => ['5']], new CursoredStream(null, 0));
        $fields = [];
        $statuses = [];
        $emitter = $this->recordingEmitter($fields, $statuses);

        $this->emits($emitter, $foreignResponse, false, true);

        self::assertNotContains('Content-Length: 5', $this->fieldLines($fields));
    }

    public function testReconcilesFromTheLiveCursorPosition(): void
    {
        $foreignResponse = new ForeignResponse(200, ['Content-Length' => ['3']], new CursoredStream(5, 2));
        $fields = [];
        $statuses = [];
        $emitter = $this->recordingEmitter($fields, $statuses);

        $this->emits($emitter, $foreignResponse, false, true);

        self::assertContains('Content-Length: 3', $this->fieldLines($fields));
    }

    public function testClampsAnOvershotCursorToAZeroRemainder(): void
    {
        $foreignResponse = new ForeignResponse(200, ['Content-Length' => ['0']], new CursoredStream(5, 8));
        $fields = [];
        $statuses = [];
        $emitter = $this->recordingEmitter($fields, $statuses);

        $this->emits($emitter, $foreignResponse, false, true);

        self::assertContains('Content-Length: 0', $this->fieldLines($fields));
    }

    public function testAMatchingContentLengthSurvivesOnANonSeekableBody(): void
    {
        $foreignResponse = new ForeignResponse(200, ['Content-Length' => ['3']], new TellGuardedStream(3));
        $fields = [];
        $statuses = [];
        $emitter = $this->recordingEmitter($fields, $statuses);

        $this->emits($emitter, $foreignResponse, false, true);

        self::assertContains('Content-Length: 3', $this->fieldLines($fields));
    }

    public function testReconcilesTheContentLengthCaseInsensitively(): void
    {
        $foreignResponse = new ForeignResponse(200, ['content-length' => ['9']], new CursoredStream(3, 0));
        $fields = [];
        $statuses = [];
        $emitter = $this->recordingEmitter($fields, $statuses);

        $this->emits($emitter, $foreignResponse, false, true);

        self::assertNotContains('content-length: 9', $this->fieldLines($fields));
    }

    public function testDropsTheContentLengthWhenTheSizeIsUnknownEvenAgainstAZeroDeclaration(): void
    {
        $foreignResponse = new ForeignResponse(200, ['Content-Length' => ['0']], new CursoredStream(null, 0));
        $fields = [];
        $statuses = [];
        $emitter = $this->recordingEmitter($fields, $statuses);

        $this->emits($emitter, $foreignResponse, false, true);

        self::assertNotContains('Content-Length: 0', $this->fieldLines($fields));
    }

    public function testDropsANonNumericContentLengthEvenWhenItCastsToTheRemainingLength(): void
    {
        $foreignResponse = new ForeignResponse(200, ['Content-Length' => ['abc']], Stream::fromString(''));
        $fields = [];
        $statuses = [];
        $emitter = $this->recordingEmitter($fields, $statuses);

        $this->emits($emitter, $foreignResponse, false, true);

        self::assertNotContains('Content-Length: abc', $this->fieldLines($fields));
    }

    public function testNeverConsultsTheCursorWhenNoContentLengthIsDeclared(): void
    {
        $cursoredStream = new CursoredStream(5, 0);
        $foreignResponse = new ForeignResponse(200, [], $cursoredStream);
        $fields = [];
        $statuses = [];
        $emitter = $this->recordingEmitter($fields, $statuses);

        $this->emits($emitter, $foreignResponse, false, true);

        self::assertSame(0, $cursoredStream->tellCalls);
        self::assertSame([], $fields);
    }

    public function testEmitsAnEmptyBodyThroughTheDefensiveEmptyRead(): void
    {
        $recordingStream = new RecordingStream(Stream::fromString(''));
        $fields = [];
        $statuses = [];
        $emitter = $this->recordingEmitter($fields, $statuses);

        $output = $this->emits($emitter, new Response(200, [], $recordingStream));

        self::assertSame('', $output);
        self::assertSame([8192], $recordingStream->readLengths);
    }

    public function testSetsTheRealStatusCodeThroughTheSapi(): void
    {
        $responseEmitter = new ResponseEmitter();

        $output = $this->emits($responseEmitter, new Response(201, ['X-Framework' => 'zef'], 'x'));

        self::assertSame('x', $output);
        self::assertSame(201, http_response_code());
    }

    public function testTheRealHeaderCallsAreDiscardedSilentlyByTheCliSapi(): void
    {
        $responseEmitter = new ResponseEmitter();

        $output = $this->emits($responseEmitter, new Response(200, ['X-Framework' => 'zef'], 'x'));

        self::assertSame('x', $output);
        self::assertSame([], headers_list());
        self::assertFalse(headers_sent());
    }

    /**
     * Emits a response and answers whatever reached the output channel.
     */
    private function emits(
        ResponseEmitter $responseEmitter,
        ResponseInterface $response,
        bool $closeBody = false,
        bool $suppressBody = false,
    ): string {
        ob_start();

        try {
            $responseEmitter->emit($response, $closeBody, $suppressBody);
        } finally {
            $output = (string) ob_get_clean();
        }

        return $output;
    }

    /**
     * @param list<array{string, bool}> $fields
     * @param list<int>                 $statuses
     */
    private function recordingEmitter(array &$fields, array &$statuses, bool $sent = false): ResponseEmitter
    {
        $fields = [];
        $statuses = [];

        return new ResponseEmitter(new SapiTransmitter(
            static function (string $line, bool $replace) use (&$fields): void {
                $fields[] = [$line, $replace];
            },
            static function (int $code) use (&$statuses): void {
                $statuses[] = $code;
            },
            static fn (): bool => $sent,
        ));
    }

    /**
     * @param list<array{string, bool}> $fields
     *
     * @return list<string>
     */
    private function fieldLines(array $fields): array
    {
        return array_map(static fn (array $field): string => $field[0], $fields);
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
