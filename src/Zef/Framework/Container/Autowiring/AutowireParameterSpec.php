<?php

declare(strict_types=1);

namespace Zef\Framework\Container\Autowiring;

/**
 * The reflection-extracted plan for one constructor parameter -- pure
 * data, produced once by {@see ReflectionMetadataExtractor} during the
 * compile pass and consumed by {@see AutowireParameterPlanner}.
 *
 * No `Reflection*` object is retained: a spec is a snapshot of the
 * constructor's own declaration, so the pass never re-reads reflection
 * for the same class (the roadmap's "reflection caching" bullet, earned
 * by construction -- the spec *is* the cache).
 *
 * The unsupported-type reason carries the honest verdict for union and
 * intersection types (a parameter admitting several types names no single
 * binding); scalar and untyped parameters carry a null class name and
 * resolve only through the explicit, defaulted or refused arms -- there
 * is no value guessing in this lane (the config-value attribute is the
 * registration-completion family's seam, not this one's).
 */
final readonly class AutowireParameterSpec
{
    public function __construct(
        public string $name,
        public bool $isVariadic,
        public bool $isOptional,
        public bool $hasDefault,
        public mixed $defaultValue = null,
        public ?string $className = null,
        public ?string $unsupportedTypeReason = null,
        public ?string $injectId = null,
        public ?string $targetClass = null,
    ) {
        if ($this->injectId !== null && $this->targetClass !== null) {
            throw new AutowiringException(
                "Parameter '\${$this->name}' cannot combine #[Inject] and #[Target] "
                . '(one parameter carries one binding intent).',
            );
        }

        if ($this->injectId !== null && $this->isVariadic) {
            throw new AutowiringException(
                "Parameter '\${$this->name}' cannot carry #[Inject] on a variadic parameter "
                . '(a variadic is a collection, not a single reference).',
            );
        }

        if ($this->targetClass !== null && $this->isVariadic) {
            throw new AutowiringException(
                "Parameter '\${$this->name}' cannot carry #[Target] on a variadic parameter "
                . "(collection binding is the tags lane's concern).",
            );
        }
    }

    /**
     * Whether any binding arm can carry this parameter: an attribute
     * named it, or it declares a class-type to resolve -- the planner's
     * single routing predicate ahead of the resolver.
     */
    public function carriesBinding(): bool
    {
        return $this->injectId !== null || $this->targetClass !== null || $this->className !== null;
    }

    /**
     * The identifier the binding arms resolve: the `#[Inject]` name
     * when the attribute named one, the declared type otherwise (the
     * `#[Target]` arm routes its own class before falling through).
     */
    public function identifier(): string
    {
        return $this->injectId ?? (string) $this->className;
    }
}
