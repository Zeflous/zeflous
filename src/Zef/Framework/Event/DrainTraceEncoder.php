<?php

declare(strict_types=1);

namespace Zef\Framework\Event;

use JsonException;

/**
 * The drain trace's writing half: the JSON translation of one
 * {@see DrainTrace} -- six keys, the trace's own order, the counts
 * spelled in the record vocabulary the entry records already own.
 * The reading side is the operator's eye: a journal record is
 * written to be read, never re-decoded by the regime, exactly as
 * the dead-letter record's own docblock promises.
 *
 * The spelling is the Event lane's own on purpose (the encoder the
 * file journal, the Redis list and every future store append the
 * SAME bytes for the SAME trace), so the record is the trace
 * either side of the store choice, exactly as the entry record is
 * the entry either side of the store choice.
 */
final class DrainTraceEncoder
{
    /**
     * Encodes one drain trace as its JSON record.
     *
     * @throws JsonException when the trace cannot be encoded --
     *                       unreachable by construction, spelled because the
     *                       encoding flags are a promise, not a hope
     */
    public function encode(DrainTrace $drainTrace): string
    {
        return json_encode(
            [
                'drained_at' => $drainTrace->drainedAt(),
                'delivered' => $drainTrace->delivered(),
                'retried' => $drainTrace->retried(),
                'dead_lettered' => $drainTrace->deadLettered(),
                'quarantined' => $drainTrace->quarantined(),
                'pending' => $drainTrace->pending(),
            ],
            \JSON_THROW_ON_ERROR | \JSON_PRESERVE_ZERO_FRACTION,
        );
    }
}
