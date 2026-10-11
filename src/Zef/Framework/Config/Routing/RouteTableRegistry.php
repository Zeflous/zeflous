<?php

declare(strict_types=1);

namespace Zef\Framework\Config\Routing;

use Zef\Framework\Config\ConfigException;

/**
 * The registration bookkeeping of a route table: one route per
 * parameter-blind pattern, one route per name.
 *
 * Registration is where the table is judged fail-closed (see `ROADMAP.md`,
 * "Router" -- duplicate detection happens "saat registrasi"): a second
 * route with the same canonical pattern -- whatever it names its
 * parameters -- would own the same tree position, so the tree refuses it
 * before anything is built; a second route with the same name would make
 * reverse routing ambiguous, so it is refused for the same reason.
 *
 * @internal
 */
final class RouteTableRegistry
{
    /** @var array<string, string> canonical pattern => original pattern */
    private array $canonicalPatterns = [];

    /** @var array<string, CompiledRoute> route name => the named route */
    private array $namedRoutes = [];

    /**
     * Registers one route, rejecting pattern and name duplicates.
     */
    public function register(CompiledRoute $compiledRoute): void
    {
        $pattern = $compiledRoute->path()->pattern();
        $canonical = $compiledRoute->path()->canonical();

        if (isset($this->canonicalPatterns[$canonical])) {
            throw new ConfigException(
                \sprintf(
                    'The route pattern "%s" duplicates the registered pattern "%s".',
                    $pattern,
                    $this->canonicalPatterns[$canonical],
                ),
            );
        }

        $this->canonicalPatterns[$canonical] = $pattern;

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
