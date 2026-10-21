<?php

declare(strict_types=1);

namespace Zef\Test\Middleware;

use Closure;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use ReflectionFunction;
use RuntimeException;
use Throwable;
use Zef\Framework\Exception\NativeErrorException;
use Zef\Framework\Exception\ValidationException;
use Zef\Framework\Http\ServerRequest;
use Zef\Framework\Http\Uri;
use Zef\Framework\Middleware\ProcessBoundaryFatals;
use Zef\Framework\Middleware\ProcessBoundaryTransmission;
use Zef\Framework\Middleware\ProcessErrorBoundary;
use Zef\Framework\Telemetry\CounterMeter;
use Zef\Test\Support\Log\RecordingLogger;

/**
 * Direct battery for the process error boundary's three arms and their
 * installation contract: the native diagnostic fold (masked severities
 * handed back, abort severities converted, everything else recorded once
 * and owned), the uncaught throwable lane (problem details, sink lines,
 * the CLI metrics honesty fold), the shutdown fatal observer (armed only
 * while installed, well-formed fatals only), and the real-branch defaults
 * every seam carries.
 *
 * The suite owns its error-reporting mask (E_ALL in setUp, the previous
 * mask restored in tearDown): the runner's own mask deliberately excludes
 * the warning and user-error families, and a boundary that respects the
 * engine's suppression contract must be observed under the mask its
 * doctrine assumes.
 *
 * @internal
 */
#[CoversClass(ProcessErrorBoundary::class)]
#[CoversClass(ProcessBoundaryTransmission::class)]
#[CoversClass(ProcessBoundaryFatals::class)]
final class ProcessErrorBoundaryTest extends TestCase
{
    private RecordingLogger $recordingLogger;

    /** @var list<ResponseInterface> */
    private array $emitted = [];

    /** @var list<string> */
    private array $sinkLines = [];

    /** @var null|array<string, mixed> */
    private ?array $lastError = null;

    private string $routedErrorLog = '';

    private false|string $previousErrorLog = false;

    private false|int $previousReporting = false;

    #[Override]
    protected function setUp(): void
    {
        $this->recordingLogger = new RecordingLogger();
        $this->emitted = [];
        $this->sinkLines = [];
        $this->lastError = null;
        $this->routedErrorLog = sys_get_temp_dir() . '/zef-process-boundary-' . uniqid() . '.log';
        $this->previousErrorLog = (string) ini_set('error_log', $this->routedErrorLog);
        $this->previousReporting = error_reporting(\E_ALL);
    }

    #[Override]
    protected function tearDown(): void
    {
        error_reporting((int) $this->previousReporting);
        ini_set('error_log', (string) $this->previousErrorLog);

        if (!is_file($this->routedErrorLog)) {
            return;
        }

        unlink($this->routedErrorLog);
    }

    public function testInstallRegistersTheArmsAndRestoreUnwindsThem(): void
    {
        $processErrorBoundary = $this->boundary();

        $processErrorBoundary->install();

        try {
            self::assertTrue($processErrorBoundary->installed());
            self::assertTrue(
                $this->activeErrorHandlerIsBoundTo($processErrorBoundary),
                "The installed boundary must own the engine's error handler.",
            );
            self::assertTrue(
                $this->activeExceptionHandlerIsBoundTo($processErrorBoundary),
                "The installed boundary must own the engine's exception handler.",
            );
        } finally {
            $processErrorBoundary->restore();
        }

        self::assertFalse($processErrorBoundary->installed());
        self::assertFalse(
            $this->activeErrorHandlerIsBoundTo($processErrorBoundary),
            "The restored boundary must no longer own the engine's error handler.",
        );
        self::assertFalse(
            $this->activeExceptionHandlerIsBoundTo($processErrorBoundary),
            "The restored boundary must no longer own the engine's exception handler.",
        );
    }

    public function testTheShutdownObserverRegistersOnceAcrossReArmCycles(): void
    {
        $registrations = [];
        $processErrorBoundary = $this->boundary(
            shutdownRegistrar: static function (callable $observer) use (&$registrations): void {
                $registrations[] = $observer;
            },
        );

        $processErrorBoundary->install();
        $processErrorBoundary->restore();
        $processErrorBoundary->install();
        $processErrorBoundary->restore();

        self::assertCount(
            1,
            $registrations,
            "The engine's one arm no restore can unwind registers exactly once per instance, re-armed or not.",
        );
    }

