<?php

declare(strict_types=1);

namespace Zef\Framework\Container\Autowiring;

/**
 * The name-keyed form's fit rule -- the name verdict the compile pass
 * reads a hybrid declaration against: every named argument must name a
 * parameter of the constructor it plans.
 *
 * Split from the pass so the verdict keeps its own archeology-sized
 * unit ({@see PositionalFit} holds the positional form's own verdict;
 * the words of both refusals live in {@see ConstructorFitRefusals}).
 */
final readonly class NamedFit
{
    /**
     * The name-keyed form's verdict.
     *
     * @param list<AutowireParameterSpec> $specs
     * @param array<string, mixed>        $named
     */
    public static function assert(string $id, array $specs, array $named): void
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

        throw ConstructorFitRefusals::namesNoParameter($id, $name, $known);
    }
}
