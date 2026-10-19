<?php

declare(strict_types=1);

namespace Zef\Test\Http;

use PDOException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Http\ErrorMappingMiddleware;
use Zef\Framework\Http\ServerRequest;
use Zef\Framework\Http\ThirdPartyExceptionMapper;
use Zef\Framework\Http\Uri;
use Zef\Test\Support\Http\ThrowingHandler;
use Zef\Test\Support\Log\RecordingLogger;

/**
 * @internal
 */
#[CoversClass(ErrorMappingMiddleware::class)]
#[CoversClass(ThirdPartyExceptionMapper::class)]
final class ThirdPartyExceptionMappingIntegrationTest extends TestCase
{
    public function testPdoFailuresBecomeSafeProblemDetailsAtTheGlobalBoundary(): void
    {
        $exception = new PDOException('SQL error with private query data');
        $exception->errorInfo = ['23000', 1062, 'private driver details'];
        $request = new ServerRequest(
            'POST',
            new Uri('https://app.example/users'),
            [],
            [],
            [],
            [],
            null,
            [],
        );
        $logger = new RecordingLogger();

        $response = new ErrorMappingMiddleware($logger)->process(
            $request,
            new ThrowingHandler($exception),
        );

        $payload = json_decode((string) $response->getBody(), true, 32, \JSON_THROW_ON_ERROR);
        self::assertIsArray($payload);

        self::assertSame(500, $response->getStatusCode());
        self::assertSame('application/problem+json; charset=utf-8', $response->getHeaderLine('Content-Type'));
        self::assertSame('DATABASE_ERROR', $payload['code']);
        self::assertSame('A database operation failed.', $payload['detail']);
        self::assertArrayNotHasKey('details', $payload);
        self::assertStringNotContainsString('private query data', (string) $response->getBody());
    }
}
