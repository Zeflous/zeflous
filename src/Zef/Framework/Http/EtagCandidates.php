<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * The ETag candidate list a `If-None-Match`-shaped header carries,
 * split on the commas that separate list members
 * ({@see IfNoneMatch} keeps the comparison the list exists for).
 *
 * A comma inside a quoted opaque validator (§8.8.3 `etagc` admits
 * 0x2C, and the grammar admits no DQUOTE inside the quotes) belongs
 * to the validator, so every DQUOTE toggles the quoting state and
 * only an unquoted comma starts the next member -- one deliberate
 * deviation from the reference's bare `explode`, whose fragments
 * could compare equal to a validator the client never stored.
 */
final class EtagCandidates
{
    /**
     * The comma-separated candidates of one list-shaped header value.
     *
     * @return list<string>
     */
    public static function split(string $headerValue): array
    {
        $candidates = [];
        $current = '';
        $quoted = false;

        // Byte offsets, not character counts: a header may carry raw
        // obs-text bytes that are not valid UTF-8, and a character-
        // counted bound would stop the walk before the final byte.
        $offset = 0;
        while (isset($headerValue[$offset])) {
            $byte = $headerValue[$offset];

            if ($byte === '"') {
                $quoted = !$quoted;
                $current .= $byte;
                ++$offset;

                continue;
            }

            if ($byte === ',' && !$quoted) {
                $candidates[] = $current;
                $current = '';
                ++$offset;

                continue;
            }

            $current .= $byte;
            ++$offset;
        }

        $candidates[] = $current;

        return $candidates;
    }
}
