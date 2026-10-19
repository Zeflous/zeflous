<?php

declare(strict_types=1);

namespace Zef\Test\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Zef\Framework\Http\ErrorFaultContextFactory;
use Zef\Framework\Http\ErrorRequestContext;
use Zef\Framework\Http\ServerRequest;
use Zef\Framework\Http\Uri;
use Zef\Test\Support\Http\RawFactsServerRequest;
use Zef\Test\Support\Http\RawPathUri;

/**
 * Direct battery for the error record's request-facts builder: the
 * redacted message, the string error-code fold, the character-scale path
 * and message bounds, and the replay id's guarded presence.
 *
 * @internal
 */
#[CoversClass(ErrorFaultContextFactory::class)]
final class ErrorFaultContextFactoryTest extends TestCase
{
    public function testTheFactoryBuildsRedactedFactsWithoutAReplayId(): void
    {
        $serverRequest = new ServerRequest('GET', new Uri('https://app.example.test/users'));

        $context = ErrorFaultContextFactory::create(
            new RuntimeException('password=secret123', 42),
            'req-123',
            $serverRequest,
        );

        self::assertSame('password=[REDACTED]', $context['exception.message']);
        self::assertSame(RuntimeException::class, $context['exception_class']);
        self::assertSame('42', $context['exception_code']);
        self::assertSame('req-123', $context['request_id']);
        self::assertSame('req-123', $context['trace_id']);
        self::assertSame('GET', $context['method']);
        self::assertSame('/users', $context['path']);
        self::assertArrayNotHasKey('replay_id', $context);
    }

    public function testLongAndMultibyteFactsAreBoundedByCharacters(): void
    {
        $inner = new ServerRequest('GET', new Uri('https://app.example.test/users'));

        $longMessage = ErrorFaultContextFactory::create(
            new RuntimeException(str_repeat('a', 2049)),
            'req-123',
            $inner,
        );
        self::assertSame(str_repeat('a', 2048), $longMessage['exception.message']);

        $multibyteMessage = ErrorFaultContextFactory::create(
            new RuntimeException(str_repeat('é', 2048)),
            'req-123',
            $inner,
        );
        self::assertSame(str_repeat('é', 2048), $multibyteMessage['exception.message']);

        $longPath = new RawFactsServerRequest(
            $inner,
            'GET',
            new RawPathUri(str_repeat('a', 2049)),
            [],
        );
        $longPathContext = ErrorFaultContextFactory::create(new RuntimeException('x'), 'req-123', $longPath);
        self::assertSame(str_repeat('a', 2048), $longPathContext['path']);

        $multibytePath = new RawFactsServerRequest(
            $inner,
            'GET',
            new RawPathUri(str_repeat('é', 2048)),
            [],
        );
        $multibytePathContext = ErrorFaultContextFactory::create(new RuntimeException('x'), 'req-123', $multibytePath);
        self::assertSame(str_repeat('é', 2048), $multibytePathContext['path']);
    }

    public function testTheReplayIdTravelsOnlyWhenTheRequestContextCarriesOne(): void
    {
        $errorRequestContext = new ErrorRequestContext('req-123');
        $errorRequestContext->setReplayId(str_repeat('f', 32));

        $withReplay = new ServerRequest('GET', new Uri('https://app.example.test/users'))
            ->withAttribute(ErrorRequestContext::ATTRIBUTE, $errorRequestContext)
        ;

        $context = ErrorFaultContextFactory::create(new RuntimeException('x'), 'req-123', $withReplay);

        self::assertSame(str_repeat('f', 32), $context['replay_id']);
    }
}
