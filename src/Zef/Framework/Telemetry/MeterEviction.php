<?php

declare(strict_types=1);

namespace Zef\Framework\Telemetry;

/**
 * The bounded table's room-making walk: the fold that owns the
 * eviction a fresh identity at a full {@see MeterTable} pays -- the
 * walk that retires the newest non-bucket series the table holds
 * (the casualty the {@see MeterOverflow} grammar picks), folding
 * every casualty onto its own metric's overflow bucket through the
 * {@see MeterFold} unit, until the incoming bucket's room is made.
 *
 * THE WALK IS BOUNDED: a casualty whose own bucket exists folds
 * onto it (one net slot made, the walk done); a casualty whose
 * bucket is absent seeds it in the slot the casualty freed (the net
 * zero the next casualty pays for); a fold bucket is never the next
 * casualty's pick, so each iteration retires one non-bucket series
 * and the walk ends. The pathological table of ONLY overflow
 * buckets -- a world of a thousand collapsed names -- answers the
 * honest residual: the newest bucket is dropped, its count lost,
 * the world the operator already chose when every series collapsed
 * (issue #788, finding 1: the first spelling dropped the newest
 * series outright -- metric A's bucket was the casualty of metric
 * B's fresh identity, and A's count vanished from the snapshot
 * while the table's own docblock promised the count still adds up).
 *
 * The ceiling arrives as a parameter, never a class reference: the
 * walk reads the table's own bound without the cycle a back
 * reference would close.
 */
final class MeterEviction
{
    /**
     * The table data the incoming name's overflow bucket can live
     * in: the room-making walk over the table's own series.
     *
     * @param array<string, array{
     *     count: float|int,
     *     sum: float,
     *     attributes: array<string, mixed>,
     * }> $data the table's own series
     * @param string $name    the incoming identity's metric name
     * @param int    $ceiling the series ceiling the table holds itself to
     *
     * @return array<string, array{
     *     count: float|int,
     *     sum: float,
     *     attributes: array<string, mixed>,
     * }> the data with the bucket's room made
     */
    public static function dataWithRoomFor(array $data, string $name, int $ceiling): array
    {
        $overflowKey = MeterOverflow::bucketKey($name);

        while (!isset($data[$overflowKey]) && \count($data) >= $ceiling) {
            $casualtyKey = MeterOverflow::newestNonBucketKey($data);

            if ($casualtyKey === null) {
                return \array_slice($data, 0, -1, true);
            }

            $data = MeterFold::folded($data, $casualtyKey);
        }

        return $data;
    }
}
