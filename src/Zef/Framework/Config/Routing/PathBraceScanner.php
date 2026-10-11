<?php

declare(strict_types=1);

namespace Zef\Framework\Config\Routing;

use Zef\Framework\Config\ConfigException;

/**
 * The brace pre-scan over a whole route path.
 *
 * The path is split on `{`, which makes every part after the first begin
 * inside a brace; splitting such a part on `}` (limit 2) then yields the
 * parameter content before the closing brace and the text after it. The
 * four failure shapes fall out directly:
 *
 * - a `}` before any `{` -- or after a closed parameter -- is an
 *   unmatched closing brace;
 * - a part with no `}` at all means a second `{` opened inside (a nested
 *   parameter brace), unless it is the last part, in which case the brace
 *   never closes;
 * - a `/` inside the parameter content is a brace that spans more than
 *   one segment.
 *
 * This is the only pass that can see a brace spanning a slash or braces
 * crossing segment boundaries, because the segment split happens after it;
 * afterwards every segment's braces are balanced, so the per-segment pass
 * only has to judge *where* inside the segment the braces sit.
 *
 * @internal
 */
final class PathBraceScanner
{
    /**
     * Asserts that the path's braces are balanced, disjoint and confined to
     * single segments; throws a {@see ConfigException} naming the path and
     * the failure otherwise.
     */
    public static function assertWellFormed(string $path): void
    {
        $parts = explode('{', $path);

        if (str_contains($parts[0], '}')) {
            throw new ConfigException(
                \sprintf('The route path "%s" has an unmatched closing brace.', $path),
            );
        }

        $lastIndex = \count($parts) - 1;

        for ($index = 1; $index <= $lastIndex; ++$index) {
            $halves = explode('}', $parts[$index], 2);

            if (!isset($halves[1])) {
                throw new ConfigException(
                    \sprintf(
                        'The route path "%s" has %s parameter brace.',
                        $path,
                        $index === $lastIndex ? 'an unclosed' : 'a nested',
                    ),
                );
            }

            if (str_contains($halves[0], '/')) {
                throw new ConfigException(
                    \sprintf('The route path "%s" has a parameter brace that spans more than one segment.', $path),
                );
            }

            if (str_contains($halves[1], '}')) {
                throw new ConfigException(
                    \sprintf('The route path "%s" has an unmatched closing brace.', $path),
                );
            }
        }
    }
}
