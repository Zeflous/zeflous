<?php

declare(strict_types=1);

namespace Zef\Test\Support\Container\Decorators;

/**
 * The factory-shaped decorator over non-logger targets: static
 * wrappers whose first argument is the layer they wrap (the decorator
 * entry's reserved '@inner' reference).
 */
final class ValueWrappers
{
    public static function exclaim(string $banner): string
    {
        return $banner . '!';
    }

    /**
     * @return array{mixed, mixed}
     */
    public static function pair(mixed $first, mixed $second): array
    {
        return [$first, $second];
    }
}
