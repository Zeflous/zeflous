<?php

declare(strict_types=1);

namespace Zef\Framework\Middleware;

use Closure;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Throwable;
use Zef\Framework\Telemetry\MeterInterface;

/**
 * The process error boundary: the composition lane's second error
 * face, installed around the PHP process itself rather than around a
 * request.
 *
 * The request-scoped boundary ({@see ErrorMappingMiddleware}) owns every
 * failure a handler lets escape inside the pipeline. This boundary owns
 * everything the pipeline can never see, because PHP raises it at the
 * process level: the diagnostic the engine emits mid-request (a warning,
 * a notice, a deprecation -- {@see ProcessNativeDiagnostics}), the
 * throwable nothing catches (a boot failure, an emission failure, host
 * glue outside the stack -- {@see ProcessUncaughtThrowables}), and the
 * fatal the engine dies on (E_ERROR, E_PARSE, the compile family --
 * {@see ProcessShutdownFatals} over {@see ProcessBoundaryFatals}' witness
 * grammar). All three arms route into the same error system the request
 * boundary feeds: the bounded, redacted, privacy-by-default fault record
 * ({@see ProcessBoundaryRecords} through the JSONL lane's writer) and
 * the RFC 9457 answer ({@see ErrorEnvelope}) whenever a client is still
 * listening -- the arms' bodies carrying the lane's own vocabulary, the
 * shell carrying nothing but the installation contract.
 *
 * The installation contract is honest about PHP's own seams: the error and
 * exception handlers are pushed onto the engine's stacks and popped by
 * {@see self::restore()} (the last install unwinds first -- callers that
 * installed their own handlers afterwards unwind them before restoring
 * this one), while the shutdown callback cannot be unregistered at all,
 * so it is registered once per instance and disarmed by the same restore
 * -- a restored boundary's shutdown arm observes and stays silent.
 *
 * The constructor-accepted seams are the observation points the lane's
 * tests drive ({@see \Zef\Framework\Http\SapiTransmitter}'s sink
 * doctrine): the request the arms synthesise, the transmission lane
 * (the response emission, the CLI sink, the headers-already-sent
 * probe, the SAPI spelling -- each defaulted where its own fold lives,
 * so the defaults are exactly what a booted process runs), and the
 * engine's last error.
 */
final class ProcessErrorBoundary
{
    private bool $installed = false;

    private bool $shutdownRegistered = false;

    /** @var Closure(callable(): void): void */
    private readonly Closure $shutdownRegistrar;

    private readonly ProcessNativeDiagnostics $processNativeDiagnostics;

    private readonly ProcessUncaughtThrowables $processUncaughtThrowables;

    private readonly ProcessShutdownFatals $processShutdownFatals;

    private readonly ProcessBoundaryRecords $processBoundaryRecords;

    /** @var Closure(int, string, string, int): bool the exact error-handler instance this boundary pushes -- the restore probe's own reference */
    private readonly Closure $errorHandler;

    /** @var Closure(Throwable): void the exact exception-handler instance this boundary pushes -- the restore probe's own reference */
    private readonly Closure $exceptionHandler;

    /**
     * @param null|LoggerInterface                   $logger            the PSR-3 sink the
     *                                                                  fault records and diagnostics
     *                                                                  write through; null routes them to
     *                                                                  the SAPI error log
     * @param bool                                   $debug             the disclosure-approved
     *                                                                  debug posture (the factory owns
     *                                                                  the APP_ENV gating)
     * @param null|MeterInterface                    $meter             the metrics boundary;
     *                                                                  null leaves the counters alone
     * @param null|Closure(): ServerRequestInterface $requestSource     answers the request the
     *                                                                  arms' facts synthesise from;
     *                                                                  null reads the SAPI superglobals
     * @param null|ProcessBoundaryTransmission       $transmission      the shipping lane the
     *                                                                  arms' answers ride, carrying the
     *                                                                  response emission, the CLI sink,
     *                                                                  the headers-already-sent probe
     *                                                                  and the SAPI spelling; null
     *                                                                  constructs the real engine
     *                                                                  defaults
     * @param null|Closure(): ?array<string, mixed>  $lastErrorProvider answers the engine's
     *                                                                  last error (the shutdown arm's
     *                                                                  fatal witness); null asks the
     *                                                                  engine's own `error_get_last()`
     * @param null|Closure(callable(): void): void   $shutdownRegistrar receives the
     *                                                                  shutdown-observer registration;
     *                                                                  null registers with the engine
     *                                                                  for real -- the one arm no
     *                                                                  restore can unwind, observed
     *                                                                  through the same sink doctrine
     *                                                                  the transmission lane carries
     */
    public function __construct(
        ?LoggerInterface $logger = null,
        bool $debug = false,
        ?MeterInterface $meter = null,
        ?Closure $requestSource = null,
        ?ProcessBoundaryTransmission $transmission = null,
        ?Closure $lastErrorProvider = null,
        ?Closure $shutdownRegistrar = null,
    ) {
        $processBoundaryRecords = new ProcessBoundaryRecords($logger, $requestSource);
        $processBoundaryTransmission = $transmission ?? new ProcessBoundaryTransmission();
        $this->shutdownRegistrar = $shutdownRegistrar ?? register_shutdown_function(...);
        $this->errorHandler = $this->onNativeDiagnostic(...);
        $this->exceptionHandler = $this->onUncaughtThrowable(...);
        $this->processBoundaryRecords = $processBoundaryRecords;

        $this->processNativeDiagnostics = new ProcessNativeDiagnostics($processBoundaryRecords);
        $this->processUncaughtThrowables = new ProcessUncaughtThrowables(
            $processBoundaryRecords,
            $processBoundaryTransmission,
            $debug,
            $logger,
            $meter,
        );
        $this->processShutdownFatals = new ProcessShutdownFatals(
            $processBoundaryRecords,
            $processBoundaryTransmission,
            $debug,
            $lastErrorProvider,
        );
    }

