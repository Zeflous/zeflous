<?php

declare(strict_types=1);

namespace Zef\Framework\Config\Routing;

use Zef\Framework\Config\ConfigException;

/**
 * The registration bookkeeping of a route table: a pattern's methods are
 * claimed once ({@see RouteMethodClaims}), and a route name is claimed
 * once.
 *
 * Registration is where the table is judged fail-closed (see `ROADMAP.md`,
 * "Router" -- duplicate detection happens "saat registrasi"): a method
 * claimed twice on one pattern would make the answer ambiguous, and a
 * second route with the same name would make reverse routing ambiguous
 * -- both are refused before anything is built.
 *
 * @internal
 */
final class RouteTableRegistry
{
    private readonly RouteMethodClaims $routeMethodClaims;

    /** @var array<string, CompiledRoute> route name => the named route */
    private array $namedRoutes = [];

    public function __construct()
    {
        $this->routeMethodClaims = new RouteMethodClaims();
    }

    /**
     * Registers one route, rejecting method and name duplicates.
     */
    public function register(CompiledRoute $compiledRoute): void
    {
        $this->routeMethodClaims->claim($compiledRoute);

        $name = $compiledRoute->name();

        if ($name === null) {
            return;
        }

        if (isset($this->namedRoutes[$name])) {
            throw new ConfigException(
                \sprintf(
                    'The route name "%s" duplicates the name of the pattern "%s".',
                    $name,
                    $this->namedRoutes[$name]->path()->pattern(),
                ),
            );
        }

        $this->namedRoutes[$name] = $compiledRoute;
    }

    /**
     * The registered routes keyed by name: the reverse-routing index.
     *
     * @return array<string, CompiledRoute>
     */
    public function namedRoutes(): array
    {
        return $this->namedRoutes;
    }
}
