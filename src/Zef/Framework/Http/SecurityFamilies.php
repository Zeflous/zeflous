<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * The `security` map's family tree: the census of legitimate families,
 * shared by every family's factory so an unknown sibling halts the boot.
 *
 * The security-headers PR deferred this seam on purpose ("the strictness
 * that knows the whole family set belongs to the PR that introduces a
 * second family to know it", KDD-SC3): with one family, an unknown child
 * of `security` could only be a typo of the one knob map that existed.
 * The rate-limit family is the second, so the tree is declared here --
 * `headers`, `rate_limit` -- and each family's factory reads its subtree
 * through {@see self::subtree()}, which rejects an unknown child with
 * the known list spelled out: a `security.rate_limitt` typo must never
 * fall through to the default posture unnoticed, exactly as an unknown
 * knob inside a family map never does.
 *
 * The census is family-additive: the CSRF and authentication families
 * append their names here when they land, and the rejection message
 * grows with the tree.
 */
final class SecurityFamilies
{
    /**
     * The legitimate children of the `security` map, in landing order.
     */
    private const array FAMILIES = ['headers', 'rate_limit'];

    /**
     * Reads one family's subtree out of the merged `security` map.
     *
     * @param mixed  $security the `security` map as the frozen repository
     *                         carries it: a family map, or null when the
     *                         whole key is absent
     * @param string $family   the family whose subtree is read
     *
     * @throws HttpException when the map carries an unknown family, or is
     *                       neither a map nor null
     */
    public static function subtree(mixed $security, string $family): mixed
    {
        if ($security === null) {
            return null;
        }

        if (!\is_array($security)) {
            throw new HttpException(
                'The security configuration must be a map, ' . get_debug_type($security) . ' given.',
            );
        }

        foreach (array_keys($security) as $child) {
            if (!\is_string($child) || !\in_array($child, self::FAMILIES, true)) {
                throw new HttpException(
                    "The security configuration carries the unknown family '"
                    . (\is_string($child) ? $child : get_debug_type($child))
                    . "' (known families: " . implode(', ', self::FAMILIES) . ').',
                );
            }
        }

        return $security[$family] ?? null;
    }
}