    /**
     * Installs the three arms onto the PHP process: the error handler, the
     * exception handler, and the shutdown observer. Idempotent -- a
     * boundary already installed observes the second call and does
     * nothing -- and the shutdown callback is registered once per
     * instance, so install/restore cycles never stack observers. Each
     * installation opens a fresh facts window
     * ({@see ProcessBoundaryRecords::freshRequest()}): an
     * install/restore cycle per request observes the request it serves,
     * never the one the process started with.
     */
    public function install(): void
    {
        if ($this->installed) {
            return;
        }

        $this->pushEngineHandlers();
        $this->registerShutdownObserver();
        $this->installed = true;
        $this->processBoundaryRecords->freshRequest();
    }

    /**
     * Unwinds the installation: the error and exception handlers are
     * popped off the engine's stacks (the last install unwinds first), and
     * the shutdown observer is disarmed -- it stays registered, because
     * PHP cannot unregister it, but a restored boundary's arm observes and
     * stays silent.
     *
     * The documented contract is enforced, not trusted: the handlers are
     * popped only when the boundary still owns the top of both engine
     * stacks. A foreign handler installed after this boundary's own sits
     * between them, and popping past it would remove the foreigner's --
     * the probe ({@see EngineHandlerStacks::toppedBy()}) refuses the
     * restoration loudly instead, leaving both stacks exactly as they are
     * and the boundary installed, for the caller that still owes the
     * engine its own unwinding.
     */
    public function restore(): void
    {
        if (!$this->installed) {
            return;
        }

        if (!EngineHandlerStacks::toppedBy($this->errorHandler, $this->exceptionHandler)) {
            $refusal = 'ZEF process boundary restore refused: a handler installed after the boundary still owns '
                . 'the engine stack -- unwind the handlers installed after it before restoring the boundary.';
            error_log($refusal);

            return;
        }

        restore_error_handler();
        restore_exception_handler();
        $this->installed = false;
    }

    /**
     * Whether the boundary is currently installed.
     */
    public function installed(): bool
    {
        return $this->installed;
    }

    /**
     * The native diagnostic arm: the engine's error-handler contract.
     *
     * @param int    $severity the engine's severity bits
     * @param string $message  the engine's diagnostic text
     * @param string $file     the file the diagnostic names
     * @param int    $line     the line the diagnostic names
     *
     * @return bool false hands the diagnostic back to the engine's own
     *              handling; true owns it (the engine writes nothing)
     */
    public function onNativeDiagnostic(int $severity, string $message, string $file, int $line): bool
    {
        return $this->processNativeDiagnostics->handle($severity, $message, $file, $line);
    }

    /**
     * The uncaught throwable arm: the engine's exception-handler contract.
     */
    public function onUncaughtThrowable(Throwable $throwable): void
    {
        $this->processUncaughtThrowables->handle($throwable);
    }

    /**
     * The shutdown arm: the engine's fatal-error observer -- silent for
     * every shutdown of a boundary that is not installed.
     */
    public function onShutdown(): void
    {
        $armed = $this->installed;

        if (!$armed) {
            return;
        }

        $this->processShutdownFatals->observe();
    }

    /**
     * Pushes the two handler closures onto the engine's stacks -- the
     * installation half of the pair {@see EngineHandlerStacks} probes
     * and the engine's own restores unwind.
     */
    private function pushEngineHandlers(): void
    {
        set_error_handler($this->errorHandler);
        set_exception_handler($this->exceptionHandler);
    }

    /**
     * Registers the shutdown observer once per instance -- the engine's
     * one arm no restore can ever unwind, so re-arming after a restore
     * reuses the registration it already holds.
     */
    private function registerShutdownObserver(): void
    {
        if ($this->shutdownRegistered) {
            return;
        }

        $observer = $this->onShutdown(...);
        ($this->shutdownRegistrar)($observer);
        $this->shutdownRegistered = true;
    }
}
