<?php

declare(strict_types=1);

namespace Zef\Framework\Adapters\Amqp;

use InvalidArgumentException;

/**
 * The AMQP field table's own spelling: the length-prefixed set of
 * name-typed value entries the client properties spell -- one fold
 * the wire writer's primitives compose, split from the writer the
 * same ceiling every slice's folds answer. The type vocabulary is
 * the subset the family's own tables carry: booleans, the two
 * integer widths and strings -- every other kind the protocol
 * spells is refused, because no table the family writes ever
 * carries one.
 */
final class AmqpFieldTable
{
    /**
     * One field table's own bytes.
     *
     * @param array<string, bool|int|string> $values the table's entries, in their own declaration order
     *
     * @throws InvalidArgumentException never at runtime -- every
     *                                  entry's own bounds refuse before the table is asked to
     *                                  carry them
     */
    public function encode(array $values): string
    {
        $entries = '';

        foreach ($values as $name => $value) {
            $entries .= $this->entry($name, $value);
        }

        return (new AmqpWireWriter())
            ->long(mb_strlen($entries, '8bit'))
            ->raw($entries)
            ->bytes();
    }

    /**
     * One table entry's own bytes: the name's shortstr spelling, the
     * type key, and the typed value -- built on a fresh writer so
     * the entry's own bounds refuse the way every other field's do,
     * never relying on the caller's table being well-formed.
     *
     * @param bool|int|string $value the entry's own value
     */
    private function entry(string $name, bool|int|string $value): string
    {
        $entry = new AmqpWireWriter();
        $entry->shortstr($name);

        if (\is_bool($value)) {
            return $entry->raw('t')->octet($value ? 1 : 0)->bytes();
        }

        if (\is_int($value)) {
            if ($value >= 0 && $value <= 0xffffffff) {
                return $entry->raw('I')->long($value)->bytes();
            }

            return $entry->raw('l')->longlong($value)->bytes();
        }

        return $entry->raw('S')->longstr($value)->bytes();
    }
}
