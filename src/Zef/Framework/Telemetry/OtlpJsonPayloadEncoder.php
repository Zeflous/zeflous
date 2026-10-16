<?php

declare(strict_types=1);

namespace Zef\Framework\Telemetry;

/**
 * The OTLP/JSON attribute encoder: the KeyValue list the wire
 * protocol's payloads carry every attribute map as -- spans,
 * events and the resource block alike -- one shape, one encoder,
 * so the exporter stays inside its own class-size budget and the
 * encoding logic sits in a unit of its own. The span and event
 * blocks themselves live here too, exactly where the
 * archaeology gate demanded the split: the exporter owns the
 * wire (the transport, the triage, the endpoint fold) and this
 * unit owns the payload shapes the transport serialises.
 *
 * The OTLP/JSON mapping is typed by the protocol's own value
 * model: a boolean answers a `boolValue`, an integer a `intValue`
 * (as the string the protocol's 64-bit field spells, never the
 * JSON number whose precision a collector would lose), a float a
 * `doubleValue` (the NaN and infinities the JSON grammar refuses
 * never reach the encoder -- the recording surface's own value
 * sanitisation answers their string spellings before the payload
 * exists, {@see TelemetrySanitizer::value()}), an array an
 * `arrayValue` of its encoded elements, and everything else a
 * `stringValue` -- a scalar the encoder sanitises through the
 * hygiene unit's clamp ({@see TelemetrySanitizer::string()}) or,
 * for a value that is not a string at all, the debug type: the
 * wire never carries a value the protocol has no branch for, it
 * carries the name of the type that arrived.
 *
 * The encoder is stateless, so every entry point is static --
 * the same shape the reference's own extracted encoder carried
 * ({@see OtlpHttpJsonExporter}, whose three payload builders all
 * read through this unit).
 *
 * Ported from the reference implementation (legacy
 * `zef-framework-src` `Infrastructure/Observability/
 * OtlpJsonPayloadEncoder.php`): the match arms, the integer's
 * string spelling and the array recursion move verbatim, over
 * the port's already-landed hygiene unit.
 */
final class OtlpJsonPayloadEncoder
{
    /**
     * Encodes one delivery's whole payload: the resource block the
     * exporter's identity declared, the single scope the lane
     * names, and the encoded spans -- the envelope shape every
     * signal's wire body carries.
     *
     * @param array<string, mixed> $resource the resource block's attribute map
     * @param list<SpanData>       $spans    the completed spans one delivery carries
     *
     * @return array<string, mixed>
     */
    public static function payload(array $resource, array $spans): array
    {
        return [
            'resourceSpans' => [
                [
                    'resource' => ['attributes' => self::attributes($resource)],
                    'scopeSpans' => [
                        [
                            'scope' => ['name' => 'zef-observability'],
                            'spans' => array_map(self::span(...), $spans),
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Encodes an attribute map as the protocol's KeyValue list, in
     * the map's own order.
     *
     * @param array<string, mixed> $attributes
     *
     * @return list<array{key: string, value: array<string, mixed>}>
     */
    public static function attributes(array $attributes): array
    {
        $encoded = [];

        foreach ($attributes as $key => $value) {
            $encoded[] = ['key' => $key, 'value' => self::anyValue($value)];
        }

        return $encoded;
    }

    /**
     * Encodes one completed span as the protocol's span block: the
     * identity and parent, the name and kind, the two timestamps as
     * the protocol's string nanoseconds, the encoded attributes, the
     * status with its folded code and clamped message, and the
     * events with their own clamped names and encoded attributes.
     *
     * @return array<string, mixed>
     */
    public static function span(SpanData $spanData): array
    {
        $encoded = [
            'traceId' => $spanData->context->traceId,
            'spanId' => $spanData->context->spanId,
            'name' => $spanData->name,
            'kind' => 'SPAN_KIND_SERVER',
            'startTimeUnixNano' => (string) $spanData->startUnixNano,
            'endTimeUnixNano' => (string) $spanData->endUnixNano,
            'attributes' => self::attributes($spanData->attributes),
            'status' => [
                'code' => match ($spanData->status) {
                    'OK' => 'STATUS_CODE_OK',
                    'ERROR' => 'STATUS_CODE_ERROR',
                    default => 'STATUS_CODE_UNSET',
                },
            ],
        ];

        $parent = $spanData->parent;

        if ($parent instanceof SpanContext && $parent->isValid()) {
            $encoded['parentSpanId'] = $parent->spanId;
        }

        if ($spanData->statusDescription !== null) {
            $encoded['status']['message'] = TelemetrySanitizer::string($spanData->statusDescription, 1024);
        }

        if ($spanData->events !== []) {
            $encoded['events'] = array_map(self::event(...), $spanData->events);
        }

        return $encoded;
    }

    /**
     * Encodes one value as the protocol's typed any-value block.
     *
     * @return array<string, mixed>
     */
    private static function anyValue(mixed $value): array
    {
        return match (true) {
            \is_bool($value) => ['boolValue' => $value],
            \is_int($value) => ['intValue' => (string) $value],
            \is_float($value) => ['doubleValue' => $value],
            \is_array($value) => self::arrayValue($value),
            default => self::scalarValue($value),
        };
    }

    /**
     * Encodes an array value as the protocol's array-of-values
     * block: the elements in their own order, each encoded, the
     * keys the protocol's array value does not carry.
     *
     * @param array<mixed> $value
     *
     * @return array<string, mixed>
     */
    private static function arrayValue(array $value): array
    {
        return [
            'arrayValue' => ['values' => array_map(self::anyValue(...), array_values($value))],
        ];
    }

    /**
     * Encodes a scalar as the protocol's string value: the
     * clamped string for a string, the debug type for anything
     * else.
     *
     * @return array<string, mixed>
     */
    private static function scalarValue(mixed $value): array
    {
        return [
            'stringValue' => TelemetrySanitizer::string(
                \is_string($value) ? $value : get_debug_type($value),
            ),
        ];
    }

    /**
     * Encodes one recorded event: the clamped name, the timestamp
     * in the protocol's camelCase nanoseconds field, and the
     * encoded attribute list -- the internal event shape keeps its
     * snake_case key, the wire does not.
     *
     * @param array{name: string, time_unix_nano: int, attributes: array<string, mixed>} $event
     *
     * @return array<string, mixed>
     */
    private static function event(array $event): array
    {
        return [
            'name' => TelemetrySanitizer::string($event['name'], 256),
            'timeUnixNano' => (string) $event['time_unix_nano'],
            'attributes' => self::attributes($event['attributes']),
        ];
    }
}
