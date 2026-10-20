<?php

declare(strict_types=1);

namespace Zef\Test\Support\Container\Lazy;

/**
 * The lazy factory shape's unnameable return-type spellings, one method
 * each: the grammar refuses a lazy factory whose return type cannot
 * name exactly one existing class -- no return type at all, a nullable,
 * a union, a builtin, and the unresolved `self`/`static` spellings.
 */
final class LazyReturnShapes
{
    /**
     * The absent return declaration: the native signature deliberately
     * carries no return type, and the docblock stays prose -- reflection's
     * own question is the fixture's whole subject, so the toolchain's
     * skips for this file keep every hand off the signature.
     */
    public static function none()
    {
        return new HeavyEngine(new EngineFuel());
    }

    /**
     * The nullable product: a factory whose answer may honestly be
     * absent, null when asked -- the declared type names both paths.
     */
    public static function nullable(bool $absent = false): ?HeavyEngine
    {
        return $absent ? null : new HeavyEngine(new EngineFuel());
    }

    /**
     * The union product: a factory that answers either shape,
     * honestly -- the declared type names both.
     */
    public static function union(bool $shapes = false): HeavyEngine|self
    {
        return $shapes ? new self() : new HeavyEngine(new EngineFuel());
    }

    public static function builtin(): string
    {
        return 'nope';
    }

    public static function selfed(): self
    {
        return new self();
    }

    public static function statted(): static
    {
        return new self();
    }

    /**
     * The interface return: a named type that names no proxyable
     * class (an interface has no instance to stand in for the proxy).
     */
    public static function interfaced(): EngineContract
    {
        return new FuelledEngine();
    }

    /**
     * The ghost return: a class the runtime never carries -- the stub
     * file keeps the analysers honest while the boot's own
     * class-existence check refuses the declaration.
     */
    public static function ghosted(): GhostEngine
    {
        return new GhostEngine();
    }
}
