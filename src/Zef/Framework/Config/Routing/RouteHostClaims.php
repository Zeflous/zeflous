<?php

declare(strict_types=1);

namespace Zef\Framework\Config\Routing;

use Zef\Framework\Config\ConfigException;

/**
 * The parameterised-host claims of a route table: which host patterns
 * were declared per path + version scope and method, so a later
 * equal-specificity pattern that overlaps an earlier one
 * ({@see RouteHostAmbiguities}) is refused as an ambiguity rather than
 * silently resolved by registration order.
 *
 * @internal
 */
final class RouteHostClaims
{
    /**
     * The claims: scope (path + version) => method => the declared host
     * patterns, in declaration order.
     *
     * @var array<string, array<string, list<array{host: string, pattern: string}>>>
     */
    private array $claims = [];

    /**
     * Claims a host pattern on one scope and method, refusing an
     * equal-specificity overlap with an earlier claim.
     */
    public function claim(string $scope, string $method, string $host, string $pattern): void
    {
        $conflict = RouteHostAmbiguities::conflicting($host, $this->claims[$scope][$method] ?? []);

        if ($conflict !== null) {
            throw new ConfigException(
                \sprintf(
                    'The route host pattern "%s" overlaps "%s" at equal specificity for method "%s" and path "%s".',
                    $host,
                    $conflict['host'],
                    $method,
                    $pattern,
                ),
            );
        }

        $this->claims[$scope][$method][] = ['host' => $host, 'pattern' => $pattern];
    }
}
