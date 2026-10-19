<?php

declare(strict_types=1);

namespace Zef\Test\Container;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Container\Autowiring\AutoconfigMap;
use Zef\Framework\Container\Container;
use Zef\Framework\Container\ContainerCompilePipeline;
use Zef\Framework\Container\ContainerCompilePlan;
use Zef\Framework\Container\ContextualMap;
use Zef\Framework\Container\DecoratorMap;
use Zef\Framework\Container\DefinitionException;
use Zef\Framework\Container\InstanceDefinition;
use Zef\Framework\Container\ParameterValues;
use Zef\Framework\Container\ServiceDefinition;
use Zef\Framework\Container\ServiceDefinitionGraph;
use Zef\Test\Support\Container\Aot\Window;

/**
 * The multi-pass pipeline's own behaviour: the sequence's product and
 * the validation the pipeline owns -- the reference graph proven
 * acyclic *before* anything installs, the pass order's output the boot
 * consumes (ROADMAP area 8's first bullet, the named stages as one
 * unit).
 *
 * @internal
 */
#[CoversClass(ContainerCompilePipeline::class)]
#[CoversClass(ContainerCompilePlan::class)]
#[CoversClass(ServiceDefinitionGraph::class)]
final class ContainerCompilePipelineTest extends TestCase
{
    /**
     * A cycle the pipeline must refuse before a single definition
     * installs: two declarations naming each other -- the graph
     * validation the pipeline runs fourth, the walk the unreferenced
     * report's own edge collection does not make (it collects, it does
     * not judge), so the refusal is the pipeline's alone.
     */
    public function testACycleIsRefusedBeforeAnythingInstalls(): void
    {
        $containerCompilePipeline = new ContainerCompilePipeline();

        try {
            $containerCompilePipeline->compile(
                [
                    new InstanceDefinition('app.a', Window::class, [['service' => 'app.b']]),
                    new InstanceDefinition('app.b', Window::class, [['service' => 'app.a']]),
                ],
                new Container(),
                ParameterValues::empty(),
                AutoconfigMap::parse('tags', null),
                ContextualMap::parse('contextual', null),
                DecoratorMap::parse('decorators', null),
            );
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "Service 'app.a' is part of a circular reference chain: app.a -> app.b -> app.a.",
                $definitionException->getMessage(),
            );
        }
    }

    /**
     * The plan the happy path answers: every planned definition, the tag
     * index walked out of the planned set, and the unreferenced report
     * naming what no edge reaches -- the one product three consumers
     * read.
     */
    public function testTheHappyPathAnswersThePlan(): void
    {
        $containerCompilePipeline = new ContainerCompilePipeline();

        $plan = $containerCompilePipeline->compile(
            [
                new InstanceDefinition('app.window', Window::class),
                new InstanceDefinition(
                    'app.house',
                    Window::class,
                    [['service' => 'app.window']],
                ),
            ],
            new Container(),
            ParameterValues::empty(),
            AutoconfigMap::parse('tags', null),
            ContextualMap::parse('contextual', null),
            DecoratorMap::parse('decorators', null),
        );

        self::assertSame(['app.window', 'app.house'], array_map(
            static fn (ServiceDefinition $serviceDefinition): string => $serviceDefinition->id,
            $plan->definitions,
        ));
        self::assertSame([], $plan->tagIndex->ids('any.tag'));
        self::assertSame(['app.house'], $plan->unreferenced);
    }
}
