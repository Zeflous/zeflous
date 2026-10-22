<?php

declare(strict_types=1);

namespace Zef\Framework\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Throwable;
use Zef\Framework\Http\ErrorEnvelope;
use Zef\Framework\Http\ErrorStatusMap;
use Zef\Framework\Http\FallbackResponse;
use Zef\Framework\Http\ThirdPartyExceptionMapper;
use Zef\Framework\Telemetry\MeterInterface;

/**
 * The process boundary's uncaught throwable arm: the engine's
 * exception-handler body, answering the throwable nothing caught.
 *
 * The throwable is normalised ({@see ThirdPartyExceptionMapper}),
 * status-mapped ({@see ErrorStatusMap}), fault-recorded (the recorder,
 * carrying the metrics boundary's own honesty: a CLI process holds no
 * counter, because an uncaught CLI throwable has no HTTP semantics to
 * count) and answered with the full RFC 9457 problem-details contract
 * negotiated off the request the SAPI still holds -- through the
 * bounded, control-byte-stripped sink line when the SAPI is a CLI one,
 * with the engine's own termination owning the exit code.
 *
 * The arm's own failure is contained: the boundary never throws out of
 * the handler the engine called in its last moment -- the double
 * failure is named on the SAPI log, and an HTTP client still listening
 * receives the plain-text fallback instead of garbage.
 */
final readonly class ProcessUncaughtThrowables
{
    private ErrorFaultRecorder $errorFaultRecorder;

    public function __construct(
        private ProcessBoundaryRecords $processBoundaryRecords,
        private ProcessBoundaryTransmission $processBoundaryTransmission,
        private bool $debug,
        ?LoggerInterface $logger = null,
        ?MeterInterface $meter = null,
    ) {
        $this->errorFaultRecorder = new ErrorFaultRecorder(
            $logger,
            $this->processBoundaryTransmission->answersHttp() ? $meter : null,
        );
    }

    /**
     * Handles one throwable nothing caught.
     *
     * @param Throwable $throwable the throwable the engine could not deliver
     */
    public function handle(Throwable $throwable): void
    {
        try {
            $facts = $this->processBoundaryRecords->facts();

            $mappedThrowable = ThirdPartyExceptionMapper::normalize($throwable);
            $status = ErrorStatusMap::statusFor($mappedThrowable);
            $this->errorFaultRecorder->record($mappedThrowable, $status, $facts['correlation'], $facts['request']);

            $this->answer(
                ErrorEnvelope::answered(
                    $mappedThrowable,
                    $status,
                    $this->debug,
                    $facts['correlation'],
                    $facts['request'],
                ),
                $facts['correlation'],
                $throwable,
            );
        } catch (Throwable $boundaryFailure) {
            error_log('ZEF process boundary failure: ' . $boundaryFailure::class);

            if ($this->processBoundaryTransmission->answersHttp()) {
                try {
                    $this->processBoundaryTransmission->ship(FallbackResponse::internalError('unavailable'));
                } catch (Throwable $emissionFailure) {
                    error_log('ZEF process boundary fallback failed: ' . $emissionFailure::class);
                }
            }
        }
    }

    /**
     * Ships an answer to whichever consumer the SAPI names: the client
     * through the transmission fold's emitter, or the CLI operator
     * through its bounded sink line -- naming the headers-already-sent
     * refusal on the SAPI log when neither can ship.
     *
     * @param ResponseInterface $response      the answer the throwable mapped to
     * @param string            $correlationId the request the answer stamps
     * @param Throwable         $throwable     the throwable the answer is for
     */
    private function answer(ResponseInterface $response, string $correlationId, Throwable $throwable): void
    {
        if (!$this->processBoundaryTransmission->answersHttp()) {
            $this->processBoundaryTransmission->sink($throwable::class, $throwable->getMessage());

            return;
        }

        if (!$this->processBoundaryTransmission->ship($response)) {
            error_log(
                'ZEF process boundary could not answer ' . $correlationId . ': headers already sent.',
            );
        }
    }
}
