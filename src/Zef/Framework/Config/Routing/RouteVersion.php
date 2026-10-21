<?php

declare(strict_types=1);

namespace Zef\Framework\Config\Routing;

use Zef\Framework\Config\ConfigException;

/**
 * Canonicalises declared API versions and untrusted request header values.
 *
 * @internal
 */
final class RouteVersion
{
    /**
     * @return non-empty-string
     */
    public static function canonical(string $version): string
    {
        if (!preg_match('/\A[a-zA-Z0-9][a-zA-Z0-9._-]{0,31}\z/D', $version)) {
            throw new ConfigException(sprintf(
                'The route version "%s" must start with an alphanumeric character and contain at most 32 letters, digits, dots, underscores or hyphens.',
                $version,
            ));
        }

        return strtolower($version);
    }

    /**
     * Invalid request values are runtime misses, not route-table errors.
     */
    public static function request(?string $version): ?string
    {
        if ($version === null || trim($version) === '') {
            return null;
        }

        try {
            return self::canonical(trim($version));
        } catch (ConfigException) {
            return null;
        }
    }
}