    public function testInstallIsIdempotent(): void
    {
        $processErrorBoundary = $this->boundary();

        $processErrorBoundary->install();
        $processErrorBoundary->install();

        try {
            self::assertTrue($processErrorBoundary->installed());

            $processErrorBoundary->restore();
            self::assertFalse($processErrorBoundary->installed());

            $processErrorBoundary->restore();
            self::assertFalse($processErrorBoundary->installed());
        } finally {
            if ($processErrorBoundary->installed()) {
                $processErrorBoundary->restore();
            }
        }
    }

    public function testARestoredBoundarysShutdownStaysSilentAndReArms(): void
    {
        $processErrorBoundary = $this->boundary();
        $this->lastError = $this->fatalError();

        $processErrorBoundary->install();
        $processErrorBoundary->restore();

        $silentCalls = $this->recordingLogger->calls;
        $processErrorBoundary->onShutdown();
        self::assertSame(
            $silentCalls,
            $this->recordingLogger->calls,
            'A restored boundary must not record fatals.',
        );

        $processErrorBoundary->install();

        try {
            $processErrorBoundary->onShutdown();
            self::assertCount(1, $this->recordingLogger->calls, 'A re-armed boundary must record fatals again.');
        } finally {
            $processErrorBoundary->restore();
        }
    }

    public function testAMaskedSeverityIsHandedBackToTheEngine(): void
    {
        $previousReporting = error_reporting(\E_ALL & ~\E_WARNING);

        try {
            $owned = $this->boundary()->onNativeDiagnostic(
                \E_WARNING,
                'Undefined variable $budget',
                '/var/app/src/budget.php',
                42,
            );
        } finally {
            error_reporting($previousReporting);
        }

        self::assertFalse($owned);
        self::assertSame([], $this->recordingLogger->calls);
    }

    public function testAUserErrorIsConvertedIntoTheTypedLane(): void
    {
        try {
            $this->boundary()->onNativeDiagnostic(
                \E_USER_ERROR,
                'The budget import failed',
                '/var/app/src/budget.php',
                42,
            );
            self::fail('An E_USER_ERROR diagnostic must be converted into a typed exception.');
        } catch (NativeErrorException $nativeErrorException) {
            self::assertSame(
                'The budget import failed in /var/app/src/budget.php on line 42',
                $nativeErrorException->getMessage(),
            );
            self::assertSame('NATIVE_ERROR', $nativeErrorException->getErrorCode());
            self::assertSame('critical', $nativeErrorException->getDetails()['severity']);
            self::assertSame('/var/app/src/budget.php', $nativeErrorException->getDetails()['file']);
            self::assertSame(42, $nativeErrorException->getDetails()['line']);
            self::assertSame(500, $nativeErrorException->httpStatus());
        }

        self::assertSame(
            [],
            $this->recordingLogger->calls,
            'A converted diagnostic records through the exception lane.',
        );
    }

    public function testARecoverableErrorIsConvertedIntoTheTypedLane(): void
    {
        try {
            $this->boundary()->onNativeDiagnostic(
                \E_RECOVERABLE_ERROR,
                'Argument #1 ($budget) must be of type int, string given',
                '/var/app/src/budget.php',
                42,
            );
            self::fail('An E_RECOVERABLE_ERROR diagnostic must be converted into a typed exception.');
        } catch (NativeErrorException $nativeErrorException) {
            self::assertSame('error', $nativeErrorException->getDetails()['severity']);
        }
    }

    public function testAWarningIsRecordedOnceAndOwned(): void
    {
        $owned = $this->boundary()->onNativeDiagnostic(
            \E_WARNING,
            'Undefined variable $budget password=secret123',
            '/var/app/src/budget.php',
            42,
        );

        self::assertTrue($owned);
        self::assertCount(1, $this->recordingLogger->calls);
        self::assertSame('warning', $this->recordingLogger->calls[0][0]);
        self::assertSame('Native diagnostic', $this->recordingLogger->calls[0][1]);
        self::assertSame('warning', $this->recordingLogger->calls[0][2]['diagnostic.severity']);
        self::assertSame(
            'Undefined variable $budget password=[REDACTED]',
            $this->recordingLogger->calls[0][2]['diagnostic.message'],
        );
        self::assertSame('/var/app/src/budget.php', $this->recordingLogger->calls[0][2]['diagnostic.file']);
        self::assertSame(42, $this->recordingLogger->calls[0][2]['diagnostic.line']);
        self::assertSame('req-123', $this->recordingLogger->calls[0][2]['request_id']);
        self::assertSame('GET', $this->recordingLogger->calls[0][2]['method']);
        self::assertSame('/users', $this->recordingLogger->calls[0][2]['path']);
    }

