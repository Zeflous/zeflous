<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

/**
 * The decoration lane's identifier spellings and layer arithmetic: the
 * synthetic namespace the boot apply re-homes decorated definitions
 * into, and the fold that answers which identifier any layer of a chain
 * sits over -- one class owns the maths so the surgery, the collision
 * guards and the tests quote a single source (the
 * {@see SyntheticValueGuard} and {@see ContextualSet} precedent:
 * '@value:', '@contextual:').
 *
 * The layer grammar, for a target declared as 'T' with a chain of N
 * decorators (the first the outermost):
 *
 * - `@decor:T:base` -- the original declaration, re-homed: the innermost
 *   layer every chain resolves to;
 * - the original identifier `T` -- the outermost wrapper, the only
 *   definition the rest of the set ever observes;
 * - `@decor:T:1` ... `@decor:T:N-1` -- the wrappers between them, each
 *   referencing the next layer inward.
 */
final readonly class DecoratorLayers
{
    /** The lane's synthetic namespace prefix. */
    public const string PREFIX = '@decor:';

    /** The reserved argument reference naming the layer a decorator wraps. */
    public const string INNER = '@inner';

    /**
     * The re-homed original's synthetic identifier.
     */
    public static function base(string $target): string
    {
        return self::PREFIX . $target . ':base';
    }

    /**
     * A between-layer wrapper's synthetic identifier (k >= 1, counting
     * from the outermost wrapper).
     */
    public static function layer(string $target, int $k): string
    {
        return self::PREFIX . $target . ':' . $k;
    }

    /**
     * The identifier of the layer wrapper k sits over: the next wrapper
     * inward, or the re-homed base for the innermost wrapper.
     *
     * @param int $k     the wrapper's position in the chain, outermost = 0
     * @param int $count the chain's length
     */
    public static function innerOf(string $target, int $k, int $count): string
    {
        return $k + 1 < $count
            ? self::layer($target, $k + 1)
            : self::base($target);
    }

    /**
     * Does the identifier occupy the lane's synthetic namespace?
     */
    public static function occupies(string $id): bool
    {
        return str_starts_with($id, self::PREFIX);
    }
}
