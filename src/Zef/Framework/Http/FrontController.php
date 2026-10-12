<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * Answers the application-relative routing path for a request.
 *
 * When the SAPI reports the script through which the request entered as
 * a real front controller ({@see ScriptEntry}: the file being executed
 * IS the router entry point, not an asset beside it), the routing path
 * is the application-relative remainder: `/index.php/users` routes
 * `/users`, and a bare `/index.php` routes `/`. Any other script shape
 * leaves the path untouched. The reference implementation's derivation,
 * move-verbatim.
 */
final class FrontController
{
    /**
     * Strips the front-controller script prefix from a request path.
     *
     * @param array<string, mixed> $server the SAPI server entries
     * @param non-empty-string     $path   the request path
     *
     * @return non-empty-string the application-relative path
     */
    public static function stripScriptPrefix(array $server, string $path): string
    {
        if (!ScriptEntry::isFrontController($server)) {
            return $path;
        }

        $scriptPath = ScriptEntry::path($server);

        if ($path === $scriptPath) {
            return '/';
        }

        if (str_starts_with($path, $scriptPath . '/')) {
            $path = mb_substr($path, mb_strlen($scriptPath));
        }

        return $path === '' ? '/' : $path;
    }
}
