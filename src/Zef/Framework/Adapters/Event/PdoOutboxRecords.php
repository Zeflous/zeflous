<?php

declare(strict_types=1);

namespace Zef\Framework\Adapters\Event;

use InvalidArgumentException;
use Zef\Framework\Event\EventName;
use Zef\Framework\Event\OutboxMessage;
use Zef\Framework\Event\OutboxMessageId;

/**
 * The PDO-backed outbox store's records fold: the raw rows the
 * pending selection read, judged into the messages the relay
 * hands its handoff -- the judgment half of the relay's read, the
 * twin of the delivery store's records fold ({@see
 * PdoDeliveryRecords}). The cells are judged by the string their
 * column spells (the engine contract enforced rather than
 * assumed), and the payload decodes through the payload fold's
 * own fail-closed walk ({@see PdoOutboxPayload}), so the judgment
 * and the decode never share a seam -- two questions, two folds,
 * the delivery family's own division.
 */
final readonly class PdoOutboxRecords
{
    public function __construct(private PdoOutboxPayload $pdoOutboxPayload)
    {
    }

    /**
     * The messages the raw rows spell, in the read's own order.
     *
     * @param list<array<string, mixed>> $rows the raw rows the pending
     *                                         selection answered
     *
     * @return list<OutboxMessage> the judged messages
     *
     * @throws InvalidArgumentException when a row carries a cell
     *                                  that is not the string its column spells, an appended-at
     *                                  cell that is not the integer its column spells, or a
     *                                  payload that refuses to decode
     */
    public function answeredMessages(array $rows): array
    {
        $outboxMessages = [];

        foreach ($rows as $row) {
            $outboxMessages[] = new OutboxMessage(
                new OutboxMessageId($this->cell($row, 'message_id')),
                new EventName($this->cell($row, 'event_name')),
                $this->pdoOutboxPayload->decode(
                    $this->cell($row, 'message_id'),
                    $this->cell($row, 'payload'),
                ),
                $this->appendedAt($row, $this->cell($row, 'message_id')),
            );
        }

        return $outboxMessages;
    }

    /**
     * One cell of the read's answer, judged by the string its
     * column spells -- the engine contract enforced rather than
     * assumed.
     *
     * @param array<string, mixed> $row one raw row's answer
     *
     * @throws InvalidArgumentException when the cell is not the
     *                                  string its column spells
     */
    private function cell(array $row, string $column): string
    {
        $value = $row[$column] ?? null;

        if (!\is_string($value)) {
            throw new InvalidArgumentException(
                'The pending read refused its answer: the ' . $column
                . ' cell is not the string its column spells.',
            );
        }

        return $value;
    }

    /**
     * One row's appended-at cell, judged by the integer its column
     * spells.
     *
     * @param array<string, mixed> $row one raw row's answer
     *
     * @throws InvalidArgumentException when the cell is not the
     *                                  integer its column spells
     */
    private function appendedAt(array $row, string $messageId): int
    {
        $value = $row['appended_at'] ?? null;

        if (!\is_int($value)) {
            throw new InvalidArgumentException(
                "The outbox row '{$messageId}' carries an appended-at cell"
                . ' that is not the integer its column spells.',
            );
        }

        return $value;
    }
}