    public function testADeprecationIsRecordedAsInformational(): void
    {
        $owned = $this->boundary()->onNativeDiagnostic(
            \E_DEPRECATED,
            'The optional parameter $budget is declared implicitly nullable',
            '/var/app/src/budget.php',
            42,
        );

        self::assertTrue($owned);
        self::assertCount(1, $this->recordingLogger->calls);
        self::assertSame('info', $this->recordingLogger->calls[0][0]);
        self::assertSame('info', $this->recordingLogger->calls[0][2]['diagnostic.severity']);
    }

    public function testARequestSourceFailureIsContainedWhileTheDiagnosticIsStillOwned(): void
    {
        $owned = $this->boundary(requestSource: static function (): ServerRequest {
            throw new RuntimeException('The SAPI state is unreadable.');
        })->onNativeDiagnostic(\E_WARNING, 'A diagnostic.', '/var/app/src/budget.php', 42);

        self::assertTrue($owned);
        self::assertSame([], $this->recordingLogger->calls);
        self::assertStringContainsString(
            'ZEF diagnostic record failed: RuntimeException',
            $this->routedErrorLogContents(),
        );
    }

    public function testTheEngineDispatchesInstalledDiagnostics(): void
    {
        $processErrorBoundary = $this->boundary();
        $processErrorBoundary->install();

        try {
            trigger_error('Undefined variable $budget', \E_USER_WARNING);
        } finally {
            $processErrorBoundary->restore();
        }

        self::assertCount(1, $this->recordingLogger->calls);
        self::assertSame('Undefined variable $budget', $this->recordingLogger->calls[0][2]['diagnostic.message']);
    }

    public function testEngineSuppressedDiagnosticsNeverArrive(): void
    {
        $processErrorBoundary = $this->boundary();
        $processErrorBoundary->install();

        try {
            @trigger_error('A suppressed diagnostic', \E_USER_WARNING);
        } finally {
            $processErrorBoundary->restore();
        }

        self::assertSame([], $this->recordingLogger->calls);
    }

    public function testAnUncaughtThrowableAnswersProblemDetails(): void
    {
        $this->boundary()->onUncaughtThrowable(new RuntimeException('The worker observed an impossible state.'));

        self::assertCount(1, $this->recordingLogger->calls);
        self::assertSame('critical', $this->recordingLogger->calls[0][0]);
        self::assertSame('Request failed', $this->recordingLogger->calls[0][1]);
        self::assertSame(RuntimeException::class, $this->recordingLogger->calls[0][2]['exception_class']);
        self::assertSame('req-123', $this->recordingLogger->calls[0][2]['request_id']);

        self::assertCount(1, $this->emitted);
        $response = $this->emitted[0];
        self::assertSame(500, $response->getStatusCode());
        self::assertSame('req-123', $response->getHeaderLine('X-Request-ID'));
        self::assertStringContainsString('application/problem+json', $response->getHeaderLine('Content-Type'));

        $document = $this->decodesToAnArray((string) $response->getBody());
        self::assertSame(500, $document['status']);
        self::assertStringNotContainsString(
            'The worker observed an impossible state.',
            (string) $response->getBody(),
            'A production answer must not disclose the uncaught message.',
        );
        self::assertSame([], $this->sinkLines);
        self::assertSame(
            '',
            $this->routedErrorLogContents(),
            'An answer that shipped names nothing on the SAPI log.',
        );
    }

