<?php

declare(strict_types=1);

namespace Zef\Framework\Middleware;

use Closure;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Throwable;
use Zef\Framework\Exception\NativeErrorException;
use Zef\Framework\Http\CorrelationId;
use Zef\Framework\Http\DiagnosticContextFactory;
use Zef\Framework\Http\DiagnosticSeverity;
use Zef\Framework\Http\ErrorEnvelope;
use Zef\Framework\Http\ErrorFaultLogWriter;
use Zef\Framework\Http\ErrorStatusMap;
use Zef\Framework\Http\FallbackResponse;
use Zef\Framework\Http\RequestFactory;
use Zef\Framework\Http\ResponseEmitter;
use Zef\Framework\Http\ThirdPartyExceptionMapper;
use Zef\Framework\Telemetry\MeterInterface;

/**
 * The process error boundary: the composition lane's second error face,
 * installed around the PHP process itself rather than around a request.
 *
 * The request-scoped boundary ({@see ErrorMappingMiddleware}) owns every
 * failure a handler lets escape inside the pipeline. This boundary owns
 * everything the pipeline can never see, because PHP raises it at the
 * process level: the diagnostic the engine emits mid-request (a warning,
 * a notice, a deprecation), the throwable nothing catches (a boot
 * failure, an emission failure, host glue outside the stack), and the
 * fatal the engine dies on (E_ERROR, E_PARSE, the compile family). All
 * three arms route into the same error system the request boundary
 * feeds -- the bounded, redacted, privacy-by-default fault record
 * ({@see DiagnosticContextFactory} through {@see ErrorFaultLogWriter},
 * {@see ErrorFaultRecorder} for the throwable lane) and the RFC 9457
 * answer ({@see ErrorEnvelope}) whenever a client is still listening.
 *
 * The arms' standing:
 *
 * - **Native diagnostics** respect the engine's own suppression contract:
 *   a severity masked by `error_reporting()` (the `@` operator's mask, an
 *   ini directive's mask) is handed back to the engine untouched; the
 *   severities PHP aborts on (E_USER_ERROR, E_RECOVERABLE_ERROR) are
 *   *converted* -- thrown as {@see NativeErrorException} so a surrounding
 *   pipeline can map, record and answer them through the typed lane; every
 *   other severity is recorded once through the diagnostic lane and owned
 *   (the handler answers true, so the engine's duplicate SAPI write never
 *   happens -- one diagnostic, one record, one destination).
 * - **Uncaught throwables** are normalised ({@see ThirdPartyExceptionMapper}),
 *   status-mapped ({@see ErrorStatusMap}), fault-recorded and answered with
 *   the full problem-details contract negotiated off the request the SAPI
 *   still holds; when the SAPI is a CLI one, the answer is a bounded,
 *   control-byte-stripped diagnostic line on the sink instead, and the
 *   engine's own termination owns the exit code. Modern PHP raises most
 *   of its classical fatals as Error throwables (an undefined-function
 *   call is an Error, not a bare fatal), so this lane -- not the shutdown
 *   arm -- is where the engine's own failures arrive.
 * - **Shutdown fatals** are recorded at critical and answered the same way
 *   -- but only while headers have not already shipped: appending a JSON
 *   document to a half-emitted body would serve garbage, so the boundary
 *   names the situation on the SAPI log and lets the record speak.
 *
 * The SAPI honesty fold the metrics boundary answers to lives in the
 * construction: a CLI process's boundary carries no meter, because an
 * uncaught CLI throwable has no HTTP semantics to count and a synthetic
 * request's fabricated method would lie in the `zef.http.errors.total`
 * label set -- its faults stay on the log lane alone.
 *
 * The installation contract is honest about PHP's own seams: the error and
 * exception handlers are pushed onto the engine's stacks and popped by
 * {@see self::restore()} (the last install unwinds first -- callers that
 * installed their own handlers afterwards unwind them before restoring
 * this one), while the shutdown callback cannot be unregistered at all,
 * so it is registered once per instance and disarmed by the same restore
 * -- a restored boundary's shutdown arm observes and stays silent. The
 * constructor-accepted closures are the observation points the lane's
 * tests drive ({@see SapiTransmitter}'s sink doctrine): the request the
 * arms synthesise, the response emission, the CLI sink, the
 * headers-already-sent probe, and the SAPI spelling -- the defaults being
 * the real engine calls, exactly what a booted process runs.
 */
final class ProcessErrorBoundary
{
    /** The fatal family only the shutdown arm can observe. */
    private const int FATAL_AT_SHUTDOWN = \E_ERROR | \E_PARSE | \E_CORE_ERROR | \E_CORE_WARNING
        | \E_COMPILE_ERROR | \E_COMPILE_WARNING | \E_USER_ERROR;

    /** The severities the error arm converts into the typed exception lane. */
    private const int CONVERTS_TO_EXCEPTION = \E_USER_ERROR | \E_RECOVERABLE_ERROR;

