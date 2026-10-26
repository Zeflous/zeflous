<?php

declare(strict_types=1);

namespace Zef\Framework\Event;

use InvalidArgumentException;

/**
 * The durable record's values map, proven string-keyed: the fold
 * that narrows a decoded record's values half -- the JSON object
 * grammar itself casts a numeric key to the integer PHP array
 * keys answer, and the field-name domain is the record's own
 * vocabulary, so the numeric key is refused here, at the reading
 * boundary, with the key spelled. The map the fold answers is
 * the JSON grammar's own answer set (null, booleans, integers,
 * floats, strings and arrays -- the only kinds a decoded record
 * can carry), held in the declaration order the record spelled.
 */
final class DeliveryRecordValues
{
    /**
     * @param mixed $values the decoded record's values half
     *
     * @return array<string, null|array<mixed>|bool|float|int|string>
     *
     * @throws InvalidArgumentException when the values half is not
     *                                  a JSON object or carries a non-string field name
     */
    public function narrow(mixed $values): array
    {
        if (!\is_array($values)) {
            throw new InvalidArgumentException(
                "The durable delivery record's values must be a JSON"
                . ' object, got ' . get_debug_type($values) . '.',
            );
        }

        /** @var array<string, null|array<mixed>|bool|float|int|string> $narrowed the string-keyed map the walk below proves */
        $narrowed = [];

        /** @var null|array<mixed>|bool|float|int|string $value the JSON grammar's own answer set, decoded */
        foreach ($values as $field => $value) {
            if (!\is_string($field)) {
                throw new InvalidArgumentException(
                    "The durable delivery record's values must carry string"
                    . ' field names, got the key ' . var_export($field, true) . '.',
                );
            }

            $narrowed[$field] = $value;
        }

        return $narrowed;
    }
}
