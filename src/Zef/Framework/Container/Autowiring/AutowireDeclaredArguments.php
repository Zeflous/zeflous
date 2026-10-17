<?php

declare(strict_types=1);

namespace Zef\Framework\Container\Autowiring;

use Zef\Framework\Container\ArgumentReference;

/**
 * The declaration's own spelling for one parameter -- the planner's
 * tier one (see {@see AutowireParameterPlanner}).
 *
 * A plain parameter answers its single entry, validated through the
 * reference grammar; a variadic parameter answers the spread of its
 * named list, each element the same value-or-reference grammar -- and
 * anything but a list to spread is the honest refusal.
 */
final readonly class AutowireDeclaredArguments
{
    /**
     * @return list<mixed> the declared entries, in the definition grammar's own form
     */
    public static function entries(string $ownerId, AutowireParameterSpec $autowireParameterSpec, mixed $value): array
    {
        if (!$autowireParameterSpec->isVariadic) {
            return [ArgumentReference::assertWellFormed($ownerId, $value)];
        }

        if (!\is_array($value) || !array_is_list($value)) {
            throw AutowireRefusals::variadicSpread($ownerId, $autowireParameterSpec, $value);
        }

        return array_map(
            static fn (mixed $entry): mixed => ArgumentReference::assertWellFormed($ownerId, $entry),
            $value,
        );
    }
}
