<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * The session's data bag: the mutable key/value store the request's
 * own state ({@see SessionState}) writes through, lifted into its
 * own unit (the #801 round's own archeology answer -- the state's
 * difficulty crossed the bar with the rotation aboard, and the bag
 * -- the class's own docblock already called it "one identifier, one
 * data bag, and the three flags" -- is the cohesive piece that moves
 * out beside the rotation).
 *
 * The dirty flag is write-honest: a write marks it, a removal marks
 * it only when the key was actually there (a no-op removal that
 * dirtied the bag would answer a redundant save the storage did not
 * need), and the raw bag's exposure ({@see self::data()}) cannot
 * dirty anything because PHP's arrays are copied on return -- a host
 * mutating the array it read mutates its own copy, never the
 * session's. The destruction's emptying ({@see self::clear()}) never
 * dirties: the destroyed verdict retires the whole entry, never
 * saving whatever the bag last held.
 */
final class SessionDataBag
{
    private bool $dirty = false;

    /**
     * @param array<string, mixed> $data the loaded bag, or empty for a
     *                                   freshly minted session
     */
    public function __construct(private array $data)
    {
    }

    /**
     * One key's value, or null when the bag does not carry it -- the
     * host's own judgement over the mixed the bag honestly holds (the
     * session's data is host data; the family's analysers never widen
     * it, exactly as the parsed body's is).
     */
    public function value(string $key): mixed
    {
        return $this->data[$key] ?? null;
    }

    /**
     * The raw bag, copied on return: mutating the array this answers
     * mutates the caller's copy, never the session's.
     *
     * @return array<string, mixed>
     */
    public function data(): array
    {
        return $this->data;
    }

    /**
     * Writes one key's value, marking the bag dirty.
     */
    public function set(string $key, mixed $value): void
    {
        $this->data[$key] = $value;
        $this->dirty = true;
    }

    /**
     * Removes one key, marking the bag dirty only when the key was
     * actually there -- a removal over an absent key is a no-op the
     * storage is never asked to persist.
     */
    public function remove(string $key): void
    {
        if (!\array_key_exists($key, $this->data)) {
            return;
        }

        unset($this->data[$key]);
        $this->dirty = true;
    }

    /**
     * Whether the bag carries writes the storage has not seen.
     */
    public function isDirty(): bool
    {
        return $this->dirty;
    }

    /**
     * Marks the bag dirty without writing a key: the identifier
     * rotation's own write mark (the bag must save under the fresh
     * identifier even when the request wrote nothing new).
     */
    public function markDirty(): void
    {
        $this->dirty = true;
    }

    /**
     * Empties the bag, never dirtying: the destruction verdict
     * retires the whole entry, never saving whatever the bag last
     * held.
     */
    public function clear(): void
    {
        $this->data = [];
    }
}
