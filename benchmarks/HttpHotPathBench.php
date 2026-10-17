<?php

declare(strict_types=1);

namespace Zef\Benchmark;

use PhpBench\Attributes\Revs;
use Zef\Framework\Exception\InvariantViolationException;
use Zef\Framework\Health\HealthAggregator;
use Zef\Framework\Health\HealthChecker;
use Zef\Framework\Http\AllowScopePolicy;
use Zef\Framework\Http\AuthBoundary;
use Zef\Framework\Http\AuthMiddleware;
use Zef\Framework\Http\AuthPolicy;
use Zef\Framework\Http\BoundedReplayProtector;
use Zef\Framework\Http\CsrfMiddleware;
use Zef\Framework\Http\CsrfPolicy;
use Zef\Framework\Http\CsrfTokenManager;
use Zef\Framework\Http\InMemoryRateLimiter;
use Zef\Framework\Http\MetricsPolicy;
use Zef\Framework\Http\RateLimitMiddleware;
use Zef\Framework\Http\RateLimitPolicy;
use Zef\Framework\Http\RequestFactory;
use Zef\Framework\Http\Response;
use Zef\Framework\Http\ResponseTime;
use Zef\Framework\Http\SecurityHeadersMiddleware;
use Zef\Framework\Http\SecurityHeadersPolicy;
use Zef\Framework\Http\ServerRequest;
use Zef\Framework\Http\StaticCredentialProvider;
use Zef\Framework\Http\TimingMiddleware;
use Zef\Framework\Http\TimingPolicy;
use Zef\Framework\Http\Uri;
use Zef\Framework\Middleware\MetricsMiddleware;
use Zef\Framework\Telemetry\CounterMeter;
use Zef\Framework\Telemetry\PrometheusRenderer;
use Zef\Test\Support\FixedHealthCheck;
use Zef\Test\Support\Http\FixedResponseHandler;

/**
 * The HTTP lane's hot-path reference suite -- the per-request costs the
 * performance phase instruments for regression watching (ROADMAP.md,
 * "Performance Tuning for Shim" and "HTTP Optimizations"; the CI Bench
 * lane runs this file on every push).
 *
 * Not a victory claim. The honest cost structure these numbers pin: the
 * superglobal adapter (`RequestFactory::fromServer`) validates a fixed
 * grammar set per request -- method, target, host authority, protocol
 * -- plus one `HTTP_*` extraction pass over the bag; the immutable
 * `withHeader()` is a clone plus one row change over engine
 * copy-on-write, its case-insensitive resolution a byte-comparison
 * walk over the stored spellings ({@see \Zef\Framework\Http\HeaderIndex});
 * and the URI render reassembles percent-encoded components on every
 * cast. The bench set mirrors `RouterBench`'s discipline: each method
 * guards its own invariant, so a broken hot path fails the lane rather
 * than silently reporting a number.
 */
