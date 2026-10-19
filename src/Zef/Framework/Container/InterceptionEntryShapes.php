<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

/**
 * The interception entry grammar's shape disambiguation (the
 * {@see DecoratorEntryShapes} posture, shared by both knobs that carry
 * entries): an entry carries exactly one shape key -- 'class' or
 * 'factory' -- and this unit counts which are present, rejects the
 * ambiguous and empty cases, and answers the spelling the entry's own
 * parse dispatches on.
 */
final readonly class InterceptionEntryShapes
{
    private const array SHAPE_KEYS = ['class', 'factory'];

    /**
     * Answers the one shape key the entry carries.
     *
     * @param array<array-key, mixed> $entry
     *
     * @return 'class'|'factory'
     */
    public static function shapeOf(string $label, array $entry): string
    {
        $present = array_values(array_intersect(self::SHAPE_KEYS, array_keys($entry)));
        if ($present === []) {
            throw InterceptionRefusals::missingShapeKey($label);
        }

        if (\count($present) > 1) {
            throw InterceptionRefusals::doubledShapeKey($label, $present);
        }

        return $present[0];
    }
}
