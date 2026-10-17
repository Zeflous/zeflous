<?php

declare(strict_types=1);

namespace Zef\Framework\Container\Autowiring;

use Zef\Framework\Container\InstanceDefinition;

/**
 * The constructor-fit refusal messages -- every way a declaration can sit
 * wrong against the constructor it names ({@see ConstructorFit}'s
 * fail-closed verdicts).
 *
 * Split from the fit rules so the vocabulary of the refusals (the operand
 * weight of the messages) stays its own archeology-sized unit: the fit
 * rules keep the verdict logic, this class keeps the words -- the same
 * word-class split {@see AutowireRefusals} made for the planner.
 */
final readonly class ConstructorFitRefusals
{
    /**
     * A positional plan that runs past the constructor's last parameter.
     */
    public static function tooManyArguments(
        InstanceDefinition $instanceDefinition,
        int $accepted,
    ): AutowiringException {
        return new AutowiringException(
            "Cannot construct '{$instanceDefinition->id}': the definition declares "
            . \count($instanceDefinition->arguments) . ' arguments but '
            . "{$instanceDefinition->class}::__construct accepts only {$accepted}.",
        );
    }

    /**
     * A positional plan that stops before a required parameter: the
     * defaulted tail is the constructor's own answer, a required one is
     * the refusal -- with the two ways out in the message.
     */
    public static function stopsBeforeRequired(
        InstanceDefinition $instanceDefinition,
        AutowireParameterSpec $autowireParameterSpec,
    ): AutowiringException {
        return new AutowiringException(
            "Cannot construct '{$instanceDefinition->id}': the declared arguments stop before "
            . "the required parameter '\${$autowireParameterSpec->name}' of "
            . "{$instanceDefinition->class}::__construct -- declare the argument, "
            . "or plan the constructor (the name-keyed or 'autowire' forms).",
        );
    }

    /**
     * A name-keyed argument that names no parameter of the planned
     * constructor, with the parameters it could have named.
     *
     * @param list<string> $known
     */
    public static function namesNoParameter(string $id, string $name, array $known): AutowiringException
    {
        return new AutowiringException(
            "Cannot autowire '{$id}': the argument '{$name}' names no parameter of "
            . 'the planned constructor (parameters: '
            . ($known === [] ? 'none' : implode(', ', $known))
            . ').',
        );
    }
}
