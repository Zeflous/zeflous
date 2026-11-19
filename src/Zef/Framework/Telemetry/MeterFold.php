<?php

declare(strict_types=1);

namespace Zef\Framework\Telemetry;

/**
 * The casualty's own fold: the unit that owns one eviction's
 * retirement -- the series dropped from the table and its count
 * and sum carried onto its own metric's overflow bucket, the
 * merge onto the bucket that exists or the seed in the slot the
 * casualty freed for the bucket that does not.
 *
 * THE COUNT SURVIVES THE EVICTION (the issue #788, finding 1
 * contract): the casualty's readings advance onto its own metric's
 * bucket, so the snapshot's totals still add up through the
 * collapse -- the integer stays integer and the float joins as the
 * float, the same coercion the {@see MeterCount} arithmetic spells
 * for the accumulation path.
 */
final class MeterFold
{
    /**
     * Folds one retired series onto its own metric's overflow
     * bucket: the merge onto the bucket that exists, the seed in
     * the slot the casualty freed for the bucket that does not.
     *
     * @param array<string, array{
     *     count: float|int,
     *     sum: float,
     *     attributes: array<string, mixed>,
     * }> $data the table's own series, casualty included
     * @param string $casualtyKey the retired series' own key
     *
     * @return array<string, array{
     *     count: float|int,
     *     sum: float,
     *     attributes: array<string, mixed>,
     * }> the data with the casualty folded
     */
    public static function folded(array $data, string $casualtyKey): array
    {
        $casualty = $data[$casualtyKey];
        unset($data[$casualtyKey]);

        $foldKey = MeterOverflow::bucketKey(MeterOverflow::nameOf($casualtyKey));

        if (isset($data[$foldKey])) {
            $data[$foldKey]['count'] += $casualty['count'];
            $data[$foldKey]['sum'] += $casualty['sum'];

            return $data;
        }

        $data[$foldKey] = [
            'count' => $casualty['count'],
            'sum' => $casualty['sum'],
            'attributes' => MeterOverflow::attributes(),
        ];

        return $data;
    }
}
