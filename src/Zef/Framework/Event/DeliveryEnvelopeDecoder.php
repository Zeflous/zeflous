<?php

declare(strict_types=1);

namespace Zef\Framework\Event;

use InvalidArgumentException;
use JsonException;

/**
 * The durable spelling family's reading half: the JSON translation
 * of a pending record back into the {@see DeliveryEnvelope} it
 * spelled. The fold composes the reading boundary's own gates:
 * the JSON decode itself (invalid JSON refused with the engine's
 * own reason), the record's object shape
 * ({@see DeliveryRecordShape}: exactly the six vocabulary keys),
 * the string-keyed values map ({@see DeliveryRecordValues}), and
 * the field extractors -- each judging what one key carries, every
 * grammar the entry id, the event name and the listener identity
 * own included, all refused by name. A record that decodes on a
 * guess is a delivery that was never admitted.
 */
final readonly class DeliveryEnvelopeDecoder
{
    public function __construct(
        private DeliveryRecordShape $deliveryRecordShape = new DeliveryRecordShape(),
        private DeliveryRecordValues $deliveryRecordValues = new DeliveryRecordValues(),
    ) {
    }

    /**
     * Decodes one pending record back into the envelope it
     * spelled. Every refusal names the record's own defect -- a
     * durable store that cannot explain a refused entry is a
     * store nobody can operate.
     *
     * @throws InvalidArgumentException when the record is not the
     *                                  JSON spelling of one pending delivery
     */
    public function decode(string $json): DeliveryEnvelope
    {
        try {
            /** @var mixed $decoded the decoded record */
            $decoded = json_decode($json, true, 512, \JSON_THROW_ON_ERROR);
        } catch (JsonException $jsonException) {
            throw new InvalidArgumentException(
                'The durable delivery record is not valid JSON: '
                . $jsonException->getMessage() . '.',
                0,
                $jsonException,
            );
        }

        $record = $this->deliveryRecordShape->assert($decoded);

        return new DeliveryEnvelope(
            $this->entryId($record),
            $this->eventName($record),
            $this->deliveryRecordValues->narrow($record['values']),
            $this->listenerIdentity($record),
            $this->attempt($record),
            $this->readyAt($record),
        );
    }

    /**
     * @param array<array-key, mixed> $record
     */
    private function entryId(array $record): DeliveryEntryId
    {
        $entryId = $record['entry_id'];

        if (!\is_string($entryId)) {
            throw new InvalidArgumentException(
                "The durable delivery record's entry id must be a string,"
                . ' got ' . get_debug_type($entryId) . '.',
            );
        }

        return new DeliveryEntryId($entryId);
    }

    /**
     * @param array<array-key, mixed> $record
     */
    private function eventName(array $record): EventName
    {
        $eventName = $record['event_name'];

        if (!\is_string($eventName)) {
            throw new InvalidArgumentException(
                "The durable delivery record's event name must be a string,"
                . ' got ' . get_debug_type($eventName) . '.',
            );
        }

        return new EventName($eventName);
    }

    /**
     * @param array<array-key, mixed> $record
     */
    private function listenerIdentity(array $record): ListenerIdentity
    {
        $listenerIdentity = $record['listener_identity'];

        if (!\is_string($listenerIdentity)) {
            throw new InvalidArgumentException(
                "The durable delivery record's listener identity must be a"
                . ' string, got ' . get_debug_type($listenerIdentity) . '.',
            );
        }

        return new ListenerIdentity($listenerIdentity);
    }

    /**
     * @param array<array-key, mixed> $record
     */
    private function attempt(array $record): int
    {
        $attempt = $record['attempt'];

        if (!\is_int($attempt)) {
            throw new InvalidArgumentException(
                "The durable delivery record's attempt must be an integer,"
                . ' got ' . get_debug_type($attempt) . '.',
            );
        }

        if ($attempt < 1) {
            throw new InvalidArgumentException(
                "The durable delivery record's attempt must stand at one or"
                . ' later, got ' . $attempt . '.',
            );
        }

        return $attempt;
    }

    /**
     * @param array<array-key, mixed> $record
     */
    private function readyAt(array $record): int
    {
        $readyAt = $record['ready_at'];

        if (!\is_int($readyAt)) {
            throw new InvalidArgumentException(
                "The durable delivery record's ready-at must be an integer,"
                . ' got ' . get_debug_type($readyAt) . '.',
            );
        }

        if ($readyAt < 0) {
            throw new InvalidArgumentException(
                "The durable delivery record's ready-at cannot be negative,"
                . ' got ' . $readyAt . '.',
            );
        }

        return $readyAt;
    }
}