final readonly class HttpHotPathBench
{
    private Response $response;

    private Uri $uri;

    private SecurityHeadersMiddleware $securityHeadersMiddleware;

    private RateLimitMiddleware $rateLimitMiddleware;

    private CsrfMiddleware $csrfMiddleware;

    private ServerRequest $csrfServerRequest;

    private AuthMiddleware $authMiddleware;

    private ServerRequest $authServerRequest;

    private TimingMiddleware $timingMiddleware;

    private MetricsMiddleware $metricsMiddleware;

    private MetricsMiddleware $healthMiddleware;

    private ServerRequest $metricsScrapeRequest;

    private ServerRequest $healthPollRequest;

    private ServerRequest $serverRequest;

    private FixedResponseHandler $fixedResponseHandler;

    /** @var array<string, string> */
    private array $serverBag;

    public function __construct()
    {
        $this->response = new Response(
            200,
            [
                'Content-Type' => 'application/json',
                'Content-Length' => '42',
                'Cache-Control' => 'no-cache',
                'ETag' => '"a1b2c3d4"',
                'Last-Modified' => 'Mon, 01 Jan 2024 10:00:00 GMT',
                'Vary' => 'Accept, Accept-Encoding',
                'X-Request-ID' => 'req-123',
                'X-RateLimit-Limit' => '1000',
                'X-RateLimit-Remaining' => '998',
                'X-Content-Type-Options' => 'nosniff',
                'X-Frame-Options' => 'DENY',
                'Referrer-Policy' => 'strict-origin-when-cross-origin',
                'Server' => 'zef',
                'Date' => 'Mon, 01 Jan 2024 10:00:00 GMT',
            ],
            '{"data":{"items":[1,2,3],"page":2,"total":42}}',
        );

        $this->uri = new Uri('https://api.example.com/v1/users?page=2&sort=name');

        $this->securityHeadersMiddleware = new SecurityHeadersMiddleware(new SecurityHeadersPolicy(
            hsts: true,
            contentSecurityPolicyEnabled: true,
        ));
        $this->rateLimitMiddleware = new RateLimitMiddleware(
            new RateLimitPolicy(enabled: true, limit: 1000000, window: 3600),
            new InMemoryRateLimiter(now: static fn (): int => 1725168000),
        );
        $benchSigningKey = str_repeat('bench-hmac-key!', 3);
        $csrfTokenManager = new CsrfTokenManager($benchSigningKey);
        $this->csrfMiddleware = new CsrfMiddleware(
            new CsrfPolicy(secret: $benchSigningKey),
            $csrfTokenManager,
        );
        $csrfToken = $csrfTokenManager->issue();
        $csrfServerRequest = new ServerRequest('POST', $this->uri)
            ->withHeader('Cookie', \sprintf('ZEF-XSRF-TOKEN=%s', $csrfToken))
            ->withHeader('X-CSRF-Token', $csrfToken)
        ;
        \assert($csrfServerRequest instanceof ServerRequest);
        $this->csrfServerRequest = $csrfServerRequest;
        $benchBearerToken = str_repeat('bench-bearer-token.', 3);
        $this->authMiddleware = new AuthMiddleware(
            new AuthPolicy(tokens: [$benchBearerToken]),
            new AuthBoundary(
                new StaticCredentialProvider([$benchBearerToken]),
                new AllowScopePolicy('api'),
                new BoundedReplayProtector(1024, 300_000),
            ),
            static fn (): int => 1725168000000,
        );
        $authServerRequest = new ServerRequest('POST', $this->uri)
            ->withHeader('Authorization', \sprintf('Bearer %s', $benchBearerToken))
        ;
        \assert($authServerRequest instanceof ServerRequest);
        $this->authServerRequest = $authServerRequest;
        $this->timingMiddleware = new TimingMiddleware(
            new TimingPolicy(emitHeader: true),
            static fn (): int => 1725168000000000000,
        );
        $counterMeter = new CounterMeter();
        $counterMeter->increment('zef.http.requests', 1, [
            'http.request.method' => 'GET',
            'http.response.status_code' => 200,
        ]);
        $counterMeter->increment('zef.http.errors.total', 1, ['exception.type' => 'RuntimeException']);

        $this->metricsMiddleware = new MetricsMiddleware(
            new MetricsPolicy(enabled: true),
            $counterMeter,
            new PrometheusRenderer(),
            new HealthAggregator(new HealthChecker()),
        );
        $benchHealthChecker = new HealthChecker();
        $benchHealthChecker->add(new FixedHealthCheck('cache', true));
        $benchHealthChecker->add(new FixedHealthCheck('database', true));

        $this->healthMiddleware = new MetricsMiddleware(
            new MetricsPolicy(enabled: true),
            new CounterMeter(),
            new PrometheusRenderer(),
            new HealthAggregator($benchHealthChecker),
        );
        $this->metricsScrapeRequest = new ServerRequest('GET', new Uri('https://api.example.com/metrics'));
        $this->healthPollRequest = new ServerRequest('GET', new Uri('https://api.example.com/health'));
        $this->serverRequest = new ServerRequest('GET', $this->uri);
        $this->fixedResponseHandler = new FixedResponseHandler($this->response);

        $this->serverBag = [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/v1/users?page=2&sort=name',
            'QUERY_STRING' => 'page=2&sort=name',
            'SERVER_PROTOCOL' => 'HTTP/1.1',
            'REQUEST_TIME_FLOAT' => '1725168000.000',
            'REQUEST_TIME' => '1725168000',
            'SCRIPT_NAME' => '/index.php',
            'PHP_SELF' => '/index.php',
            'SERVER_NAME' => 'api.example.com',
            'SERVER_PORT' => '443',
            'HTTPS' => 'on',
            'HTTP_HOST' => 'api.example.com',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_ACCEPT_ENCODING' => 'gzip, deflate, br',
            'HTTP_ACCEPT_LANGUAGE' => 'en-US,en;q=0.9,id;q=0.8',
            'HTTP_USER_AGENT' => 'zef-bench/1.0',
            'HTTP_AUTHORIZATION' => 'Bearer bench-token',
            'HTTP_X_REQUEST_ID' => 'req-bench-123',
            'CONTENT_TYPE' => 'application/json',
            'CONTENT_LENGTH' => '0',
        ];
    }

    #[Revs(500)]
    public function benchAServerRequestComesFromTheSuperglobalBag(): void
    {
        $serverRequest = RequestFactory::fromServer($this->serverBag);
        if (
            $serverRequest->getMethod() !== 'GET'
            || $serverRequest->getUri()->getPath() !== '/v1/users'
            || $serverRequest->getHeaderLine('Accept') !== 'application/json'
        ) {
            throw new InvariantViolationException('The factory must build the request.');
        }
    }

    #[Revs(2000)]
    public function benchAResponseWitherReplacesOneHeader(): void
    {
        $message = $this->response->withHeader('Cache-Control', 'no-store');

        if ($message->getHeaderLine('cache-control') !== 'no-store') {
            throw new InvariantViolationException('The wither must replace the header.');
        }
    }

    #[Revs(10000)]
    public function benchAHeaderLookupWalksTheStoredSpellings(): void
    {
        if ($this->response->getHeaderLine('CONTENT-TYPE') !== 'application/json') {
            throw new InvariantViolationException('The lookup must resolve the header.');
        }
    }

    #[Revs(10000)]
    public function benchAUriRendersItsComponents(): void
    {
        if ((string) $this->uri !== 'https://api.example.com/v1/users?page=2&sort=name') {
            throw new InvariantViolationException('The URI must render its components.');
        }
    }

    #[Revs(2000)]
    public function benchASecurityHeadersDecorationStampsTheHardenedSet(): void
    {
        $response = $this->securityHeadersMiddleware->process($this->serverRequest, $this->fixedResponseHandler);

        if (
            $response->getHeaderLine('X-Content-Type-Options') !== 'nosniff'
            || $response->getHeaderLine('Strict-Transport-Security') !== 'max-age=31536000; includeSubDomains'
            || !$response->hasHeader('Content-Security-Policy')
        ) {
            throw new InvariantViolationException('The decoration must stamp the hardened set.');
        }
    }

    #[Revs(2000)]
    public function benchARateLimitedRequestAccountsAndStampsTheHeaders(): void
    {
        $response = $this->rateLimitMiddleware->process($this->serverRequest, $this->fixedResponseHandler);

        $remaining = $response->getHeaderLine('X-RateLimit-Remaining');

        if (
            $response->getHeaderLine('X-RateLimit-Limit') !== '1000000'
            || !ctype_digit($remaining)
            || (int) $remaining < 1000000 - 2500
        ) {
            throw new InvariantViolationException('The admission boundary must stamp the accounting headers.');
        }
    }

    #[Revs(2000)]
    public function benchACsrfProtectedRequestValidatesTheDoubleSubmit(): void
    {
        $response = $this->csrfMiddleware->process($this->csrfServerRequest, $this->fixedResponseHandler);

        if (
            $response->getStatusCode() !== 200
            || $response->hasHeader('Set-Cookie')
        ) {
            throw new InvariantViolationException('The double-submit boundary must admit the honoured submit.');
        }
    }

    #[Revs(2000)]
    public function benchAnAuthenticatedRequestResolvesTheBearerAndAdmits(): void
    {
        $response = $this->authMiddleware->process($this->authServerRequest, $this->fixedResponseHandler);

        if (
            $response->getStatusCode() !== 200
            || $response->hasHeader('WWW-Authenticate')
        ) {
            throw new InvariantViolationException('The bearer boundary must admit the table token.');
        }
    }

    #[Revs(2000)]
    public function benchATimedRequestMeasuresAndStampsTheHeader(): void
    {
        $response = $this->timingMiddleware->process($this->serverRequest, $this->fixedResponseHandler);

        if (
            $response->getStatusCode() !== 200
            || $response->getHeaderLine(ResponseTime::HEADER) !== '0.00ms'
        ) {
            throw new InvariantViolationException('The timing boundary must stamp the measured span.');
        }
    }

    #[Revs(2000)]
    public function benchAMetricsScrapeRendersTheSeries(): void
    {
        $response = $this->metricsMiddleware->process($this->metricsScrapeRequest, $this->fixedResponseHandler);

        if (
            $response->getStatusCode() !== 200
            || !str_contains((string) $response->getBody(), 'zef_http_requests{http_request_method="GET"')
        ) {
            throw new InvariantViolationException('The exposition must render the accumulated series.');
        }
    }

    #[Revs(2000)]
    public function benchAHealthScrapeAggregatesTheProbes(): void
    {
        $response = $this->healthMiddleware->process($this->healthPollRequest, $this->fixedResponseHandler);

        if (
            $response->getStatusCode() !== 200
            || !str_contains((string) $response->getBody(), '"status":"ok"')
        ) {
            throw new InvariantViolationException('The health surface must aggregate the registered probes.');
        }
    }
}
