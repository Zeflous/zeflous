<?php

declare(strict_types=1);

namespace Zef\Framework\Middleware;

/**
 * Weak entity-tag comparison for If-None-Match: a condition list matches
 * a response's ETag when it carries `*` or any tag whose opaque value
 * (the tag without the weakness prefix) equals the current one's.
 *
 * @internal
 */
final class EntityTagMatch
{
    public static function matches(string $condition, string $currentTag): bool
    {
        $currentOpaqueTag = preg_replace('/\AW\//i', '', $currentTag);
        preg_match_all('/(?:W\/)?"[^"]*"|\*/i', $condition, $matches);

        foreach ($matches[0] as $candidate) {
            if ($candidate === '*') {
                return true;
            }

            $candidateOpaqueTag = preg_replace('/\AW\//i', '', $candidate);
            if ($candidateOpaqueTag === $currentOpaqueTag) {
                return true;
            }
        }

        return false;
    }
}
