<?php

declare(strict_types=1);

namespace Zef\Framework\Middleware;

use Closure;
use RuntimeException;
use Throwable;
use Zef\Framework\Http\ErrorEnvelope;

/**
 * The process boundary's shutdown arm: the engine's fatal-error
 * observer body, answering the fatal family only the shutdown moment
 * can witness.
 *
 * A fatal the error handler can never see is witnessed through
 * {@see ProcessBoundaryFatals}' grammar, recorded at critical through
 * the record fold, and answered when a client is still listening --
 * but only while headers have not already shipped: appending a JSON
 * document to a half-emitted body would serve garbage, so the arm
 * names the situation on the SAPI log and lets the record speak. The
 * observer stays silent for every other shutdown (the grammar refuses
 * everything but the fatal family), and the boundary's shell owns the
 * armed-posture verdict a restored boundary answers with.
 */
final readonly class ProcessShutdownFatals
{
    /** @var Closure(): ?array<string, mixed> */
    private Closure $lastErrorProvider;

    /**
     * @param ProcessBoundaryRecords                $processBoundaryRecords      the record fold the arm
     *                                                                           writes through
     * @param ProcessBoundaryTransmission           $processBoundaryTransmission the shipping lane the
     *                                                                           arm answers through
     * @param bool                                  $debug                       the disclosure-approved
     *                                                                           debug posture
     * @param null|Closure(): ?array<string, mixed> $lastErrorProvider           answers the
     *                                                                           engine's last error (the
     *                                                                           fatal witness); null asks
     *                                                                           the engine's own
     *                                                                           `error_get_last()`
     */
    public function __construct(
        private ProcessBoundaryRecords $processBoundaryRecords,
        private ProcessBoundaryTransmission $processBoundaryTransmission,
        private bool $debug,
        ?Closure $lastErrorProvider = null,
    ) {
        $this->lastErrorProvider = $lastErrorProvider ?? error_get_last(...);
    }

    /**
     * Observes one shutdown: the witnessed fatal is recorded and
     * answered; every other shutdown stays silent.
     */
    public function observe(): void
    {
        $lastError = ($this->lastErrorProvider)();

        if ($lastError === null) {
            return;
        }

        $fatal = ProcessBoundaryFatals::witness($lastError);

        if (!$fatal instanceof ProcessBoundaryFatals) {
            return;
        }

        try {
            $facts = $this->processBoundaryRecords->facts();
            $composed = $this->composed($fatal);

            $this->processBoundaryRecords->fatal($fatal, $facts);

            if (!$this->processBoundaryTransmission->answersHttp()) {
                $this->processBoundaryTransmission->sink('PHP fatal error', $composed);
            } elseif (
                !$this->processBoundaryTransmission->ship(ErrorEnvelope::answered(
                    new RuntimeException($composed),
                    500,
                    $this->debug,
                    $facts['correlation'],
                    $facts['request'],
                ))
            ) {
                error_log('ZEF process boundary could not answer a fatal: headers already sent.');
            }
        } catch (Throwable $throwable) {
            error_log('ZEF process boundary failure: ' . $throwable::class);
        }
    }

    /**
     * The message the fatal's answer carries -- the witnessed facts
     * composed and bounded before they cross into the envelope lane.
     *
     * @param ProcessBoundaryFatals $processBoundaryFatals the witnessed fatal's facts
     *
     * @return string the composed, bounded message
     */
    private function composed(ProcessBoundaryFatals $processBoundaryFatals): string
    {
        return mb_substr(
            $processBoundaryFatals->message
                . ' in '
                . $processBoundaryFatals->file
                . ' on line '
                . $processBoundaryFatals->line,
            0,
            2048,
        );
    }
}
