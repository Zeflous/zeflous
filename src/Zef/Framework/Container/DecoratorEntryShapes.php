<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

/**
 * The decorator entry grammar's shape disambiguation (the
 * {@see DefinitionShapes} posture, narrowed to the entry grammar's two
 * builder shapes): an entry carries exactly one shape key -- 'class' or
 * 'factory' -- and this unit counts which are present, rejects the
 * ambiguous and empty cases, and answers the spelling the entry's own
 * parse dispatches on.
 */
final readonly class DecoratorEntryShapes
{
    private const array SHAPE_KEYS = ['class', 'factory'];

    /**
     * Answers the one shape key the entry carries.
     *
     * @param array<array-key, mixed> $entry
     *
     * @return 'class'|'factory'
     */
    public static function shapeOf(string $target, array $entry): string
    {
        $present = array_values(array_intersect(self::SHAPE_KEYS, array_keys($entry)));
        if ($present === []) {
            throw DecoratorRefusals::missingShapeKey($target);
        }

        if (\count($present) > 1) {
            throw DecoratorRefusals::doubledShapeKey($target, $present);
        }

        return $present[0];
    }
}
