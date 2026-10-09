<?php

declare(strict_types=1);

namespace Zef\Framework\Tooling;

/**
 * Narrowing helpers for values read out of decoded YAML/JSON, where the static
 * type is `mixed`. Kept in one place so the callers stay small.
 */
final class MixedValue
{
    /**
     * @return null|array<array-key, mixed>
     */
    public static function arrayOrNull(mixed $value): ?array
    {
        return \is_array($value) ? $value : null;
    }

    public static function stringOrNull(mixed $value): ?string
    {
        return \is_string($value) ? $value : null;
    }
}