    public function testADefaultConstructedBoundaryStaysClosed(): void
    {
        $processErrorBoundary = new ProcessErrorBoundary(
            transmission: new ProcessBoundaryTransmission(sapi: 'fpm-fcgi'),
        );

        ob_start();

        try {
            $processErrorBoundary->onUncaughtThrowable(
                new RuntimeException('The worker observed an impossible state.'),
            );
        } finally {
            $output = (string) ob_get_clean();
        }

        self::assertStringContainsString('An error occurred', $output);
        self::assertStringNotContainsString(
            'The worker observed an impossible state.',
            $output,
            'A default-constructed boundary discloses nothing: the closed posture is the default.',
        );
    }

    public function testAnUncaughtTypedThrowableAnswersItsOwnStatus(): void
    {
        $this->boundary()->onUncaughtThrowable(
            new ValidationException('The email field must be a valid address.'),
        );

        self::assertCount(1, $this->recordingLogger->calls);
        self::assertSame('warning', $this->recordingLogger->calls[0][0]);

        self::assertCount(1, $this->emitted);
        self::assertSame(400, $this->emitted[0]->getStatusCode());
    }

    public function testADebugBoundaryDisclosesTheUncaughtMessage(): void
    {
        $this->boundary(debug: true)->onUncaughtThrowable(
            new RuntimeException('The worker observed an impossible state.'),
        );

        self::assertCount(1, $this->emitted);
        self::assertStringContainsString(
            'The worker observed an impossible state.',
            (string) $this->emitted[0]->getBody(),
        );
    }

    public function testAnUncaughtThrowableInACliProcessAnswersTheSinkLine(): void
    {
        $counterMeter = new CounterMeter();

        $this->boundary(sapi: 'cli', counterMeter: $counterMeter)
            ->onUncaughtThrowable(new RuntimeException('The budget import failed.'))
        ;

        self::assertSame(['zef: RuntimeException: The budget import failed.'], $this->sinkLines);
        self::assertSame([], $this->emitted);

        self::assertCount(1, $this->recordingLogger->calls, 'The CLI lane still records the fault.');
        self::assertSame(
            [],
            $counterMeter->snapshot(),
            'A CLI process must not fabricate HTTP metric labels.',
        );
    }

    public function testAnHttpProcessCountsItsUncaughtFailures(): void
    {
        $counterMeter = new CounterMeter();

        $this->boundary(counterMeter: $counterMeter)
            ->onUncaughtThrowable(new RuntimeException('The worker observed an impossible state.'))
        ;

        self::assertNotSame([], $counterMeter->snapshot());
    }

    public function testHeadersAlreadySentNameTheSituationOnTheSapiLog(): void
    {
        $this->boundary(headersSent: true)
            ->onUncaughtThrowable(new RuntimeException('The worker observed an impossible state.'))
        ;

        self::assertSame([], $this->emitted);
        self::assertCount(1, $this->recordingLogger->calls);
        self::assertStringContainsString(
            'ZEF process boundary could not answer req-123: headers already sent.',
            $this->routedErrorLogContents(),
        );
    }

    public function testADoubleFailureAnswersThePlainTextFallback(): void
    {
        $this->boundary(requestSource: static function (): ServerRequest {
            throw new RuntimeException('The SAPI state is unreadable.');
        })->onUncaughtThrowable(new RuntimeException('The worker observed an impossible state.'));

        self::assertStringContainsString(
            'ZEF process boundary failure: RuntimeException',
            $this->routedErrorLogContents(),
        );

        self::assertCount(1, $this->emitted);
        $response = $this->emitted[0];
        self::assertSame(500, $response->getStatusCode());
        self::assertSame('text/plain', $response->getHeaderLine('Content-Type'));
        self::assertSame('Internal Server Error', (string) $response->getBody());
    }

    public function testTheShutdownArmIgnoresNonFatalLastErrors(): void
    {
        $this->lastError = ['type' => \E_WARNING, 'message' => 'A warning.', 'file' => '/var/app/a.php', 'line' => 1];

        $this->withAnArmedBoundary(
            static fn (ProcessErrorBoundary $processErrorBoundary) => $processErrorBoundary->onShutdown(),
        );

        self::assertSame([], $this->recordingLogger->calls);
        self::assertSame([], $this->emitted);
    }

    public function testTheShutdownArmIgnoresAMalformedLastError(): void
    {
        $this->lastError = ['type' => \E_ERROR];

        $this->withAnArmedBoundary(
            static fn (ProcessErrorBoundary $processErrorBoundary) => $processErrorBoundary->onShutdown(),
        );

        self::assertSame([], $this->recordingLogger->calls);
        self::assertSame([], $this->emitted);
    }

