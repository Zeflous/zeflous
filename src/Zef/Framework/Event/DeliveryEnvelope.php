<?php

declare(strict_types=1);

namespace Zef\Framework\Event;

use InvalidArgumentException;

/**
 * The serialized spelling of one pending delivery: the durable
 * twin of {@see DeferredDelivery}. Where the in-process entry
 * holds the frozen event and the listener OBJECT, this record
 * holds only what survives a process boundary -- the event's
 * name, its validated values, the listener's declared identity,
 * the attempt number the entry stands at, and the earliest moment
 * a drain may deliver it. Immutable by construction; a retry is a
 * NEW entry ({@see withNextAttempt()}) carrying a fresh
 * {@see DeliveryEntryId}, so an entry's history is never rewritten
 * in place, exactly as the in-process arm's own doctrine spells.
 *
 * Construction is the durable boundary's own refusal point: the
 * attempt must stand at one or later, the ready-at must not be
 * negative, and every payload value must survive the JSON round
 * trip ({@see SerializableValues} names the first that cannot).
 * The event's own shape is NOT re-derived here -- the record is
 * data, and the shape's judgment is the drain's own question,
 * answered against the definition that is live when the drain
 * runs.
 */
final readonly class DeliveryEnvelope
{
    /**
     * @param array<string, null|array<mixed>|bool|float|int|string> $values
     *                                                                       the validated payload map, walked for JSON
     *                                                                       round-trip survival
     *
     * @throws InvalidArgumentException when the attempt number is
     *                                  below one, the ready-at is negative, or a payload value
     *                                  cannot survive the serialized spelling
     */
    public function __construct(
        private DeliveryEntryId $deliveryEntryId,
        private EventName $eventName,
        private array $values,
        private ListenerIdentity $listenerIdentity,
        private int $attempt,
        private int $readyAt,
    ) {
        if ($attempt < 1) {
            throw new InvalidArgumentException(
                'A durable delivery stands at an attempt number of at least'
                . ' one, got ' . $attempt . '.',
            );
        }

        if ($readyAt < 0) {
            throw new InvalidArgumentException(
                "A durable delivery's ready-at moment cannot be negative,"
                . ' got ' . $readyAt . '.',
            );
        }

        $offense = SerializableValues::offense($values);

        if ($offense !== null) {
            throw new InvalidArgumentException(
                'The durable delivery envelope carries a payload value that'
                . ' cannot survive its serialized spelling: ' . $offense . '.',
            );
        }
    }

    /**
     * The entry's own identity -- the spelling a store names its
     * entry file by and a settle call means.
     */
    public function entryId(): DeliveryEntryId
    {
        return $this->deliveryEntryId;
    }

    /**
     * The event's name -- the spelling a drain re-validates the
     * payload against the live definition by.
     */
    public function eventName(): EventName
    {
        return $this->eventName;
    }

    /**
     * The validated payload map, in the shape's own declaration
     * order -- the data half of the record.
     *
     * @return array<string, null|array<mixed>|bool|float|int|string>
     */
    public function values(): array
    {
        return $this->values;
    }

    /**
     * The listener's declared identity -- the spelling a resolver
     * answers with the object that must handle the event.
     */
    public function listenerIdentity(): ListenerIdentity
    {
        return $this->listenerIdentity;
    }

    /**
     * Which attempt this entry stands at, one-based: a fresh
     * admission stands at one, a retry at the next number.
     */
    public function attempt(): int
    {
        return $this->attempt;
    }

    /**
     * The earliest moment a drain may deliver this entry -- a
     * plain integer the drain is handed, never a clock read.
     */
    public function readyAt(): int
    {
        return $this->readyAt;
    }

    /**
     * The retried entry: the same event, values and listener
     * identity, standing at the next attempt number, due no
     * earlier than the backoff answered for the attempt that just
     * failed -- and carrying a FRESH entry identity, because the
     * retry is a new entry and the spent one is settled under its
     * own identity, never conflated with the entry that replaced
     * it.
     */
    public function withNextAttempt(int $attempt, int $readyAt): self
    {
        return new self(
            DeliveryEntryId::mint(),
            $this->eventName,
            $this->values,
            $this->listenerIdentity,
            $attempt,
            $readyAt,
        );
    }
}
