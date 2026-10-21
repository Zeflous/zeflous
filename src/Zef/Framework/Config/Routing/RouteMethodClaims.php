<?php

declare(strict_types=1);

namespace Zef\Framework\Config\Routing;

use Zef\Framework\Config\ConfigException;

/**
 * The method claims of a route table: which registration first claimed
 * each method on each canonical host + canonical path pattern.
 *
 * Several routes may share one parameter-blind pattern when their
 * method sets are disjoint (the REST convention -- `GET /photos` and
 * `POST /photos` are different resources), but a method claimed twice
 * on the same pattern would own the same tree position with the same
 * method: an ambiguity, not a merge, refused here with the method and
 * the first claimant in the message. The parameterised-host side of the
 * same ambiguity question lives in {@see RouteHostClaims}.
 *
 * @internal
 */
final class RouteMethodClaims
{
    /**
     * The claims: canonical pattern => method => the pattern that first
     * claimed it.
     *
     * @var array<string, array<string, string>>
     */
    private array $claims = [];

    private RouteHostClaims $hostClaims;

    public function __construct()
    {
        $this->hostClaims = new RouteHostClaims();
    }

    /**
     * Claims the route's methods on its canonical pattern.
     */
    public function claim(CompiledRoute $compiledRoute): void
    {
        $pattern = $compiledRoute->path()->pattern();
        $host = $compiledRoute->host();
        $path = $compiledRoute->path()->canonical();
        $version = $compiledRoute->version();
        $canonical = ($host ?? '*') . '|' . ($version ?? '*') . '|' . $path;
        $hostClaimScope = $path . '|' . ($version ?? '*');

        foreach ($compiledRoute->methods() as $method) {
            if ($host !== null) {
                $this->hostClaims->claim($hostClaimScope, $method, $host, $pattern);
            }

            $registeredBy = $this->claims[$canonical][$method] ?? null;

            if ($registeredBy !== null) {
                throw new ConfigException(
                    \sprintf(
                        'The route pattern "%s" duplicates the method "%s" of the registered pattern "%s".',
                        $pattern,
                        $method,
                        $registeredBy,
                    ),
                );
            }

            $this->claims[$canonical][$method] = $pattern;
        }
    }
}
