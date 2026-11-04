<?php

declare(strict_types=1);

namespace Zef\Framework\Telemetry;

/**
 * One recorded log: the value object the logs lane carries from the
 * recording point to the exporter -- the severity's spelling, the
 * body, the timestamp in the Unix-nanosecond domain the wire
 * protocol spells as a string, and the attribute map.
 *
 * The record is deliberately raw. It is the recording point that
 * owns the hygiene (the recording surface sanitises the body and
 * the attributes as it constructs the record, the reference's own
 * posture), exactly the way the span lane's {@see SpanData} is the
 * frozen product of a timeline whose guards already ran: an
 * exporter that re-clamped a sanitised body would second-guess the
 * recording, and an exporter that trusted an unsanitised one would
 * be a recording point that never ran. The delivery cadence that
 * lands in this lane's next slice constructs records through its
 * own recording verb, and the hygiene rides there.
 *
 * The timestamp is the caller's own reading of the wall domain --
 * the one clock seam the family owns ({@see TelemetryClockInterface}
 * and its system answer), never a second clock the record mints on
 * its own behalf.
 *
 * Ported from the reference implementation (legacy `zef-main`
 * `Domain/Observability/LogRecord.php`): the four promoted fields
 * and the empty-attributes default move verbatim.
 */
final readonly class LogRecord
{
    /**
     * @param string              $severity     the severity's own spelling
     *                                          (`ERROR`, `INFO`) -- the recording
     *                                          point's vocabulary, carried verbatim
     * @param string              $body         the recorded message
     * @param int                 $timeUnixNano the record's timestamp, in the
     *                                          Unix-nanosecond domain
     * @param array<string,mixed> $attributes   the record's dimensions
     */
    public function __construct(
        public string $severity,
        public string $body,
        public int $timeUnixNano,
        public array $attributes = [],
    ) {
    }
}
