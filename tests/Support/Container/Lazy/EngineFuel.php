<?php

declare(strict_types=1);

namespace Zef\Test\Support\Container\Lazy;

/**
 * Fixture dependency that counts its own constructions, so a test can
 * tell whether a lazy service's whole dependency chain deferred with it
 * (the fuel constructed only at the engine's first use) or rode the
 * eager lane.
 */
final class EngineFuel
{
    public static int $constructions = 0;

    public function __construct()
    {
        ++self::$constructions;
    }

    public function burn(): int
    {
        return 1;
    }

    public static function reset(): void
    {
        self::$constructions = 0;
    }
}
