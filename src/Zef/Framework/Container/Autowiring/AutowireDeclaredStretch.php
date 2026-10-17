<?php

declare(strict_types=1);

namespace Zef\Framework\Container\Autowiring;

/**
 * The declaration's own stretch verdict for one parameter -- the
 * planner's tier one (see {@see AutowireParameterPlanner}).
 *
 * A parameter the declaration spells answers the spelled entry's
 * grammar ({@see AutowireDeclaredArguments}); anything unspelled --
 * variadic included -- answers null: not the declaration's own
 * business, the planner's later tiers take over (an unspelled
 * variadic is the collection tier's to answer, spelled or empty or
 * refused).
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

        return null;
    }
}
