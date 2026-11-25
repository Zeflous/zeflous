<?php

declare(strict_types=1);

namespace Zef\Framework\Adapters\Event;

use InvalidArgumentException;
use Zef\Framework\Event\DeliveryEnvelope;
use Zef\Framework\Event\DeliveryEnvelopeDecoder;

/**
 * The PDO-backed delivery store's records fold: the claimed
 * identities as deliveries, in the claim's own order -- the
 * judgment half of the due answer, the twin of the Redis answer's
 * multi-read. The rows arrive from the entry-row fold's read
 * ({@see PdoDeliveryEntries::recordsFor()}); the cells they carry
 * are judged by the string their column spells (the engine
 * contract enforced rather than assumed), and the records decode
 * through the Event lane's own decoder, a corrupt or hand-edited
 * record refused by the entry's own identity (the fail-closed
 * doctrine every answer over this port carries). A claimed
 * identity whose row the read no longer finds is SKIPPED, never
 * refused -- the row a stale drainer's late settle removed behind
 * this drainer's fresh claim is the at-least-once window the
 * port's own arithmetic owns (the entry's business is finished;
 * the duplicate the next pass would have delivered is the one the
 * doctrine already budgeted), the SQL twin of the zombie index
 * member the Redis answer completes quietly.
 */
final readonly class PdoDeliveryRecords
{
    public function __construct(
        private PdoDeliveryEntries $pdoDeliveryEntries,
        private DeliveryEnvelopeDecoder $deliveryEnvelopeDecoder,
    ) {
    }

    /**
     * The deliveries the claimed identities spell, in the claim's
     * own order.
     *
     * @param list<string> $claimedEntryIds the identities the claim fold won
     *
     * @return list<DeliveryEnvelope>
     *
     * @throws InvalidArgumentException when the read's answer carries
     *                                  a cell that is not the string its column spells,
     *                                  or a record refuses to decode
     */
    public function answeredDeliveries(array $claimedEntryIds): array
    {
        $records = [];

        foreach ($this->pdoDeliveryEntries->recordsFor($claimedEntryIds) as $row) {
            $records[$this->cell($row, 'entry_id')] = $this->cell($row, 'record');
        }

        $deliveries = [];

        foreach ($claimedEntryIds as $claimedEntryId) {
            if (!isset($records[$claimedEntryId])) {
                continue;
            }

            $deliveries[] = $this->decodeRecord($claimedEntryId, $records[$claimedEntryId]);
        }

        return $deliveries;
    }

    /**
     * One cell of the read's answer, judged by the string its column
     * spells -- the engine contract enforced rather than assumed.
     *
     * @param array<string, mixed> $row
     *
     * @throws InvalidArgumentException when the cell is not the string
     *                                  its column spells
     */
    private function cell(array $row, string $column): string
    {
        $value = $row[$column] ?? null;

        if (!\is_string($value)) {
            throw new InvalidArgumentException(
                'The claimed records read refused its answer: the ' . $column
                . ' cell is not the string its column spells.',
            );
        }

        return $value;
    }

    /**
     * Decodes one claimed entry's record, refusing the corrupt or
     * hand-edited record by the entry's own identity.
     */
    private function decodeRecord(string $entryId, string $record): DeliveryEnvelope
    {
        try {
            return $this->deliveryEnvelopeDecoder->decode($record);
        } catch (InvalidArgumentException $invalidArgumentException) {
            throw new InvalidArgumentException(
                "The delivery entry record '{$entryId}' is refused: "
                . $invalidArgumentException->getMessage(),
                0,
                $invalidArgumentException,
            );
        }
    }
}
