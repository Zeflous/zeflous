<?php

declare(strict_types=1);

namespace Zef\Framework\Telemetry;

/**
 * The OTLP/JSON logs payload fold: the `/v1/logs` wire body the
 * exporter's delivery contract serialises -- the resource block,
 * the single scope the lane names, and the recorded logs as the
 * protocol's log records. One shape, one unit, beside the metrics
 * fold the same widening landed ({@see OtlpMetricsPayload}), the
 * same split the archaeology gate demanded for the span shapes
 * ({@see OtlpJsonPayloadEncoder}, whose KeyValue arm this fold
 * composes for the resource and the record dimensions).
 *
 * The record's body rides verbatim: the fold encodes the
 * {@see LogRecord} the cadence handed over exactly as it arrived,
 * with no clamp of its own -- the recording point owns the hygiene
 * (the record arrives sanitised or it never arrives at all), the
 * span lane's own doctrine replayed for the logs lane, so the fold
 * never second-guesses a recording. The timestamp spells the
 * protocol's own field: the nanoseconds the record froze, as the
 * string the 64-bit wire field demands -- never the JSON number
 * whose precision a collector would lose.
 *
 * Ported from the reference implementation (legacy
 * `zef-framework-src` `Infrastructure/Observability/
 * OtlpHttpJsonExporter.php`, the exportLogs body): the payload
 * envelope, the record's four fields and the body's stringValue
 * spelling move verbatim -- the fold's own deviation is the
 * shape's home, the same one the metrics fold carries.
 */
final class OtlpLogsPayload
{
    /**
     * Encodes one delivery's whole payload: the resource block the
     * exporter's identity declared, the single scope the lane names,
     * and the recorded logs as the protocol's log records.
     *
     * @param array<string, mixed> $resource the resource block's attribute map
     * @param list<LogRecord>      $records  the batch the cadence assembled
     *
     * @return array<string, mixed>
     */
    public static function payload(array $resource, array $records): array
    {
        return [
            'resourceLogs' => [
                [
                    'resource' => ['attributes' => OtlpJsonPayloadEncoder::attributes($resource)],
                    'scopeLogs' => [
                        [
                            'scope' => ['name' => 'zef-observability'],
                            'logRecords' => array_map(self::record(...), $records),
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Encodes one recorded log: the timestamp as the protocol's
     * string nanoseconds, the severity's spelling, the body as the
     * string value, and the encoded attribute list.
     *
     * @return array<string, mixed>
     */
    private static function record(LogRecord $logRecord): array
    {
        return [
            'timeUnixNano' => (string) $logRecord->timeUnixNano,
            'severityText' => $logRecord->severity,
            'body' => ['stringValue' => $logRecord->body],
            'attributes' => OtlpJsonPayloadEncoder::attributes($logRecord->attributes),
        ];
    }
}
