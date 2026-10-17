<?php

declare(strict_types=1);

namespace Zef\Framework\Container\Autowiring;

/**
 * The planner's refusal messages -- every way a parameter can name no
 * binding, each with the owning service, the parameter and the way out
 * in the message ({@see AutowireParameterPlanner}'s fail-closed tail).
 *
 * Split from the planner so the vocabulary of the refusals (the operand
 * weight of the messages) stays its own archeology-sized unit: the
 * planner keeps the precedence tiers, this class keeps the words.
 */
final readonly class AutowireRefusals
{
    /**
     * A parameter whose type the lane cannot plan (a union or an
     * intersection), with the reason the reflection reader answered.
     */
    public static function unsupportedType(
        string $ownerId,
        AutowireParameterSpec $autowireParameterSpec,
    ): AutowiringException {
        return new AutowiringException(
            "Cannot autowire '{$ownerId}' parameter '\${$autowireParameterSpec->name}': "
            . "{$autowireParameterSpec->unsupportedTypeReason}. "
            . 'Name the argument, bind it with #[Inject] or #[Target], or give it a default value.',
        );
    }

    /**
     * A required parameter carrying no value the container can derive:
     * the honest refusal -- never a guessed null.
     */
    public static function underivable(
        string $ownerId,
        AutowireParameterSpec $autowireParameterSpec,
    ): AutowiringException {
        return new AutowiringException(
            "Cannot autowire '{$ownerId}' parameter '\${$autowireParameterSpec->name}': "
            . 'the parameter requires a value '
            . 'the container cannot derive -- name the argument, bind it with #[Inject] or #[Target], '
            . 'or give it a default value.',
        );
    }

    /**
     * A variadic parameter whose named argument is not the list of
     * entries to spread.
     */
    public static function variadicSpread(
        string $ownerId,
        AutowireParameterSpec $autowireParameterSpec,
        mixed $value,
    ): AutowiringException {
        return new AutowiringException(
            "Cannot autowire '{$ownerId}' parameter '\${$autowireParameterSpec->name}': a variadic parameter's "
            . 'argument must be the list of entries to spread, ' . get_debug_type($value) . ' given.',
        );
    }

    /**
     * A variadic parameter whose type admits several -- the collection
     * is the declared implementations of ONE type, and a union or
     * intersection names none.
     */
    public static function variadicCollection(
        string $ownerId,
        AutowireParameterSpec $autowireParameterSpec,
    ): AutowiringException {
        return new AutowiringException(
            "Cannot autowire '{$ownerId}' parameter '\${$autowireParameterSpec->name}': "
            . "{$autowireParameterSpec->unsupportedTypeReason}, and a variadic collection gathers "
            . 'the declared implementations of one type -- name the argument list to spread, '
            . 'or type the parameter with a single class or interface.',
        );
    }
}
