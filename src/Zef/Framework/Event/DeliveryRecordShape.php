<?php

declare(strict_types=1);

namespace Zef\Framework\Event;

use InvalidArgumentException;

/**
 * The durable record's shape fold: the one question "is this
 * decoded JSON a pending record at all" -- a JSON object carrying
 * EXACTLY the six keys the record's vocabulary spells, neither an
 * unknown key nor a missing one. The fold is the reading
 * boundary's own gate: a record that passes here is a map of the
 * six known keys, and the field extractors behind it only judge
 * what each key carries -- never whether it exists.
 */
final class DeliveryRecordShape
{
    private const array RECORD_KEYS = [
        'entry_id',
        'event_name',
        'values',
        'listener_identity',
        'attempt',
        'ready_at',
    ];

    /**
     * Asserts the decoded JSON is the object shape of one pending
     * record, answering its six-key map.
     *
     * @param mixed $decoded the JSON the reading boundary decoded
     *
     * @return array<array-key, mixed> the record's own key map
     *
     * @throws InvalidArgumentException when the record is not a
     *                                  JSON object, carries an unknown key, or misses one of the
     *                                  vocabulary's keys
     */
    public function assert(mixed $decoded): array
    {
        if (!\is_array($decoded)) {
            throw new InvalidArgumentException(
                'The durable delivery record must be a JSON object, got '
                . get_debug_type($decoded) . '.',
            );
        }

        $this->refuseUnknownKeys($decoded);
        $this->refuseMissingKeys($decoded);

        return $decoded;
    }

    /**
     * @param array<array-key, mixed> $record
     */
    private function refuseUnknownKeys(array $record): void
    {
        foreach (array_keys($record) as $key) {
            if (!\in_array($key, self::RECORD_KEYS, true)) {
                throw new InvalidArgumentException(
                    'The durable delivery record carries an unknown key'
                    . " '{$key}'.",
                );
            }
        }
    }

    /**
     * @param array<array-key, mixed> $record
     */
    private function refuseMissingKeys(array $record): void
    {
        foreach (self::RECORD_KEYS as $key) {
            if (!\array_key_exists($key, $record)) {
                throw new InvalidArgumentException(
                    "The durable delivery record misses the '{$key}' key.",
                );
            }
        }
    }
}
