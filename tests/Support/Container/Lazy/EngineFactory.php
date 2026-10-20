<?php

declare(strict_types=1);

namespace Zef\Test\Support\Container\Lazy;

/**
 * Fixture factory source for the lazy factory shape: a static method
 * with a declared concrete return class -- the one spelling the lazy
 * lane accepts, answering a fuelled engine.
 */
final class EngineFactory
{
    public static function make(EngineFuel $engineFuel): HeavyEngine
    {
        return new HeavyEngine($engineFuel, 7);
    }
}
