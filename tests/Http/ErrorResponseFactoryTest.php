<?php

declare(strict_types=1);

namespace Zef\Test\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Http\ErrorResponseFactory;
use Zef\Framework\Http\FallbackResponse;
use Zef\Framework\Http\Response;
use Zef\Framework\Http\Stream;

/**
 * @internal
 */
#[CoversClass(ErrorResponseFactory::class)]
#[CoversClass(FallbackResponse::class)]
#[CoversClass(Response::class)]
#[CoversClass(Stream::class)]
final class ErrorResponseFactoryTest extends TestCase
{
    public function testTheEnvelopeCarriesTheStatusTheMessageAndTheCorrelationId(): void
    {
        $response = ErrorResponseFactory::create(413, 'Content Too Large', 'req-123');

        self::assertSame(413, $response->getStatusCode());
        self::assertSame('Content Too Large', $response->getReasonPhrase());
        self::assertSame('application/json', $response->getHeaderLine('Content-Type'));

        $envelope = $this->envelope($response);

        self::assertTrue($envelope['error']);
        self::assertSame(413, $envelope['status']);
        self::assertSame('Content Too Large', $envelope['message']);
        self::assertSame('req-123', $envelope['correlation_id']);
    }

    /**
     * The reference's flag set escapes forward slashes, so diagnostic
     * messages keep the engine's historical spelling.
     */
    public function testForwardSlashesStayEscaped(): void
    {
        $response = ErrorResponseFactory::create(500, 'failed at /api/v1/users', 'req-456');

        self::assertStringContainsString('\/api\/v1\/users', (string) $response->getBody());
    }

    public function testTheFallbackIsAPlainTextInternalErrorCarryingTheCorrelationId(): void
    {
        $response = FallbackResponse::internalError('req-789');

        self::assertSame(500, $response->getStatusCode());
        self::assertSame('text/plain', $response->getHeaderLine('Content-Type'));
        self::assertSame('req-789', $response->getHeaderLine('X-Request-ID'));
        self::assertSame('Internal Server Error', (string) $response->getBody());
    }

    /**
     * @return array<array-key, mixed> the decoded error envelope
     */
    private function envelope(Response $response): array
    {
        $decoded = json_decode((string) $response->getBody(), true, 512, \JSON_THROW_ON_ERROR);

        if (!\is_array($decoded)) {
            self::fail('The error envelope did not decode to an object.');
        }

        return $decoded;
    }
}
