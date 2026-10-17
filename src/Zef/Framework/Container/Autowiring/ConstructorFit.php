<?php

declare(strict_types=1);

namespace Zef\Framework\Container\Autowiring;

use Zef\Framework\Container\InstanceDefinition;

/**
 * The declared constructor's fit rules -- the arity and name verdicts
 * the compile pass reads a class-shaped declaration against (the
 * positional shape's honesty read, and the name-keyed form's own).
 *
 * Split from {@see AutowireCompilerPass} so the verdicts keep their own
 * archeology-sized unit: the pass owns the planning tiers and the
 * births, this class owns the two ways a declaration can sit wrong
 * against the constructor it names -- a positional list that stops
 * before a required parameter or runs past the last one, and a
 * name-keyed argument that names no parameter at all.
 */
final readonly class ConstructorFit
{
    /**
     * The positional shape's verdict: the declared list must neither run
     * past the constructor's last parameter nor stop before its
     * required prefix.
     *
     * @param list<AutowireParameterSpec> $specs
     */
    public static function assertPositional(InstanceDefinition $instanceDefinition, array $specs): void
    {
        if (\count($instanceDefinition->arguments) > \count($specs)) {
            self::refuse(
                $instanceDefinition->id,
                'the definition declares ' . \count($instanceDefinition->arguments) . ' arguments but '
                . $instanceDefinition->class . '::__construct accepts only ' . \count($specs),
            );
        }

        $beyond = \array_slice($specs, \count($instanceDefinition->arguments));
        foreach ($beyond as $autowireParameterSpec) {
            self::assertDeclared($instanceDefinition, $autowireParameterSpec);
        }
    }

    /**
     * The name-keyed form's verdict: every named argument must name a
     * parameter of the constructor it plans.
     *
     * @param list<AutowireParameterSpec> $specs
     * @param array<string, mixed>        $named
     */
    public static function assertNames(string $id, array $specs, array $named): void
    {
        $known = array_map(
            static fn (AutowireParameterSpec $autowireParameterSpec): string => $autowireParameterSpec->name,
            $specs,
        );

        foreach (array_keys($named) as $name) {
            self::assertNamed($id, $name, $known);
        }
    }

    /**
     * One parameter beyond the declared plan: defaulted parameters are
     * the constructor's own answer, required ones are the refusal.
     */
    private static function assertDeclared(
        InstanceDefinition $instanceDefinition,
        AutowireParameterSpec $autowireParameterSpec,
    ): void {
        if ($autowireParameterSpec->isOptional) {
            return;
        }

        self::refuse(
            $instanceDefinition->id,
            "the declared arguments stop before the required parameter '\${$autowireParameterSpec->name}' of "
            . $instanceDefinition->class . '::__construct -- declare the argument, '
            . "or plan the constructor (the name-keyed or 'autowire' forms)",
        );
    }

    /**
     * One named argument: a parameter's own name passes, anything else
     * is the refusal.
     *
     * @param list<string> $known
     */
    private static function assertNamed(string $id, string $name, array $known): void
    {
        if (\in_array($name, $known, true)) {
            return;
        }

        self::refuseNamed(
            $id,
            "the argument '{$name}' names no parameter of the planned constructor "
            . '(parameters: ' . ($known === [] ? 'none' : implode(', ', $known)) . ')',
        );
    }

    private static function refuse(string $id, string $reason): never
    {
        throw new AutowiringException("Cannot construct '{$id}': {$reason}.");
    }

    private static function refuseNamed(string $id, string $reason): never
    {
        throw new AutowiringException("Cannot autowire '{$id}': {$reason}.");
    }
}
