<?php

declare(strict_types=1);

namespace Zef\Framework\Event;

use InvalidArgumentException;

/**
 * The immutable, already-validated payload an event carries. Only
 * the shape's own {@see EventShape::validate()} builds one, so by
 * construction every field the shape demands is present and
 * answers its demanded kind -- listeners read values out without
 * re-checking, and the "is this payload well-formed?" question is
 * answered exactly once, at the dispatch boundary.
 */
final class EventPayload
{
    /** @param array<string, null|array<mixed>|bool|float|int|string> $values the validated field map */
    public function __construct(private array $values)
    {
    }

    /**
     * One field's value. The return union is the vocabulary's whole
     * kind set plus null, so a listener narrows with its own native
     * check -- the shape has already proven the check will pass.
     *
     * @return null|array<mixed>|bool|float|int|string
     *
     * @throws InvalidArgumentException when the field is not one the
     *                                  payload carries
     */
    public function value(string $field): array|bool|float|int|string|null
    {
        if (!\array_key_exists($field, $this->values)) {
            throw new InvalidArgumentException(
                "The event payload does not carry a '{$field}' field.",
            );
        }

        return $this->values[$field];
    }

    /**
     * The field names the payload carries, in the shape's own
     * declaration order -- the vocabulary a debugging listener
     * prints without re-deriving it.
     *
     * @return list<string>
     */
    public function fields(): array
    {
        return array_keys($this->values);
    }

    /**
     * The whole validated map, the shape's own declaration order
     * held -- the spelling a durable defer serializes into its
     * entry record. A copy by PHP's own value semantics, so a
     * caller reading the map can never rewrite what the frozen
     * event carries.
     *
     * @return array<string, null|array<mixed>|bool|float|int|string>
     */
    public function values(): array
    {
        return $this->values;
    }
}
