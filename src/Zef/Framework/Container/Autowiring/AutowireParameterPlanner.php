<?php

declare(strict_types=1);

namespace Zef\Framework\Container\Autowiring;

/**
 * The per-parameter planner: one {@see AutowireParameterSpec} plus the
 * declaration's name-keyed arguments, one stretch of the positional plan
 * -- expressed in the definition grammar's own argument form, so the
 * output needs no new consumption machinery at all.
 *
 * The plan entries are exactly what a hand-written positional `arguments`
 * list already carries: plain values verbatim, `['service' => id]` maps
 * for references. The precedence is the roadmap's "explicit wins over
 * auto", tiered:
 *
 * 1. a **name-keyed argument** for the parameter -- the declaration's
 *    own spelling, whatever it holds (a variadic parameter's entry is
 *    the list to spread, each element the same value-or-reference
 *    grammar), read through {@see AutowireDeclaredStretch::of()};
 * 2. **the unspelled variadic's collection** -- when the parameter is
 *    variadic, the declared implementations of its type, each a plain
 *    service reference (the tags lane's gathering, through
 *    {@see AutowireReferences::implementations()}); a scalar or
 *    untyped variadic answers the empty stretch, a union or
 *    intersection one is refused -- it names no single type whose
 *    implementations the collection would be;
 * 3. **the `#[Value]` address** -- the parameters map's value for the
 *    address the attribute names, materialised as the synthetic
 *    `@value:` service and referenced like any dependency (through
 *    {@see AutowireReferences::valueService()}, the value lane the
 *    registration-completion family threads);
 * 4. **the constructor's own default** -- baked verbatim, the honest
 *    value the class itself declared.
 *
 * The defaulted nullable's own tier order: a class-typed parameter
 * that declared a default keeps the binding tier while the type
 * resolves (an alias, a declared service, a birthable class), but an
 * unbound type name must not veto the constructor's own default --
 * the same tier falls through to the default instead of the refusal
 * whose way out the declaration already took. The attribute arms
 * (`#[Inject]`, `#[Target]`) keep the refusal: an explicit binding
 * that names nothing is a declaration defect, not a defaulted
 * parameter's fallback (explicit wins over auto, the precedence this
 * whole planner keeps).
 *
 * The attribute and type arms resolve together through the
 * {@see AutowireReferences::resolve()} resolver the caller threads in
 * ({@see AutowireParameterSpec::carriesBinding()} is the routing
 * predicate -- the arms' has/birth/refuse verdicts are the resolver's,
 * keeping this class pure tiers). Under both tiers,
 * an unplanned parameter that names no binding is the honest refusal
 * (through {@see AutowireRefusals}): an interface without a binding, a
 * required scalar without a default, an unsupported union -- each with
 * the owning class, parameter and the way out in the message. Null is
 * never guessed into a slot: a nullable parameter still has to earn its
 * null explicitly (a default, or the declaration spelling it), the same
 * fail-closed reading the runtime guard gives a re-entering factory.
 */
final readonly class AutowireParameterPlanner
{
    /**
     * Plans one parameter into its stretch of the positional argument
     * list (a variadic's stretch may be empty or hold several entries).
     *
     * @param null|array<string, mixed> $named the declaration's name-keyed
     *                                         arguments, null for the
     *                                         whole-constructor `autowire` form
     *
     * @return list<mixed> the plan entries, in the definition grammar's own argument form
     */
    public function plan(
        string $ownerId,
        AutowireParameterSpec $autowireParameterSpec,
        ?array $named,
        AutowireReferences $autowireReferences,
    ): array {
        $declaredStretch = AutowireDeclaredStretch::of($ownerId, $autowireParameterSpec, $named);
        if ($declaredStretch !== null) {
            return $declaredStretch;
        }

        return $this->unplanned($ownerId, $autowireParameterSpec, $autowireReferences);
    }

    /**
     * The unspelled parameter's tiers: the variadic's collection, then
     * the value address, the binding arms and the fallback (the
     * non-variadic half kept its own unit so the tier sequence stays
     * three returns apiece -- the S1142 line).
     *
     * @return list<mixed>
     */
    private function unplanned(
        string $ownerId,
        AutowireParameterSpec $autowireParameterSpec,
        AutowireReferences $autowireReferences,
    ): array {
        if ($autowireParameterSpec->isVariadic) {
            return AutowireVariadicCollection::stretch($ownerId, $autowireParameterSpec, $autowireReferences);
        }

        return $this->unvariadic($ownerId, $autowireParameterSpec, $autowireReferences);
    }

    /**
     * The non-variadic tiers: the `#[Value]` address, the parameter's
     * own binding, the fallback.
     *
     * @return list<mixed>
     */
    private function unvariadic(
        string $ownerId,
        AutowireParameterSpec $autowireParameterSpec,
        AutowireReferences $autowireReferences,
    ): array {
        if ($autowireParameterSpec->carriesValue()) {
            return [['service' => $autowireReferences->valueService($ownerId, $autowireParameterSpec)]];
        }

        if ($autowireParameterSpec->carriesBinding()) {
            if ($this->unboundTypeWithADefault($autowireParameterSpec, $autowireReferences)) {
                return AutowireFallback::stretch($ownerId, $autowireParameterSpec);
            }

            return [['service' => $autowireReferences->resolve($ownerId, $autowireParameterSpec)]];
        }

        return AutowireFallback::stretch($ownerId, $autowireParameterSpec);
    }

    /**
     * Whether the binding tier must yield to the constructor's own
     * default: the parameter declared a default, its binding is the
     * declared type alone (no attribute spelled an explicit intent),
     * and that type names no binding the resolver could act on -- the
     * tier-4 default the refusal's way out already promised.
     */
    private function unboundTypeWithADefault(
        AutowireParameterSpec $autowireParameterSpec,
        AutowireReferences $autowireReferences,
    ): bool {
        return $autowireParameterSpec->hasDefault
            && $autowireParameterSpec->injectId === null
            && $autowireParameterSpec->targetClass === null
            && $autowireParameterSpec->className !== null
            && !$autowireReferences->typeBindingResolvable($autowireParameterSpec);
    }
}
