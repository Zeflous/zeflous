<?php

declare(strict_types=1);

namespace Zef\Framework\Telemetry;

/**
 * The exposition's series identity: the composite snapshot key split
 * back into the metric name and the attribute map the series was
 * keyed by -- the unit the {@see PrometheusRenderer} reads, over the
 * name grammar the identity's own half carries ({@see
 * PrometheusMetricName}).
 *
 * The snapshot keys are the meter's own composite spellings (`name
 * | json`), split on the separator; a series whose key carries no
 * separator reads as a bare metric name with the attributes on the
 * series itself.
 */
final class PrometheusSeries
{
    /**
     * The composite snapshot key split back into its identity.
     *
     * @param mixed                                                           $rawKey the snapshot key
     * @param array{count:float|int,sum:float,attributes:array<string,mixed>} $series
     *
     * @return array{metric:string,attributes:array<mixed,mixed>}
     */
    public static function identity(mixed $rawKey, array $series): array
    {
        $rawKey = \is_string($rawKey) ? $rawKey : '';
        $pipe = mb_strpos($rawKey, '|');
        if ($pipe !== false) {
            return [
                'metric' => PrometheusMetricName::sanitized(mb_substr($rawKey, 0, $pipe)),
                'attributes' => self::asArray(json_decode(mb_substr($rawKey, $pipe + 1), true)),
            ];
        }

        return [
            'metric' => PrometheusMetricName::sanitized($rawKey),
            'attributes' => $series['attributes'],
        ];
    }

    /**
     * Narrows the composite key's decoded identity half to the attribute
     * map it must be: anything the JSON half decodes to but an array is
     * not an attribute map at all, and the empty map is the honest
     * answer the series' identity then carries.
     *
     * @param mixed $decoded the decoded identity half, as json_decode answered it
     *
     * @return array<mixed,mixed>
     */
    private static function asArray(mixed $decoded): array
    {
        return \is_array($decoded) ? $decoded : [];
    }
}
