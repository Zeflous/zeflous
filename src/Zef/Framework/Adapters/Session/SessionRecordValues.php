<?php

declare(strict_types=1);

namespace Zef\Framework\Adapters\Session;

use InvalidArgumentException;

/**
 * The session record's bag half, proven string-keyed: the fold
 * that narrows a decoded record's data half -- the JSON object
 * grammar itself casts a numeric key to the integer PHP array
 * keys answer, and the bag's key domain is the host's own
 * vocabulary, so the numeric key is refused here, at the reading
 * boundary, with the key's own kind spelled. The map the fold
 * answers is the JSON grammar's own answer set (null, booleans,
 * integers, floats, strings and arrays -- the only kinds a decoded
 * record can carry), held in the declaration order the record
 * spelled -- the delivery record's values fold's own discipline
 * ({@see \Zef\Framework\Event\DeliveryRecordValues}), replayed for
 * the session bag.
 */
final class SessionRecordValues
{
    /**
     * Narrows the record's data half into the string-keyed bag it
     * must be.
     *
     * @param mixed  $data the decoded record's data half
     * @param string $id   the record's own identifier, spelled into
     *                     every refusal
     *
     * @return array<string, mixed> the bag, string-keyed
     *
     * @throws InvalidArgumentException when the data half is not a
     *                                  map or carries a non-string key
     */
    public function narrow(mixed $data, string $id): array
    {
        if (!\is_array($data)) {
            throw new InvalidArgumentException(
                "The session record '{$id}' data key must be a map, "
                . get_debug_type($data) . ' given.',
            );
        }

        /** @var array<string, mixed> $values the bag's own string-keyed map */
        $values = [];

        /** @var null|array<mixed>|bool|float|int|string $bagValue the JSON grammar's own answer set, decoded */
        foreach ($data as $key => $bagValue) {
            if (!\is_string($key)) {
                throw new InvalidArgumentException(
                    "The session record '{$id}' data map must be keyed by strings, "
                    . get_debug_type($key) . ' given.',
                );
            }

            $values[$key] = $bagValue;
        }

        return $values;
    }
}
