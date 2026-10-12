<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * Extracts the HTTP headers a SAPI exposes as flat server entries.
 *
 * The CGI/1.1 convention buries request headers in the server array as
 * `HTTP_<FIELD-NAME>` entries (dashes flattened to underscores); the two
 * body-describing fields `CONTENT_TYPE` and `CONTENT_LENGTH` are the only
 * named exceptions that are not prefixed. This collaborator is the single
 * translation point for that convention: every non-string server value and
 * every non-header entry is skipped -- the SAPI array legitimately carries
 * non-header metadata (`SCRIPT_NAME`, `REQUEST_TIME`, ...) that must never
 * reach the header store. The names are answered lowercased with dash
 * separators, the deterministic representation the factory forwards to
 * {@see ServerRequest}'s validating constructor.
 */
final class ServerHeaders
{
    /**
     * The CGI/1.1 named exceptions: the body-describing fields that are
     * server entries without the `HTTP_` prefix, with their header names.
     */
    private const array NAMED_HEADERS = [
        'CONTENT_TYPE' => 'content-type',
        'CONTENT_LENGTH' => 'content-length',
    ];

    /**
     * Extracts the header map from a server array.
     *
     * @param array<string, mixed> $server the SAPI server entries, typically $_SERVER
     *
     * @return array<string, list<string>> lowercased header names => single-value lists
     */
    public static function extract(array $server): array
    {
        $headers = [];

        foreach ($server as $key => $value) {
            $name = self::headerName($key);

            if ($name === null || !\is_string($value)) {
                continue;
            }

            $headers[$name] = [$value];
        }

        return $headers;
    }

    /**
     * Answers the header field name a server entry carries, when it is one.
     *
     * The `HTTP_` prefix is stripped through an anchored pattern capture --
     * only a real prefix matches, an entry such as `XHTTP_ACCEPT` carries
     * no header -- and the name is folded through the full Unicode case
     * map with underscores flattened to the field-name dashes. The
     * body-describing fields carry their names from the named-exception
     * table; every other entry is not a header carrier.
     *
     * @param string $key the server entry key
     *
     * @return null|string the lowercased field name, or null when the entry
     *                     is not a header carrier
     */
    private static function headerName(string $key): ?string
    {
        if (preg_match('~^HTTP_(.+)$~', $key, $parts) === 1) {
            return mb_strtolower(str_replace('_', '-', $parts[1]));
        }

        return self::NAMED_HEADERS[$key] ?? null;
    }
}
