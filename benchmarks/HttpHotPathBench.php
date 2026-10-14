<?php

declare(strict_types=1);

namespace Zef\Benchmark;

use PhpBench\Attributes\Revs;
use Zef\Framework\Exception\InvariantViolationException;
use Zef\Framework\Http\RequestFactory;
use Zef\Framework\Http\Response;
use Zef\Framework\Http\SecurityHeadersMiddleware;
use Zef\Framework\Http\SecurityHeadersPolicy;
use Zef\Framework\Http\ServerRequest;
use Zef\Framework\Http\Uri;
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
}