    /** The SAPI spellings that answer no HTTP client. */
    private const array CLI_SAPI_SPELLINGS = ['cli', 'phpdbg', 'embed'];

    /** The bounded length of one sink line's message head. */
    private const int SINK_MESSAGE_BOUND = 120;

    private bool $installed = false;

    private bool $shutdownRegistered = false;

    /** @var Closure(): ServerRequestInterface */
    private readonly Closure $requestSource;

    /** @var Closure(ResponseInterface): void */
    private readonly Closure $responseEmitter;

    /** @var Closure(string): void */
    private readonly Closure $diagnosticSink;

    /** @var Closure(): bool */
    private readonly Closure $headersSentProbe;

    /** @var Closure(): ?array<string, mixed> */
    private readonly Closure $lastErrorProvider;

    private readonly string $sapi;

    private readonly ErrorFaultRecorder $errorFaultRecorder;

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
     * @param null|Closure(ResponseInterface): void  $responseEmitter   ships an answer to the
     *                                                                  client; null emits through the
     *                                                                  HTTP layer's own emitter
     * @param null|Closure(string): void             $diagnosticSink    receives the bounded CLI
     *                                                                  diagnostic line; null routes it
     *                                                                  through the SAPI error log -- the
     *                                                                  CLI operator's own channel, the
     *                                                                  same destination the lane's other
     *                                                                  fallbacks write to
     * @param null|Closure(): bool                   $headersSentProbe  answers whether the SAPI
     *                                                                  already shipped its headers; null
     *                                                                  asks the engine
     * @param null|string                            $sapi              the SAPI spelling the arms
     *                                                                  branch on; null reads the running
     *                                                                  process's own
     * @param null|Closure(): ?array<string, mixed>  $lastErrorProvider answers the engine's
     *                                                                  last error (the shutdown arm's
     *                                                                  fatal witness); null asks the
     *                                                                  engine's own `error_get_last()`
     */
    public function __construct(
        private readonly ?LoggerInterface $logger = null,
        private readonly bool $debug = false,
        ?MeterInterface $meter = null,
        ?Closure $requestSource = null,
        ?Closure $responseEmitter = null,
        ?Closure $diagnosticSink = null,
        ?Closure $headersSentProbe = null,
        ?string $sapi = null,
        ?Closure $lastErrorProvider = null,
    ) {
        $this->sapi = $sapi ?? \PHP_SAPI;
        $this->errorFaultRecorder = new ErrorFaultRecorder($this->logger, $this->answersHttp() ? $meter : null);
        $this->requestSource = $requestSource ?? static fn (): ServerRequestInterface => RequestFactory::fromGlobals();
        $this->responseEmitter = $responseEmitter ?? static function (ResponseInterface $response): void {
            new ResponseEmitter()->emit($response);
        };
        $this->diagnosticSink = $diagnosticSink ?? static function (string $line): void {
            error_log($line);
        };
        $this->headersSentProbe = $headersSentProbe ?? static fn (): bool => headers_sent();
        $this->lastErrorProvider = $lastErrorProvider ?? error_get_last(...);
    }

    /**
     * Installs the three arms onto the PHP process: the error handler, the
     * exception handler, and the shutdown observer. Idempotent -- a
     * boundary already installed observes the second call and does
     * nothing -- and the shutdown callback is registered once per
     * instance, so install/restore cycles never stack observers.
     */
    public function install(): void
    {
        if ($this->installed) {
            return;
        }

        set_error_handler($this->onNativeDiagnostic(...));
        set_exception_handler($this->onUncaughtThrowable(...));

        if (!$this->shutdownRegistered) {
            register_shutdown_function($this->onShutdown(...));
            $this->shutdownRegistered = true;
        }

        $this->installed = true;
    }

