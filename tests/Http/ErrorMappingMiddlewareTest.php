<?php

declare(strict_types=1);

namespace Zef\Test\Http;

use LogicException;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Zef\Framework\Exception\CarriesHttpStatus;
use Zef\Framework\Exception\InternalException;
use Zef\Framework\Exception\MethodNotAllowedException;
use Zef\Framework\Exception\NotFoundException;
use Zef\Framework\Exception\RateLimitException;
use Zef\Framework\Exception\ValidationException;
use Zef\Framework\Exception\ZefException;
use Zef\Framework\Http\CorrelationId;
use Zef\Framework\Http\ErrorDisclosure;
use Zef\Framework\Http\ErrorEnvelope;
use Zef\Framework\Http\ErrorFaultRecorder;
use Zef\Framework\Http\ErrorMappingMiddleware;
use Zef\Framework\Http\ErrorResponseFactory;
use Zef\Framework\Http\FallbackResponse;
use Zef\Framework\Http\HttpException;
use Zef\Framework\Http\PayloadTooLargeException;
use Zef\Framework\Http\RedactedMessage;
use Zef\Framework\Http\RedactionCandidate;
use Zef\Framework\Http\RequestBodyPolicy;
use Zef\Framework\Http\RequestFactory;
use Zef\Framework\Http\Response;
use Zef\Framework\Http\ServerRequest;
use Zef\Framework\Http\Stream;
use Zef\Framework\Http\Uri;
use Zef\Test\Support\Http\FixedResponseHandler;
use Zef\Test\Support\Http\ForeignResponse;
use Zef\Test\Support\Http\ThrowingHandler;
use Zef\Test\Support\Log\FailingLogger;
use Zef\Test\Support\Log\RecordingLogger;

/**
 * @internal
 */
#[CoversClass(ErrorMappingMiddleware::class)]
#[CoversClass(ErrorFaultRecorder::class)]
#[CoversClass(ErrorEnvelope::class)]
#[CoversClass(CorrelationId::class)]
#[CoversClass(ErrorDisclosure::class)]
#[CoversClass(ErrorResponseFactory::class)]
#[CoversClass(FallbackResponse::class)]
#[CoversClass(RedactedMessage::class)]
#[CoversClass(RedactionCandidate::class)]
#[CoversClass(Response::class)]
#[CoversClass(Stream::class)]
#[CoversClass(ServerRequest::class)]
#[CoversClass(InternalException::class)]
#[CoversClass(MethodNotAllowedException::class)]
#[CoversClass(NotFoundException::class)]
#[CoversClass(RateLimitException::class)]
#[CoversClass(ValidationException::class)]
#[CoversClass(PayloadTooLargeException::class)]
final class ErrorMappingMiddlewareTest extends TestCase
{
    private const string CLAIMED_ID = 'req-123';

    private ?string $routedErrorLog = null;

    private false|string $previousErrorLog = false;

    #[Override]
    protected function tearDown(): void
    {
        if ($this->routedErrorLog === null) {
            return;
        }

        ini_set('error_log', (string) $this->previousErrorLog);
        @unlink($this->routedErrorLog);
    }

