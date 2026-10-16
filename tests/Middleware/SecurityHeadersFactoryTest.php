<?php

declare(strict_types=1);

namespace Zef\Test\Middleware;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Config\Config;
use Zef\Framework\Http\HttpException;
use Zef\Framework\Http\Response;
use Zef\Framework\Http\ServerRequest;
use Zef\Framework\Http\Uri;
use Zef\Framework\Middleware\SecurityHeadersFactory;
use Zef\Test\Support\Http\FixedResponseHandler;

/**
 * The security-headers construction seam, on its own terms: the factory
 * the framework profile's `security.headers` registration names, reading
 * the frozen repository's subtree through the same strict policy the map
 * reading owns -- including the raw-string values an environment overlay
 * writes over native booleans.
 *
 * @internal
 */
#[CoversClass(SecurityHeadersFactory::class)]
final class SecurityHeadersFactoryTest extends TestCase
{
    public function testTheFactoryBuildsTheMiddlewareFromTheFrozenSubtree(): void
    {
        $config = new Config([
            'security' => [
                'headers' => [
                    'hsts' => true,
                    'content_security_policy' => 'default-src https:',
                ],
            ],
        ]);

        $response = SecurityHeadersFactory::fromConfig($config)
            ->process($this->request(), new FixedResponseHandler(new Response(200, [], 'answered')))
        ;

        self::assertSame('max-age=31536000; includeSubDomains', $response->getHeaderLine('Strict-Transport-Security'));
        self::assertFalse($response->hasHeader('Content-Security-Policy'), 'the csp knob stayed at its default: off');
        self::assertSame('nosniff', $response->getHeaderLine('X-Content-Type-Options'));
    }

    public function testTheFactoryReadsTheDefaultsWhenTheFamilyIsAbsent(): void
    {
        $response = SecurityHeadersFactory::fromConfig(new Config())
            ->process($this->request(), new FixedResponseHandler(new Response(200, [], 'answered')))
        ;

        self::assertSame('nosniff', $response->getHeaderLine('X-Content-Type-Options'));
        self::assertSame(
            'camera=(), microphone=(), geolocation=(), payment=()',
            $response->getHeaderLine('Permissions-Policy'),
        );
        self::assertFalse($response->hasHeader('Strict-Transport-Security'));
        self::assertFalse($response->hasHeader('Content-Security-Policy'));
    }

    public function testTheFactoryFoldsEnvironmentStringValuesThroughTheStrictReading(): void
    {
        $config = new Config([
            'security' => [
                'headers' => [
                    'hsts' => 'on',
                    'permissions_policy' => 'off',
                ],
            ],
        ]);

        $response = SecurityHeadersFactory::fromConfig($config)
            ->process($this->request(), new FixedResponseHandler(new Response(200, [], 'answered')))
        ;

        self::assertSame('max-age=31536000; includeSubDomains', $response->getHeaderLine('Strict-Transport-Security'));
        self::assertFalse(
            $response->hasHeader('Permissions-Policy'),
            "the 'off' spelling folds to a switched-off toggle",
        );
    }

    public function testTheFactoryHaltsOnAnInvalidKnob(): void
    {
        $config = new Config(['security' => ['headers' => ['hsts' => 'maybe']]]);

        try {
            SecurityHeadersFactory::fromConfig($config);
            self::fail('An invalid knob must halt the construction.');
        } catch (HttpException $httpException) {
            self::assertSame(
                'The security.headers hsts knob must be a boolean or one of the boolean string '
                . 'spellings (true/false, on/off, yes/no, 1/0), string given.',
                $httpException->getMessage(),
            );
        }
    }

    private function request(): ServerRequest
    {
        return new ServerRequest('GET', new Uri('https://app.example.com/health'));
    }
}
