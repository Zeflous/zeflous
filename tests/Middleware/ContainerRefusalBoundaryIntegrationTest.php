<?php

declare(strict_types=1);

namespace Zef\Test\Middleware;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use stdClass;
use Zef\Framework\Container\Container;
use Zef\Framework\Container\NotFoundException;
use Zef\Framework\Http\ServerRequest;
use Zef\Framework\Http\Uri;
use Zef\Framework\Middleware\ErrorMappingMiddleware;
use Zef\Test\Support\Log\RecordingLogger;
use Zef\Test\Support\Middleware\ContainerResolvingHandler;

/**
 * The two campaigns' composition proof: a container resolution refusal
 * mid-request flows through the error lane's boundary exactly the way
 * the disclosure doctrine promises -- production answers the generic
 * RFC 9457 envelope (the wiring structure never leaks), development
 * answers the redacted descriptive message (the identifier named), and
 * the log carries the full refusal on both sides.
 *
 * @internal
 */
#[CoversClass(ErrorMappingMiddleware::class)]
final class ContainerRefusalBoundaryIntegrationTest extends TestCase
{
    public function testProductionAnswersTheGenericEnvelopeAndNeverLeaksTheIdentifier(): void
    {
        $recordingLogger = new RecordingLogger();

        $response = $this->boundary($recordingLogger, debug: false)->process(
            $this->request(),
            $this->resolving('app.absent'),
        );

        self::assertSame(500, $response->getStatusCode());
        self::assertSame('application/problem+json; charset=utf-8', $response->getHeaderLine('Content-Type'));

        $envelope = $this->envelope($response);

        self::assertSame('An error occurred', $envelope['detail']);
        self::assertArrayHasKey('trace_id', $envelope);
        self::assertStringNotContainsString('app.absent', (string) $response->getBody());
    }

    public function testDevelopmentDisclosesTheRedactedRefusal(): void
    {
        $response = $this->boundary(debug: true)->process(
            $this->request(),
            $this->resolving('app.absent'),
        );

        $envelope = $this->envelope($response);

        self::assertSame(500, $response->getStatusCode());
        self::assertSame('Service "app.absent" is not defined.', $envelope['detail']);
    }

    /**
     * The log side of the doctrine: production hides the wiring from
     * the client but keeps the full refusal for the operator -- the
     * recorder's context carries the exception's own class and its
     * redacted message whichever mode answered the request.
     */
    public function testTheLogCarriesTheFullRefusalInBothModes(): void
    {
        $recordingLogger = new RecordingLogger();

        $this->boundary($recordingLogger, debug: false)->process(
            $this->request(),
            $this->resolving('app.absent'),
        );

        self::assertCount(1, $recordingLogger->calls);

        $context = $recordingLogger->calls[0][2];

        self::assertSame('critical', $recordingLogger->calls[0][0]);
        self::assertSame('Request failed', $recordingLogger->calls[0][1]);

        $refusal = $context['exception.message'] ?? null;

        self::assertIsString($refusal);
        self::assertStringContainsString('app.absent', $refusal);
        self::assertSame(NotFoundException::class, $context['exception_class'] ?? null);
    }

    /**
     * A booted container answers the same composition: the runtime
     * wiring a real configuration installed, its miss path the same
     * refusal the bare container answers.
     */
    public function testABootedContainerAnswersTheSameEnvelope(): void
    {
        $container = new Container();
        $container->set('app.known', static fn (): object => new stdClass());

        $response = $this->boundary(debug: false)->process(
            $this->request(),
            $this->resolving('app.never-declared'),
        );

        $envelope = $this->envelope($response);

        self::assertSame(500, $response->getStatusCode());
        self::assertSame('An error occurred', $envelope['detail']);
    }

    private function boundary(?RecordingLogger $recordingLogger = null, bool $debug = false): ErrorMappingMiddleware
    {
        return new ErrorMappingMiddleware($recordingLogger, $debug);
    }

    /**
     * The terminal handler the refusal rises from: a handler that
     * resolves the given identifier through the container.
     */
    private function resolving(string $identifier, ?Container $container = null): ContainerResolvingHandler
    {
        return new ContainerResolvingHandler($container ?? new Container(), $identifier);
    }

    private function request(): ServerRequest
    {
        return new ServerRequest(
            'GET',
            new Uri('https://app.example/users'),
            [],
            [],
            [],
            [],
            null,
            ['X-Request-ID' => 'claim-1234'],
        );
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
