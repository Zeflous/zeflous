<?php

declare(strict_types=1);

namespace Zef\Framework\Telemetry;

/**
 * The metrics engine's bounded table: the series store and its own
 * overflow judgement -- the unit the {@see CounterMeter} records
 * through, extracted whole so the accumulation and the bound stay
 * one named place each.
 *
 * THE BOUND IS THE POINT ({@see self::MAX_SERIES}). An unbounded
 * series table is a memory leak the observability subsystem would
 * inflict on the very application it instruments: every distinct
 * attribute set is a new series, and a high-cardinality dimension
 * (a request path, a user handle) turns one metric into millions of
 * series under load. The bound answers the overflow the honest way:
 * the series past the ceiling collapse into the overflow bucket
 * ({@see self::OVERFLOW_ATTRIBUTES}), the newest casualty evicted to
 * make room, so the count still adds up and the cardinality still
 * has a name the exposition can carry (`zef.cardinality.bucket =
 * overflow`) instead of an OOM the process answers with. An
 * established series still counts past the ceiling -- a table at its
 * bound never refuses the identities it already holds.
 */
final class MeterTable
{
    /**
     * The series ceiling: the table never holds more distinct series
     * than this, the overflow bucket included.
     */
    private const int MAX_SERIES = 1024;

    /**
     * The overflow bucket's own dimensions: the series the cardinality
     * past the ceiling collapses into, carrying the name the exposition
     * can show.
     */
    private const array OVERFLOW_ATTRIBUTES = ['zef.cardinality.bucket' => 'overflow'];

    /** @var array<string,array{count:float|int,sum:float,attributes:array<string,mixed>}> */
    private array $data = [];

    /**
     * Resolves one recording's series identity: the name's dimension
     * policy applied ({@see MeterDimensions}), the overflow posture
     * answered when the table is full.
     *
     * @param string              $name       the metric name
     * @param array<string,mixed> $attributes the recorded attribute map
     */
    public function slot(string $name, array $attributes): MeterSlot
    {
        $normalized = MeterDimensions::normalized($name, $attributes);
        $meterSlot = MeterSlot::named($name, $normalized);

        if ($this->hasRoomFor($meterSlot->key)) {
            return $meterSlot;
        }

        return $this->overflow($name);
    }

    /**
     * The series the slot's identity currently holds, or the fresh
     * series the identity starts.
     *
     * @return array{count:float|int,sum:float,attributes:array<string,mixed>}
     */
    public function series(MeterSlot $meterSlot): array
    {
        return $this->data[$meterSlot->key] ?? $meterSlot->freshSeries();
    }

    /**
     * Records the series under the slot's identity.
     *
     * @param array{count:float|int,sum:float,attributes:array<string,mixed>} $series
     */
    public function write(MeterSlot $meterSlot, array $series): void
    {
        $this->data[$meterSlot->key] = $series;
    }

    /**
     * The accumulated series table, keyed by the composite series
     * identity.
     *
     * @return array<string,array{count:float|int,sum:float,attributes:array<string,mixed>}>
     */
    public function snapshot(): array
    {
        return $this->data;
    }

    /**
     * Answers whether the identity has room in the table: a table
     * under its ceiling always has room, and a full table still has
     * room for the series it already holds.
     */
    private function hasRoomFor(string $key): bool
    {
        if (\count($this->data) < self::MAX_SERIES) {
            return true;
        }

        return isset($this->data[$key]);
    }

    /**
     * The overflow posture for a fresh identity at a full table: the
     * identity collapses into the overflow bucket, the newest
     * casualty evicted to make the bucket room (the bucket itself is
     * never its own casualty).
     */
    private function overflow(string $name): MeterSlot
    {
        $overflowKey = MeterSlot::key($name, self::OVERFLOW_ATTRIBUTES);
        if (!isset($this->data[$overflowKey])) {
            // The eviction drops the newest series the table holds: the
            // slice keeps every key but the last, over the insertion order
            // the table's own writes laid down.
            $this->data = \array_slice($this->data, 0, -1, true);
        }

        return MeterSlot::named($name, self::OVERFLOW_ATTRIBUTES);
    }
}