    public function testASuccessfulResponseIsStampedWithTheClaimedCorrelationId(): void
    {
        $response = $this->middleware()->process(
            $this->requestWithClaim(),
            new FixedResponseHandler(new Response(200)),
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(self::CLAIMED_ID, $response->getHeaderLine('X-Request-ID'));
    }

    public function testASuccessfulResponseCarriesAFreshIdWhenTheClaimIsMalformed(): void
    {
        $response = $this->middleware()->process(
            $this->requestWithClaim('not a token!'),
            new FixedResponseHandler(new Response(200)),
        );

        self::assertDoesNotMatchRegularExpression(
            '/^not a token!$/',
            $response->getHeaderLine('X-Request-ID'),
        );
    }

    public function testAForeignResponseIsStampedToo(): void
    {
        $foreignResponse = new ForeignResponse(200, [], Stream::fromString(''));

        $response = $this->middleware()->process($this->requestWithClaim(), new FixedResponseHandler($foreignResponse));

        self::assertInstanceOf(ForeignResponse::class, $response);
        self::assertArrayHasKey('X-Request-ID', $response->getHeaders());
    }

    /**
     * The end-to-end pin: the ingress gate's own refusal -- thrown by the
     * real {@see RequestFactory::validateIngress()} over an oversized
     * Content-Length claim -- is what the middleware maps to 413, so the
     * status table's refusal text stays pinned to the real throw sites
     * instead of to a shared constant.
     */
    public function testTheIngressBudgetRefusalAnswersContentTooLarge(): void
    {
        $serverRequest = new ServerRequest(
            'POST',
            new Uri('https://app.example/users'),
            [],
            [],
            [],
            [],
            null,
            ['Content-Length' => '10485760', 'X-Request-ID' => self::CLAIMED_ID],
        );

        try {
            RequestFactory::validateIngress($serverRequest, [], [], new RequestBodyPolicy(1048576));
            self::fail('The oversized claim must be refused.');
        } catch (HttpException $httpException) {
            $response = $this->middleware()->process($serverRequest, new ThrowingHandler($httpException));
        }

        self::assertSame(413, $response->getStatusCode());
        self::assertSame('Content Too Large', $response->getReasonPhrase());
        self::assertSame('application/json', $response->getHeaderLine('Content-Type'));
        self::assertSame(self::CLAIMED_ID, $response->getHeaderLine('X-Request-ID'));

        $envelope = $this->envelope($response);

        self::assertTrue($envelope['error']);
        self::assertSame(413, $envelope['status']);
        self::assertSame('Content Too Large', $envelope['message']);
        self::assertSame(self::CLAIMED_ID, $envelope['correlation_id']);
    }

    public function testASyntheticBudgetRefusalIsMappedTheSameWay(): void
    {
        $response = $this->middleware()->process(
            $this->requestWithClaim(),
            new ThrowingHandler(new PayloadTooLargeException()),
        );

        self::assertSame(413, $response->getStatusCode());
        self::assertSame('Content Too Large', $this->envelope($response)['message']);
    }

    /**
     * The retirement pin: a layer failure wearing the refusal's words is a
     * plain 400 now -- the message stopped being a signal the day the
     * refusal became a class.
     */
    public function testABudgetSizedLayerFailureAnswersTheCoarseBadRequest(): void
    {
        $response = $this->middleware()->process(
            $this->requestWithClaim(),
            new ThrowingHandler(new HttpException('Request body exceeds configured size limit.')),
        );

        self::assertSame(400, $response->getStatusCode());
        self::assertSame('Invalid request.', $this->envelope($response)['message']);
    }

    public function testATypedNotFoundAnswersItsStatusAndPublicMessage(): void
    {
        $response = $this->middleware()->process(
            $this->requestWithClaim(),
            new ThrowingHandler(new NotFoundException('User with ID 123 not found.')),
        );

        self::assertSame(404, $response->getStatusCode());
        self::assertSame('Not Found', $response->getReasonPhrase());
        self::assertSame('application/json', $response->getHeaderLine('Content-Type'));

        $envelope = $this->envelope($response);

        self::assertSame(404, $envelope['status']);
        self::assertSame('User with ID 123 not found.', $envelope['message']);
        self::assertSame(self::CLAIMED_ID, $envelope['correlation_id']);
        self::assertSame(self::CLAIMED_ID, $response->getHeaderLine('X-Request-ID'));
    }

    public function testATypedMethodNotAllowedAnswersItsStatus(): void
    {
        $response = $this->middleware()->process(
            $this->requestWithClaim(),
            new ThrowingHandler(new MethodNotAllowedException('GET', '/users', ['POST', 'PUT'])),
        );

        self::assertSame(405, $response->getStatusCode());
        self::assertSame("Method 'GET' is not allowed for /users.", $this->envelope($response)['message']);
    }

    public function testATypedRateLimitAnswersItsStatus(): void
    {
        $response = $this->middleware()->process(
            $this->requestWithClaim(),
            new ThrowingHandler(new RateLimitException(30, 'Too many sign-in attempts.')),
        );

        self::assertSame(429, $response->getStatusCode());
        self::assertSame('Too many sign-in attempts.', $this->envelope($response)['message']);
    }

    /**
     * The typed internal fault is the 500 the author chose the words for:
     * the message shows in production, and the fault is logged like every
     * server-tier failure.
     */
    public function testATypedInternalFaultShowsItsMessageAndIsLogged(): void
    {
        $recordingLogger = new RecordingLogger();
        $internalException = new InternalException('The queue worker observed an impossible state.');

        $response = $this->middleware($recordingLogger)->process(
            $this->requestWithClaim(),
            new ThrowingHandler($internalException),
        );

        self::assertSame(500, $response->getStatusCode());
        self::assertSame(
            'The queue worker observed an impossible state.',
            $this->envelope($response)['message'],
        );
        self::assertCount(1, $recordingLogger->calls);
        self::assertSame($internalException, $recordingLogger->calls[0][2]['exception']);
    }

    public function testATypedClientErrorIsNotLogged(): void
    {
        $recordingLogger = new RecordingLogger();

        $this->middleware($recordingLogger)->process(
            $this->requestWithClaim(),
            new ThrowingHandler(new NotFoundException('User with ID 123 not found.')),
        );

        self::assertSame([], $recordingLogger->calls);
    }

    /**
     * The recording gate's boundary, both sides: a custom signal one step
     * below the server-error floor stays unrecorded, so a loosened gate
     * cannot hide behind the family's own statuses.
     */
    public function testAJustBelowServerErrorSignalIsNotLogged(): void
    {
        $recordingLogger = new RecordingLogger();
        $teapot = new class extends ZefException implements CarriesHttpStatus {
            #[Override]
            public function httpStatus(): int
            {
                return 499;
            }
        };

        $response = $this->middleware($recordingLogger)->process(
            $this->requestWithClaim(),
            new ThrowingHandler($teapot),
        );

        self::assertSame(499, $response->getStatusCode());
        self::assertSame([], $recordingLogger->calls);
    }

    /**
     * The typed family's redaction line of defence: a validation message
     * that embedded credential material never reaches the client, even in
     * dev mode.
     */
    public function testATypedValidationMessageIsRedactedInBothModes(): void
    {
        $response = $this->middleware()->process(
            $this->requestWithClaim(),
            new ThrowingHandler(new ValidationException('password=hunter2 is not acceptable')),
        );

        self::assertSame(400, $response->getStatusCode());
        self::assertSame(
            'password=[REDACTED] is not acceptable',
            $this->envelope($response)['message'],
        );
    }

    public function testAnotherLayerFailureAnswersBadRequestWithTheGenericText(): void
    {
        $response = $this->middleware()->process(
            $this->requestWithClaim(),
            new ThrowingHandler(new HttpException('Stream detached.')),
        );

        self::assertSame(400, $response->getStatusCode());
        self::assertSame('Bad Request', $response->getReasonPhrase());

        $envelope = $this->envelope($response);

        self::assertSame('Invalid request.', $envelope['message']);
        self::assertSame(self::CLAIMED_ID, $envelope['correlation_id']);
    }

    public function testALayerFailureAnswersItsValidationTextInDevMode(): void
    {
        $response = $this->middleware(debug: true)->process(
            $this->requestWithClaim(),
            new ThrowingHandler(new HttpException('Stream detached.')),
        );

        $envelope = $this->envelope($response);

        self::assertSame(400, $response->getStatusCode());
        self::assertSame('Stream detached.', $envelope['message']);
    }

    public function testAnApplicationFaultAnswersTheGenericInternalError(): void
    {
        $response = $this->middleware()->process(
            $this->requestWithClaim(),
            new ThrowingHandler(new RuntimeException('boom')),
        );

        self::assertSame(500, $response->getStatusCode());
        self::assertSame('Internal Server Error', $response->getReasonPhrase());

        $envelope = $this->envelope($response);

        self::assertSame('An error occurred', $envelope['message']);
        self::assertSame(self::CLAIMED_ID, $envelope['correlation_id']);
    }

    public function testAnApplicationFaultRedactsItsMessageInDevMode(): void
    {
        $response = $this->middleware(debug: true)->process(
            $this->requestWithClaim(),
            new ThrowingHandler(new RuntimeException('login failed for token=abcd1234')),
        );

        $envelope = $this->envelope($response);

        self::assertSame(500, $response->getStatusCode());
        self::assertSame('login failed for token=[REDACTED]', $envelope['message']);
    }

    public function testAnApplicationFaultIsLoggedWithRedactedContextAndRequestFacts(): void
    {
        $recordingLogger = new RecordingLogger();
        $runtimeException = new RuntimeException('token=abcd1234 broke');

        $response = new ErrorMappingMiddleware($recordingLogger)->process(
            $this->requestWithClaim('req-ctx-1'),
            new ThrowingHandler($runtimeException),
        );

        self::assertSame(500, $response->getStatusCode());
        self::assertCount(1, $recordingLogger->calls);

        [$level, $message, $context] = $recordingLogger->calls[0];

        self::assertSame('error', $level);
        self::assertSame('Unhandled application exception', $message);
        self::assertSame($runtimeException, $context['exception']);
        self::assertSame('token=[REDACTED] broke', $context['exception.message']);
        self::assertSame('req-ctx-1', $context['request_id']);
        self::assertSame('GET', $context['method']);
        self::assertSame('/users', $context['path']);
    }

    public function testALayerFailureIsNotLogged(): void
    {
        $recordingLogger = new RecordingLogger();

        $this->middleware($recordingLogger)->process(
            $this->requestWithClaim(),
            new ThrowingHandler(new HttpException('Stream detached.')),
        );

        self::assertSame([], $recordingLogger->calls);
    }

    public function testABrokenLoggerStillAnswersTheInternalError(): void
    {
        $this->routeErrorLog();

        $response = new ErrorMappingMiddleware(new FailingLogger(new LogicException('sink broken')))->process(
            $this->requestWithClaim(),
            new ThrowingHandler(new RuntimeException('boom')),
        );

        self::assertSame(500, $response->getStatusCode());

        $envelope = $this->envelope($response);

        self::assertSame('An error occurred', $envelope['message']);
        self::assertStringContainsString(
            'ZEF logging failure: ' . LogicException::class,
            $this->routedErrorLogContents(),
        );
    }

    public function testNoLoggerMeansNoSapiErrorLog(): void
    {
        $this->routeErrorLog();

        $response = $this->middleware()->process(
            $this->requestWithClaim(),
            new ThrowingHandler(new RuntimeException('boom')),
        );

        self::assertSame(500, $response->getStatusCode());
        self::assertStringNotContainsString(
            'ZEF logging failure',
            $this->routedErrorLogContents(),
            'An absent logger must not reach the SAPI error log.',
        );
    }

    /**
     * The double failure: dev-mode diagnostics over a message that is not
     * valid UTF-8 make the envelope's json_encode() throw, and the client
     * still receives the plain-text 500 with the correlation id.
     */
    public function testDevDiagnosticsOverInvalidUtf8FallBackToPlainText(): void
    {
        $response = $this->middleware(debug: true)->process(
            $this->requestWithClaim(),
            new ThrowingHandler(new RuntimeException("\xB1\x31 broken")),
        );

        self::assertSame(500, $response->getStatusCode());
        self::assertSame('text/plain', $response->getHeaderLine('Content-Type'));
        self::assertSame('Internal Server Error', (string) $response->getBody());
        self::assertSame(self::CLAIMED_ID, $response->getHeaderLine('X-Request-ID'));
    }

    public function testProdDiagnosticsNeverNeedTheFallback(): void
    {
        $response = $this->middleware()->process(
            $this->requestWithClaim(),
            new ThrowingHandler(new RuntimeException("\xB1\x31 broken")),
        );

        self::assertSame('application/json', $response->getHeaderLine('Content-Type'));

        $envelope = $this->envelope($response);

        self::assertSame('An error occurred', $envelope['message']);
    }

    private function middleware(?RecordingLogger $recordingLogger = null, bool $debug = false): ErrorMappingMiddleware
    {
        return new ErrorMappingMiddleware($recordingLogger, $debug);
    }

    private function requestWithClaim(string $claim = self::CLAIMED_ID): ServerRequest
    {
        return new ServerRequest(
            'GET',
            new Uri('https://app.example/users'),
            [],
            [],
            [],
            [],
            null,
            ['X-Request-ID' => $claim],
        );
    }

    /**
     * Routes the SAPI error log to a per-test file so the last-resort write
     * is observable; tearDown restores the previous destination and removes
     * the file.
     */
    private function routeErrorLog(): void
    {
        $this->routedErrorLog = sys_get_temp_dir() . '/zef-pr-m-errorlog-' . uniqid() . '.log';
        $this->previousErrorLog = ini_set('error_log', $this->routedErrorLog);
    }

    /**
     * Reads back whatever reached the routed SAPI error log (the empty
     * string when nothing did).
     */
    private function routedErrorLogContents(): string
    {
        self::assertNotNull($this->routedErrorLog, 'The test body routed the SAPI error log first.');

        $contents = @file_get_contents($this->routedErrorLog);

        return \is_string($contents) ? $contents : '';
    }

    /**
     * @return array<array-key, mixed> the decoded error envelope
     */
    private function envelope(ResponseInterface $response): array
    {
        $decoded = json_decode((string) $response->getBody(), true, 512, \JSON_THROW_ON_ERROR);

        if (!\is_array($decoded)) {
            self::fail('The error envelope did not decode to an object.');
        }

        return $decoded;
    }
}
