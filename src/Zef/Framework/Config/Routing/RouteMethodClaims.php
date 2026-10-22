<?php

declare(strict_types=1);

namespace Zef\Framework\Config\Routing;

use Zef\Framework\Config\ConfigException;

/**
 * The method claims of a route table: which registration first claimed
 * each method on each canonical host + version + canonical path pattern.
 *
 * Several routes may share one parameter-blind pattern when their
 * method sets are disjoint (the REST convention -- `GET /photos` and
 * `POST /photos` are different resources), but a method claimed twice
 * on the same host + version + pattern would own the same tree position
 * with the same method: an ambiguity, not a merge, refused here with the
 * method and the first claimant in the message. The parameterised-host
 * side of the same ambiguity question lives in {@see RouteHostClaims}.
 *
 * The claims map is a nested tuple (host => version => path => method),
 * not one concatenated key: the claim identity is the tuple itself, so
 * no separator grammar can alias two different tuples onto one string.
 * Host-agnostic routes claim under the empty string, which no canonical
 * host can equal (the grammar refuses empty hosts), and unversioned
 * routes under "*", which {@see RouteVersion} refuses as a version.
 *
 * @internal
 */
final class RouteMethodClaims
{
    /**
     * The claims: host => version => canonical path => method => the
     * pattern that first claimed it.
     *
     * @var array<string, array<string, array<string, array<string, string>>>>
     */
    private array $claims = [];

    private readonly RouteHostClaims $routeHostClaims;

    public function __construct()
    {
        $this->routeHostClaims = new RouteHostClaims();
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
        $hostClaimScope = $path . '|' . ($version ?? '*');
        $hostKey = $host ?? '';
        $versionKey = $version ?? '*';

        foreach ($compiledRoute->methods() as $method) {
            if ($host !== null) {
                $this->routeHostClaims->claim($hostClaimScope, $method, $host, $pattern);
            }

            $registeredBy = $this->claims[$hostKey][$versionKey][$path][$method] ?? null;

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

            $this->claims[$hostKey][$versionKey][$path][$method] = $pattern;
        }
    }
}
