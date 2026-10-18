<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

/**
 * One method-call injection entry (the value object of the `calls`
 * grammar; see {@see MethodCalls} for the list-level parsing).
 *
 * A call is the declaration's instruction to invoke one method on the
 * built instance -- the roadmap's "method call injection", the opt-in
 * extension that wires additional or optional dependencies after
 * construction. The arguments carry the same grammar the constructor's
 * do: a `['service' => id]` reference resolves through the container,
 * every other value is a literal.
 *
 * The invoked method's answer is deliberately discarded: a call is an
 * injection, not a builder -- the instance the call mutated is the
 * service's answer, whatever the method returned.
 */
final readonly class MethodCall
{
    /**
     * @param list<mixed> $arguments the method's positional argument plan
     */
    public function __construct(
        public string $method,
        public array $arguments = [],
    ) {
        if ($method === '') {
            throw new DefinitionException('A method call requires a non-empty method name.');
        }
    }

    /**
     * Parses one raw call entry into its value object, fail-closed
     * (the {@see ServiceDefinition::fromArray()} precedent: the value
     * object owns its own entry grammar).
     *
     * @param null|class-string $class the class shape's class, when the
     *                                 product's type is known at parse time
     */
    public static function fromEntry(string $id, ?string $class, mixed $call): self
    {
        $entry = MethodCallShape::entry($id, $call);

        return new self(
            MethodCallNames::proven($id, $class, $entry['method'] ?? null),
            DefinitionArguments::assertValid($id, $entry['arguments'] ?? []),
        );
    }

    /**
     * The same call with every naming reference to the dependency
     * rewritten to the synthetic identifier -- the contextual-binding
     * lane's write half over a call's arguments.
     */
    public function withSubstitutedReferences(string $dep, string $via): self
    {
        return new self(
            $this->method,
            ContextualReference::substitutedList($this->arguments, $dep, $via),
        );
    }
}
