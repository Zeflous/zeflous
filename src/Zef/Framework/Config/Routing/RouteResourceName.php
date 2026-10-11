<?php

declare(strict_types=1);

namespace Zef\Framework\Config\Routing;

use Zef\Framework\Config\ConfigException;

/**
 * A resource's name base: the static segments of its path, joined with
 * dots.
 *
 * The parameters a resource path declares (`/users/{user}/posts`) shape
 * its routes but not its name -- only the static segments name the
 * resource. A path with no static segment at all (the root, or nothing
 * but parameters) cannot name a resource and fails closed.
 *
 * @internal
 */
final class RouteResourceName
{
    /**
     * The resource path's name base, e.g. `/admin/users` => `admin.users`.
     */
    public static function base(string $path): string
    {
        $staticSegments = [];

        foreach (RoutePath::parse($path)->segments() as $pathSegment) {
            if ($pathSegment->isParameter()) {
                continue;
            }

            $staticSegments[] = $pathSegment->value();
        }

        if ($staticSegments === []) {
            throw new ConfigException(
                \sprintf(
                    'The resource path "%s" must contain at least one static segment to name the resource.',
                    $path,
                ),
            );
        }

        return implode('.', $staticSegments);
    }
}
