<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

/**
 * The `lazy` key's truthiness parse (the declaration grammar's deferral
 * request; see {@see InstanceShape} and {@see FactoryShape} for the two
 * shapes that carry it).
 *
 * The key is a strict boolean, the same honesty every knob of this
 * grammar keeps: an absent key answers "not lazy" (the behaviour every
 * earlier declaration already had), `true` asks for the deferred
 * construction, `false` spells the same answer explicitly, and anything
 * else -- a truthy string, a 1, a null-ish placeholder -- is the
 * declaration defect it is, refused with the service's identifier and
 * the accepted spellings rather than coerced ({@see LazyRefusals::keyNotABoolean()}).
 */
final readonly class LazyKey
{
    /**
     * Parses the `lazy` key's truthiness for one service.
     *
     * @param mixed $key the raw `lazy` value from the definition map
     */
    public static function flag(string $id, mixed $key): bool
    {
        if ($key === null) {
            return false;
        }

        if (!\is_bool($key)) {
            throw LazyRefusals::keyNotABoolean($id, $key);
        }

        return $key;
    }
}
