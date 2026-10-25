<?php

declare(strict_types=1);

namespace Zef\Framework\Event;

use InvalidArgumentException;

/**
 * The event's payload contract: the map of field names to the kind
 * each must answer, spelled once where the event is defined and
 * enforced at every dispatch. The validation is fail-closed in all
 * three directions -- a field the shape never named, a field the
 * shape demanded but the producer left out, and a field present
 * but answering the wrong kind are all refused by name, before a
 * single listener runs.
 *
 * That ordering is the whole point of the roadmap's "payload
 * validation" demand: a mis-shaped event is a producer bug, and
 * the bug must surface at the dispatch call site with the field,
 * the demanded kind and the found kind named -- not as a type
 * error inside some far-away listener.
 */
final readonly class EventShape
{
    /** @var array<string, EventShapeField> */
    private array $fields;

    /**
     * @param array<array-key, string> $fields the field spellings, keyed
     *                                         by field name -- `'userId' => 'int'`, `'email' => '?string'`.
     *                                         The key type is array-key on purpose: PHP casts a numeric
     *                                         string key to an integer, and a numeric or empty field
     *                                         name is refused below, not silently accepted.
     *
     * @throws InvalidArgumentException when the field map is empty or
     *                                  a spelling is not a kind the vocabulary carries
     */
    public function __construct(array $fields)
    {
        if ($fields === []) {
            throw new InvalidArgumentException(
                'An event shape must demand at least one field; the empty'
                . ' shape would validate any payload and catch nothing.',
            );
        }

        $parsed = [];

        foreach ($fields as $field => $spelling) {
            if (!\is_string($field) || $field === '') {
                throw new InvalidArgumentException(
                    'An event shape field must be named with a non-empty string key.',
                );
            }

            $parsed[$field] = new EventShapeField($spelling);
        }

        $this->fields = $parsed;
    }

    /**
     * Validates a candidate payload against the contract and wraps
     * it as the immutable {@see EventPayload} an event carries.
     *
     * @param array<array-key, mixed> $values the producer's payload map
     *                                        -- array-key because PHP casts a numeric string key
     *                                        to an integer, and an integer key never matches a
     *                                        demanded field name, so it is refused below as the
     *                                        unknown field it is
     *
     * @throws InvalidArgumentException when the payload carries an
     *                                  unknown field, misses a demanded field, or a field
     *                                  answers the wrong kind
     */
    public function validate(array $values): EventPayload
    {
        foreach (array_keys($values) as $field) {
            if (!\is_string($field) || !\array_key_exists($field, $this->fields)) {
                throw new InvalidArgumentException(
                    'The payload carries a field the event shape never'
                    . " demanded: '{$field}'.",
                );
            }
        }

        /** @var array<string, null|array<mixed>|bool|float|int|string> $validated */
        $validated = [];

        foreach ($this->fields as $field => $shapeField) {
            if (!\array_key_exists($field, $values)) {
                throw new InvalidArgumentException(
                    'The payload misses the field the event shape demands:'
                    . " '{$field}' ({$shapeField->type()->spelling()}"
                    . ($shapeField->isNullable() ? ', nullable' : '')
                    . ').',
                );
            }

            /** @var null|array<mixed>|bool|float|int|string $value */
            $value = $values[$field];

            if (!$shapeField->accepts($value)) {
                throw new InvalidArgumentException(
                    "The payload field '{$field}' must answer"
                    . " {$shapeField->type()->spelling()}"
                    . ($shapeField->isNullable() ? ' or null' : '')
                    . ', got ' . get_debug_type($value) . '.',
                );
            }

            $validated[$field] = $value;
        }

        return new EventPayload($validated);
    }

    /**
     * The demanded fields in their declaration order -- the order
     * the refusal walk and the payload's own lookups keep, so a
     * wrong payload's first complaint is about the shape's first
     * unsatisfied demand.
     *
     * @return array<string, EventShapeField>
     */
    public function fields(): array
    {
        return $this->fields;
    }
}
