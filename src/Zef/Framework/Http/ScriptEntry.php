<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * Identifies the script a SAPI reports as the request's entry point.
 *
 * `SCRIPT_NAME` names the script through which the request entered and
 * `SCRIPT_FILENAME` the file actually executing; the request entered a
 * real front controller when the two agree on the basename -- the
 * running file IS the router entry point, not an asset beside it. The
 * script path is `SCRIPT_NAME`'s parseable path form. The reference
 * implementation's comparison, move-verbatim.
 */
final class ScriptEntry
{
    /**
     * Answers whether the SAPI script entry is a front controller.
     *
     * @param array<string, mixed> $server the SAPI server entries
     */
    public static function isFrontController(array $server): bool
    {
        $scriptPath = self::path($server);
        $filenameBase = basename(ServerValues::text($server, 'SCRIPT_FILENAME', ''));

        return $scriptPath !== '' && $filenameBase !== '' && basename($scriptPath) === $filenameBase;
    }

    /**
     * Answers the script path a front-controller comparison uses.
     *
     * @param array<string, mixed> $server the SAPI server entries
     *
     * @return string the parsed `SCRIPT_NAME` path, empty when unparseable
     */
    public static function path(array $server): string
    {
        $parsedPath = parse_url(ServerValues::text($server, 'SCRIPT_NAME', ''), \PHP_URL_PATH);

        return \is_string($parsedPath) ? $parsedPath : '';
    }
}
