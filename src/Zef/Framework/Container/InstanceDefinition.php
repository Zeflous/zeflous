<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

use Override;

/**
 * The constructor instantiation shape: `new <class>(...arguments)`.
 *
 * The class is validated to exist at parse time, so a definition that
 * survives boot can only fail at instantiation through its arguments'
 * references (which the {@see ServiceDefinitionGraph} has already proven
 * resolvable) -- never through a missing class. The optional
 * {@see MethodCall} list carries the method-call injection lane: each
 * method is invoked on the built instance after construction, its
 * arguments resolved through the same reference grammar.
 */
final readonly class InstanceDefinition extends ServiceDefinition
{
    /**
     * @param null|class-string $lazyClass the class the declaration's
     *                                     native proxy stands in for
     */
    public function __construct(
        string $id,
        /** @var class-string */
        public string $class,
        /** @var list<mixed> */
        public array $arguments = [],
        ServiceLifetime $serviceLifetime = ServiceLifetime::SINGLETON,
        ServiceTags $serviceTags = new ServiceTags(),
        /** @var list<MethodCall> */
        public array $calls = [],
        ?string $lazyClass = null,
    ) {
        parent::__construct($id, $serviceLifetime, $serviceTags, $lazyClass);
    }

    /**
     * The same declaration carrying a further tag list -- the merge
     * the autoconfiguration apply uses to add the tags a declaration
     * earned to the tags it spelled. The value object's own grammar
     * still holds: a tag both lists carry is the duplicate the
     * constructor refuses (the apply has already refused the
     * collision, so the refusal here is the unreachable guard it
     * nevertheless keeps).
     *
     * @param list<string> $added the earned tags, in earn order
     */
    public function withAddedTags(array $added): self
    {
        return new self(
            $this->id,
            $this->class,
            $this->arguments,
            $this->lifetime,
            new ServiceTags([...$this->tags->all(), ...$added]),
            $this->calls,
            $this->lazyClass,
        );
    }

    /**
     * The same declaration carrying a method-call injection list -- the
     * grammar's answer to the shape's `calls` key, every call applied to
     * the built instance after construction.
     *
     * @param list<MethodCall> $methodCalls
     */
    #[Override]
    public function withMethodCalls(array $methodCalls): self
    {
        return new self(
            $this->id,
            $this->class,
            $this->arguments,
            $this->lifetime,
            $this->tags,
            $methodCalls,
            $this->lazyClass,
        );
    }

    /**
     * The same declaration deferring its construction behind a native
     * lazy proxy of this shape's own class ({@see LazyConstructions}:
     * the class is the proxy's, so the lazy key's derivation is the
     * class the declaration already carries).
     */
    #[Override]
    public function withLazyClass(?string $lazyClass): static
    {
        return new self(
            $this->id,
            $this->class,
            $this->arguments,
            $this->lifetime,
            $this->tags,
            $this->calls,
            $lazyClass,
        );
    }

    /**
     * The same declaration with every naming reference to the
     * dependency rewritten to the synthetic identifier -- the
     * contextual-binding lane's write half over both reference realms
     * (the constructor's arguments and the method calls).
     */
    public function withRewrittenReferences(string $dep, string $via): self
    {
        return new self(
            $this->id,
            $this->class,
            ContextualReference::substitutedList($this->arguments, $dep, $via),
            $this->lifetime,
            $this->tags,
            array_map(
                static fn (MethodCall $methodCall): MethodCall => $methodCall->withSubstitutedReferences($dep, $via),
                $this->calls,
            ),
            $this->lazyClass,
        );
    }
}
