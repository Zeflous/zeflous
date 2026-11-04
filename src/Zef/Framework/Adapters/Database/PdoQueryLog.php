<?php

declare(strict_types=1);

namespace Zef\Framework\Adapters\Database;

use Closure;
use Psr\Log\LoggerInterface;
use Throwable;
use Zef\Framework\Database\QueryLogPolicy;
use Zef\Framework\Database\SqlQuery;
use Zef\Framework\Telemetry\MeterInterface;
use Zef\Framework\Telemetry\TelemetryClock;

/**
 * The query-log family's bracket: the measured window one armed
 * connection holds around its statement runs -- the slow-request
 * family's own measured-span shape over an injectable monotonic clock
 * seam, the one closure the duration domain needs (the telemetry
 * clock's own interface exists for the two-domain span; a single
 * reading scatters across no seam at all).
 *
 * {@see begin()} reads the window's opening instant and {@see end()}
 * its closing one, and the emission between them is best-effort the
 * way the router lane's own telemetry is ({@see
 * \Zef\Framework\Middleware\RouteMatchTelemetry}): the observation is
 * never worth the query's own answer, so a failing sink -- or a
 * failing clock -- can neither convert a successful statement into a
 * failure nor mask the exception a failing statement already threw,
 * which matters double here because the emission runs in the finally
 * seam the throw path shares: a statement that answered slowly
 * records, and a statement that threw slowly records and then
 * propagates its throw untouched.
 *
 * The bracket is the whole seam the connection holds, or null -- the
 * honest void: a connection the wiring handed no query log never reads
 * the clock, the timing family's own inert posture, so there is no
 * observable difference between "measured and discarded" and "never
 * measured". {@see self::whenArmed()} is the fold that answers which
 * posture a declaration spells.
 */
final readonly class PdoQueryLog
{
    /** @var Closure():int the monotonic clock seam, nanoseconds */
    private Closure $nowNs;

    /**
     * @param QueryLogPolicy       $queryLogPolicy the family's frozen knob set
     * @param null|MeterInterface  $meter          the metrics seam, or null
     *                                             when the deployment wires
     *                                             no meter
     * @param null|LoggerInterface $logger         the record seam, or null
     *                                             when the deployment
     *                                             declares no structured
     *                                             log address
     * @param null|(Closure():int) $nowNs          the monotonic clock seam,
     *                                             nanoseconds; defaults to
     *                                             the telemetry engine's
     *                                             own system clock
     *                                             ({@see TelemetryClock})
     */
    public function __construct(
        private QueryLogPolicy $queryLogPolicy,
        private ?MeterInterface $meter = null,
        private ?LoggerInterface $logger = null,
        ?Closure $nowNs = null,
    ) {
        $this->nowNs = $nowNs ?? new TelemetryClock()->monotonicNs(...);
    }

    /**
     * The measured window's opening instant, in monotonic
     * nanoseconds.
     */
    public function begin(): int
    {
        return ($this->nowNs)();
    }

    /**
     * The window's closing edge: the emission the statement's span
     * answers, contained -- the observation is never worth the query's
     * own answer.
     *
     * @param SqlQuery $sqlQuery the statement that ran inside the window
     * @param int      $startNs  the window's opening instant, from
     *                           {@see self::begin()}
     */
    public function end(SqlQuery $sqlQuery, int $startNs): void
    {
        try {
            PdoQuerySignals::emit(
                $this->meter,
                $this->logger,
                $sqlQuery,
                ($this->nowNs)() - $startNs,
                $this->queryLogPolicy,
            );
        } catch (Throwable) {
            // Best-effort: the query's own answer is never worth replacing.
        }
    }
}
