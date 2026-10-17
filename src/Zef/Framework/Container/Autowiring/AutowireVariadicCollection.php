<?php

declare(strict_types=1);

namespace Zef\Framework\Container\Autowiring;

/**
 * The unspelled variadic's collection tier (the planner's second
 * tier; see {@see AutowireParameterPlanner} for the precedence).
 *
 * A variadic parameter typed with one class or interface gathers the
 * declared implementations of that type -- each a plain service
 * reference whose edges flow into the graph like any hand-written
 * one. A scalar or untyped variadic answers the empty stretch (no
 * declared ecosystem to gather); a union or intersection one is the
 * ambiguity it is: the collection is the declared implementations of
 * ONE type, and a type admitting several names none.
 */
final readonly class AutowireVariadicCollection
{
    /**
     * @return list<mixed> the stretch, in the definition grammar's own form
     */
    public static function stretch(
        string $ownerId,
        AutowireParameterSpec $autowireParameterSpec,
        AutowireReferences $autowireReferences,
    ): array {
        if ($autowireParameterSpec->className === null) {
            return self::scalarStretch($ownerId, $autowireParameterSpec);
        }

        return array_map(
            static fn (string $id): array => ['service' => $id],
            $autowireReferences->implementations($autowireParameterSpec->className),
        );
    }

    /**
     * @return list<mixed>
     */
    private static function scalarStretch(string $ownerId, AutowireParameterSpec $autowireParameterSpec): array
    {
        if ($autowireParameterSpec->unsupportedTypeReason !== null) {
            throw AutowireRefusals::variadicCollection($ownerId, $autowireParameterSpec);
        }

        return [];
    }
}
