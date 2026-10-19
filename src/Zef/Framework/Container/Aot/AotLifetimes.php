<?php

declare(strict_types=1);

namespace Zef\Framework\Container\Aot;

use Zef\Framework\Container\ServiceLifetime;

/**
 * The artifact entry's lifetime member, read and converted: the one
 * member every entry kind shares, spelled once -- the spelling must
 * name a lifetime the container knows, or the entry's identifier
 * carries the refusal.
 */
final readonly class AotLifetimes
{
    /**
     * Reads one entry's lifetime member as the lifetime it names.
     *
     * @param array<array-key, mixed> $entry the entry carrying the lifetime
     */
    public static function from(int|string $id, array $entry): ServiceLifetime
    {
        $spelling = $entry['lifetime'] ?? null;

        if (!\is_string($spelling)) {
            throw AotRefusals::entryLifetime($id, get_debug_type($spelling));
        }

        return ServiceLifetime::tryFrom($spelling)
            ?? throw AotRefusals::entryLifetime($id, $spelling);
    }
}
