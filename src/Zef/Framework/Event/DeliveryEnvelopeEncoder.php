<?php

declare(strict_types=1);

namespace Zef\Framework\Event;

use JsonException;

/**
 * The durable spelling family's writing half: the JSON translation
 * of a {@see DeliveryEnvelope} and of the dead-letter record a
 * spent entry becomes. Encoding is guaranteed by the envelope's
 * own construction walk (every value survives the round trip or
 * the envelope was refused), so this fold is pure translation.
 *
 * Two boundaries the spelling pins on purpose: floats keep their
 * zero fraction (`1.0` encodes as `1.0`, never collapses to the
 * integer `1` -- a float-kind field stays a float-kind field
 * across the process boundary), and a ready-at of
 * {@see PHP_INT_MAX} -- the retry arithmetic's overflow cap --
 * encodes as the same integer. The reading half lives in
 * {@see DeliveryEnvelopeDecoder}; the dead-letter record is
 * written for the operator to read, never re-decoded by the
 * regime.
 */
final class DeliveryEnvelopeEncoder
{
    /**
     * Encodes one pending entry as its JSON record.
     *
     * @throws JsonException when the envelope cannot be encoded --
     *                       unreachable by construction, spelled because the
     *                       encoding flags are a promise, not a hope
     */
    public function encode(DeliveryEnvelope $deliveryEnvelope): string
    {
        return json_encode(
            $this->record($deliveryEnvelope),
            \JSON_THROW_ON_ERROR | \JSON_PRESERVE_ZERO_FRACTION,
        );
    }

    /**
     * Encodes the dead-letter record a spent entry becomes: the
     * entry's own record plus the failure's class, the failure's
     * message, and the dead-at moment the drain handed in.
     *
     * @throws JsonException when the record cannot be encoded --
     *                       unreachable by construction, spelled because the
     *                       encoding flags are a promise, not a hope
     */
    public function encodeDeadLetter(
        DeliveryEnvelope $deliveryEnvelope,
        string $failureClass,
        string $failureMessage,
        int $deadAt,
    ): string {
        return json_encode(
            [
                ...$this->record($deliveryEnvelope),
                'failure_class' => $failureClass,
                'failure_message' => $failureMessage,
                'dead_at' => $deadAt,
            ],
            \JSON_THROW_ON_ERROR | \JSON_PRESERVE_ZERO_FRACTION,
        );
    }

    /**
     * @return array<string, mixed> the record's six keys, the envelope's own order
     */
    private function record(DeliveryEnvelope $deliveryEnvelope): array
    {
        return [
            'entry_id' => $deliveryEnvelope->entryId()->spelling(),
            'event_name' => $deliveryEnvelope->eventName()->spelling(),
            'values' => $deliveryEnvelope->values(),
            'listener_identity' => $deliveryEnvelope->listenerIdentity()->spelling(),
            'attempt' => $deliveryEnvelope->attempt(),
            'ready_at' => $deliveryEnvelope->readyAt(),
        ];
    }
}
