<?php

declare(strict_types=1);

namespace Zef\Framework\Telemetry;

use Zef\Framework\Exception\ZefException;

/**
 * The OTLP delivery lane's own failure root: the transport-level
 * and transient rejections the exporter raises -- a refused
 * connection, a timed-out exchange, a collector answering 5xx --
 * as the framework's single catchable failure type ({@see
 * ZefException}: framework code never throws a bare SPL
 * exception, callers keep the one root to catch).
 *
 * The root's own split from the permanent rejection is the retry
 * lane's judgement surface ({@see BatchSpanProcessor}'s drain):
 * this exception answers the failures a retry can honestly
 * address -- the transport might come back, the collector might
 * recover -- while the permanent rejection stays the plain {@see
 * \InvalidArgumentException} the exporter raises for a 4xx answer,
 * a failure no number of retries changes into a delivery. The
 * drain reads the types, never the messages, so the split is the
 * contract.
 *
 * The transport failure's diagnostic rides the message, already
 * redacted through the hygiene unit ({@see TelemetrySanitizer::
 * redact()}): an engine error message is a free-form diagnostic
 * string like any other, and the transport's own text (a URL, a
 * socket reason) may carry credential material a query parameter
 * or a credential-bearing endpoint path would explain -- the
 * same redaction every diagnostic string the lanes emit answers
 * through, so the failure itself cannot become the leak.
 *
 * Ported from the reference implementation (legacy
 * `zef-framework-src` `Infrastructure/Observability/
 * OtlpExporterException.php`), with the port's one deviation: the
 * reference extended the SPL {@see \RuntimeException} directly,
 * and this port extends the framework root the repository's own
 * exception discipline demands -- the root already extends the
 * same SPL type, so the structural catchability the reference
 * kept is kept.
 */
final class OtlpExporterException extends ZefException
{
}
