<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

/**
 * The declarative definition grammar's key vocabulary.
 *
 * One place owns the spelling of every accepted key, so the parser's
 * rejections can quote the known list (the configuration layer's schema
 * style: unknown keys are reported with what *would* have been accepted,
 * never silently ignored).
 */
final readonly class DefinitionKeys
{
    public const array DEFINITION = ['class', 'factory', 'alias', 'value', 'arguments'];

    /**
     * Returns the first key of the entry that is not part of the known
     * vocabulary, or null when every key is known.
     *
     * @param array<array-key, mixed> $entry
     * @param list<string>            $known
     */
    public static function unknownIn(array $entry, array $known): ?string
    {
        foreach (array_keys($entry) as $key) {
            if (!\in_array($key, $known, true)) {
                return (string) $key;
            }
        }

        return null;
    }

    /**
     * Spells a key list for an error message ("class, factory, alias").
     *
     * @param list<string> $keys
     */
    public static function spelled(array $keys): string
    {
        return implode(', ', $keys);
    }
}