    public function testTheShutdownArmRefusesAFatalWithAMistypedFile(): void
    {
        $this->lastError = ['type' => \E_ERROR, 'message' => 'A fatal.', 'file' => 42, 'line' => 1];

        $this->withAnArmedBoundary(
            static fn (ProcessErrorBoundary $processErrorBoundary) => $processErrorBoundary->onShutdown(),
        );

        self::assertSame([], $this->recordingLogger->calls);
        self::assertSame([], $this->emitted);
    }

    public function testTheShutdownArmRefusesAFatalWithAMistypedLine(): void
    {
        $this->lastError = [
            'type' => \E_ERROR,
            'message' => 'A fatal.',
            'file' => '/var/app/src/boot.php',
            'line' => '1',
        ];

        $this->withAnArmedBoundary(
            static fn (ProcessErrorBoundary $processErrorBoundary) => $processErrorBoundary->onShutdown(),
        );

        self::assertSame([], $this->recordingLogger->calls);
        self::assertSame([], $this->emitted);
    }

    public function testTheShutdownArmRecordsAndAnswersAFatal(): void
    {
        $this->lastError = $this->fatalError();

        $this->withAnArmedBoundary(
            static fn (ProcessErrorBoundary $processErrorBoundary) => $processErrorBoundary->onShutdown(),
        );

        self::assertCount(1, $this->recordingLogger->calls);
        self::assertSame('critical', $this->recordingLogger->calls[0][0]);
        self::assertSame('Uncaught fatal', $this->recordingLogger->calls[0][1]);
        self::assertSame('critical', $this->recordingLogger->calls[0][2]['diagnostic.severity']);
        self::assertSame(
            'Call to undefined function missing()',
            $this->recordingLogger->calls[0][2]['diagnostic.message'],
        );
        self::assertSame('/var/app/src/boot.php', $this->recordingLogger->calls[0][2]['diagnostic.file']);
        self::assertSame(42, $this->recordingLogger->calls[0][2]['diagnostic.line']);
        self::assertSame('req-123', $this->recordingLogger->calls[0][2]['request_id']);

        self::assertCount(1, $this->emitted);
        $response = $this->emitted[0];
        self::assertSame(500, $response->getStatusCode());
        self::assertSame('req-123', $response->getHeaderLine('X-Request-ID'));

        $document = $this->decodesToAnArray((string) $response->getBody());
        self::assertSame(500, $document['status']);
        self::assertStringNotContainsString(
            'Call to undefined function missing()',
            (string) $response->getBody(),
            'A production fatal answer must not disclose the engine message.',
        );
        self::assertSame([], $this->sinkLines);
    }

    public function testAFatalInACliProcessAnswersTheSinkLine(): void
    {
        $this->lastError = $this->fatalError();

        $this->withAnArmedBoundary(
            static fn (ProcessErrorBoundary $processErrorBoundary) => $processErrorBoundary->onShutdown(),
            sapi: 'cli',
        );

        self::assertCount(1, $this->recordingLogger->calls);
        self::assertSame(
            ['zef: PHP fatal error: Call to undefined function missing() in /var/app/src/boot.php on line 42'],
            $this->sinkLines,
        );
        self::assertSame([], $this->emitted);
    }

    public function testAFatalWithHeadersAlreadySentIsNamedOnTheSapiLog(): void
    {
        $this->lastError = $this->fatalError();

        $this->withAnArmedBoundary(
            static fn (ProcessErrorBoundary $processErrorBoundary) => $processErrorBoundary->onShutdown(),
            headersSent: true,
        );

        self::assertCount(1, $this->recordingLogger->calls);
        self::assertSame([], $this->emitted);
        self::assertStringContainsString(
            'ZEF process boundary could not answer a fatal: headers already sent.',
            $this->routedErrorLogContents(),
        );
    }

    public function testAShutdownFailureIsContained(): void
    {
        $this->lastError = $this->fatalError();

        $this->withAnArmedBoundary(
            static fn (ProcessErrorBoundary $processErrorBoundary) => $processErrorBoundary->onShutdown(),
            requestSource: static function (): ServerRequest {
                throw new RuntimeException('The SAPI state is unreadable.');
            },
        );

        self::assertStringContainsString(
            'ZEF process boundary failure: RuntimeException',
            $this->routedErrorLogContents(),
        );
    }

