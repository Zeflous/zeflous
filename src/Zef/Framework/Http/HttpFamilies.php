<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * The `http` map's family tree: the census of legitimate families,
 * shared by every family's factory so an unknown sibling halts the
 * boot.
 *
 * The security map's own census ({@see SecurityFamilies}) deferred
 * this seam on purpose until a second family existed to know the
 * whole set, and the `http` map reaches that moment here: the error
 * family landed first ({@see ErrorMappingFactory} over
 * `http.error_handling`), and the compression family is the second
 * (`http.compression`). The tree is declared with both, and the
 * compression family's factory reads its subtree through
 * {@see self::subtree()}, which rejects an unknown child with the
 * known list spelled out: an `http.compresssion` typo must never
 * fall through to the default posture unnoticed, exactly as an
 * unknown knob inside a family map never does.
 *
 * The census is family-additive: the error family's own factory
 * still reads its knobs through the repository's dotted addresses
 * (its strict reading predates the seam), and its migration to the
 * family seam is a one-line follow-up its own lane owns -- the
 * census already protects the map for every boot that resolves a
 * family through it.
 */
final class HttpFamilies
{
    /**
     * The legitimate children of the `http` map, in landing order.
     */
    private const array FAMILIES = ['error_handling', 'compression'];

    /**
     * Reads one family's subtree out of the merged `http` map.
     *
     * @param mixed  $http   the `http` map as the frozen repository carries
     *                       it: a family map, or null when the whole key is
     *                       absent
     * @param string $family the family whose subtree is read
     *
     * @throws HttpException when the map carries an unknown family, or is
     *                       neither a map nor null
     */
    public static function subtree(mixed $http, string $family): mixed
    {
        if ($http === null) {
            return null;
        }

        if (!\is_array($http)) {
            throw new HttpException(
                'The http configuration must be a map, ' . get_debug_type($http) . ' given.',
            );
        }

        foreach (array_keys($http) as $child) {
            if (!\is_string($child) || !\in_array($child, self::FAMILIES, true)) {
                throw new HttpException(
                    "The http configuration carries the unknown family '"
                    . (\is_string($child) ? $child : get_debug_type($child))
                    . "' (known families: " . implode(', ', self::FAMILIES) . ').',
                );
            }
        }

        return $http[$family] ?? null;
    }
}
