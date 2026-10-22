<?php

declare(strict_types=1);

namespace Zef\Framework\Config\Routing;

use Zef\Framework\Config\ConfigException;

/**
 * The untrusted request-host face of the host grammar: canonicalises what a
 * client sent, or answers null -- a malformed host is a runtime miss, never
 * a config error, and a parameter or wildcard spelling is a pattern, not a
 * hostname.
 *
 * @internal
 */
final class RouteHostRequest
{
    /**
     * Canonicalises untrusted request input without turning it into a config error.
     */
    public static function canonicalise(?string $host): ?string
    {
        if ($host === null || str_contains($host, '{') || str_contains($host, '*')) {
            return null;
        }

        try {
            return RouteHostGrammar::canonical($host);
        } catch (ConfigException) {
            return null;
        }
    }
}
