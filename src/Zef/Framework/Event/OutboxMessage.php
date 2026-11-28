<?php

declare(strict_types=1);

namespace Zef\Framework\Event;

use InvalidArgumentException;

/**
 * The serialized spelling of one appended outbox row: the
 * transactional twin of {@see DeliveryEnvelope}. Where the
 * delivery envelope holds one LISTENER's pending delivery, this
 * record holds the EVENT the host appended inside its own
 * transaction -- the row lands when the host's whole transaction
 * commits and never lands when it rolls back, which is the
 * exactly-once answer the at-least-once durable arm deliberately
 * defers to the transactional/outbox lane for (KDD-DU1).
 *
 * Construction is the append boundary's own refusal point: the
 * appended-at moment must not be negative, and every payload value
 * must survive the JSON round trip ({@see SerializableValues}
 * names the first that cannot) -- the row is data the relay reads
 * back across the process boundary, so a value the spelling
 * cannot carry is refused HERE, at the defer call, never
 * discovered as corruption at the relay. The event's own shape is
 * NOT re-derived here: the append judged it against the live
 * definition (KDD-EA10), and the relay judges it AGAIN against the
 * definition live when the relay runs -- the DU11 doctrine, the
 * live definitions stay the one authority on both sides of the
 * boundary alike.
 *
 * Immutable by construction; the row is written once and removed
 * once, never rewritten.
 */
final readonly class OutboxMessage
{
    /**
     * @param array<string, null|array<mixed>|bool|float|int|string> $values     the
     *                                                                           validated payload map, walked for JSON
     *                                                                           round-trip survival
     * @param int                                                    $appendedAt
     *                                                                           the append's own moment, the host's
     *                                                                           clock spelled at the defer call (the
     *                                                                           regime owns no clock, KDD-EA4 carried):
     *                                                                           the read order's first key
     *
     * @throws InvalidArgumentException when the appended-at moment is
     *                                  negative, or a payload value cannot survive the
     *                                  serialized spelling
     */
    public function __construct(
        private OutboxMessageId $outboxMessageId,
        private EventName $eventName,
        private array $values,
        private int $appendedAt,
    ) {
        if ($appendedAt < 0) {
            throw new InvalidArgumentException(
                "An outbox row's appended-at moment cannot be negative, got "
                . $appendedAt . '.',
            );
        }

        $offense = SerializableValues::offense($values);

        if ($offense !== null) {
            throw new InvalidArgumentException(
                'An outbox row carries values that cannot survive the'
                . ' serialized spelling: ' . $offense . '.',
            );
        }
    }

    /**
     * The row's own identity -- the mark the relay's removal
     * answers and nothing else ever carries.
     */
    public function messageId(): OutboxMessageId
    {
        return $this->outboxMessageId;
    }

    /**
     * The event's name -- the address the relay's defer re-spells.
     */
    public function name(): EventName
    {
        return $this->eventName;
    }

    /**
     * The event's validated values -- the payload the relay's
     * defer re-validates against the definition live at the relay.
     *
     * @return array<string, null|array<mixed>|bool|float|int|string>
     */
    public function values(): array
    {
        return $this->values;
    }

    /**
     * The append's own moment -- the read order's first key, the
     * row identity the tiebreak the same index order the delivery
     * store's due scan already spells (DU9 carried).
     */
    public function appendedAt(): int
    {
        return $this->appendedAt;
    }
}
