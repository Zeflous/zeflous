<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

/**
 * The decorator entry grammar's parse pipeline: the map shape, the
 * known keys, the one shape key, and the composition of the parsed
 * {@see DecoratorEntry} from the shape's own proofs -- the
 * {@see ServiceDefinition::fromArray()} posture, split from the value
 * object it answers so the grammar's shape parsers never reference the
 * entry type back (a vocabulary that closes on itself is a dependency
 * cycle the boot's own gate refuses).
 */
final readonly class DecoratorEntryParser
{
    /**
     * Parses one decorator entry for the target, fail-closed.
     *
     * @param mixed $entry the raw decorator map
     */
    public static function parsed(string $target, mixed $entry): DecoratorEntry
    {
        if (\is_array($entry)) {
            return self::parsedMap($target, $entry);
        }

        throw DecoratorRefusals::entryNotAMap($target, $entry);
    }

    /**
     * The validated map's parse: the known keys, then the shape
     * dispatch composing the entry from the shape's own proofs.
     *
     * @param array<array-key, mixed> $entry
     */
    private static function parsedMap(string $target, array $entry): DecoratorEntry
    {
        DecoratorEntryKeys::unknownChecked($target, $entry);

        return match (DecoratorEntryShapes::shapeOf($target, $entry)) {
            'class' => new DecoratorEntry(
                DecoratorClassShape::existingClass($target, $entry['class']),
                null,
                DecoratorEntryArguments::validated($target, $entry),
            ),
            default => new DecoratorEntry(
                null,
                FactorySpellings::validated('decorators.' . $target, $entry['factory']),
                DecoratorEntryArguments::validated($target, $entry),
            ),
        };
    }
}
