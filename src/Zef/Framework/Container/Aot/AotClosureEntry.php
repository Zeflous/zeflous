<?php

declare(strict_types=1);

namespace Zef\Framework\Container\Aot;

use Closure;
use Zef\Framework\Container\Container;
use Zef\Framework\Container\ServiceLifetime;

/**
 * The load side of a closure-kind artifact entry: the identifier's live
 * factory -- code the dump embedded in the artifact's array literal, the
 * `require` itself evaluated into a `Closure` -- with the lifetime the
 * dump baked.
 *
 * The value object is its own grammar ({@see validated()}): the entry's
 * members checked into the typed form the moment it is read, the same
 * self-throwing shape the definition grammar's per-shape units use -- so
 * {@see AotDefinitions} installs proof, not promises. The identifier is
 * the entry's key in the artifact's map -- one spelling, one source.
 */
final readonly class AotClosureEntry
{
    /**
     * The members a closure-kind entry carries, exactly -- the shape
     * guard's own spelling.
     */
    private const array MEMBERS = ['kind', 'closure', 'lifetime'];

    /**
     * @param Closure(Container): mixed $closure the rendered factory the
     *                                           `require` evaluated
     */
    public function __construct(
        public Closure $closure,
        public ServiceLifetime $lifetime,
    ) {
    }

    /**
     * Validates one closure-kind entry into its typed form: the members
     * exactly, the closure live, the lifetime already converted by the
     * dispatch that chose this kind.
     *
     * @param array<array-key, mixed> $entry the payload's entry
     */
    public static function validated(string $id, array $entry, ServiceLifetime $serviceLifetime): self
    {
        if (array_keys($entry) !== self::MEMBERS) {
            throw AotRefusals::entryMalformed($id);
        }

        $closure = $entry['closure'];

        if (!$closure instanceof Closure) {
            throw AotRefusals::entryMalformed($id);
        }

        // The artifact contract guarantees the signature the dump renders
        // -- factories that take the container -- the same trust every
        // other structural promise the validation above already extends.
        // @var Closure(Container): mixed $closure
        return new self($closure, $serviceLifetime);
    }
}
