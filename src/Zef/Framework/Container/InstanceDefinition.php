<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

/**
 * The constructor instantiation shape: `new <class>(...arguments)`.
 *
 * The class is validated to exist at parse time, so a definition that
 * survives boot can only fail at instantiation through its arguments'
 * references (which the {@see ServiceDefinitionGraph} has already proven
 * resolvable) -- never through a missing class.
 */
final readonly class InstanceDefinition extends ServiceDefinition
{
    public function __construct(
        string $id,
        /** @var class-string */
        public string $class,
        /** @var list<mixed> */
        public array $arguments = [],
    ) {
        parent::__construct($id);
    }
}
