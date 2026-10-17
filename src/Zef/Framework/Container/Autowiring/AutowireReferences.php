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
 * One entry point -- {@see resolve()} -- answers the parameter's own
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

    public function __construct(
        ReflectionMetadataExtractor $reflectionMetadataExtractor,
        AutowireBirths $autowireBirths,
    ) {
        $this->autowireReuse = new AutowireReuse($autowireBirths);
        $this->autowireBirther = new AutowireBirther($autowireBirths, $reflectionMetadataExtractor);
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
        $id = $autowireParameterSpec->identifier();
        if ($autowireParameterSpec->targetClass !== null) {
            $id = $autowireParameterSpec->targetClass;
            if (!ConstructorSpecs::instantiable($id)) {
                throw AutowireReferenceRefusals::targetNotInstantiable($ownerId, $autowireParameterSpec->name, $id);
            }
        }

        if ($this->autowireReuse->known($id)) {
            return $id;
        }

        if (ConstructorSpecs::birthable($id)) {
            return $this->autowireBirther->birth($id, $this);
        }

        throw AutowireReferenceRefusals::unbound($ownerId, $autowireParameterSpec->name, $id);
    }
}
