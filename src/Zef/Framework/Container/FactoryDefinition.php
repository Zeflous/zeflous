<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

/**
 * The external factory shape: `Target::method(...arguments)`.
 *
 * The verbatim `'Target::method'` string is stored as the single source of
 * truth. `Target` resolves at instantiation time with a deterministic
 * precedence: a *registered service identifier* wins over an equally-named
 * class, and a class-only target is called statically -- the same
 * disambiguation {@see DefinitionReferences} applies when the boot graph
 * decides whether the target is a container edge at all.
 *
 * @param list<mixed> $arguments
 */
final readonly class FactoryDefinition extends ServiceDefinition
{
    public function __construct(
        string $id,
        public string $factory,
        /** @var list<mixed> */
        public array $arguments = [],
        ServiceLifetime $serviceLifetime = ServiceLifetime::SINGLETON,
        ServiceTags $serviceTags = new ServiceTags(),
    ) {
        parent::__construct($id, $serviceLifetime, $serviceTags);
    }
}
