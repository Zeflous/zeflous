<?php

declare(strict_types=1);

namespace Zef\Framework\Config\Routing;

/**
 * Detects the ambiguity among declared host claims: a host pattern that
 * overlaps an earlier claim's pattern at equal specificity, so neither
 * outranks the other and matching could not choose deterministically.
 *
 * @internal
 */
final class RouteHostAmbiguities
{
    /**
     * The claim the host pattern conflicts with, or null when every
     * earlier claim is disjoint or outranked.
     *
     * @param list<array{host: string, pattern: string}> $claims
     *
     * @return null|array{host: string, pattern: string}
     */
    public static function conflicting(string $host, array $claims): ?array
    {
        foreach ($claims as $claim) {
            if (
                $host !== $claim['host']
                && RouteHostPrecedence::overlaps($host, $claim['host'])
                && RouteHostPrecedence::specificity($host) === RouteHostPrecedence::specificity($claim['host'])
            ) {
                return $claim;
            }
        }

        return null;
    }
}
