<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

/**
 * The `class` shape's unplanned form -- a definition that asks the
 * autowiring compile pass to plan its constructor ({@see InstanceShape}
 * parses the name-keyed and `autowire` declarations into it).
 *
 * The distinction is load-bearing: an {@see InstanceDefinition} carries
 * a *positional* plan the graph and the compiler consume directly,
 * while this class carries the *request* -- the class to plan plus the
 * declaration's own name-keyed arguments (null for the whole-constructor
 * `autowire` form). Only {@see Autowiring\AutowireCompilerPass}
 * produces planned definitions from it; a definition still unplanned at
 * the graph or the compiler is a misuse of the seam and fails closed
 * with the run's own instruction in the message.
 */
final readonly class UnplannedInstanceDefinition extends ServiceDefinition
{
    /**
     * @param class-string              $class
     * @param null|array<string, mixed> $namedArguments the declaration's
     *                                                  name-keyed arguments, null
     *                                                  for the whole-constructor
     *                                                  `autowire` form
     */
    public function __construct(
        string $id,
        public string $class,
        public ?array $namedArguments = null,
        ServiceLifetime $serviceLifetime = ServiceLifetime::SINGLETON,
        ServiceTags $serviceTags = new ServiceTags(),
    ) {
        parent::__construct($id, $serviceLifetime, $serviceTags);
    }
}
