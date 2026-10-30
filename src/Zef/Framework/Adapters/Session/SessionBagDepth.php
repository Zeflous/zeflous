<?php

declare(strict_types=1);

namespace Zef\Framework\Adapters\Session;

use InvalidArgumentException;

/**
 * The bag's own nesting-depth question: the walk that measures how
 * deep a session bag nests and refuses the record the engine's own
 * JSON grammar could not read back -- a written record a load can
 * never answer is a save that lied, so the depth ceiling is
 * honoured at the write boundary, not discovered as an unreadable
 * record at the load.
 */
final class SessionBagDepth
{
    /**
     * Refuses a bag nested deeper than the asked ceiling.
     *
     * @param array<string, mixed> $data    the bag being written
     * @param int                  $ceiling the depth the reading grammar reads back
     *
     * @throws InvalidArgumentException when the bag nests past the ceiling
     */
    public function refuseDeeperThan(array $data, int $ceiling): void
    {
        $depth = self::depthOf($data);

        if ($depth > $ceiling) {
            throw new InvalidArgumentException(
                'The session record cannot carry a bag nested deeper than '
                . $ceiling . ' levels, ' . $depth . ' given.',
            );
        }
    }

    /**
     * The bag's own nesting depth: a flat map is one level, a
     * nested array one deeper than its deepest item.
     *
     * @param array<array-key, mixed> $value the bag being measured
     */
    private static function depthOf(array $value): int
    {
        $depth = 0;

        /** @var mixed $item one nested value, measured by the walk below */
        foreach ($value as $item) {
            if (!\is_array($item)) {
                continue;
            }

            $depth = max($depth, self::depthOf($item));
        }

        return $depth + 1;
    }
}