    /**
     * Unwinds the installation: the error and exception handlers are
     * popped off the engine's stacks (the last install unwinds first), and
     * the shutdown observer is disarmed -- it stays registered, because
     * PHP cannot unregister it, but a restored boundary's arm observes and
     * stays silent.
     */
    public function restore(): void
    {
        if (!$this->installed) {
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
        if (($severity & error_reporting()) === 0) {
            return false;
        }

        if (($severity & self::CONVERTS_TO_EXCEPTION) !== 0) {
            throw NativeErrorException::diagnostic(
                mb_substr($message . ' in ' . $file . ' on line ' . $line, 0, 2048),
                DiagnosticSeverity::for($severity),
                $file,
                $line,
            );
        }

        try {
            $serverRequest = ($this->requestSource)();
            $level = DiagnosticSeverity::for($severity);
            ErrorFaultLogWriter::write(
                $this->logger,
                $level,
                DiagnosticContextFactory::create(
                    $level,
                    $message,
                    $file,
                    $line,
                    CorrelationId::for($serverRequest),
                    $serverRequest,
                ),
                'Native diagnostic',
            );
        } catch (Throwable $throwable) {
            error_log('ZEF diagnostic record failed: ' . $throwable::class);
        }

        return true;
    }

    /**
     * The uncaught throwable arm: the engine's exception-handler contract.
     * A throwable nothing caught is normalised, status-mapped,
     * fault-recorded and answered -- through the full problem-details
     * contract when a client is listening, through the bounded sink line
     * when the SAPI is a CLI one.
     */
    public function onUncaughtThrowable(Throwable $throwable): void
    {
        try {
            $serverRequest = ($this->requestSource)();
            $correlationId = CorrelationId::for($serverRequest);

            $mappedThrowable = ThirdPartyExceptionMapper::normalize($throwable);
            $status = ErrorStatusMap::statusFor($mappedThrowable);
            $this->errorFaultRecorder->record($mappedThrowable, $status, $correlationId, $serverRequest);

            $response = ErrorEnvelope::answered(
                $mappedThrowable,
                $status,
                $this->debug,
                $correlationId,
                $serverRequest,
            );

            $this->answer($response, $correlationId, $throwable);
        } catch (Throwable $boundaryFailure) {
            error_log('ZEF process boundary failure: ' . $boundaryFailure::class);

            if ($this->answersHttp() && !($this->headersSentProbe)()) {
                try {
                    ($this->responseEmitter)(FallbackResponse::internalError('unavailable'));
                } catch (Throwable $emissionFailure) {
                    error_log('ZEF process boundary fallback failed: ' . $emissionFailure::class);
                }
            }
        }
    }

    /**
     * The shutdown arm: the engine's fatal-error observer. A fatal the
     * error handler can never see is recorded at critical and answered
     * when a client is still listening; the observer stays silent for
     * every other shutdown, and for every shutdown of a restored
     * boundary.
     */
    public function onShutdown(): void
    {
        if (!$this->installed) {
            return;
        }

        $lastError = ($this->lastErrorProvider)();

        if ($lastError === null) {
            return;
        }

        $type = $lastError['type'] ?? null;
        $message = $lastError['message'] ?? null;
        $file = $lastError['file'] ?? null;
        $line = $lastError['line'] ?? null;

        if (
            !\is_int($type)
            || ($type & self::FATAL_AT_SHUTDOWN) === 0
            || !\is_string($message)
            || !\is_string($file)
            || !\is_int($line)
        ) {
            return;
        }

        try {
            $serverRequest = ($this->requestSource)();
            $correlationId = CorrelationId::for($serverRequest);
            $composed = mb_substr($message . ' in ' . $file . ' on line ' . $line, 0, 2048);

            ErrorFaultLogWriter::write(
                $this->logger,
                'critical',
                DiagnosticContextFactory::create(
                    'critical',
                    $message,
                    $file,
                    $line,
                    $correlationId,
                    $serverRequest,
                ),
                'Uncaught fatal',
            );

            if ($this->answersHttp() && !($this->headersSentProbe)()) {
                $response = ErrorEnvelope::answered(
                    new RuntimeException($composed),
                    500,
                    $this->debug,
                    $correlationId,
                    $serverRequest,
                );
                ($this->responseEmitter)($response);
            } elseif (!$this->answersHttp()) {
                ($this->diagnosticSink)($this->sinkLine('PHP fatal error', $composed));
            } else {
                error_log('ZEF process boundary could not answer a fatal: headers already sent.');
            }
        } catch (Throwable $throwable) {
            error_log('ZEF process boundary failure: ' . $throwable::class);
        }
    }

    /**
     * Whether the running SAPI answers an HTTP client.
     */
    private function answersHttp(): bool
    {
        return !\in_array($this->sapi, self::CLI_SAPI_SPELLINGS, true);
    }

    /**
     * Ships an answer to whichever consumer the SAPI names: the client
     * through the emitter, or the CLI operator through the sink line.
     *
     * @param ResponseInterface $response      the answer the throwable mapped to
     * @param string            $correlationId the request the answer stamps
     * @param Throwable         $throwable     the throwable the answer is for
     */
    private function answer(ResponseInterface $response, string $correlationId, Throwable $throwable): void
    {
        if (!$this->answersHttp()) {
            ($this->diagnosticSink)($this->sinkLine($throwable::class, $throwable->getMessage()));

            return;
        }

        if (($this->headersSentProbe)()) {
            error_log(
                'ZEF process boundary could not answer ' . $correlationId . ': headers already sent.',
            );

            return;
        }

        ($this->responseEmitter)($response);
    }

    /**
     * The bounded, control-byte-stripped line the CLI sink carries -- the
     * report display's own hygiene, so a stored escape sequence can never
     * ride a boundary line into the operator's terminal.
     *
     * @param string $origin  the line's origin label
     * @param string $message the message the line carries
     *
     * @return non-empty-string the sink line
     */
    private function sinkLine(string $origin, string $message): string
    {
        $stripped = preg_replace('/[\x00-\x1F\x7F]/', '', mb_substr($message, 0, self::SINK_MESSAGE_BOUND));
        \assert(\is_string($stripped));

        $head = $stripped !== '' ? $stripped : 'no message';

        return 'zef: ' . $origin . ': ' . $head;
    }
}
