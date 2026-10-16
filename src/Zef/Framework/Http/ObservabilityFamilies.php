<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * The observability map's family census: the legitimate children of
 * the `observability` key, and the subtree each family's policy reads
 * -- the observability campaign's own mirror of the {@see
 * SecurityFamilies} reader the security campaign landed per family.
 *
 * The campaign's shape: the `timing` family (the response-time
 * boundary, {@see TimingPolicy}) and the `tracing` family (the
 * tracing core's config face, {@see TracingPolicy}) are live, and
 * each later family (the correlation lane, the OTLP export lane,
 * metrics) grows this list as it lands -- an unknown child halts
 * the boot with the known families spelled out, exactly as the
 * security map's own census does, so a `timming` typo in a host's
 * override file is a named refusal rather than a silently absent
 * family.
 */
final class ObservabilityFamilies
{
    /**
     * The legitimate children of the `observability` map, in landing
     * order.
     */
    private const array FAMILIES = ['timing', 'tracing'];

    /**
     * Reads one family's subtree out of the merged `observability` map.
     *
     * @param mixed  $observability the `observability` map as the frozen
     *                              repository carries it: a family map,
     *                              or null when the whole key is absent
     * @param string $family        the family whose subtree is read
     *
     * @throws HttpException when the map carries an unknown family, or is
     *                       neither a map nor null
     */
    public static function subtree(mixed $observability, string $family): mixed
    {
        if ($observability === null) {
            return null;
        }

        if (!\is_array($observability)) {
            throw new HttpException(
                'The observability configuration must be a map, '
                . get_debug_type($observability) . ' given.',
            );
        }

        foreach (array_keys($observability) as $child) {
            if (!\is_string($child) || !\in_array($child, self::FAMILIES, true)) {
                throw new HttpException(
                    "The observability configuration carries the unknown family '"
                    . (\is_string($child) ? $child : get_debug_type($child))
                    . "' (known families: " . implode(', ', self::FAMILIES) . ').',
                );
            }
        }

        return $observability[$family] ?? null;
    }
}
