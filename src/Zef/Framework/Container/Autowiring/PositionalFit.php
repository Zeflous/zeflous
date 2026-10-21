<?php

declare(strict_types=1);

namespace Zef\Framework\Container\Autowiring;

use Zef\Framework\Container\InstanceDefinition;

/**
 * The positional shape's fit rule -- the arity verdict the compile pass
 * reads a hand-written plan against: the declared list must neither run
 * past the constructor's last parameter nor stop before its required
 * prefix. A trailing variadic parameter accepts an unbounded positional
 * tail, so it never caps the list.
 *
 * Split from the pass so the verdict keeps its own archeology-sized
 * unit ({@see NamedFit} holds the name-keyed form's own verdict; the
 * words of both refusals live in {@see ConstructorFitRefusals}).
 */
final readonly class PositionalFit
{
    /**
     * The positional shape's verdict.
     *
     * @param list<AutowireParameterSpec> $specs
     */
    public static function assert(InstanceDefinition $instanceDefinition, array $specs): void
    {
        $capacity = self::capacity($specs);

        if (\count($instanceDefinition->arguments) > $capacity) {
            throw ConstructorFitRefusals::tooManyArguments($instanceDefinition, $capacity);
        }

        $beyond = \array_slice($specs, \count($instanceDefinition->arguments));
        foreach ($beyond as $autowireParameterSpec) {
            self::assertDeclared($instanceDefinition, $autowireParameterSpec);
        }
    }

    /**
     * The number of positional arguments the parameter list accepts: a
     * trailing variadic parameter accepts an unbounded tail --
     * `newInstanceArgs(['a', 'b'])` over `__construct(...$notes)` is
     * valid PHP -- and every other list accepts exactly its own length.
     *
     * @param list<AutowireParameterSpec> $specs
     */
    private static function capacity(array $specs): int
    {
        $lastIndex = array_key_last($specs);

        if ($lastIndex === null) {
            return 0;
        }

        if ($specs[$lastIndex]->isVariadic) {
            return \PHP_INT_MAX;
        }

        return \count($specs);
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

        throw ConstructorFitRefusals::stopsBeforeRequired($instanceDefinition, $autowireParameterSpec);
    }
}
