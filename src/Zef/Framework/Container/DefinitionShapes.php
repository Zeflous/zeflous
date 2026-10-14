<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

/**
 * The shape disambiguation of a validated definition map.
 *
 * A definition carries exactly one shape key -- `class`, `factory`, `alias`
 * or `value`. This class counts which shape keys are present, rejects the
 * ambiguous and empty cases, and dispatches to the matching shape parser
 * ({@see InstanceShape}, {@see FactoryShape}, {@see AliasShape} or
 * {@see ValueShape}). It is the second half of
 * {@see ServiceDefinition::fromArray()}, kept its own unit so the grammar
 * concerns stay archeology-sized.
 */
final readonly class DefinitionShapes
{
    private const array SHAPES = ['class', 'factory', 'alias', 'value'];

    /**
     * @param array<array-key, mixed> $entry
     */
    public static function fromEntry(string $id, array $entry): ServiceDefinition
    {
        $present = [];
        foreach (self::SHAPES as $shape) {
            if (!\array_key_exists($shape, $entry)) {
                continue;
            }

            $present[] = $shape;
        }

        if ($present === []) {
            throw new DefinitionException(
                "Service '{$id}' carries none of the shape keys (exactly one of "
                . self::spelledShapes() . ' is required).',
            );
        }

        if (\count($present) > 1) {
            throw new DefinitionException(
                "Service '{$id}' carries the shape keys " . self::quoted($present)
                . ' (exactly one of ' . self::spelledShapes() . ' is allowed).',
            );
        }

        return match ($present[0]) {
            'class' => InstanceShape::fromEntry($id, $entry),
            'factory' => FactoryShape::fromEntry($id, $entry),
            'alias' => AliasShape::fromEntry($id, $entry),
            default => ValueShape::fromEntry($id, $entry),
        };
    }

    /** @param list<string> $keys */
    private static function quoted(array $keys): string
    {
        return "'" . implode("', '", $keys) . "'";
    }

    private static function spelledShapes(): string
    {
        return self::quoted(self::SHAPES);
    }
}
