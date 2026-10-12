<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * The reading policy for SAPI server entries.
 *
 * A server array is untyped process state: every entry the HTTP layer
 * consults arrives as `mixed`, and the reference implementation answered
 * with `(string)` casts -- which coerce an array to `Array` (plus a
 * warning) and a bool to `1`. This collaborator states the layer's single
 * policy instead: a non-string entry is corruption, and corruption is
 * treated exactly like absence -- the caller's default applies. Every
 * default is already a tested, documented behaviour (a missing
 * `REQUEST_METHOD` answers `GET`, a missing host answers the localhost
 * authority, a missing `SERVER_PROTOCOL` answers `HTTP/1.1`), so the
 * corrupted-entry outcome is deterministic without inventing new failure
 * modes. This is a deliberate, documented deviation from the reference
 * implementation's silent coercions.
 */
final class ServerValues
{
    /**
     * Answers the string value a server entry carries.
     *
     * @param array<string, mixed> $server  the SAPI server entries
     * @param non-empty-string     $key     the entry to read
     * @param string               $default the value absent and corrupted
     *                                      entries answer
     *
     * @return string the entry's string value, or the default
     */
    public static function text(array $server, string $key, string $default): string
    {
        return self::orDefault($server[$key] ?? null, $default);
    }

    /**
     * Answers the string a raw entry value carries, or the default when
     * the value is corrupt (anything not a string).
     *
     * @param mixed $value the raw entry value, null when the key is absent
     */
    private static function orDefault(mixed $value, string $default): string
    {
        return \is_string($value) ? $value : $default;
    }
}
