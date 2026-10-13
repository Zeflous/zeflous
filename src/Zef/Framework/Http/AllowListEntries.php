<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * The CORS allow-list's configuration union, narrowed to the entries a
 * policy can judge: one origin, a list of them, or null when CORS is off
 * -- always answered as the trimmed, non-empty string entries.
 *
 * This is the construction walk's first half, split from
 * {@see AllowedOrigins} so each stays a small, single-purpose object: an
 * entry that is not a string fails closed loudly (the layer's
 * construction-time narrowing idiom, legacy issues #278 / #281 in the
 * trusted-host lineage -- the reference skipped such entries silently),
 * and an entry that trims to nothing is no entry at all. The wildcard
 * scan and the origin normalisation that follow live in
 * {@see AllowedOrigins}.
 */
final class AllowListEntries
{
    /**
     * @param null|list<mixed>|string $allowOrigin the allow-list as the caller supplied it
     *
     * @return list<string> the trimmed, non-empty string entries
     *
     * @throws HttpException when an array entry is not a string
     */
    public static function narrowed(array|string|null $allowOrigin): array
    {
        $origins = self::asList($allowOrigin);
        $entries = [];

        foreach ($origins as $origin) {
            if (!\is_string($origin)) {
                throw new HttpException(
                    'CORS allow-list entries must be strings, got ' . get_debug_type($origin) . '.',
                );
            }

            $trimmed = mb_trim($origin);
            if ($trimmed === '') {
                continue;
            }

            $entries[] = $trimmed;
        }

        return $entries;
    }

    /**
     * @param null|list<mixed>|string $allowOrigin the allow-list as the caller supplied it
     *
     * @return list<mixed> the entries as a list, whatever union shape they came in
     */
    private static function asList(array|string|null $allowOrigin): array
    {
        return match (true) {
            $allowOrigin === null => [],
            \is_array($allowOrigin) => $allowOrigin,
            default => [$allowOrigin],
        };
    }
}
