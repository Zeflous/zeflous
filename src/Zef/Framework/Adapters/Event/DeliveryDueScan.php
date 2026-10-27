<?php

declare(strict_types=1);

namespace Zef\Framework\Adapters\Event;

use InvalidArgumentException;
use Zef\Framework\Event\DeliveryEnvelope;
use Zef\Framework\Event\DeliveryEnvelopeDecoder;

/**
 * The file-backed delivery store's due-selection fold: the scan
 * that answers which pending entries are due at the given now --
 * every entry file read, decoded fail-closed with the filename
 * named on any refusal (a record that decodes on a guess is a
 * delivery that was never admitted), the due ones selected, and
 * the answer ordered by ready-at and then entry identity. The
 * fold is the whole book: one pass reads every pending record
 * (the capacity bound keeps that pass operator-sized), and an
 * index is the Redis and PDO adapters' own business, exactly as
 * their claim semantics are.
 */
final readonly class DeliveryDueScan
{
    public function __construct(
        private DeliveryFiles $deliveryFiles,
        private DeliveryEnvelopeDecoder $deliveryEnvelopeDecoder,
    ) {
    }

    /**
     * The due entries at the given now, ordered by ready-at and
     * then entry identity -- a READ, never a removal: the entries
     * stay until they are settled.
     *
     * @return list<DeliveryEnvelope>
     *
     * @throws InvalidArgumentException when an entry file cannot
     *                                  be read or its record refuses to decode
     */
    public function takeDue(int $now): array
    {
        $due = [];

        foreach ($this->deliveryFiles->entryFilenames() as $filename) {
            $envelope = $this->decodeEntry($filename);

            if ($envelope->readyAt() > $now) {
                continue;
            }

            $due[] = $envelope;
        }

        usort(
            $due,
            static fn (DeliveryEnvelope $left, DeliveryEnvelope $right): int => [
                $left->readyAt(),
                $left->entryId()->spelling(),
            ] <=> [$right->readyAt(), $right->entryId()->spelling()],
        );

        return $due;
    }

    /**
     * Decodes one entry file the scan answered, refusing the
     * corrupt or hand-edited record by filename.
     */
    private function decodeEntry(string $filename): DeliveryEnvelope
    {
        $json = $this->deliveryFiles->readEntry($filename);

        try {
            return $this->deliveryEnvelopeDecoder->decode($json);
        } catch (InvalidArgumentException $invalidArgumentException) {
            throw new InvalidArgumentException(
                "The delivery entry file '{$filename}' is refused: "
                . $invalidArgumentException->getMessage(),
                0,
                $invalidArgumentException,
            );
        }
    }
}
