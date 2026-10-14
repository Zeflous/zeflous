<?php

declare(strict_types=1);

namespace Zef\Test;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Zef\Framework\Http\ClientAddress;
use Zef\Framework\Http\CorrelationId;
use Zef\Framework\Http\ErrorResponseFactory;
use Zef\Framework\Http\RateLimitDecision;
use Zef\Framework\Http\RateLimitMiddleware;
use Zef\Framework\Http\RateLimitPolicy;
use Zef\Framework\Http\Response;
use Zef\Framework\Http\ServerRequest;
use Zef\Framework\Http\Uri;
use Zef\Test\Support\Http\FixedResponseHandler;
use Zef\Test\Support\Http\RecordingRateLimiter;

/**
 * The admission control boundary, on its own terms: the disarmed
 * pass-through, the accounting stamps and their replace-not-append
 * posture, the denial and outage answers with the layer's error envelope,
 * and the client address the accounting is keyed by.
 *
 * @internal
 */
#[CoversClass(RateLimitMiddleware::class)]
#[CoversClass(ClientAddress::class)]
#[CoversClass(CorrelationId::class)]
#[CoversClass(ErrorResponseFactory::class)]
final class RateLimitMiddlewareTest extends TestCase
{
    public function testADisarmedPolicyIsAPassThrough(): void
    {
        $recordingRateLimiter = new RecordingRateLimiter();

        $response = $this->middleware(new RateLimitPolicy(), $recordingRateLimiter)
            ->process($this->request(), new FixedResponseHandler(new Response(200, [], 'answered')))
        ;

        self::assertSame('answered', (string) $response->getBody());
        self::assertFalse($response->hasHeader('X-RateLimit-Limit'), 'a disarmed middleware accounts nothing');
        self::assertSame([], $recordingRateLimiter->checks);
    }

    public function testAnAllowedRequestIsStampedWithTheAccountingHeaders(): void
    {
        $recordingRateLimiter = new RecordingRateLimiter(new RateLimitDecision(true, 100, 99, 60));

        $response = $this->middleware($this->armedPolicy(), $recordingRateLimiter)
            ->process($this->request(), new FixedResponseHandler(new Response(200, [], 'answered')))
        ;

        self::assertSame('100', $response->getHeaderLine('X-RateLimit-Limit'));
        self::assertSame('99', $response->getHeaderLine('X-RateLimit-Remaining'));
        self::assertSame('answered', (string) $response->getBody());
    }

    public function testTheAccountingStampsReplaceHandlerSetValuesNeverAppend(): void
    {
        $recordingRateLimiter = new RecordingRateLimiter(new RateLimitDecision(true, 100, 99, 60));
        $handlerResponse = new Response(200, ['X-RateLimit-Remaining' => '999'], 'answered');

        $response = $this->middleware($this->armedPolicy(), $recordingRateLimiter)
            ->process($this->request(), new FixedResponseHandler($handlerResponse))
        ;

        self::assertSame('99', $response->getHeaderLine('X-RateLimit-Remaining'));
    }

    public function testADeniedRequestAnswers429WithTheRetryWindowAndTheEnvelope(): void
    {
        $recordingRateLimiter = new RecordingRateLimiter(new RateLimitDecision(false, 100, 0, 59));

        $response = $this->middleware($this->armedPolicy(), $recordingRateLimiter)
            ->process($this->request(), new FixedResponseHandler(new Response(200, [], 'never reached')))
        ;

        self::assertSame(429, $response->getStatusCode());
        self::assertSame('59', $response->getHeaderLine('Retry-After'));
        self::assertSame('100', $response->getHeaderLine('X-RateLimit-Limit'));
        self::assertSame('0', $response->getHeaderLine('X-RateLimit-Remaining'));
        self::assertSame('application/json', $response->getHeaderLine('Content-Type'));

        $envelope = $this->envelope($response);
        self::assertTrue($envelope['error']);
        self::assertSame(429, $envelope['status']);
        self::assertSame('Too Many Requests', $envelope['message']);
        self::assertIsString($envelope['correlation_id']);
        self::assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $envelope['correlation_id']);
    }

    public function testADeniedRequestCarriesTheClaimedCorrelationId(): void
    {
        $recordingRateLimiter = new RecordingRateLimiter(new RateLimitDecision(false, 100, 0, 59));

        $response = $this->middleware($this->armedPolicy(), $recordingRateLimiter)
            ->process(
                $this->request(['X-Request-ID' => 'req-123']),
                new FixedResponseHandler(new Response(200, [], 'never reached')),
            )
        ;

        $envelope = $this->envelope($response);
        self::assertSame('req-123', $envelope['correlation_id']);
    }

    public function testALimiterFailureAnswersTheFailClosed503(): void
    {
        $recordingRateLimiter = new RecordingRateLimiter(new RateLimitDecision(false, 100, 0, 59));
        $recordingRateLimiter->throws = true;

        $response = $this->middleware($this->armedPolicy(), $recordingRateLimiter)
            ->process($this->request(), new FixedResponseHandler(new Response(200, [], 'never reached')))
        ;

        self::assertSame(503, $response->getStatusCode());
        self::assertSame('1', $response->getHeaderLine('Retry-After'));
        self::assertSame('application/json', $response->getHeaderLine('Content-Type'));

        $envelope = $this->envelope($response);
        self::assertTrue($envelope['error']);
        self::assertSame(503, $envelope['status']);
        self::assertSame('Service Unavailable', $envelope['message']);
    }

    public function testTheAccountingIsKeyedByTheUntrustedPeerAddress(): void
    {
        $recordingRateLimiter = new RecordingRateLimiter();
        $request = $this->request([], ['REMOTE_ADDR' => '203.0.113.9']);

        $this->middleware($this->armedPolicy(), $recordingRateLimiter)
            ->process($request, new FixedResponseHandler(new Response(200, [], 'answered')))
        ;

        self::assertSame(
            [['203.0.113.9', 100, 60]],
            $recordingRateLimiter->checks,
            'the limit and window travel from the policy to the limiter',
        );
    }

    public function testTheAccountingHonoursTheTrustedProxyBoundary(): void
    {
        $recordingRateLimiter = new RecordingRateLimiter();
        $request = $this->request(
            ['X-Forwarded-For' => '198.51.100.7, 10.0.0.1'],
            ['REMOTE_ADDR' => '10.0.0.1'],
        );

        $rateLimitPolicy = new RateLimitPolicy(enabled: true, trustedProxies: ['10.0.0.1']);

        $this->middleware($rateLimitPolicy, $recordingRateLimiter)
            ->process($request, new FixedResponseHandler(new Response(200, [], 'answered')))
        ;

        self::assertSame(
            [['198.51.100.7', 100, 60]],
            $recordingRateLimiter->checks,
            "a trusted proxy's forwarded candidate is the address the accounting keys by",
        );
    }

    private function middleware(
        RateLimitPolicy $rateLimitPolicy,
        RecordingRateLimiter $recordingRateLimiter,
    ): RateLimitMiddleware {
        return new RateLimitMiddleware($rateLimitPolicy, $recordingRateLimiter);
    }

    private function armedPolicy(): RateLimitPolicy
    {
        return new RateLimitPolicy(enabled: true, limit: 100, window: 60);
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

    /**
     * @param array<string, string> $headers
     * @param array<string, mixed>  $serverParams
     */
    private function request(array $headers = [], array $serverParams = []): ServerRequest
    {
        return new ServerRequest(
            'GET',
            new Uri('https://app.example.com/health'),
            serverParams: $serverParams,
            headers: $headers,
        );
    }
}
