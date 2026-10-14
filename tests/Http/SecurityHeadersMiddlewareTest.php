<?php

declare(strict_types=1);

namespace Zef\Test;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Zef\Framework\Http\BaselineSecurityHeaders;
use Zef\Framework\Http\Response;
use Zef\Framework\Http\SecurityHeadersMiddleware;
use Zef\Framework\Http\SecurityHeadersPolicy;
use Zef\Framework\Http\ServerRequest;
use Zef\Framework\Http\ToggledSecurityHeaders;
use Zef\Framework\Http\Uri;
use Zef\Test\Support\Http\FixedResponseHandler;

/**
 * The hardened response decoration, on its own terms: the baseline set
 * every response carries, every toggle's applicability rule, and the
 * replace-not-append posture over handler-set headers.
 *
 * @internal
 */
#[CoversClass(SecurityHeadersMiddleware::class)]
#[CoversClass(BaselineSecurityHeaders::class)]
#[CoversClass(ToggledSecurityHeaders::class)]
final class SecurityHeadersMiddlewareTest extends TestCase
{
    private const array BASELINE = [
        'X-Content-Type-Options' => 'nosniff',
        'X-Frame-Options' => 'DENY',
        'Referrer-Policy' => 'no-referrer',
        'X-Permitted-Cross-Domain-Policies' => 'none',
        'Cross-Origin-Opener-Policy' => 'same-origin',
        'Cross-Origin-Resource-Policy' => 'same-origin',
    ];

    public function testADefaultPolicyStampsTheBaselineAndTheDenyAllPermissionsPolicy(): void
    {
        $response = $this->decorated(new SecurityHeadersPolicy(), 'https://app.example.com/health');

        self::assertSame([...self::BASELINE, ...[
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=()',
        ]], $this->headerLines($response));
    }

    public function testTheToggleSetFollowsThePolicy(): void
    {
        $securityHeadersPolicy = new SecurityHeadersPolicy(
            hsts: true,
            contentSecurityPolicyEnabled: true,
            contentSecurityPolicy: 'default-src https:',
            permissionsPolicy: false,
        );

        $response = $this->decorated($securityHeadersPolicy, 'https://app.example.com/health');

        self::assertSame([...self::BASELINE, ...[
            'Content-Security-Policy' => 'default-src https:',
            'Strict-Transport-Security' => 'max-age=31536000; includeSubDomains',
        ]], $this->headerLines($response));
    }

    public function testHstsIsEmittedOnlyOverHttps(): void
    {
        $securityHeadersPolicy = new SecurityHeadersPolicy(hsts: true);

        $httpsResponse = $this->decorated($securityHeadersPolicy, 'https://app.example.com/health');
        self::assertSame(
            'max-age=31536000; includeSubDomains',
            $httpsResponse->getHeaderLine('Strict-Transport-Security'),
        );

        $httpResponse = $this->decorated($securityHeadersPolicy, 'http://app.example.com/health');
        self::assertFalse(
            $httpResponse->hasHeader('Strict-Transport-Security'),
            'HSTS is never emitted over cleartext.',
        );
    }

    public function testTheCspToggleCarriesTheDefaultPolicyStringWhenNoOverrideIsDeclared(): void
    {
        $securityHeadersPolicy = new SecurityHeadersPolicy(contentSecurityPolicyEnabled: true);

        $response = $this->decorated($securityHeadersPolicy, 'https://app.example.com/health');

        self::assertSame(
            "default-src 'self'; frame-ancestors 'none'; base-uri 'self'",
            $response->getHeaderLine('Content-Security-Policy'),
        );
    }

    public function testTheDecorationReplacesHandlerSetHeadersNeverAppends(): void
    {
        $fixedResponseHandler = new FixedResponseHandler(new Response(
            200,
            ['X-Frame-Options' => 'SAMEORIGIN', 'X-Content-Type-Options' => 'text/plain'],
            'answered',
        ));

        $response = new SecurityHeadersMiddleware(new SecurityHeadersPolicy())
            ->process($this->request('https://app.example.com/frame'), $fixedResponseHandler)
        ;

        self::assertSame('DENY', $response->getHeaderLine('X-Frame-Options'));
        self::assertSame('nosniff', $response->getHeaderLine('X-Content-Type-Options'));
        self::assertSame('answered', (string) $response->getBody(), 'the decoration never touches the body');
    }

    public function testTheBaselinePairsAreExactlyTheSixHardeningHeaders(): void
    {
        self::assertSame(
            [
                ['X-Content-Type-Options', 'nosniff'],
                ['X-Frame-Options', 'DENY'],
                ['Referrer-Policy', 'no-referrer'],
                ['X-Permitted-Cross-Domain-Policies', 'none'],
                ['Cross-Origin-Opener-Policy', 'same-origin'],
                ['Cross-Origin-Resource-Policy', 'same-origin'],
            ],
            BaselineSecurityHeaders::pairs(),
        );
    }

    public function testTheToggledPairsFollowEveryApplicabilityRule(): void
    {
        $allOff = new SecurityHeadersPolicy(permissionsPolicy: false);

        self::assertSame([], ToggledSecurityHeaders::pairs($allOff, 'https'));

        $permissionsOnly = new SecurityHeadersPolicy(permissionsPolicy: true);
        self::assertSame(
            [['Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()']],
            ToggledSecurityHeaders::pairs($permissionsOnly, 'http'),
        );

        $hstsOverHttp = new SecurityHeadersPolicy(hsts: true, permissionsPolicy: false);
        self::assertSame([], ToggledSecurityHeaders::pairs($hstsOverHttp, 'http'));

        $hstsOverHttps = new SecurityHeadersPolicy(hsts: true, permissionsPolicy: false);
        self::assertSame(
            [['Strict-Transport-Security', 'max-age=31536000; includeSubDomains']],
            ToggledSecurityHeaders::pairs($hstsOverHttps, 'https'),
        );

        $csp = new SecurityHeadersPolicy(
            contentSecurityPolicyEnabled: true,
            contentSecurityPolicy: 'default-src https:',
            permissionsPolicy: false,
        );
        self::assertSame(
            [['Content-Security-Policy', 'default-src https:']],
            ToggledSecurityHeaders::pairs($csp, 'http'),
        );
    }

    /**
     * The decoration over one request: the middleware processing a GET for
     * the given URI against a plain 200 handler.
     */
    private function decorated(SecurityHeadersPolicy $securityHeadersPolicy, string $uri): ResponseInterface
    {
        return new SecurityHeadersMiddleware($securityHeadersPolicy)
            ->process($this->request($uri), new FixedResponseHandler(new Response(200, [], 'answered')))
        ;
    }

    private function request(string $uri): ServerRequest
    {
        return new ServerRequest('GET', new Uri($uri));
    }

    /**
     * The response's header lines as one name => value map, for the exact
     * set assertions above.
     *
     * @return array<string, string>
     */
    private function headerLines(ResponseInterface $response): array
    {
        $lines = [];

        foreach ($response->getHeaders() as $name => $values) {
            $lines[$name] = implode(', ', $values);
        }

        return $lines;
    }
}
