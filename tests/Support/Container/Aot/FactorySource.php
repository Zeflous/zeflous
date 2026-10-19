<?php

declare(strict_types=1);

namespace Zef\Test\Support\Container\Aot;

/**
 * Fixture factory target for the build-kind artifact entry: a static
 * construction entry the factory shape's resolution lane calls.
 */
final readonly class FactorySource
{
    public static function make(Window $window, string $frame): FactoryProduct
    {
        return new FactoryProduct($window, $frame);
    }
}
