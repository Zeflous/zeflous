<?php

declare(strict_types=1);

namespace Zef\Framework\Modules;

/**
 * The boot-order fold: the declared modules sorted so every module's
 * requirements are registered and booted before the module that requires
 * them, with the declaration order as the explicit tiebreak.
 *
 * The sort is the Kahn walk the roadmap's dependency-resolution item spells,
 * with the tree's own ordering doctrine riding it: among the modules whose
 * requirements are all satisfied, the one DECLARED FIRST goes first --
 * engine sort stability is never relied on, the pick is by declaration
 * index every round, so a host's list order survives as the tiebreak it
 * reads as.
 *
 * The fold refuses before it sorts: a name declared twice, a requirement no
 * declared module answers, and a cycle (spelled as the chain the walk
 * closed, `a -> b -> a`) are all boot-time refusals -- the ordering never
 * silently drops a declaration or breaks a tie by luck.
 */
final class ModuleOrder
{
    /**
     * @param list<ModuleDeclaration> $declarations the declarations in the
     *                                              knob's declaration order
     *
     * @return list<ModuleDeclaration> the boot order
     *
     * @throws ModuleException on a duplicate name, an unknown requirement,
     *                         or a dependency cycle
     */
    public static function ordered(array $declarations): array
    {
        $pending = self::indexByName($declarations);
        self::assertRequirementsDeclared($declarations, $pending);

        $ordered = [];
        $satisfied = [];

        while (\count($ordered) < \count($declarations)) {
            $picked = self::pickReady($declarations, $pending, $satisfied);

            if ($picked === null) {
                self::refuseCycle($declarations, $pending);
            }

            $satisfied[$picked] = true;
            $ordered[] = $declarations[$pending[$picked]];
            unset($pending[$picked]);
        }

        return $ordered;
    }

    /**
     * @param list<ModuleDeclaration> $declarations
     *
     * @return array<string, int>
     */
    private static function indexByName(array $declarations): array
    {
        $pending = [];

        foreach ($declarations as $index => $declaration) {
            $name = $declaration->metadata->name;

            if (isset($pending[$name])) {
                throw new ModuleException(
                    "The module name '{$name}' is declared twice (entries "
                    . "{$pending[$name]} and {$index}); one name is one module.",
                );
            }

            $pending[$name] = $index;
        }

        return $pending;
    }

    /**
     * @param list<ModuleDeclaration> $declarations
     * @param array<string, int>      $pending
     */
    private static function assertRequirementsDeclared(array $declarations, array $pending): void
    {
        foreach ($declarations as $declaration) {
            foreach ($declaration->metadata->requires as $required) {
                if (!isset($pending[$required])) {
                    $declared = implode(', ', array_keys($pending));

                    throw new ModuleException(
                        "The module '{$declaration->metadata->name}' requires "
                        . "'{$required}', which no declared module answers "
                        . "(declared: {$declared}).",
                    );
                }
            }
        }
    }

    /**
     * The smallest declaration index among the modules whose requirements
     * are all satisfied, or null when none is ready.
     *
     * @param list<ModuleDeclaration> $declarations
     * @param array<string, int>      $pending
     * @param array<string, bool>     $satisfied
     */
    private static function pickReady(array $declarations, array $pending, array $satisfied): ?string
    {
        $picked = null;
        $pickedIndex = null;

        foreach ($pending as $name => $index) {
            if (!self::isReady($declarations[$index], $satisfied)) {
                continue;
            }

            if ($pickedIndex !== null && $index >= $pickedIndex) {
                continue;
            }

            $picked = $name;
            $pickedIndex = $index;
        }

        return $picked;
    }

    /**
     * @param array<string, bool> $satisfied
     */
    private static function isReady(ModuleDeclaration $moduleDeclaration, array $satisfied): bool
    {
        return array_all(
            $moduleDeclaration->metadata->requires,
            static fn (string $required): bool => isset($satisfied[$required]),
        );
    }

    /**
     * @param list<ModuleDeclaration> $declarations
     * @param array<string, int>      $pending
     *
     * @throws ModuleException always
     */
    private static function refuseCycle(array $declarations, array $pending): never
    {
        $first = null;

        foreach ($pending as $name => $index) {
            if ($first !== null && $index >= $pending[$first]) {
                continue;
            }

            $first = $name;
        }

        $walked = [];
        $order = [];
        $current = $first;

        while ($current !== null && !isset($walked[$current])) {
            $walked[$current] = true;
            $order[] = $current;
            $current = self::firstPendingRequirement($declarations, $pending, $current);
        }

        $cycle = $current === null
            ? $order
            : [...\array_slice($order, (int) array_search($current, $order, true)), $current];

        throw new ModuleException(
            'The module declarations close a dependency cycle: '
            . implode(' -> ', $cycle) . '.',
        );
    }

    /**
     * @param list<ModuleDeclaration> $declarations
     * @param array<string, int>      $pending
     */
    private static function firstPendingRequirement(
        array $declarations,
        array $pending,
        string $name,
    ): ?string {
        foreach ($declarations[$pending[$name]]->metadata->requires as $required) {
            if (isset($pending[$required])) {
                return $required;
            }
        }

        return null;
    }
}
