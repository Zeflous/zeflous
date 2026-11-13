<?php

declare(strict_types=1);

namespace Zef\Test\Support\Routing;

/**
 * A route `meta` value object that CANNOT cross the compiled route tree
 * cache: `var_export()` still renders it as a `::__set_state()` call,
 * but the class never answers it, so the artifact's `require` raises an
 * `Error` -- the exact shape of issue #768's `ProbeStamp` (a schema-legal
 * route table whose cached artifact dumps fine and then fails the next
 * boot with an uncaught `Error` instead of the documented
 * `ConfigException`).
 */
final readonly class RouteMetaOpaque
{
    /**
     * @param non-empty-string $label
     */
    public function __construct(
        public string $label,
    ) {
    }
}
