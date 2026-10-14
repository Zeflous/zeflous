<?php

declare(strict_types=1);

namespace Zef\Framework\Middleware;

use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Zef\Framework\Config\Config;
use Zef\Framework\Container\Container;

/**
 * Builds the PSR-15 pipeline from the `middleware.stack` configuration.
 *
 * The construction/execution split is the reference repository's
 * `PipelineFactory` pattern (`ROADMAP.md`, "Middleware Pipeline" ->
 * container integration): this class owns *construction* -- reading the
 * frozen configuration, ordering the stack, resolving every middleware
 * through the PSR-11 container and validating the PSR-15 contract at build
 * time -- while {@see MiddlewarePipeline} owns *execution*.
 *
 * Ordering (the reference's issue #174 fix, ported): entries are ordered by
 * `priority` HIGHEST FIRST (500 = outermost), ties keep the
 * `middleware.stack` declaration order through an explicit index tiebreak
 * (engine sort stability is never relied on). Entries without a `priority`
 * default to 0, so stacks that never set priorities keep their exact
 * configuration order.
 *
 * Fail-closed at build time (the P0 discipline): a middleware service that
 * is not registered in the container, or whose resolved service does not
 * implement the PSR-15 `MiddlewareInterface`, aborts the build -- the
 * pipeline never silently skips a declared middleware.
 */
final readonly class PipelineFactory
{
    public function __construct(
        private Container $container,
        private Config $config,
        private RequestHandlerInterface $requestHandler,
    ) {
    }

    /**
     * Builds the pipeline from the `middleware.stack` configuration key.
     *
     * A missing key (or an explicit null) is a valid empty stack: the
     * terminal handler alone answers. Any other non-list value fails
     * closed.
     */
    public function build(): MiddlewarePipeline
    {
        $entries = $this->config->get('middleware.stack');

        if ($entries === null) {
            return new MiddlewarePipeline([], $this->requestHandler);
        }

        if (!\is_array($entries)) {
            throw new MiddlewareException(
                "The 'middleware.stack' configuration must be a list of middleware entries, "
                . get_debug_type($entries) . ' given.',
            );
        }

        if (!array_is_list($entries)) {
            throw new MiddlewareException(
                "The 'middleware.stack' configuration must be a list of middleware entries, array given.",
            );
        }

        $definitions = array_map(
            MiddlewareDefinition::fromArray(...),
            $entries,
        );

        $stack = [];
        foreach ($this->sortByPriority($definitions) as $middlewareDefinition) {
            $id = $middlewareDefinition->serviceId;

            if (!$this->container->has($id)) {
                throw new MiddlewareException("Middleware service '{$id}' is not registered.");
            }

            $middleware = $this->container->get($id);
            if (!$middleware instanceof MiddlewareInterface) {
                throw new MiddlewareException("Service '{$id}' does not implement MiddlewareInterface.");
            }

            $stack[] = $middleware;
        }

        return new MiddlewarePipeline($stack, $this->requestHandler);
    }

    /**
     * Stable priority ordering: highest first, ties broken by ascending
     * declaration index (a Schwartzian transform -- the comparison never
     * sees equal elements, so the sort is stable by construction).
     *
     * The comparator is one tuple spaceship -- `[$b[0], $a[1]] <=>
     * [$a[0], $b[1]]` -- rather than a stepped comparison with an
     * intermediate equality check: the tuple form has no integer literal
     * for the mutators to nudge, and the priority-descending/index-ascending
     * policy reads in one expression.
     *
     * @param list<MiddlewareDefinition> $definitions
     *
     * @return list<MiddlewareDefinition>
     */
    private function sortByPriority(array $definitions): array
    {
        $tagged = [];
        foreach ($definitions as $index => $definition) {
            $tagged[] = [$definition->priority, $index, $definition];
        }

        usort($tagged, static fn (array $a, array $b): int => [$b[0], $a[1]] <=> [$a[0], $b[1]]);

        $ordered = [];
        foreach ($tagged as $row) {
            $ordered[] = $row[2];
        }

        return $ordered;
    }
}
