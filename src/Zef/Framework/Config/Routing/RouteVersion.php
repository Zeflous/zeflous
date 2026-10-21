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
        $canonical = mb_strtolower($version);
        if ($canonical === '' || preg_match('/\A[a-zA-Z0-9][a-zA-Z0-9._-]{0,31}\z/D', $canonical) !== 1) {
            throw new ConfigException(\sprintf(
                'The route version "%s" must start with an alphanumeric character and contain '
                . 'at most 32 letters, digits, dots, underscores or hyphens.',
                $version,
            ));
        }

        return $canonical;
    }

    /**
     * Invalid request values are runtime misses, not route-table errors.
     */
    public static function request(?string $version): ?string
    {
        if ($version === null || mb_trim($version) === '') {
            return null;
        }

        try {
            return self::canonical(mb_trim($version));
        } catch (ConfigException) {
            return null;
        }
    }
}
