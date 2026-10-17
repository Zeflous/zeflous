<?php

declare(strict_types=1);

namespace Zef\Framework\Container\Autowiring;

use Zef\Framework\Container\ArgumentReference;

/**
 * The declaration's spelled entry for one parameter -- the grammar the
 * tier-one verdict hands a named argument to (see
 * {@see AutowireDeclaredStretch}).
 *
 * A plain parameter answers its single entry, validated through the
 * reference grammar; a variadic parameter answers the spread of its
 * named list, each element the same value-or-reference grammar -- and
 * anything but a list to spread is the honest refusal.
 */
final readonly class AutowireDeclaredArguments
{
    /**
     * @return list<mixed> the spelled entry's stretch, in the definition grammar's own form
     */
    public static function entries(
        string $ownerId,
        AutowireParameterSpec $autowireParameterSpec,
        mixed $value,
    ): array {
        if ($autowireParameterSpec->isVariadic) {
            return self::spreadList($ownerId, $autowireParameterSpec, $value);
        }

        return [ArgumentReference::assertWellFormed($ownerId, $value)];
    }

    /**
     * The variadic's own grammar: the named value must be the list of
     * entries to spread, each element the same value-or-reference
     * grammar.
     *
     * @return list<mixed> the spread entries, in the definition grammar's own form
     */
    private static function spreadList(
        string $ownerId,
        AutowireParameterSpec $autowireParameterSpec,
        mixed $value,
    ): array {
        if (\is_array($value) && array_is_list($value)) {
            return array_map(
                static fn (mixed $entry): mixed => ArgumentReference::assertWellFormed($ownerId, $entry),
                $value,
            );
        }

        throw AutowireRefusals::variadicSpread($ownerId, $autowireParameterSpec, $value);
    }
}
