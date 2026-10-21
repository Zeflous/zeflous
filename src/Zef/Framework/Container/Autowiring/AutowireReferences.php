<?php

declare(strict_types=1);

namespace Zef\Framework\Container\Autowiring;

use Zef\Framework\Container\Container;
use Zef\Framework\Container\ServiceDefinition;

/**
 * The compile pass's reference-resolution half: the arms a planned
 * parameter's binding routes through, and the births those arms may
 * take (see {@see AutowireCompilerPass} for the run's orchestration).
 *
 * Two entry points answer a planned parameter's intent. The binding
 * arm -- {@see resolve()} -- answers the parameter's own service
 * binding ({@see AutowireParameterSpec::carriesBinding()} routed the
 * planner here): the `#[Inject]` identifier, the `#[Target]` class, or
 * the declared type name. Three answers exist for any identifier, and
 * this class owns all of them: **reuse** ({@see AutowireReuse}: a
 * declared definition, a service the container already carries, or a
 * class this run already birthed -- the first spelling wins, never
 * re-planned), **birth** (an unregistered instantiable class becomes
 * its own declared definition, planned recursively through the same
 * planner tiers, memoised so a second parameter naming the class
 * reuses the first birth), and the **honest refusal** (an unbound
 * interface, an `#[Inject]` naming nothing -- each with the owning
 * service, the parameter and every way out in the message, through
 * {@see AutowireReferenceRefusals}).
 *
 * The value arm -- {@see valueService()} -- routes the `#[Value]`
 * attribute's address to the registration-completion family's own
 * lane ({@see AutowireValueServices}), which materialises the
 * synthetic value service the plan references; the arm lives here so
 * the planner threads one resolver for every planned parameter, the
 * same seam the variadic collection's answer already uses.
 *
 * The variadic collection's answer also routes through here
 * ({@see implementations()}) -- the same declared set the reuse arms
 * consult, gathered by {@see AutowireImplementations}.
 *
 * A birth chain that closes onto itself is refused with the walked
 * chain, the same message shape the runtime cycle guard carries. The
 * run's bookkeeping lives in {@see AutowireBirths} (shared with the
 * pass, which reads the report from it); one run opens with
 * {@see open()} and resets the bookkeeping with it.
 */
final readonly class AutowireReferences
{
    private AutowireReuse $autowireReuse;

    private AutowireBirther $autowireBirther;

    private AutowireImplementations $autowireImplementations;

    public function __construct(
        ReflectionMetadataExtractor $reflectionMetadataExtractor,
        AutowireBirths $autowireBirths,
        private AutowireValueServices $autowireValueServices,
    ) {
        $this->autowireReuse = new AutowireReuse($autowireBirths);
        $this->autowireBirther = new AutowireBirther($autowireBirths, $reflectionMetadataExtractor);
        $this->autowireImplementations = new AutowireImplementations();
    }

    /**
     * Opens the run: the declared set to resolve against, the container
     * whose own services answer as reuse, the bookkeeping reset.
     *
     * @param list<ServiceDefinition> $definitions
     */
    public function open(array $definitions, Container $container): void
    {
        $this->autowireReuse->open($definitions, $container);
        $this->autowireImplementations->open($definitions, $container);
    }

    /**
     * The declared implementations of one type -- the variadic
     * collection's answer, from the same declared set the reuse arms
     * consult (births excluded: an incident of planning is not a
     * declaration of the type's ecosystem).
     *
     * @param string $type the class or interface the collection gathers
     *
     * @return list<string>
     */
    public function implementations(string $type): array
    {
        return $this->autowireImplementations->ids($type);
    }

    /**
     * Whether the parameter's declared-type binding would resolve at
     * all: the reuse verdict or the birth verdict, consulted without
     * side effects. The defaulted nullable's consultation ({@see
     * AutowireParameterPlanner}): an unbound type name must not veto
     * the constructor's own default. The attribute arms stay outside
     * -- an explicit `#[Inject]` or `#[Target]` that names nothing is
     * the refusal it is.
     */
    public function typeBindingResolvable(AutowireParameterSpec $autowireParameterSpec): bool
    {
        $id = $autowireParameterSpec->identifier();

        return $this->autowireReuse->known($id) || ConstructorSpecs::birthable($id);
    }

    /**
     * Resolves the parameter's binding to the identifier the plan
     * references: the `#[Target]` class when the attribute named one
     * (it must be instantiable -- a binding that cannot produce an
     * instance is a declaration defect), else the `#[Inject]` identifier
     * or the declared type name -- reused, birthed, or honestly refused
     * with the parameter and every way out in the message.
     */
    public function resolve(string $ownerId, AutowireParameterSpec $autowireParameterSpec): string
    {
        $id = AutowireTargetBinding::id($ownerId, $autowireParameterSpec);

        if ($this->autowireReuse->known($id)) {
            return $id;
        }

        if (ConstructorSpecs::birthable($id)) {
            return $this->autowireBirther->birth($id, $this);
        }

        throw AutowireReferenceRefusals::unbound($ownerId, $autowireParameterSpec->name, $id);
    }

    /**
     * The value arm's answer: the synthetic `@value:` identifier the
     * `#[Value]` attribute's plan references, materialised by the
     * registration-completion family's lane (see
     * {@see AutowireValueServices::service()}).
     */
    public function valueService(string $ownerId, AutowireParameterSpec $autowireParameterSpec): string
    {
        return $this->autowireValueServices->service($ownerId, $autowireParameterSpec);
    }
}
