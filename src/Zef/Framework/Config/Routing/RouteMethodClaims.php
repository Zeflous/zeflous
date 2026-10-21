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
 * the first claimant in the message.
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

    /**
     * @var array<string, array<string, list<array{host: non-empty-string, pattern: non-empty-string}>>>
     */
    private array $parameterHostClaims = [];

    /**
     * Claims the route's methods on its canonical pattern.
     */
    public function claim(CompiledRoute $compiledRoute): void
    {
        $pattern = $compiledRoute->path()->pattern();
        $host = $compiledRoute->host();
        $path = $compiledRoute->path()->canonical();
        $canonical = ($host ?? '*') . '|' . $path;

        foreach ($compiledRoute->methods() as $method) {
            if ($host !== null) {
                foreach ($this->parameterHostClaims[$path][$method] ?? [] as $claim) {
                    if (
                        $host !== $claim['host']
                        && RouteHost::overlaps($host, $claim['host'])
                        && RouteHost::specificity($host) === RouteHost::specificity($claim['host'])
                    ) {
                        throw new ConfigException(
                            sprintf(
                                'The route host pattern "%s" overlaps "%s" at equal specificity for method "%s" and path "%s".',
                                $host,
                                $claim['host'],
                                $method,
                                $pattern,
                            ),
                        );
                    }
                }

                $this->parameterHostClaims[$path][$method][] = ['host' => $host, 'pattern' => $pattern];
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