    public function testTheSinkLineIsBoundedAndStripped(): void
    {
        $this->lastError = $this->fatalError(
            "Call to undefined function missing()\x1b[31m and control bytes\r\n injected",
            1,
        );

        $this->withAnArmedBoundary(
            static fn (ProcessErrorBoundary $processErrorBoundary) => $processErrorBoundary->onShutdown(),
            sapi: 'cli',
        );

        self::assertSame(
            ['zef: PHP fatal error: Call to undefined function missing()[31m and control bytes injected'
                . ' in /var/app/src/boot.php on line 1'],
            $this->sinkLines,
        );
    }

    public function testTheSinkLineBoundsItsHeadAtOneHundredTwentyCharacters(): void
    {
        $this->lastError = $this->fatalError(str_repeat('a', 200), 1);

        $this->withAnArmedBoundary(
            static fn (ProcessErrorBoundary $processErrorBoundary) => $processErrorBoundary->onShutdown(),
            sapi: 'cli',
        );

        self::assertSame(
            ['zef: PHP fatal error: ' . str_repeat('a', 120)],
            $this->sinkLines,
        );
    }

    public function testTheSinkLineBoundsItsHeadByCharactersNotBytes(): void
    {
        $this->lastError = $this->fatalError(str_repeat("\u{00E9}", 200), 1);

        $this->withAnArmedBoundary(
            static fn (ProcessErrorBoundary $processErrorBoundary) => $processErrorBoundary->onShutdown(),
            sapi: 'cli',
        );

        self::assertSame(
            ['zef: PHP fatal error: ' . str_repeat("\u{00E9}", 120)],
            $this->sinkLines,
            'The head bound is a character bound: a multibyte message keeps whole characters.',
        );
    }

    public function testTheSinkLineNamesAnEmptyMessageHonestly(): void
    {
        $this->boundary(sapi: 'cli')->onUncaughtThrowable(new RuntimeException("\x1b\x1b\r\n"));

        self::assertSame(
            ['zef: RuntimeException: no message'],
            $this->sinkLines,
        );
    }

    public function testTheDefaultSeamsRunTheRealBranchesUnderTheEngine(): void
    {
        $processErrorBoundary = new ProcessErrorBoundary($this->recordingLogger);

        $processErrorBoundary->install();

        try {
            $processErrorBoundary->onUncaughtThrowable(new RuntimeException('The budget import failed.'));
        } finally {
            $processErrorBoundary->restore();
        }

        self::assertCount(1, $this->recordingLogger->calls);
        self::assertSame(
            'GET',
            $this->recordingLogger->calls[0][2]['method'],
            'The default source reads the SAPI state.',
        );
        self::assertStringContainsString(
            'zef: RuntimeException: The budget import failed.',
            $this->routedErrorLogContents(),
            'The default sink routes through the SAPI error log.',
        );

        $processErrorBoundary->install();

        try {
            $processErrorBoundary->onShutdown();
        } finally {
            $processErrorBoundary->restore();
        }

        $lastRecordedIndex = \count($this->recordingLogger->calls) - 1;
        self::assertSame(
            'Request failed',
            $this->recordingLogger->calls[$lastRecordedIndex][1],
            "The engine's own last error must not be a fatal the observer answers.",
        );
    }

    public function testTheDefaultEmitterShipsTheAnswerItWasHanded(): void
    {
        $processErrorBoundary = new ProcessErrorBoundary(
            $this->recordingLogger,
            transmission: new ProcessBoundaryTransmission(sapi: 'fpm-fcgi'),
        );

        ob_start();

        try {
            $processErrorBoundary->onUncaughtThrowable(
                new RuntimeException('The worker observed an impossible state.'),
            );
        } finally {
            $output = (string) ob_get_clean();
        }

        self::assertStringContainsString('"status":500', $output);
        self::assertCount(1, $this->recordingLogger->calls);
    }

