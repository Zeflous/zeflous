<?php

declare(strict_types=1);

namespace Zef\Framework\Modules;

/**
 * The ordering fold's refusals: the duplicate name, the requirement no
 * declared module answers, and the dependency cycle spelled as the chain
 * the walk closed -- one fold, so the walk's every dead end carries the
 * same sentence for the same defect.
 */
final class ModuleOrderRefusals
{
    /**
     * @throws ModuleException always
     */
    public static function duplicateName(string $name, int $first, int $second): never
    {
        throw new ModuleException(
            "The module name '{$name}' is declared twice (entries "
            . "{$first} and {$second}); one name is one module.",
        );
    }

    /**
     * @param list<string> $declared
     *
     * @throws ModuleException always
     */
    public static function requirementUnknown(
        string $name,
        string $required,
        array $declared,
    ): never {
        throw new ModuleException(
            "The module '{$name}' requires '{$required}', which no declared "
            . 'module answers (declared: ' . implode(', ', $declared) . ').',
        );
    }

    /**
     * The cycle the walk closed: from the first remaining module (the
     * remaining map's own declaration order), following the first
     * outstanding requirement until a name repeats -- the loop spelled
     * without its tail.
     *
     * @param array<string, int>          $remaining
     * @param array<string, list<string>> $requires
     *
     * @throws ModuleException always
     */
    public static function dependencyCycleFrom(array $remaining, array $requires): never
    {
        $seen = [];
        $walked = [];
        $current = array_key_first($remaining);

        while ($current !== null && !isset($seen[$current])) {
            $seen[$current] = $current;
            $walked[] = $current;

            $next = null;

            foreach ($requires[$current] as $required) {
                if (isset($remaining[$required])) {
                    $next = $required;

                    break;
                }
            }

            $current = $next;
        }

        $cycle = $current === null
            ? $walked
            : [...\array_slice($walked, (int) array_search($current, $walked, true)), $current];

        throw new ModuleException(
            'The module declarations close a dependency cycle: '
            . implode(' -> ', $cycle) . '.',
        );
    }
}
