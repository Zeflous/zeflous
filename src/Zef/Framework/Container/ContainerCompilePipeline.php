<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

use Zef\Framework\Container\Autowiring\AutoconfigMap;
use Zef\Framework\Container\Autowiring\Autoconfiguration;
use Zef\Framework\Container\Autowiring\AutowireCompilerPass;

/**
 * The multi-pass compilation over the declarative definition set (ROADMAP
 * area 8): the ordered stages a definition set passes through before a
 * single one of them is installed, formalised as one unit with one
 * product ({@see ContainerCompilePlan}).
 *
 * The passes are the ones the bootstrapper has run in this order since
 * the lanes that own them landed, each still its own tested unit -- the
 * pipeline adds the *sequence as an artefact*, not a new behaviour: the
 * autowire compile pass first (reflection boot-only, the name-keyed and
 * `autowire` declarations rewritten to the positional form, the `#[Value]`
 * addresses materialised as their synthetic value services, the planned
 * births appended), the autoconfiguration apply second (the tags the
 * declarations earned merged in), the contextual-binding apply third
 * (each consumer's dependency reference rewritten through its binding's
 * synthetic alias), the decoration apply fourth (each decorated target
 * re-homed and wrapped, the tags travelling with the original
 * identifier), the reference graph proven resolvable, acyclic and
 * lifetime-honest fifth, and the tag index walked out of the planned
 * set last -- the collecting half of the tags lane's read side, now a
 * named stage whose product the AOT artifact bakes.
 *
 * The unreferenced report rides the plan: the identifiers no definition
 * edge names. Every zeflous service stays public and addressable, so a
 * declaration nothing references is *named*, never dropped -- the honest
 * form the roadmap's unused-service removal takes under an all-public
 * model, the answer to "is anything dead here?" the outcome service
 * carries to the host.
 *
 * The extension surface -- a library registering its own pass into the
 * sequence -- is deliberately not here: that is the architectural
 * extensibility area's (ROADMAP area 9) to open against this pipeline,
 * not this area's to guess at.
 */
final readonly class ContainerCompilePipeline
{
    public function __construct(
        private AutowireCompilerPass $autowireCompilerPass = new AutowireCompilerPass(),
    ) {
    }

    /**
     * Runs the passes over the parsed definition set and answers the
     * plan the boot installs from.
     *
     * @param list<ServiceDefinition> $definitions     the two definition maps, parsed and concatenated
     * @param ParameterValues         $parameterValues the global parameter values (the `#[Value]`
     *                                                 addresses' source)
     * @param AutoconfigMap           $autoconfigMap   the earned-tag rules
     * @param ContextualMap           $contextualMap   the contextual bindings
     * @param DecoratorMap            $decoratorMap    the decoration chains
     */
    public function compile(
        array $definitions,
        Container $container,
        ParameterValues $parameterValues,
        AutoconfigMap $autoconfigMap,
        ContextualMap $contextualMap,
        DecoratorMap $decoratorMap,
    ): ContainerCompilePlan {
        $autowireResult = $this->autowireCompilerPass->process($definitions, $container, $parameterValues);

        $planned = Autoconfiguration::apply($autowireResult->definitions, $autoconfigMap);

        $planned = ContextualBindings::apply($planned, $contextualMap);

        $planned = Decorators::apply($planned, $decoratorMap);

        ServiceDefinitionGraph::validate($planned, $container);

        return new ContainerCompilePlan(
            $planned,
            ServiceTagIndex::fromDefinitions($planned),
            $this->unreferenced($planned, $container),
        );
    }

    /**
     * The identifiers no definition edge names: unreferenced in the
     * set's own graph, in declaration order. A runtime `get()` may still
     * name any of them -- that is the all-public model the report
     * respects by naming rather than dropping.
     *
     * @param list<ServiceDefinition> $planned
     *
     * @return list<string>
     */
    private function unreferenced(array $planned, Container $container): array
    {
        $edges = DefinitionEdges::collect($planned, $container);

        $named = [];
        foreach ($edges as $edge) {
            foreach ($edge as $target) {
                $named[] = $target;
            }
        }

        $unreferenced = [];
        foreach ($planned as $definition) {
            if (\in_array($definition->id, $named, true)) {
                continue;
            }

            $unreferenced[] = $definition->id;
        }

        return $unreferenced;
    }
}