    /**
     * Runs the given observation against an armed boundary, restoring it
     * afterwards -- the shutdown arm's direct-call battery must observe
     * the armed posture the engine's own registration would run under.
     *
     * @param Closure(ProcessErrorBoundary): void    $observation   the armed observation
     * @param null|Closure(): ServerRequestInterface $requestSource the failing request source,
     *                                                              when the observation needs one
     */
    private function withAnArmedBoundary(
        Closure $observation,
        bool $headersSent = false,
        string $sapi = 'fpm-fcgi',
        ?Closure $requestSource = null,
    ): void {
        $processErrorBoundary = $this->boundary(requestSource: $requestSource, headersSent: $headersSent, sapi: $sapi);

        $processErrorBoundary->install();

        try {
            $observation($processErrorBoundary);
        } finally {
            $processErrorBoundary->restore();
        }
    }

    /**
     * Builds the boundary under observation: the recorder, the emitter,
     * the sink, the probe and the fatal witness all record into this
     * test's own state, and the request is the deterministic one the
     * arms' facts are asserted against.
     *
     * @param null|Closure(): ServerRequestInterface $requestSource     the arms' request source
     * @param null|Closure(callable(): void): void   $shutdownRegistrar the shutdown-observer
     *                                                                  registration observation
     */
    private function boundary(
        ?Closure $requestSource = null,
        bool $headersSent = false,
        string $sapi = 'fpm-fcgi',
        ?CounterMeter $counterMeter = null,
        bool $debug = false,
        ?Closure $shutdownRegistrar = null,
    ): ProcessErrorBoundary {
        return new ProcessErrorBoundary(
            $this->recordingLogger,
            $debug,
            $counterMeter,
            $requestSource ?? static fn (): ServerRequest => new ServerRequest(
                'GET',
                new Uri('https://app.example.test/users'),
                [],
                [],
                [],
                [],
                null,
                ['X-Request-ID' => 'req-123'],
            ),
            new ProcessBoundaryTransmission(
                function (ResponseInterface $response): void {
                    $this->emitted[] = $response;
                },
                function (string $line): void {
                    $this->sinkLines[] = $line;
                },
                static fn (): bool => $headersSent,
                $sapi,
            ),
            fn (): ?array => $this->lastError,
            $shutdownRegistrar,
        );
    }

    /**
     * Whether the engine's currently active error handler is the given
     * boundary's own arm -- observed without triggering a single engine
     * diagnostic: the probe handler is pushed (answering the active
     * handler) and immediately popped, so the runner's own stack is left
     * exactly as it was found.
     */
    private function activeErrorHandlerIsBoundTo(ProcessErrorBoundary $processErrorBoundary): bool
    {
        $active = set_error_handler(static fn (int $severity, string $message, string $file, int $line): bool => true);
        restore_error_handler();

        if (!$active instanceof Closure) {
            return false;
        }

        $boundThis = new ReflectionFunction($active)->getClosureThis();

        return $boundThis === $processErrorBoundary;
    }

    /**
     * Whether the engine's currently active exception handler is the
     * given boundary's own arm -- the same push-probe-pop observation the
     * error handler answers to, over the engine's exception stack.
     */
    private function activeExceptionHandlerIsBoundTo(ProcessErrorBoundary $processErrorBoundary): bool
    {
        $active = set_exception_handler(static function (Throwable $throwable): void {
        });
        restore_exception_handler();

        if (!$active instanceof Closure) {
            return false;
        }

        $boundThis = new ReflectionFunction($active)->getClosureThis();

        return $boundThis === $processErrorBoundary;
    }

    /**
     * @return array<string, mixed> a well-formed fatal last error
     */
    private function fatalError(string $message = 'Call to undefined function missing()', int $line = 42): array
    {
        return ['type' => \E_ERROR, 'message' => $message, 'file' => '/var/app/src/boot.php', 'line' => $line];
    }

    /**
     * Reads back whatever reached the routed SAPI error log (the empty
     * string when nothing did).
     */
    private function routedErrorLogContents(): string
    {
        if (!is_file($this->routedErrorLog)) {
            return '';
        }

        $contents = file_get_contents($this->routedErrorLog);
        self::assertIsString($contents, 'The routed error log must be readable.');

        return $contents;
    }

    /**
     * @return array<array-key, mixed> the decoded document
     */
    private function decodesToAnArray(string $json): array
    {
        $decoded = json_decode($json, true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded, 'The answer body must decode to an object.');

        return $decoded;
    }
}
