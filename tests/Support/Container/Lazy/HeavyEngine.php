<?php

declare(strict_types=1);

namespace Zef\Test\Support\Container\Lazy;

/**
 * Fixture service that counts its own constructions, so a test can tell
 * a deferred lazy construction (zero constructions after the boot and
 * the resolution, one after the first use) from an eager one.
 */
final class HeavyEngine
{
    public static int $constructions = 0;

    public function __construct(
        public readonly EngineFuel $fuel,
        public readonly int $thrust = 3,
    ) {
        ++self::$constructions;
    }

    public function thrust(): int
    {
        return $this->thrust;
    }

    public static function reset(): void
    {
        self::$constructions = 0;
    }
}
