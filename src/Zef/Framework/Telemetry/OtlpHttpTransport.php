<?php

declare(strict_types=1);

namespace Zef\Framework\Telemetry;

use InvalidArgumentException;

/**
 * The OTLP/HTTP transport: the wire exchange the exporter's
 * delivery lane rides -- one POST per payload over the engine's
 * own stream context (no client library, the zero-dependency
 * posture the whole repository carries), extracted from the
 * exporter when the archaeology gate demanded the split: the
 * exporter owns the delivery contract (the signal path, the
 * payload shape, the idempotent shutdown) and this unit owns the
 * exchange itself -- the endpoint fold, the POST context, the
 * scoped error capture, and the answer's triage.
 *
 * The triage reads the answer's own status line (the engine's
 * `$http_response_header`, populated only when the HTTP layer
 * answered -- a refused connection leaves it empty and the status
 * answers zero): a 2xx is a delivery, a 4xx is the permanent
 * rejection the plain {@see InvalidArgumentException} carries (no
 * retry addresses it), anything else is the transient failure the
 * lane's own {@see OtlpExporterException} carries. The one 4xx the
 * OTLP/HTTP retry spec itself puts on the transient side is the
 * collector's throttle answer -- a 429 with a `Retry-After` hint is
 * a "come back later" the lane's own backoff addresses, not a
 * payload it should drop on the floor.
 *
 * The transport's failure diagnostics are captured through a
 * scoped error handler so the refused connection's own reason
 * rides the exception, redacted through the hygiene unit first
 * ({@see TelemetrySanitizer::redact()}): an engine error message
 * is a free-form diagnostic string, and the transport's text is
 * exactly where a credential-bearing URL would otherwise leak.
 */
final readonly class OtlpHttpTransport
{
    /**
     * @param int $timeoutMs the exchange's own wait, clamped to the
     *                       engine's stream timeout
     *
     * @throws InvalidArgumentException when the timeout sits outside
     *                                  its allowed range
     */
    public function __construct(
        private int $timeoutMs,
    ) {
        if ($timeoutMs < 1 || $timeoutMs > 10000) {
            throw new InvalidArgumentException('timeoutMs is outside its allowed range.');
        }
    }

    /**
     * Delivers one payload to the address given: the JSON body, the
     * POST context with the exchange's timeout, the scoped error
     * capture that keeps the transport's own diagnostics, and the
     * answer's triage -- a delivery, a permanent rejection, or the
     * transient failure the lane's own exception carries with the
     * redacted reason.
     *
     * @param array<string, mixed> $payload
     */
    public function post(string $url, array $payload): void
    {
        $json = json_encode($payload, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES);
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\n"
                    . "Accept: application/json\r\n"
                    . 'Content-Length: ' . mb_strlen($json) . "\r\n",
                'content' => $json,
                'timeout' => max(0.01, $this->timeoutMs / 1000),
                'ignore_errors' => true,
            ],
        ]);

        $transportErrors = [];
        set_error_handler(
            static function (int $severity, string $message) use (&$transportErrors): bool {
                $transportErrors[] = [$severity, $message];

                return true;
            },
        );

        // The engine's own response-header variable is pre-declared:
        // the transport call below populates it only when the HTTP
        // wrapper engages, and a non-HTTP scheme would leave the
        // read below touching an undefined name.
        /** @var list<string> $http_response_header the engine's own response-header lines */
        $http_response_header = [];

        try {
            $result = file_get_contents($url, false, $context);
        } finally {
            restore_error_handler();
        }

        // The engine's own response-header variable is populated in
        // this function's scope, by the transport call above, and only
        // when the HTTP layer answered -- a refused connection leaves
        // it empty, so the status answers zero and the transport arm
        // below owns the failure.
        $status = 0;

        foreach ($http_response_header as $header) {
            if (preg_match('/^HTTP\/\S+\s+(\d{3})/D', $header, $match) === 1) {
                $status = (int) $match[1];

                break;
            }
        }

        if ($result === false) {
            $reason = $transportErrors === []
                ? null
                : $transportErrors[\count($transportErrors) - 1][1];
            $detail = $reason === null ? '.' : ': ' . TelemetrySanitizer::redact($reason) . '.';

            throw new OtlpExporterException('OTLP exporter transport failure' . $detail);
        }

        match (true) {
            $status >= 500 || $status === 429 => throw new OtlpExporterException('OTLP exporter transient rejection.'),
            $status >= 400 => throw new InvalidArgumentException('OTLP exporter permanent rejection.'),
            default => null,
        };
    }
}
