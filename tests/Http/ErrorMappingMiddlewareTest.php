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
use Zef\Framework\Config\Config;
use Zef\Framework\Config\ConfigException;
use Zef\Framework\Telemetry\CounterMeter;
use Zef\Framework\Http\CorrelationId;
use Zef\Framework\Http\ErrorContentNegotiator;
use Zef\Framework\Http\ErrorDetailsSanitizer;
use Zef\Framework\Http\ErrorDisclosure;
use Zef\Framework\Http\ErrorEnvelope;
use Zef\Framework\Http\ErrorFaultRecorder;
use Zef\Framework\Http\ErrorMappingFactory;
use Zef\Framework\Http\ErrorMappingMiddleware;
use Zef\Framework\Http\ErrorResponseFactory;
use Zef\Framework\Http\HtmlErrorPageRenderer;
use Zef\Framework\Http\ProblemDetailsMetadata;
use Zef\Framework\Telemetry\MeterSlot;
use Zef\Framework\Http\ProblemDetailsResponseFactory;
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
#[CoversClass(ErrorMappingFactory::class)]
#[CoversClass(ErrorReplayStore::class)]
#[CoversClass(ReplayPayloadSanitizer::class)]
#[CoversClass(CounterMeter::class)]
#[CoversClass(MeterSlot::class)]
#[CoversClass(ErrorFaultRecorder::class)]
#[CoversClass(ErrorEnvelope::class)]
#[CoversClass(CorrelationId::class)]
#[CoversClass(ErrorDisclosure::class)]
#[CoversClass(ErrorResponseFactory::class)]
#[CoversClass(ProblemDetailsResponseFactory::class)]
#[CoversClass(ErrorContentNegotiator::class)]
#[CoversClass(ErrorDetailsSanitizer::class)]
#[CoversClass(ProblemDetailsMetadata::class)]
#[CoversClass(HtmlErrorPageRenderer::class)]
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
        self::assertSame('application/problem+json; charset=utf-8', $response->getHeaderLine('Content-Type'));
        self::assertSame(self::CLAIMED_ID, $response->getHeaderLine('X-Request-ID'));

        $envelope = $this->envelope($response);

        self::assertSame('https://zef.io/errors/content-too-large', $envelope['type']);
        self::assertSame('Content Too Large', $envelope['title']);
        self::assertSame(413, $envelope['status']);
        self::assertSame('Content Too Large', $envelope['detail']);
        self::assertSame(self::CLAIMED_ID, $envelope['trace_id']);
    }

    public function testASyntheticBudgetRefusalIsMappedTheSameWay(): void
    {
        $response = $this->middleware()->process(
            $this->requestWithClaim(),
            new ThrowingHandler(new PayloadTooLargeException()),
        );

        self::assertSame(413, $response->getStatusCode());
        self::assertSame('Content Too Large', $this->envelope($response)['detail']);
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
        self::assertSame('Invalid request.', $this->envelope($response)['detail']);
    }

    public function testATypedNotFoundAnswersItsStatusAndPublicMessage(): void
    {
        $response = $this->middleware()->process(
            $this->requestWithClaim(),
            new ThrowingHandler(new NotFoundException('User with ID 123 not found.')),
        );

        self::assertSame(404, $response->getStatusCode());
        self::assertSame('Not Found', $response->getReasonPhrase());
        self::assertSame('application/problem+json; charset=utf-8', $response->getHeaderLine('Content-Type'));

        $envelope = $this->envelope($response);

        self::assertSame(404, $envelope['status']);
        self::assertSame('User with ID 123 not found.', $envelope['detail']);
        self::assertSame(self::CLAIMED_ID, $envelope['trace_id']);
        self::assertSame(self::CLAIMED_ID, $response->getHeaderLine('X-Request-ID'));
    }

    public function testATypedMethodNotAllowedAnswersItsStatus(): void
    {
        $response = $this->middleware()->process(
            $this->requestWithClaim(),
            new ThrowingHandler(new MethodNotAllowedException('GET', '/users', ['POST', 'PUT'])),
        );

        self::assertSame(405, $response->getStatusCode());
        self::assertSame("Method 'GET' is not allowed for /users.", $this->envelope($response)['detail']);
        self::assertSame('POST, PUT', $response->getHeaderLine('Allow'));
    }

    public function testATypedRateLimitAnswersItsStatus(): void
    {
        $response = $this->middleware()->process(
            $this->requestWithClaim(),
            new ThrowingHandler(new RateLimitException(30, 'Too many sign-in attempts.')),
        );

        self::assertSame(429, $response->getStatusCode());
        self::assertSame('Too many sign-in attempts.', $this->envelope($response)['detail']);
        self::assertSame('30', $response->getHeaderLine('Retry-After'));
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
            $this->envelope($response)['detail'],
        );
        self::assertCount(1, $recordingLogger->calls);
        self::assertSame($internalException, $recordingLogger->calls[0][2]['exception']);
    }

    public function testATypedClientErrorIsLoggedAsWarning(): void
    {
        $recordingLogger = new RecordingLogger();

        $this->middleware($recordingLogger)->process(
            $this->requestWithClaim(),
            new ThrowingHandler(new NotFoundException('User with ID 123 not found.')),
        );

        self::assertCount(1, $recordingLogger->calls);
        self::assertSame('warning', $recordingLogger->calls[0][0]);
    }

    /**
     * The recording gate's boundary, both sides: a custom signal one step
     * below the server-error floor stays unrecorded, so a loosened gate
     * cannot hide behind the family's own statuses.
     */
    public function testAClientErrorStatusBelowServerErrorIsLoggedAsWarning(): void
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
        self::assertCount(1, $recordingLogger->calls);
        self::assertSame('warning', $recordingLogger->calls[0][0]);
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
            $this->envelope($response)['detail'],
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

        self::assertSame('Invalid request.', $envelope['detail']);
        self::assertSame(self::CLAIMED_ID, $envelope['trace_id']);
    }

    public function testALayerFailureAnswersItsValidationTextInDevMode(): void
    {
        $response = $this->middleware(debug: true)->process(
            $this->requestWithClaim(),
            new ThrowingHandler(new HttpException('Stream detached.')),
        );

        $envelope = $this->envelope($response);

        self::assertSame(400, $response->getStatusCode());
        self::assertSame('Stream detached.', $envelope['detail']);
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

        self::assertSame('An error occurred', $envelope['detail']);
        self::assertSame(self::CLAIMED_ID, $envelope['trace_id']);
    }

    public function testAnApplicationFaultRedactsItsMessageInDevMode(): void
    {
        $response = $this->middleware(debug: true)->process(
            $this->requestWithClaim(),
            new ThrowingHandler(new RuntimeException('login failed for token=abcd1234')),
        );

        $envelope = $this->envelope($response);

        self::assertSame(500, $response->getStatusCode());
        self::assertSame('login failed for token=[REDACTED]', $envelope['detail']);
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

        self::assertSame('critical', $level);
        self::assertSame('Request failed', $message);
        self::assertSame($runtimeException, $context['exception']);
        self::assertSame('token=[REDACTED] broke', $context['exception.message']);
        self::assertSame('req-ctx-1', $context['request_id']);
        self::assertSame('req-ctx-1', $context['trace_id']);
        self::assertSame(RuntimeException::class, $context['exception_class']);
        self::assertSame('GET', $context['method']);
        self::assertSame('/users', $context['path']);
    }

    public function testALayerFailureIsLoggedAsWarning(): void
    {
        $recordingLogger = new RecordingLogger();

        $this->middleware($recordingLogger)->process(
            $this->requestWithClaim(),
            new ThrowingHandler(new HttpException('Stream detached.')),
        );

        self::assertCount(1, $recordingLogger->calls);
        self::assertSame('warning', $recordingLogger->calls[0][0]);
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

        self::assertSame('An error occurred', $envelope['detail']);
        self::assertStringContainsString(
            'ZEF logging failure: ' . LogicException::class,
            $this->routedErrorLogContents(),
        );
    }

    public function testNoLoggerUsesStructuredSapiErrorLog(): void
    {
        $this->routeErrorLog();

        $response = $this->middleware()->process(
            $this->requestWithClaim(),
            new ThrowingHandler(new RuntimeException('boom')),
        );

        self::assertSame(500, $response->getStatusCode());
        self::assertStringContainsString('"level":"critical"', $this->routedErrorLogContents());
        self::assertStringContainsString('"exception_class":"RuntimeException"', $this->routedErrorLogContents());
        self::assertStringContainsString('"exception.message":"boom"', $this->routedErrorLogContents());
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

        self::assertSame('application/problem+json; charset=utf-8', $response->getHeaderLine('Content-Type'));

        $envelope = $this->envelope($response);

        self::assertSame('An error occurred', $envelope['detail']);
    }

    public function testProblemDetailsUseStableCodesAndOmitQueryStrings(): void
    {
        $request = new ServerRequest(
            'GET',
            new Uri('https://app.example/users?token=secret'),
            [],
            [],
            [],
            [],
            null,
            ['X-Request-ID' => self::CLAIMED_ID, 'Accept' => 'application/problem+json'],
        );

        $response = $this->middleware()->process(
            $request,
            new ThrowingHandler(new NotFoundException('User not found.')),
        );
        $problem = $this->envelope($response);

        self::assertSame('application/problem+json; charset=utf-8', $response->getHeaderLine('Content-Type'));
        self::assertSame('NOT_FOUND', $problem['code']);
        self::assertSame('/users', $problem['instance']);
        self::assertSame(self::CLAIMED_ID, $problem['trace_id']);
        self::assertStringNotContainsString('secret', (string) $response->getBody());
        self::assertSame('no-store', $response->getHeaderLine('Cache-Control'));
        self::assertSame('Accept', $response->getHeaderLine('Vary'));
    }

    public function testHtmlFallbackEscapesUntrustedExceptionMessages(): void
    {
        $response = $this->middleware()->process(
            $this->requestWithClaim(self::CLAIMED_ID, 'text/html'),
            new ThrowingHandler(new NotFoundException('<script>alert(1)</script>')),
        );

        self::assertSame('text/html; charset=utf-8', $response->getHeaderLine('Content-Type'));
        self::assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', (string) $response->getBody());
        self::assertStringNotContainsString('<script>', (string) $response->getBody());
    }

    public function testDeveloperDetailsAreOnlyIncludedInDebugProblemResponsesAndRedacted(): void
    {
        $exception = new class(
            'Invalid input.',
            0,
            null,
            'INVALID_INPUT',
            ['diagnostic' => 'password=secret123'],
        ) extends ZefException {
        };
        $request = new ServerRequest(
            'POST',
            new Uri('https://app.example/users'),
            [],
            [],
            [],
            [],
            null,
            ['X-Request-ID' => self::CLAIMED_ID, 'Accept' => 'application/problem+json'],
        );

        $response = $this->middleware(debug: true)->process($request, new ThrowingHandler($exception));
        $problem = $this->envelope($response);

        self::assertSame('INVALID_INPUT', $problem['code']);
        self::assertSame('password=[REDACTED]', $problem['details']['diagnostic']);
        self::assertStringNotContainsString('secret123', (string) $response->getBody());
    }

    public function testContentNegotiationHonoursExplicitQualityValues(): void
    {
        self::assertFalse(ErrorContentNegotiator::prefersHtml(''));
        self::assertFalse(ErrorContentNegotiator::prefersHtml('*/*'));
        self::assertFalse(ErrorContentNegotiator::prefersHtml('text/html;q=1.5'));
        self::assertFalse(ErrorContentNegotiator::prefersHtml('text/html;q=0.4, application/problem+json;q=0.8'));
        self::assertTrue(ErrorContentNegotiator::prefersHtml('text/html;q=0.8, application/json;q=0.5'));
    }

    public function testProblemMetadataMapsCommonAndUnknownStatuses(): void
    {
        self::assertSame('https://zef.io/errors/authentication-required', ProblemDetailsMetadata::typeFor(401));
        self::assertSame('Forbidden', ProblemDetailsMetadata::titleFor(403));
        self::assertSame('https://zef.io/errors/conflict', ProblemDetailsMetadata::typeFor(409));
        self::assertSame('Internal Server Error', ProblemDetailsMetadata::titleFor(418));
    }

    public function testDiagnosticSanitizerBoundsDepthAndFiltersUnsupportedValues(): void
    {
        $details = [
            'text' => 'password=secret123',
            'integer' => 42,
            'float' => 1.5,
            'boolean' => true,
            'nothing' => null,
            'nested' => ['safe' => 'value'],
            'object' => new RuntimeException('not serializable'),
            'bad key!' => 'must be omitted',
            9 => 'numeric key must be omitted',
        ];
        $deep = ['value' => 'bottom'];
        for ($depth = 0; $depth < 5; ++$depth) {
            $deep = ['next' => $deep];
        }
        $details['deep'] = $deep;

        $safe = ErrorDetailsSanitizer::sanitize($details);

        self::assertSame('password=[REDACTED]', $safe['text']);
        self::assertSame(42, $safe['integer']);
        self::assertSame(1.5, $safe['float']);
        self::assertTrue($safe['boolean']);
        self::assertNull($safe['nothing']);
        self::assertSame('value', $safe['nested']['safe']);
        self::assertSame('[RuntimeException]', $safe['object']);
        self::assertArrayNotHasKey('bad key!', $safe);
        self::assertArrayNotHasKey(9, $safe);
        self::assertTrue($safe['deep']['next']['next']['next']['_truncated']);
    }

    public function testFactoryEnablesDebugOnlyInExplicitDevelopmentEnvironments(): void
    {
        $previousEnvironment = getenv('APP_ENV');

        try {
            putenv('APP_ENV=local');
            $developmentMiddleware = ErrorMappingFactory::fromConfig(new Config([
                'http' => ['error_handling' => ['debug' => true]],
            ]));
            $developmentResponse = $developmentMiddleware->process(
                $this->requestWithClaim(),
                new ThrowingHandler(new RuntimeException('diagnostic-visible')),
            );
            self::assertSame('diagnostic-visible', $this->envelope($developmentResponse)['detail']);

            putenv('APP_ENV=production');
            $productionMiddleware = ErrorMappingFactory::fromConfig(new Config([
                'http' => ['error_handling' => ['debug' => true]],
            ]));
            $productionResponse = $productionMiddleware->process(
                $this->requestWithClaim(),
                new ThrowingHandler(new RuntimeException('must-not-leak')),
            );
            self::assertSame('An error occurred', $this->envelope($productionResponse)['detail']);
            self::assertStringNotContainsString('must-not-leak', (string) $productionResponse->getBody());
        } finally {
            if ($previousEnvironment === false) {
                putenv('APP_ENV');
            } else {
                putenv('APP_ENV=' . $previousEnvironment);
            }
        }
    }

    public function testErrorCounterIsRecordedOnlyWhenMetricsAreEnabled(): void
    {
        $meter = new CounterMeter();
        $middleware = ErrorMappingFactory::fromConfig(new Config([
            'http' => ['error_handling' => ['debug' => false]],
            'observability' => ['metrics' => ['enabled' => true]],
        ]), $meter);

        $middleware->process(
            $this->requestWithClaim('metric-req'),
            new ThrowingHandler(new NotFoundException('Not found.')),
        );

        $series = array_values($meter->snapshot());
        self::assertCount(1, $series);
        self::assertSame(1, $series[0]['count']);
        self::assertSame(1.0, $series[0]['sum']);
        self::assertSame([
            'exception.type' => 'NotFoundException',
            'http.request.method' => 'GET',
            'http.response.status_code' => 404,
        ], $series[0]['attributes']);
    }

    public function testFactoryCapturesReplayOnlyWhenExplicitlyEnabledLocally(): void
    {
        $previousEnvironment = getenv('APP_ENV');
        $directory = sys_get_temp_dir() . '/zef-replay-middleware-' . bin2hex(random_bytes(8));
        mkdir($directory, 0700);
        $resource = fopen('php://temp', 'r+b');
        self::assertIsResource($resource);
        fwrite($resource, '{"email":"person@example.test","password":"hunter2"}');
        rewind($resource);

        try {
            putenv('APP_ENV=local');
            $middleware = ErrorMappingFactory::fromConfig(new Config([
                'http' => [
                    'error_handling' => [
                        'debug' => true,
                        'log_path' => '',
                        'replay_enabled' => true,
                        'replay_path' => $directory,
                    ],
                ],
            ]));
            $request = new ServerRequest(
                'POST',
                new Uri('https://app.example.test/users?token=secret123'),
                [],
                [],
                [],
                [],
                null,
                ['X-Request-ID' => 'replay-req', 'Content-Type' => 'application/json'],
                new Stream($resource),
            );
            $response = $middleware->process($request, new ThrowingHandler(new RuntimeException('replay failure')));
            $files = glob($directory . '/*.json') ?: [];
            self::assertCount(1, $files);

            $snapshot = json_decode((string) file_get_contents($files[0]), true, 512, JSON_THROW_ON_ERROR);
            self::assertSame('replay-req', $snapshot['request_id']);
            self::assertTrue($snapshot['body_replayable']);
            self::assertStringNotContainsString('secret123', (string) file_get_contents($files[0]));
            self::assertStringNotContainsString('hunter2', (string) file_get_contents($files[0]));
            self::assertSame('replay-req', $this->envelope($response)['trace_id']);
        } finally {
            if ($previousEnvironment === false) {
                putenv('APP_ENV');
            } else {
                putenv('APP_ENV=' . $previousEnvironment);
            }
            foreach (glob($directory . '/*') ?: [] as $file) {
                @unlink($file);
            }
            @rmdir($directory);
        }
    }

    public function testReplayConfigurationFailsClosedOutsideLocalDebugMode(): void
    {
        $previousEnvironment = getenv('APP_ENV');
        putenv('APP_ENV=production');
        $directory = sys_get_temp_dir();

        try {
            $this->expectException(ConfigException::class);
            ErrorMappingFactory::fromConfig(new Config([
                'http' => [
                    'error_handling' => [
                        'debug' => true,
                        'replay_enabled' => true,
                        'replay_path' => $directory,
                    ],
                ],
            ]));
        } finally {
            if ($previousEnvironment === false) {
                putenv('APP_ENV');
            } else {
                putenv('APP_ENV=' . $previousEnvironment);
            }
        }
    }

    public function testFactoryWiresConfiguredJsonlLogDestination(): void
    {
        $logPath = sys_get_temp_dir() . '/zef-error-factory-' . bin2hex(random_bytes(8)) . '.jsonl';

        try {
            $middleware = ErrorMappingFactory::fromConfig(new Config([
                'http' => ['error_handling' => ['debug' => false, 'log_path' => $logPath]],
            ]));
            $middleware->process(
                $this->requestWithClaim('factory-req'),
                new ThrowingHandler(new RuntimeException('factory failure')),
            );

            $contents = file_get_contents($logPath);
            self::assertIsString($contents);
            self::assertStringContainsString('"level":"critical"', $contents);
            self::assertStringContainsString('"request_id":"factory-req"', $contents);
            self::assertStringContainsString('"exception_class":"RuntimeException"', $contents);
        } finally {
            @unlink($logPath);
        }
    }

    public function testFactoryRejectsNonStringLogPathConfiguration(): void
    {
        $this->expectException(ConfigException::class);
        ErrorMappingFactory::fromConfig(new Config([
            'http' => ['error_handling' => ['log_path' => []]],
        ]));
    }

    public function testFactoryRejectsNonBooleanDebugConfiguration(): void
    {
        $this->expectException(ConfigException::class);
        ErrorMappingFactory::fromConfig(new Config([
            'http' => ['error_handling' => ['debug' => 'true']],
        ]));
    }

    public function testHtmlDebugPageIncludesSanitizedDeveloperContextAndSecurityHeaders(): void
    {
        $exception = new class(
            'Failed safely.',
            0,
            null,
            'FAILED_SAFELY',
            ['credential' => 'token=secret123'],
        ) extends ZefException {
        };
        $response = $this->middleware(debug: true)->process(
            $this->requestWithClaim(self::CLAIMED_ID, 'text/html'),
            new ThrowingHandler($exception),
        );

        self::assertSame("default-src 'none'; style-src 'unsafe-inline'; base-uri 'none'; frame-ancestors 'none'", $response->getHeaderLine('Content-Security-Policy'));
        self::assertStringContainsString('Developer context', (string) $response->getBody());
        self::assertStringContainsString('ZEF Profiler', (string) $response->getBody());
        self::assertStringContainsString('Exceptions: 1', (string) $response->getBody());
        self::assertStringNotContainsString('secret123', (string) $response->getBody());
    }

    private function middleware(?RecordingLogger $recordingLogger = null, bool $debug = false): ErrorMappingMiddleware
    {
        return new ErrorMappingMiddleware($recordingLogger, $debug);
    }

    private function requestWithClaim(
        string $claim = self::CLAIMED_ID,
        ?string $accept = null,
    ): ServerRequest
    {
        return new ServerRequest(
            'GET',
            new Uri('https://app.example/users'),
            [],
            [],
            [],
            [],
            null,
            $accept === null
                ? ['X-Request-ID' => $claim]
                : ['X-Request-ID' => $claim, 'Accept' => $accept],
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
