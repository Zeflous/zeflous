<?php

declare(strict_types=1);

namespace Zef\Framework\Container\Autowiring;

/**
 * The binding identifier a planned parameter resolves under -- the
 * `#[Target]` arm's own extraction from the resolver's arms (see
 * {@see AutowireReferences}).
 *
 * The `#[Target]` class wins over the declared type's own name, and a
 * target that cannot produce an instance is a declaration defect
 * refused here with the parameter and every way out -- the identifier
 * the plan references must be one the reuse and birth arms can act
 * on.
 */
final readonly class AutowireTargetBinding
{
    /**
     * The identifier the binding arms resolve: the `#[Target]` class
     * when the attribute named one (proven instantiable), the `#[Inject]`
     * or declared-type name otherwise.
     */
    public static function id(string $ownerId, AutowireParameterSpec $autowireParameterSpec): string
    {
        if ($autowireParameterSpec->targetClass === null) {
            return $autowireParameterSpec->identifier();
        }

        if (!ConstructorSpecs::instantiable($autowireParameterSpec->targetClass)) {
            throw AutowireReferenceRefusals::targetNotInstantiable(
                $ownerId,
                $autowireParameterSpec->name,
                $autowireParameterSpec->targetClass,
            );
        }

        return $autowireParameterSpec->targetClass;
    }
}
