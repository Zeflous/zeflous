<?php

declare(strict_types=1);

namespace Zef\Framework\Config;

/**
 * Fail-closed resolution of a dot-notation path to the list it addresses.
 *
 * List mutations ({@see DotListWriter}) may only touch a target that exists
 * and really is a list; this class performs that resolution and throws a
 * {@see ConfigException} with the full dot-notation key otherwise, so an
 * unknown shape is never silently guessed.
 */
final readonly class DotListLocator
{
    /**
     * Resolves the segments against the data to the list they address, or
     * throws when the path does not resolve.
     *
     * @param array<array-key, mixed> $data
     * @param non-empty-list<string>  $segments
     *
     * @return array<array-key, mixed>
     */
    public static function target(array $data, array $segments, string $operation): array
    {
        $result = DotKey::lookup($data, $segments);

        if (!$result[0]) {
            throw new ConfigException(
                \sprintf('Cannot %s configuration key "%s": it is not set.', $operation, implode('.', $segments)),
            );
        }

        return self::listValue($result[1], $segments, $operation);
    }

    /**
     * Narrows the addressed value to a list, or throws when it is a scalar or
     * an associative map.
     *
     * @param non-empty-list<string> $segments
     *
     * @return array<array-key, mixed>
     */
    private static function listValue(mixed $value, array $segments, string $operation): array
    {
        if (!\is_array($value) || !array_is_list($value)) {
            throw new ConfigException(
                \sprintf('Cannot %s configuration key "%s": it is not a list.', $operation, implode('.', $segments)),
            );
        }

        return $value;
    }
}
