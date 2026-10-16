<?php

declare(strict_types=1);

namespace Zef\Framework\Telemetry;

use InvalidArgumentException;
use Override;

/**
 * The OTLP/HTTP-JSON span exporter: the delivery lane the
 * campaign's hygiene-and-export family lands -- the out-of-process
 * sink an armed export family swaps the tracing core's in-memory
 * default for, one POST per drained batch over the wire protocol
 * every collector speaks.
 *
 * The exporter owns exactly the delivery contract, nothing else:
 * the payload's shapes and attribute encoding sit in their own
 * unit ({@see OtlpJsonPayloadEncoder}), the hygiene sits in the
 * family's own census ({@see TelemetrySanitizer}, already
 * filtering at the recording points before a record ever reaches
 * the queue), the retry discipline sits in the processor that owns
 * the drain ({@see BatchSpanProcessor}), and the wire exchange
 * itself -- the endpoint fold, the POST context, the scoped error
 * capture, the answer's triage -- sits in the transport unit the
 * archaeology gate's split landed ({@see OtlpHttpTransport}). What
 * this class owns is the contract between them: the `/v1/traces`
 * signal path, the JSON body with the resource block and the scope
 * the reference declared, and the idempotent shutdown.
 *
 * Ported from the reference implementation (legacy
 * `zef-framework-src` `Infrastructure/Observability/
 * OtlpHttpJsonExporter.php`): the payload shapes, the signal-path
 * suffix rule, the scoped transport-error capture, the triage
 * arms and the idempotent shutdown move verbatim (the capture and
 * the triage into the transport unit, the shapes into the
 * encoder). The port's sequencing deviation: the reference's
 * exporter also answered the metrics and logs contracts, and this
 * port carries the span lane alone -- those contracts belong to
 * the campaign's metrics family, and the exporter arrives when
 * they do, widened, not before its consumers.
 */
final readonly class OtlpHttpJsonExporter implements SpanExporterInterface
{
    /** The wire exchange unit the delivery contract rides. */
    private OtlpHttpTransport $otlpHttpTransport;

    /**
     * @param string               $endpoint  the collector's base
     *                                        URL -- the `/v1/traces`
     *                                        signal path is appended
     *                                        unless already present
     * @param array<string, mixed> $resource  the resource block every
     *                                        payload carries (the
     *                                        service identity and the
     *                                        SDK declaration)
     * @param int                  $timeoutMs the exchange's own wait,
     *                                        clamped to the engine's
     *                                        stream timeout
     *
     * @throws InvalidArgumentException when the timeout sits outside
     *                                  its allowed range
     */
    public function __construct(
        private string $endpoint,
        private array $resource,
        int $timeoutMs = 500,
    ) {
        $this->otlpHttpTransport = new OtlpHttpTransport($timeoutMs);
    }

    #[Override]
    public function export(array $spans): void
    {
        if ($spans === []) {
            return;
        }

        $this->otlpHttpTransport->post(
            $this->endpointFor('/v1/traces'),
            OtlpJsonPayloadEncoder::payload($this->resource, $spans),
        );
    }

    #[Override]
    public function shutdown(): void
    {
        // Idempotent by construction, not by guard: there is no state a
        // second close could touch -- the transport holds nothing open
        // (one context per exchange), and the processor's own shutdown
        // calls this exactly once after its drain. The reference's
        // idempotence FLAG guarded the same empty body against the
        // future close-body the metrics family's facade will land; the
        // port drops the dead guard (its removal is value-equivalent
        // today, the mutation campaign's own honesty test) and the flag
        // returns with that body, when a second close is a second
        // behaviour.
    }

    /**
     * Answers the endpoint one signal path addresses: the base
     * URL's trailing slash folded, the path appended unless the
     * endpoint already names it -- a collector's full signal URL
     * is honoured as given, a base URL gets the conventional path.
     *
     * The query string is the one arm the fold must respect: the
     * signal path belongs to the path, so a base URL that already
     * carries query parameters keeps them after the appended path
     * -- never folded into it, where the transport would address
     * the query as part of the path.
     */
    private function endpointFor(string $signalPath): string
    {
        [$base, $query] = $this->splitQuery(mb_rtrim($this->endpoint, '/'));

        $url = mb_rtrim($base, '/');

        return (str_ends_with($url, $signalPath) ? $url : $url . $signalPath) . $query;
    }

    /**
     * Splits the endpoint's own query string off its base: the
     * leading '?' and everything after it, or the empty query when
     * the endpoint carries none -- the two arms the signal path's
     * own fold rejoins over.
     *
     * @return array{string, string} the base URL and the query with its leading '?'
     */
    private function splitQuery(string $endpoint): array
    {
        $cut = mb_strpos($endpoint, '?');

        return $cut === false
            ? [$endpoint, '']
            : [mb_substr($endpoint, 0, $cut), mb_substr($endpoint, $cut)];
    }
}
