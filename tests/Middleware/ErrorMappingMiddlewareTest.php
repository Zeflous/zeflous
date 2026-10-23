<?php

declare(strict_types=1);

namespace Zef\Test\Middleware;

use InvalidArgumentException;
use LogicException;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Zef\Framework\Config\Config;
use Zef\Framework\Config\ConfigException;
use Zef\Framework\Exception\CarriesHttpStatus;
use Zef\Framework\Exception\InternalException;
use Zef\Framework\Exception\MethodNotAllowedException;
use Zef\Framework\Exception\NotFoundException;
use Zef\Framework\Exception\RateLimitException;
use Zef\Framework\Exception\ValidationException;
use Zef\Framework\Exception\ZefException;
use Zef\Framework\Http\AcceptHeaderParser;
use Zef\Framework\Http\CorrelationId;
use Zef\Framework\Http\ErrorContentNegotiator;
use Zef\Framework\Http\ErrorDetailKeys;
use Zef\Framework\Http\ErrorDetailsSanitizer;
use Zef\Framework\Http\ErrorDisclosure;
use Zef\Framework\Http\ErrorEnvelope;
use Zef\Framework\Http\ErrorFaultContextFactory;
use Zef\Framework\Http\ErrorFaultLogWriter;
use Zef\Framework\Http\ErrorFaultSeverity;
use Zef\Framework\Http\ErrorReplayStore;
use Zef\Framework\Http\ErrorRequestContext;
use Zef\Framework\Http\ErrorResponseFactory;
use Zef\Framework\Http\ErrorTraceIdResolver;
use Zef\Framework\Http\FallbackResponse;
use Zef\Framework\Http\HtmlErrorPageContext;
use Zef\Framework\Http\HtmlErrorPageRenderer;
use Zef\Framework\Http\HttpException;
use Zef\Framework\Http\PayloadTooLargeException;
use Zef\Framework\Http\ProblemDetailsJsonResponseFactory;
use Zef\Framework\Http\ProblemDetailsMetadata;
use Zef\Framework\Http\ProblemDetailsResponseFactory;
use Zef\Framework\Http\ProblemDetailsResponseHeaders;
use Zef\Framework\Http\RedactedMessage;
use Zef\Framework\Http\RedactionCandidate;
use Zef\Framework\Http\ReplayPayloadSanitizer;
use Zef\Framework\Http\RequestBodyPolicy;
use Zef\Framework\Http\RequestFactory;
use Zef\Framework\Http\Response;
use Zef\Framework\Http\ServerRequest;
use Zef\Framework\Http\Stream;
use Zef\Framework\Http\Uri;
use Zef\Framework\Middleware\ErrorFaultMetricsRecorder;
use Zef\Framework\Middleware\ErrorFaultRecorder;
use Zef\Framework\Middleware\ErrorMappingFactory;
use Zef\Framework\Middleware\ErrorMappingMiddleware;
use Zef\Framework\Middleware\ErrorReplayWiring;
use Zef\Framework\Telemetry\CounterMeter;
use Zef\Framework\Telemetry\MeterInterface;
use Zef\Framework\Telemetry\MeterSlot;
use Zef\Test\Support\Exception\Excëption\LocaleFailure;
use Zef\Test\Support\Exception\ExplanatoryException;
use Zef\Test\Support\Exception\ForgedTraceIdException;
use Zef\Test\Support\Exception\ThrowingStatusException;
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
#[CoversClass(ErrorReplayWiring::class)]
#[CoversClass(ErrorReplayStore::class)]
#[CoversClass(ReplayRetention::class)]
#[CoversClass(ReplayPayloadSanitizer::class)]
#[CoversClass(CounterMeter::class)]
#[CoversClass(MeterSlot::class)]
#[CoversClass(ErrorFaultRecorder::class)]
#[CoversClass(ErrorEnvelope::class)]
#[CoversClass(CorrelationId::class)]
#[CoversClass(ErrorDisclosure::class)]
#[CoversClass(ErrorResponseFactory::class)]
#[CoversClass(ProblemDetailsJsonResponseFactory::class)]
#[CoversClass(ProblemDetailsResponseHeaders::class)]
#[CoversClass(HtmlErrorPageContext::class)]
#[CoversClass(ErrorTraceIdResolver::class)]
#[CoversClass(AcceptHeaderParser::class)]
#[CoversClass(ErrorFaultSeverity::class)]
#[CoversClass(ErrorFaultMetricsRecorder::class)]
#[CoversClass(ErrorFaultContextFactory::class)]
#[CoversClass(ErrorFaultLogWriter::class)]
#[CoversClass(ProblemDetailsResponseFactory::class)]
#[CoversClass(ErrorContentNegotiator::class)]
#[CoversClass(ErrorDetailsSanitizer::class)]
#[CoversClass(ErrorDetailKeys::class)]
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
    protected function setUp(): void
    {
        // Every construction lane in this battery -- the bare middleware,
        // the factory's config-built wiring, the logger that fails -- can
        // reach the fault path's last-resort SAPI write. Routing that
        // destination once up front keeps those writes observable and off
        // the process's real stderr: Infection's initial test run
        // terminates the suite on the first stderr byte it sees, so an
        // unrouted write kills the mutation lane before a single mutant
        // is generated.
        $this->routeErrorLog();
    }

    #[Override]
    protected function tearDown(): void
    {
        if ($this->routedErrorLog === null) {
            return;
        }

        ini_set('error_log', (string) $this->previousErrorLog);
        if (is_file($this->routedErrorLog) && !unlink($this->routedErrorLog)) {
            throw new RuntimeException('Unable to remove the temporary error log.');
        }
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
        self::assertSame(InternalException::class, $recordingLogger->calls[0][2]['exception_class']);
        self::assertArrayNotHasKey('exception', $recordingLogger->calls[0][2]);
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

    public function testDevModeRedactsAndBoundsUntrustedHttpExceptionMessages(): void
    {
        $response = $this->middleware(debug: true)->process(
            $this->requestWithClaim(),
            new ThrowingHandler(new HttpException('token=secret123 is invalid')),
        );

        self::assertSame(400, $response->getStatusCode());
        self::assertSame('token=[REDACTED] is invalid', $this->envelope($response)['detail']);
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
        self::assertArrayNotHasKey('exception', $context);
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
     * Invalid UTF-8 in a diagnostic message must be substituted rather than
     * changing the response media type or forcing the fallback response.
     */
    public function testDevDiagnosticsOverInvalidUtf8RemainValidProblemDetails(): void
    {
        $response = $this->middleware(debug: true)->process(
            $this->requestWithClaim(),
            new ThrowingHandler(new RuntimeException("\xB1\x31 broken")),
        );

        self::assertSame(500, $response->getStatusCode());
        self::assertSame('application/problem+json; charset=utf-8', $response->getHeaderLine('Content-Type'));
        self::assertSame(self::CLAIMED_ID, $response->getHeaderLine('X-Request-ID'));
        $detail = $this->envelope($response)['detail'];
        self::assertIsString($detail);
        self::assertStringContainsString('broken', $detail);
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
        $serverRequest = new ServerRequest(
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
            $serverRequest,
            new ThrowingHandler(new NotFoundException('User not found.')),
        );
        $problem = $this->envelope($response);

        self::assertSame('application/problem+json; charset=utf-8', $response->getHeaderLine('Content-Type'));
        self::assertSame('N_F', $problem['code']);
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
        $explanatoryException = new ExplanatoryException(
            'Invalid input.',
            0,
            null,
            'INVALID_INPUT',
            ['diagnostic' => 'password=secret123'],
        );
        $serverRequest = new ServerRequest(
            'POST',
            new Uri('https://app.example/users'),
            [],
            [],
            [],
            [],
            null,
            ['X-Request-ID' => self::CLAIMED_ID, 'Accept' => 'application/problem+json'],
        );

        $response = $this->middleware(debug: true)->process($serverRequest, new ThrowingHandler($explanatoryException));
        $problem = $this->envelope($response);
        $details = $problem['details'] ?? null;

        self::assertSame('INVALID_INPUT', $problem['code']);
        self::assertIsArray($details);
        self::assertSame('password=[REDACTED]', $details['diagnostic']);
        self::assertStringNotContainsString('secret123', (string) $response->getBody());
    }

    public function testContentNegotiationHonoursExplicitQualityValues(): void
    {
        self::assertFalse(ErrorContentNegotiator::prefersHtml(''));
        self::assertFalse(ErrorContentNegotiator::prefersHtml('*/*'));
        self::assertFalse(ErrorContentNegotiator::prefersHtml('text/html;q=1.5'));
        self::assertFalse(ErrorContentNegotiator::prefersHtml('text/html;q=0.4, application/problem+json;q=0.8'));
        self::assertTrue(ErrorContentNegotiator::prefersHtml('text/html;q=0.8, application/json;q=0.5'));
        self::assertFalse(ErrorContentNegotiator::prefersHtml('text/html;q=0, */*;q=1'));
        self::assertTrue(
            ErrorContentNegotiator::prefersHtml('application/problem+json;q=0, text/html;q=0.2, */*;q=0.1'),
        );
        self::assertFalse(ErrorContentNegotiator::prefersHtml('text/html;q=.8, application/problem+json;q=.5'));
        self::assertFalse(ErrorContentNegotiator::prefersHtml('invalid-media-range, application/problem+json;q=0.8'));
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
        $nested = $safe['nested'] ?? null;
        self::assertIsArray($nested);
        self::assertSame('value', $nested['safe']);
        self::assertSame('[RuntimeException]', $safe['object']);
        self::assertArrayNotHasKey('bad key!', $safe);
        self::assertArrayNotHasKey(9, $safe);
        $deep = $safe['deep'] ?? null;
        self::assertIsArray($deep);
        for ($hop = 0; $hop < 3; ++$hop) {
            $deep = $deep['next'] ?? null;
            self::assertIsArray($deep);
        }

        self::assertTrue($deep['_truncated']);
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

    /**
     * An unset debug key answers the production posture, not the
     * development one: the default is the safe false, so a configuration
     * that never mentions the knob still hides the diagnostic message in
     * the very environment the knob would have unlocked.
     */
    public function testTheFactoryDefaultsAnUnsetDebugKeyToTheProductionPosture(): void
    {
        $previousEnvironment = getenv('APP_ENV');

        try {
            putenv('APP_ENV=local');
            $middleware = ErrorMappingFactory::fromConfig(new Config([]));

            $response = $middleware->process(
                $this->requestWithClaim(),
                new ThrowingHandler(new RuntimeException('never-disclosed')),
            );

            self::assertSame('An error occurred', $this->envelope($response)['detail']);
            self::assertStringNotContainsString('never-disclosed', (string) $response->getBody());
        } finally {
            if ($previousEnvironment === false) {
                putenv('APP_ENV');
            } else {
                putenv('APP_ENV=' . $previousEnvironment);
            }
        }
    }

    /**
     * An unset metrics key answers the silent arm, not the recorded one:
     * the default is the safe false, so a configuration that never
     * mentions the knob leaves the injected meter untouched -- the error
     * still answers, and the counter stays empty.
     */
    public function testTheFactoryDefaultsAnUnsetMetricsKeyToTheSilentArm(): void
    {
        $counterMeter = new CounterMeter();
        $middleware = ErrorMappingFactory::fromConfig(new Config([]), $counterMeter);

        $response = $middleware->process(
            $this->requestWithClaim('unset-metrics'),
            new ThrowingHandler(new RuntimeException('silent failure')),
        );

        self::assertSame(500, $response->getStatusCode());
        self::assertSame([], $counterMeter->snapshot(), 'The default metrics posture leaves the meter untouched.');
    }

    public function testErrorCounterIsRecordedOnlyWhenMetricsAreEnabled(): void
    {
        $counterMeter = new CounterMeter();
        $middleware = ErrorMappingFactory::fromConfig(new Config([
            'http' => ['error_handling' => ['debug' => false]],
            'observability' => ['metrics' => ['enabled' => true]],
        ]), $counterMeter);

        $middleware->process(
            $this->requestWithClaim('metric-req'),
            new ThrowingHandler(new NotFoundException('Not found.')),
        );

        $series = array_values($counterMeter->snapshot());
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
        mkdir($directory, 0o700);
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
            $serverRequest = new ServerRequest(
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
            $response = $middleware->process(
                $serverRequest,
                new ThrowingHandler(new RuntimeException('replay failure')),
            );
            $files = glob($directory . '/*.json');
            $files = $files === false ? [] : $files;
            self::assertCount(1, $files);

            $snapshot = json_decode((string) file_get_contents($files[0]), true, 512, \JSON_THROW_ON_ERROR);
            self::assertIsArray($snapshot);
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

            $leftover = glob($directory . '/*');
            foreach (\is_array($leftover) ? $leftover : [] as $file) {
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

    public function testTheReplayRetentionBoundFailsClosedOnNonPositiveValues(): void
    {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessageIsOrContains(
            'http.error_handling.replay_max_snapshots must be an integer of 1 or above.',
        );

        ErrorMappingFactory::fromConfig(new Config([
            'http' => [
                'error_handling' => [
                    'replay_max_snapshots' => 0,
                ],
            ],
        ]));
    }

    public function testFactoryWiresTheConfiguredRetentionBound(): void
    {
        $previousEnvironment = getenv('APP_ENV');
        $directory = sys_get_temp_dir() . '/zef-replay-retention-mw-' . bin2hex(random_bytes(8));
        mkdir($directory, 0o700);

        try {
            putenv('APP_ENV=local');
            $middleware = ErrorMappingFactory::fromConfig(new Config([
                'http' => [
                    'error_handling' => [
                        'debug' => true,
                        'log_path' => '',
                        'replay_enabled' => true,
                        'replay_path' => $directory,
                        'replay_max_snapshots' => 1,
                    ],
                ],
            ]));

            $serverRequest = new ServerRequest('GET', new Uri('https://app.example.test/retention'));

            $middleware->process($serverRequest, new ThrowingHandler(new RuntimeException('first failure')));
            $filesAfterFirst = glob($directory . '/*.json');
            self::assertIsArray($filesAfterFirst);
            self::assertCount(1, $filesAfterFirst);
            self::assertTrue(touch($filesAfterFirst[0], 1_000_000));

            $middleware->process($serverRequest, new ThrowingHandler(new RuntimeException('second failure')));

            $filesAfterSecond = glob($directory . '/*.json');
            self::assertIsArray($filesAfterSecond);
            self::assertCount(1, $filesAfterSecond, 'the retention bound holds through the factory wiring');
            self::assertNotSame(
                $filesAfterFirst[0],
                $filesAfterSecond[0],
                'the oldest snapshot was pruned, not the newest',
            );
        } finally {
            if ($previousEnvironment === false) {
                putenv('APP_ENV');
            } else {
                putenv('APP_ENV=' . $previousEnvironment);
            }

            $leftover = glob($directory . '/*');
            foreach (\is_array($leftover) ? $leftover : [] as $file) {
                @unlink($file);
            }

            @rmdir($directory);
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
        $explanatoryException = new ExplanatoryException(
            'Failed safely.',
            0,
            null,
            'FAILED_SAFELY',
            ['credential' => 'token=secret123'],
        );
        $response = $this->middleware(debug: true)->process(
            $this->requestWithClaim(self::CLAIMED_ID, 'text/html'),
            new ThrowingHandler($explanatoryException),
        );

        self::assertSame(
            "default-src 'none'; style-src 'unsafe-inline'; base-uri 'none'; frame-ancestors 'none'",
            $response->getHeaderLine('Content-Security-Policy'),
        );
        self::assertStringContainsString('Developer context', (string) $response->getBody());
        self::assertStringContainsString('ZEF Profiler', (string) $response->getBody());
        self::assertStringContainsString('Exceptions: 1', (string) $response->getBody());
        self::assertStringNotContainsString('secret123', (string) $response->getBody());
    }

    public function testStatusMappingFailureFallsBackToInternalServerError(): void
    {
        $throwingStatusException = new ThrowingStatusException('mapping failure');

        $response = $this->middleware()->process(
            $this->requestWithClaim(),
            new ThrowingHandler($throwingStatusException),
        );

        self::assertSame(500, $response->getStatusCode());
        self::assertSame('Internal Server Error', $this->envelope($response)['title']);
    }

    /**
     * The mapping-failure fallback is observable at all three of its
     * seams: the SAPI log line names the mapping's own failure (the
     * class whose consultation crashed, prefixed so no later grep can
     * mistake it for the application's failure), the fault record wraps
     * the crash into a plain RuntimeException whose code and message the
     * error lane carries forward, and the dev-mode envelope discloses
     * the wrapper's message rather than the mapping's own words.
     */
    public function testStatusMappingFailureLogsWrapsAndDisclosesTheFallback(): void
    {
        $recordingLogger = new RecordingLogger();
        $throwingStatusException = new ThrowingStatusException('mapping failure');

        $response = $this->middleware($recordingLogger, true)->process(
            $this->requestWithClaim(),
            new ThrowingHandler($throwingStatusException),
        );

        self::assertSame(500, $response->getStatusCode());
        self::assertSame('Error mapping failed.', $this->envelope($response)['detail']);

        $routedLog = $this->routedErrorLogContents();
        self::assertSame(
            1,
            mb_substr_count($routedLog, "\n"),
            'Exactly one line reaches the SAPI log for one mapping failure.',
        );
        self::assertStringEndsWith(
            'ZEF error status mapping failed: RuntimeException' . "\n",
            $routedLog,
            'The SAPI log line must name the class whose consultation crashed, prefixed as the mapping failure it is.',
        );

        self::assertCount(1, $recordingLogger->calls);
        [$level, $message, $context] = $recordingLogger->calls[0];
        self::assertSame('critical', $level);
        self::assertSame('Request failed', $message);
        self::assertSame('Error mapping failed.', $context['exception.message']);
        self::assertSame(RuntimeException::class, $context['exception_class']);
        self::assertSame(
            '0',
            $context['exception_code'],
            'The wrapper carries the neutral zero code, not a mutation of it.',
        );
    }

    /**
     * The double failure is observable at both of its seams: the client
     * still receives the plain-text 500 with the correlation id, and the
     * SAPI log names the construction failure's own class -- prefixed, so
     * no later grep can mistake it for the application's failure -- the
     * same visibility doctrine the mapping failure's line carries. The
     * trigger is the JSON arm's honest refusal of an unencodable
     * diagnostic value (a non-finite float the sanitizer passes through
     * as a scalar), reached in dev mode where the details member is
     * added.
     */
    public function testADoubleFailureAnswersTheFallbackAndNamesItsCauseOnTheSapiLog(): void
    {
        $response = $this->middleware(null, true)->process(
            $this->requestWithClaim(),
            new ThrowingHandler(
                new ExplanatoryException('nan details', 0, null, 'NAN_DETAILS', ['budget' => \NAN]),
            ),
        );

        self::assertSame(500, $response->getStatusCode());
        self::assertSame('text/plain', $response->getHeaderLine('Content-Type'));
        self::assertSame('Internal Server Error', (string) $response->getBody());
        self::assertSame(self::CLAIMED_ID, $response->getHeaderLine('X-Request-ID'));

        $routedLog = $this->routedErrorLogContents();
        self::assertSame(
            2,
            mb_substr_count($routedLog, "\n"),
            'Exactly two lines reach the SAPI log: the fault record and the construction failure.',
        );
        self::assertStringEndsWith(
            'ZEF error response construction failed: JsonException' . "\n",
            $routedLog,
            'The SAPI log line must name the class whose construction failed, prefixed as the double failure it is.',
        );
    }

    /**
     * The fault tag carries the short class name through the multibyte
     * namespace: the separator search and the tail read are both
     * character-based, so the two-byte e-acute ahead of the separator
     * must not shift the tag's start -- the tag is the whole short name,
     * never a tail that begins one character late.
     */
    public function testTheFaultTagCarriesTheWholeShortNameThroughAMultibyteNamespace(): void
    {
        $counterMeter = new CounterMeter();
        $errorMappingMiddleware = new ErrorMappingMiddleware(null, false, $counterMeter);

        $errorMappingMiddleware->process(
            $this->requestWithClaim('multibyte-req'),
            new ThrowingHandler(new LocaleFailure('locale failure')),
        );

        $series = array_values($counterMeter->snapshot());
        self::assertCount(1, $series);
        self::assertSame('LocaleFailure', $series[0]['attributes']['exception.type']);
        self::assertSame(500, $series[0]['attributes']['http.response.status_code']);
    }

    /**
     * The SAPI fallback record carries the UTC stamp: when no logger was
     * injected, the structured record that reaches the SAPI log is signed
     * with the engine's own strict format -- a record without one is a
     * record the writer refused to timestamp.
     */
    public function testTheSapiFallbackRecordCarriesTheTimestamp(): void
    {
        $response = $this->middleware()->process(
            $this->requestWithClaim('sapi-record'),
            new ThrowingHandler(new RuntimeException('unlogged failure')),
        );

        self::assertSame(500, $response->getStatusCode());

        $routedLog = $this->routedErrorLogContents();
        self::assertSame(
            1,
            mb_substr_count($routedLog, "\n"),
            'Exactly one record reaches the SAPI log for one failure.',
        );
        $record = json_decode((string) mb_strstr($routedLog, '{'), true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($record);
        $timestamp = $record['timestamp'] ?? null;
        self::assertIsString($timestamp, 'The SAPI fallback record must carry the UTC timestamp.');
        self::assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/',
            $timestamp,
            "The SAPI fallback record must carry the UTC timestamp in the engine's strict format.",
        );
        self::assertSame('critical', $record['level']);
        self::assertSame('Request failed', $record['message']);
        $recordContext = $record['context'] ?? null;
        self::assertIsArray($recordContext);
        self::assertSame(RuntimeException::class, $recordContext['exception_class']);
    }

    /**
     * The resolver prefers the request context's own trace: the context
     * the middleware attached carries the trace the request arrived with,
     * and the resolver reads it before the correlation fallback -- never
     * the other way around.
     */
    public function testTheResolverPrefersTheRequestContextsTraceId(): void
    {
        $errorRequestContext = new ErrorRequestContext('context-trace');

        self::assertSame(
            'context-trace',
            ErrorTraceIdResolver::resolve(
                new RuntimeException('untraced'),
                $errorRequestContext,
                'request-id',
                $this->requestWithClaim('resolver-claim'),
            ),
        );
    }

    /**
     * A trace candidate that ends in a newline is refused, not accepted:
     * the grammar's dollar anchor matches the end of the candidate itself,
     * not the position just before a final newline, so the resolver falls
     * back to the request's own correlation id.
     */
    public function testTheResolverRefusesATraceCandidateEndingInANewline(): void
    {
        self::assertSame(
            self::CLAIMED_ID,
            ErrorTraceIdResolver::resolve(
                new RuntimeException('untraced'),
                null,
                self::CLAIMED_ID . "\n",
                $this->requestWithClaim(),
            ),
        );
    }

    /**
     * The developer details travel only in debug mode and only when there
     * is something to carry: the conjunction gates the envelope's details
     * key on both the mode and the non-empty map, so production never
     * sees the diagnostics and a typed failure without details never
     * carries an empty key.
     */
    public function testTheEnvelopeCarriesTheDetailsOnlyInDebugModeAndOnlyWhenPresent(): void
    {
        $withDetails = new ExplanatoryException('carrying details', 0, null, 'E_DETAILS', ['safe' => 'visible']);
        $withoutDetails = new ExplanatoryException('no details', 0, null, 'E_BARE');

        $debugResponse = $this->middleware(null, true)->process(
            $this->requestWithClaim('details-debug'),
            new ThrowingHandler($withDetails),
        );
        $productionResponse = $this->middleware(null, false)->process(
            $this->requestWithClaim('details-prod'),
            new ThrowingHandler($withDetails),
        );
        $bareDebugResponse = $this->middleware(null, true)->process(
            $this->requestWithClaim('details-bare'),
            new ThrowingHandler($withoutDetails),
        );

        self::assertSame(['safe' => 'visible'], $this->envelope($debugResponse)['details']);
        self::assertArrayNotHasKey('details', $this->envelope($productionResponse));
        self::assertArrayNotHasKey('details', $this->envelope($bareDebugResponse));
    }

    public function testMetricBackendFailureDoesNotReplaceTheErrorResponse(): void
    {
        $meter = new class implements MeterInterface {
            #[Override]
            public function increment(string $name, float|int $value = 1, array $attributes = []): void
            {
                throw new RuntimeException('metrics backend unavailable');
            }

            #[Override]
            public function observe(string $name, float $value, array $attributes = []): void
            {
                // The metric backend's observe lane is not the one under
                // test here; the interface contract keeps it a no-op.
            }

            #[Override]
            public function snapshot(): array
            {
                return [];
            }
        };

        $errorMappingMiddleware = new ErrorMappingMiddleware(new RecordingLogger(), false, $meter);
        $response = $errorMappingMiddleware->process(
            $this->requestWithClaim(),
            new ThrowingHandler(new RuntimeException('application failure')),
        );

        self::assertSame(500, $response->getStatusCode());
        self::assertSame('Internal Server Error', $this->envelope($response)['title']);
    }

    public function testResponseTraceIdFallsBackWhenAnExtensionOverridesValidation(): void
    {
        $forgedTraceIdException = new ForgedTraceIdException('bad trace');

        $response = $this->middleware()->process(
            $this->requestWithClaim(),
            new ThrowingHandler($forgedTraceIdException),
        );

        self::assertSame(self::CLAIMED_ID, $this->envelope($response)['trace_id']);
    }

    public function testRejectsMalformedTraceIdsOnTypedExceptions(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ExplanatoryException('invalid trace', 0, null, 'INVALID_TRACE', [], "trace\r\nX-Forged: yes");
    }

    public function testRejectsMalformedErrorCodesOnTypedExceptions(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ExplanatoryException('invalid code', 0, null, 'bad-code');
    }

    public function testSubErrorStatusesAreNotRecorded(): void
    {
        $recordingLogger = new RecordingLogger();

        new ErrorFaultRecorder($recordingLogger)->record(
            new RuntimeException('nothing worth recording'),
            302,
            self::CLAIMED_ID,
            $this->requestWithClaim(),
        );

        self::assertCount(0, $recordingLogger->calls);
        self::assertSame('', $this->routedErrorLogContents());
    }

    private function middleware(?RecordingLogger $recordingLogger = null, bool $debug = false): ErrorMappingMiddleware
    {
        return new ErrorMappingMiddleware($recordingLogger, $debug);
    }

    private function requestWithClaim(
        string $claim = self::CLAIMED_ID,
        ?string $accept = null,
    ): ServerRequest {
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
     * Routes the SAPI error log to a per-test file (invoked by setUp for the
     * whole battery) so the last-resort write is observable; tearDown
     * restores the previous destination and removes the file.
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
        self::assertNotNull($this->routedErrorLog, 'The suite routed the SAPI error log in setUp.');

        if (!is_file($this->routedErrorLog)) {
            return '';
        }

        $contents = file_get_contents($this->routedErrorLog);
        self::assertIsString($contents, 'The routed error log must be readable.');

        return $contents;
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
