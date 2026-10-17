<?php

declare(strict_types=1);

namespace Zef\Framework\Container\Autowiring;

/**
 * The declaration's own stretch verdict for one parameter -- the
 * planner's tier one (see {@see AutowireParameterPlanner}).
 *
 * A parameter the declaration spells answers the spelled entry's
 * grammar ({@see AutowireDeclaredArguments}); an unspelled variadic
 * answers the empty stretch -- an empty collection is a valid
 * collection; anything else unspelled answers null: not the
 * declaration's business, the resolver and fallback tiers take over.
 */
final readonly class AutowireDeclaredStretch
{
    /**
     * @param null|array<string, mixed> $named the declaration's name-keyed
     *                                         arguments, null for the
     *                                         whole-constructor `autowire` form
     *
     * @return null|list<mixed> the stretch, in the definition grammar's own form
     */
    public static function of(string $ownerId, AutowireParameterSpec $autowireParameterSpec, ?array $named): ?array
    {
        if (\is_array($named) && \array_key_exists($autowireParameterSpec->name, $named)) {
            return AutowireDeclaredArguments::entries(
                $ownerId,
                $autowireParameterSpec,
                $named[$autowireParameterSpec->name],
            );
        }

        if ($autowireParameterSpec->isVariadic) {
            return [];
        }

        return null;
    }
}
