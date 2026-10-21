<?php

declare(strict_types=1);

namespace Zef\Framework\Container\Autowiring;

/**
 * The unspelled non-variadic parameter's ladder (the planner's value,
 * binding and fallback tiers; see {@see AutowireParameterPlanner} for
 * the precedence).
 *
 * Three tiers answer one stretch: the `#[Value]` address first, the
 * parameter's own binding second, the constructor's own default last
 * -- each entry in the definition grammar's own argument form, a plain
 * `['service' => id]` map for every reference, so the output needs no
 * new consumption machinery at all.
 *
 * The defaulted nullable's own yield rule lives here: a class-typed
 * parameter that declared a default keeps the binding tier while the
 * type resolves (an alias, a declared service, a birthable class), but
 * an unbound type name must not veto the constructor's own default --
 * the binding tier falls through to the default instead of the refusal
 * whose way out the declaration already took. The attribute arms
 * (`#[Inject]`, `#[Target]`) keep the refusal: an explicit binding
 * that names nothing is a declaration defect, not a defaulted
 * parameter's fallback (explicit wins over auto, the precedence the
 * whole planner keeps).
 */
final readonly class AutowireBindingTiers
{
    /**
     * Answers one non-variadic parameter's stretch through the tier
     * ladder: the value address, the binding, the fallback.
     *
     * @return list<mixed> the stretch, in the definition grammar's own form
     */
    public static function stretch(
        string $ownerId,
        AutowireParameterSpec $autowireParameterSpec,
        AutowireReferences $autowireReferences,
    ): array {
        if ($autowireParameterSpec->carriesValue()) {
            return [['service' => $autowireReferences->valueService($ownerId, $autowireParameterSpec)]];
        }

        if (
            self::bindingYieldsToTheDefault($autowireParameterSpec, $autowireReferences)
            || !$autowireParameterSpec->carriesBinding()
        ) {
            return AutowireFallback::stretch($ownerId, $autowireParameterSpec);
        }

        return [['service' => $autowireReferences->resolve($ownerId, $autowireParameterSpec)]];
    }

    /**
     * Whether the binding tier must yield to the constructor's own
     * default: the parameter's binding is the declared type alone
     * ({@see AutowireParameterSpec::bindingIsTheBareDeclaredType()}),
     * and that type names no binding the resolver could act on -- the
     * tier-4 default the refusal's way out already promised.
     */
    private static function bindingYieldsToTheDefault(
        AutowireParameterSpec $autowireParameterSpec,
        AutowireReferences $autowireReferences,
    ): bool {
        return $autowireParameterSpec->bindingIsTheBareDeclaredType()
            && !$autowireReferences->typeBindingResolvable($autowireParameterSpec);
    }
}
