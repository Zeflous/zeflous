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
 * resolve through the explicit, defaulted, value or refused arms. The
 * `#[Value]` key is the registration-completion family's seam: a scalar
 * parameter whose value the parameters map answers, materialised by
 * {@see AutowireValueServices} -- never combined with the binding
 * attributes, never on a variadic, a class-typed or a union parameter
 * (a value is one scalar or array, not a collection, an instance or a
 * type union).
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
        public ?string $valueKey = null,
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

        if ($this->valueKey !== null && ($this->injectId !== null || $this->targetClass !== null)) {
            throw new AutowiringException(
                "Parameter '\${$this->name}' cannot combine #[Value] with #[Inject] or #[Target] "
                . '(one parameter carries one binding intent).',
            );
        }

        if ($this->valueKey !== null && $this->isVariadic) {
            throw new AutowiringException(
                "Parameter '\${$this->name}' cannot carry #[Value] on a variadic parameter "
                . '(a variadic is a collection, not one value).',
            );
        }

        if ($this->valueKey !== null && $this->className !== null) {
            throw new AutowiringException(
                "Parameter '\${$this->name}' cannot carry #[Value] on a class-typed parameter "
                . "({$this->className} is an instance type, a value is a scalar or an array).",
            );
        }

        if ($this->valueKey !== null && $this->unsupportedTypeReason !== null) {
            throw new AutowiringException(
                "Parameter '\${$this->name}' cannot carry #[Value] on a union or intersection "
                . 'parameter (a value names no single type).',
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
     * Whether the value arm carries this parameter: the `#[Value]`
     * attribute named its parameter address (the class-typed and union
     * guards make the value and binding arms mutually exclusive by
     * construction).
     */
    public function carriesValue(): bool
    {
        return $this->valueKey !== null;
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
