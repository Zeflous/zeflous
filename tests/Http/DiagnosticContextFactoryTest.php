<?php

declare(strict_types=1);

namespace Zef\Test\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Http\DiagnosticContextFactory;
use Zef\Framework\Http\ServerRequest;
use Zef\Framework\Http\Uri;
use Zef\Test\Support\Http\RawFactsServerRequest;
use Zef\Test\Support\Http\RawPathUri;

/**
 * Direct battery for the diagnostic record's facts builder: the redacted
 * message, the character-scale bounds, and the privacy posture that keeps
 * the record to method and path alone.
 *
 * @internal
 */
#[CoversClass(DiagnosticContextFactory::class)]
final class DiagnosticContextFactoryTest extends TestCase
{
    public function testTheFactoryBuildsRedactedFacts(): void
    {
        $serverRequest = new ServerRequest('GET', new Uri('https://app.example.test/users'));

        $context = DiagnosticContextFactory::create(
            'warning',
            'Undefined variable $budget password=secret123',
            '/var/app/src/budget.php',
            42,
            'req-123',
            $serverRequest,
        );

        self::assertSame('warning', $context['diagnostic.severity']);
        self::assertSame(
            'Undefined variable $budget password=[REDACTED]',
            $context['diagnostic.message'],
        );
        self::assertSame('/var/app/src/budget.php', $context['diagnostic.file']);
        self::assertSame(42, $context['diagnostic.line']);
        self::assertSame('req-123', $context['request_id']);
        self::assertSame('GET', $context['method']);
        self::assertSame('/users', $context['path']);
        self::assertArrayNotHasKey('trace_id', $context);
        self::assertArrayNotHasKey('user_id', $context);
        self::assertArrayNotHasKey('ip_address', $context);
        self::assertArrayNotHasKey('user_agent', $context);
    }

    public function testLongAndMultibyteFactsAreBoundedByCharacters(): void
    {
        $serverRequest = new ServerRequest('GET', new Uri('https://app.example.test/users'));

        $longMessage = DiagnosticContextFactory::create(
            'notice',
            str_repeat('a', 2049),
            '/var/app/src/budget.php',
            1,
            'req-123',
            $serverRequest,
        );
        self::assertSame(str_repeat('a', 2048), $longMessage['diagnostic.message']);

        $multibyteMessage = DiagnosticContextFactory::create(
            'notice',
            str_repeat('é', 2048),
            '/var/app/src/budget.php',
            1,
            'req-123',
            $serverRequest,
        );
        self::assertSame(str_repeat('é', 2048), $multibyteMessage['diagnostic.message']);

        $longFile = DiagnosticContextFactory::create(
            'notice',
            'A diagnostic.',
            str_repeat('a', 2049),
            1,
            'req-123',
            $serverRequest,
        );
        self::assertSame(str_repeat('a', 2048), $longFile['diagnostic.file']);

        $rawFactsServerRequest = new RawFactsServerRequest(
            $serverRequest,
            'GET',
            new RawPathUri(str_repeat('a', 2049)),
            [],
        );
        $boundedPath = DiagnosticContextFactory::create(
            'notice',
            'A diagnostic.',
            '/var/app/src/budget.php',
            1,
            'req-123',
            $rawFactsServerRequest,
        );
        self::assertSame(str_repeat('a', 2048), $boundedPath['path']);
    }

    public function testTheLineFactTravelsUnbounded(): void
    {
        $serverRequest = new ServerRequest('POST', new Uri('https://app.example.test/budget'));

        $context = DiagnosticContextFactory::create(
            'error',
            'A diagnostic.',
            '/var/app/src/budget.php',
            9007199254740993,
            'req-123',
            $serverRequest,
        );

        self::assertSame(9007199254740993, $context['diagnostic.line']);
        self::assertSame('POST', $context['method']);
    }
}
