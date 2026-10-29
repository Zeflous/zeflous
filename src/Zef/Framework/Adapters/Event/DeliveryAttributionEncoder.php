<?php

declare(strict_types=1);

namespace Zef\Framework\Adapters\Event;

use JsonException;

/**
 * The attribution record's writing half: the JSON translation of
 * one attributed death -- the entry's identity, the listener's
 * identity the death belongs to, and the attribution moment. The
 * reading half lives in {@see DeliveryAttributionDecoder}; the
 * record is written to be read by the count the threshold asks,
 * never re-decoded by the regime beyond that one question.
 *
 * The spelling mirrors the family the entry records own: the
 * snake_case keys in the record's own order, the encoder's flags a
 * promise (the record is integers and grammar-bound ASCII by
 * construction -- the equivalence the mutation ledger documents).
 */
final class DeliveryAttributionEncoder
{
    /**
     * Encodes one attributed death as its JSON record.
     *
     * @throws JsonException when the record cannot be encoded --
     *                       unreachable by construction, spelled because the
     *                       encoding flags are a promise, not a hope
     */
    public function encode(string $entryId, string $listenerIdentity, int $attributedAt): string
    {
        return json_encode(
            [
                'entry_id' => $entryId,
                'listener_identity' => $listenerIdentity,
                'attributed_at' => $attributedAt,
            ],
            \JSON_THROW_ON_ERROR | \JSON_PRESERVE_ZERO_FRACTION,
        );
    }
}
