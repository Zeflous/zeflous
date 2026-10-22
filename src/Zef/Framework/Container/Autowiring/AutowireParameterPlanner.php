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
 * The dispatch itself is this unit's whole vocabulary: the declared
 * stretch answers tier 1, the variadic shape routes to
 * {@see AutowireVariadicCollection}, and every other parameter walks
 * the non-variadic ladder ({@see AutowireBindingTiers} keeps the
 * value, binding and fallback tiers together with the defaulted
 * nullable's own yield rule).
 *
 * The attribute and type arms resolve together through the
 * {@see AutowireReferences::resolve()} resolver the caller threads in
 * ({@see AutowireParameterSpec::carriesBinding()} is the routing
 * predicate -- the arms' has/birth/refuse verdicts are the resolver's,
 * keeping every tier unit pure). Under both tiers,
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

        if ($autowireParameterSpec->isVariadic) {
            return AutowireVariadicCollection::stretch($ownerId, $autowireParameterSpec, $autowireReferences);
        }

        return AutowireBindingTiers::stretch($ownerId, $autowireParameterSpec, $autowireReferences);
    }
}
