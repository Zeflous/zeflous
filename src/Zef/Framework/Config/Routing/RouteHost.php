<?php

declare(strict_types=1);

namespace Zef\Framework\Config\Routing;

use Zef\Framework\Config\ConfigException;

/**
 * Canonicalises an exact DNS host for route registration and request matching.
 *
 * Wildcard hosts are deliberately not accepted by this first host-aware slice:
 * wildcard precedence and host-parameter capture need a separate grammar and
 * are tracked as a follow-up roadmap item.
 *
 * @internal
 */
final class RouteHost
{
    /**
     * Validates and lower-cases an exact hostname.
     *
     * @return non-empty-string
     */
    public static function canonical(string $host): string
    {
        if ($host === '' || strlen($host) > 253 || !preg_match(
            '/\A[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?(?:\.[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?)*\z/D',
            $host,
        )) {
            throw new ConfigException(
                sprintf('The route host "%s" must be an exact DNS hostname without a port or wildcard.', $host),
            );
        }

        return strtolower($host);
    }

    /**
     * Canonicalises untrusted request input without turning it into a config error.
     */
    public static function request(?string $host): ?string
    {
        if ($host === null) {
            return null;
        }

        try {
            return self::canonical($host);
        } catch (ConfigException) {
            return null;
        }
    }
}
