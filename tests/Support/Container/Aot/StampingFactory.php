<?php

declare(strict_types=1);

namespace Zef\Test\Support\Container\Aot;

/**
 * Fixture factory target whose product records the method-call
 * injection the artifact's build entry reconstructs, so a round trip
 * can prove the calls survived the boundary.
 */
final class StampingFactory
{
    public static function make(Window $window): StampingProduct
    {
        return new StampingProduct($window);
    }
}
