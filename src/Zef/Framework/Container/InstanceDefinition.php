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
        ServiceLifetime $serviceLifetime = ServiceLifetime::SINGLETON,
        ServiceTags $serviceTags = new ServiceTags(),
    ) {
        parent::__construct($id, $serviceLifetime, $serviceTags);
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
        );
    }
}
