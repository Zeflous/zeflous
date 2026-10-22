<?php

declare(strict_types=1);

namespace Zef\Test\Middleware;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use Zef\Framework\Http\ServerRequest;
use Zef\Framework\Http\Uri;
use Zef\Framework\Middleware\DiagnosticRepeats;
use Zef\Framework\Middleware\ProcessBoundaryFatals;
use Zef\Framework\Middleware\ProcessBoundaryRecords;
use Zef\Framework\Middleware\ProcessBoundaryTransmission;
use Zef\Framework\Middleware\ProcessErrorBoundary;
use Zef\Framework\Middleware\ProcessNativeDiagnostics;
use Zef\Framework\Middleware\ProcessShutdownFatals;
use Zef\Framework\Middleware\ProcessUncaughtThrowables;
use Zef\Framework\Middleware\RequestFactsMemo;
use Zef\Test\Support\Log\RecordingLogger;

/**
 * Focused regression for the successful HTTP transmission branch: a
 * response that ships must not be followed by the headers-already-sent
 * diagnostic. Kept separate from the broader process-boundary battery so
 * Infection's test selection can observe this contract independently.
 *
 * @internal
 */
#[CoversClass(ProcessErrorBoundary::class)]
#[CoversClass(ProcessBoundaryTransmission::class)]
#[CoversClass(DiagnosticRepeats::class)]
#[CoversClass(RequestFactsMemo::class)]
#[CoversClass(ProcessBoundaryFatals::class)]
#[CoversClass(ProcessBoundaryRecords::class)]
#[CoversClass(ProcessNativeDiagnostics::class)]
#[CoversClass(ProcessUncaughtThrowables::class)]
#[CoversClass(ProcessShutdownFatals::class)]
final class ProcessUncaughtThrowablesTransmissionTest extends TestCase
{
    private string $errorLogPath = '';

    private false|string $previousErrorLog = false;

    #[Override]
    protected function setUp(): void
    {
        $this->errorLogPath = sys_get_temp_dir() . '/zef-successful-transmission-' . uniqid() . '.log';
        $this->previousErrorLog = ini_set('error_log', $this->errorLogPath);
    }

    #[Override]
    protected function tearDown(): void
    {
        ini_set('error_log', (string) $this->previousErrorLog);

        if (!is_file($this->errorLogPath)) {
            return;
        }

        unlink($this->errorLogPath);
    }

    public function testSuccessfulHttpTransmissionDoesNotLogARefusal(): void
    {
        /** @var list<ResponseInterface> $responses */
        $responses = [];
        $boundary = new ProcessErrorBoundary(
            logger: new RecordingLogger(),
            requestSource: static fn (): ServerRequestInterface => new ServerRequest(
                'GET',
                new Uri('https://app.example.test/users'),
            ),
            transmission: new ProcessBoundaryTransmission(
                responseEmitter: static function (ResponseInterface $response) use (&$responses): void {
                    $responses[] = $response;
                },
                headersSentProbe: static fn (): bool => false,
                sapi: 'fpm-fcgi',
            ),
            lastErrorProvider: static fn (): ?array => null,
            shutdownRegistrar: static function (callable $observer): void {
            },
        );

        $boundary->onUncaughtThrowable(new RuntimeException('The worker observed an impossible state.'));

        self::assertCount(1, $responses, 'The HTTP response must be shipped exactly once.');
        self::assertSame(
            '',
            is_file($this->errorLogPath) ? (string) file_get_contents($this->errorLogPath) : '',
            'A successful transmission must not be logged as a headers-already-sent refusal.',
        );
    }
}
