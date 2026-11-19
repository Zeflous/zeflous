<?php

declare(strict_types=1);

namespace Zef\Framework\Telemetry;

/**
 * The overflow bucket's own grammar: the identity the cardinality
 * past the ceiling collapses into -- the bucket's dimensions, the
 * composite key one metric name's bucket carries, and the name half
 * of a composite series key -- the folds the bounded table's own
 * overflow judgement ({@see MeterEviction}) reads, extracted so the
 * grammar and the walk stay one named place each.
 *
 * The bucket's identity is the cardinality's own name: the
 * `zef.cardinality.bucket = overflow` dimension the exposition can
 * show instead of an OOM the process answers with. A bucket's own
 * key is never the casualty pick of the room-making walk ({@see
 * self::newestNonBucketKey()} -- a bucket never funds another
 * bucket's room, and the fold's own casualty rides onto its metric's
 * bucket, never out of it).
 */
final class MeterOverflow
{
    /**
     * The overflow bucket's own dimensions: the series the
     * cardinality past the ceiling collapses into, carrying the
     * name the exposition can show.
     */
    private const array OVERFLOW_ATTRIBUTES = ['zef.cardinality.bucket' => 'overflow'];

    /**
     * The overflow bucket's own dimensions, the map the collapsed
     * series' slot carries.
     *
     * @return array<string, string>
     */
    public static function attributes(): array
    {
        return self::OVERFLOW_ATTRIBUTES;
    }

    /**
     * The bucket key one metric name's overflow collapses into.
     */
    public static function bucketKey(string $name): string
    {
        return MeterSlot::key($name, self::OVERFLOW_ATTRIBUTES);
    }

    /**
     * The metric name a composite series key carries: the prefix
     * before the separator, the same half the exposition's own
     * identity fold splits back on ({@see PrometheusSeries}) -- the
     * key grammar's names carry no separator of their own.
     */
    public static function nameOf(string $key): string
    {
        $separator = mb_strpos($key, '|');

        return $separator === false ? $key : mb_substr($key, 0, $separator);
    }

    /**
     * The newest series the table holds whose own key is not an
     * overflow bucket's: the insertion order the table's own writes
     * laid down, read from the end, skipping the buckets (a bucket
     * never funds another bucket's room).
     *
     * @param array<string, array{
     *     count: float|int,
     *     sum: float,
     *     attributes: array<string, mixed>,
     * }> $data the table's own series
     *
     * @return null|string the casualty candidate's own key, or null when every series is a bucket
     */
    public static function newestNonBucketKey(array $data): ?string
    {
        foreach (array_keys(array_reverse($data, true)) as $key) {
            if (self::bucketKey(self::nameOf($key)) !== $key) {
                return $key;
            }
        }

        return null;
    }
}
