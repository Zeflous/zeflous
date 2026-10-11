<?php

declare(strict_types=1);

namespace Zef\Framework\Config\Routing;

/**
 * The number of leading characters two strings share.
 *
 * The pair is peeled one character at a time, so a mismatch anywhere
 * stops the count exactly there, and multi-byte text is peeled by whole
 * characters (`mb_*`).
 *
 * @internal
 */
final class SharedPrefixLength
{
    /**
     * The length of the shared prefix of two strings, in characters.
     */
    public static function of(string $first, string $second): int
    {
        $head = mb_substr($first, 0, 1);
        $peel = mb_strlen($head);

        if ($peel === 0) {
            return 0;
        }

        if ($head !== mb_substr($second, 0, 1)) {
            return 0;
        }

        return 1 + self::of(mb_substr($first, $peel), mb_substr($second, $peel));
    }
}
