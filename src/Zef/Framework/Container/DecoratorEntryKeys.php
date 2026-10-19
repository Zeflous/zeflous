<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

/**
 * The decorator entry grammar's key vocabulary: the spelling of every
 * accepted key, and the unknown-key check that quotes it (the
 * {@see DefinitionKeys} posture -- one place owns the vocabulary, so the
 * entry parser's rejections report what *would* have been accepted,
 * never silently ignored).
 */
final readonly class DecoratorEntryKeys
{
    private const array ENTRY_KEYS = ['class', 'factory', 'arguments'];

    /**
     * Rejects the entry's first key that is not part of the known
     * vocabulary.
     *
     * @param array<array-key, mixed> $entry
     */
    public static function unknownChecked(string $target, array $entry): void
    {
        $unknown = DefinitionKeys::unknownIn($entry, self::ENTRY_KEYS);
        if ($unknown !== null) {
            throw DecoratorRefusals::unknownEntryKey($target, $unknown);
        }
    }
}
