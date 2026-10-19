<?php

declare(strict_types=1);

namespace Zef\Test\Support\Container\Aot;

/**
 * Fixture service that counts its own constructions, so a test can tell
 * an eagerly warmed singleton (constructed at boot) from a lazily
 * resolved one (constructed at the first `get()`).
 */
final class WarmCounter
{
    public static int $constructions = 0;

    public function __construct(
        public string $label = 'warm',
    ) {
        ++self::$constructions;
    }

    public static function reset(): void
    {
        self::$constructions = 0;
    }
}
