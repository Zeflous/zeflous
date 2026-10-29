<?php

declare(strict_types=1);

namespace Zef\Framework\Adapters\Event;

use InvalidArgumentException;
use JsonException;

/**
 * The attribution record's reading half: the JSON translation of
 * one attributed-death record into the listener identity the death
 * is attributed to, fail-closed in every direction -- invalid JSON
 * (the engine's own reason carried, the decode depth ceiling the
 * entry record's decoder family pins), a non-object, a missing
 * key, a wrongly-typed field, or an extra key all refused by the
 * file the record lives in. A record that decodes on a guess is a
 * count the threshold must never read.
 *
 * The writing half lives in {@see DeliveryAttributionLane}'s own
 * record spelling; this fold owns the read, the
 * {@see DeliveryEnvelopeDecoder} family's posture replayed for the
 * books' own vocabulary.
 */
final class DeliveryAttributionDecoder
{
    /** The decode depth ceiling the engine's own decoder family pins. */
    private const int DECODE_DEPTH_CEILING = 512;

    /**
     * Decodes one attribution record and answers the listener
     * identity the death is attributed to.
     *
     * @throws InvalidArgumentException when the record is not the
     *                                  attribution record the store could have written
     */
    public function decodedListenerIdentity(string $json, string $filename): string
    {
        try {
            /** @var mixed $decoded the decoded record */
            $decoded = json_decode($json, true, self::DECODE_DEPTH_CEILING, \JSON_THROW_ON_ERROR);
        } catch (JsonException $jsonException) {
            throw new InvalidArgumentException(
                "The attribution record '{$filename}' is not valid JSON: "
                . $jsonException->getMessage() . '.',
                0,
                $jsonException,
            );
        }

        if (!\is_array($decoded)) {
            throw new InvalidArgumentException(
                "The attribution record '{$filename}' is refused: it is not"
                . ' an attribution record the store could have written.',
            );
        }

        $entryId = $decoded['entry_id'] ?? null;
        $listenerIdentity = $decoded['listener_identity'] ?? null;
        $attributedAt = $decoded['attributed_at'] ?? null;

        if (
            !\is_string($entryId)
            || !\is_string($listenerIdentity)
            || !\is_int($attributedAt)
            || \count($decoded) !== 3
        ) {
            throw new InvalidArgumentException(
                "The attribution record '{$filename}' is refused: it is not"
                . ' an attribution record the store could have written.',
            );
        }

        return $listenerIdentity;
    }
}
