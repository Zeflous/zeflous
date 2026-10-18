<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

use Override;

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
 * The optional {@see MethodCall} list carries the method-call injection
 * lane. Unlike the class shape, the factory's product type is unknown at
 * boot, so the call methods are proven at first resolution instead (the
 * uniform runtime guard in {@see MethodCalls::apply()}).
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
        /** @var list<MethodCall> */
        public array $calls = [],
    ) {
        parent::__construct($id, $serviceLifetime, $serviceTags);
    }

    /**
     * The same declaration carrying a method-call injection list -- the
     * grammar's answer to the shape's `calls` key, every call applied to
     * the factory's product after invocation.
     *
     * @param list<MethodCall> $methodCalls
     */
    #[Override]
    public function withMethodCalls(array $methodCalls): self
    {
        return new self(
            $this->id,
            $this->factory,
            $this->arguments,
            $this->lifetime,
            $this->tags,
            $methodCalls,
        );
    }

    /**
     * The same declaration with every naming reference to the
     * dependency rewritten to the synthetic identifier -- the
     * contextual-binding lane's write half over both reference realms
     * (the factory's arguments and the method calls).
     */
    public function withRewrittenReferences(string $dep, string $via): self
    {
        return new self(
            $this->id,
            $this->factory,
            ContextualReference::substitutedList($this->arguments, $dep, $via),
            $this->lifetime,
            $this->tags,
            array_map(
                static fn (MethodCall $methodCall): MethodCall => $methodCall->withSubstitutedReferences($dep, $via),
                $this->calls,
            ),
        );
    }
}
