<?php

declare(strict_types=1);

namespace Zef\Framework\Event;

/**
 * The payload vocabulary's own type system: the five scalar-ish
 * kinds an event shape may demand of a field, each owning the one
 * native check that decides whether a value answers it. Keeping
 * the check here -- not in the shape or the dispatcher -- means a
 * new kind joins the vocabulary in exactly one place, and every
 * refusal names the kind the field asked for.
 *
 * The set is intentionally small: strings, integers, floats,
 * booleans and lists. The list kind is the escape hatch for a
 * compound value (its own items stay whatever the listener
 * narrows them to), because a framework event is a notification,
 * not a serializer: richer shapes belong to the event's own class
 * when a domain outgrows the map form.
 */
enum PayloadFieldType: string
{
    case String = 'string';
    case Integer = 'int';
    case Float = 'float';
    case Boolean = 'bool';
    case List = 'list';

    /**
     * The shape spelling this kind answers to -- the one word the
     * shape field parser accepts and every refusal quotes back.
     */
    public function spelling(): string
    {
        return $this->value;
    }

    /**
     * Whether a candidate payload value answers this kind. A value
     * that does not is refused by the shape with both spellings
     * named -- the kind the field asked for and the kind PHP
     * actually found -- so the mis-shaped producer reads its own
     * mistake without opening the decision record.
     */
    public function accepts(mixed $value): bool
    {
        return match ($this) {
            self::String => \is_string($value),
            self::Integer => \is_int($value),
            self::Float => \is_float($value),
            self::Boolean => \is_bool($value),
            self::List => \is_array($value),
        };
    }
}
