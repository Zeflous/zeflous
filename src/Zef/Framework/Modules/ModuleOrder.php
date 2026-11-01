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
 * requirements are all answered, the one DECLARED FIRST goes first -- the
 * remaining set is scanned in declaration order every round, so engine sort
 * stability is never relied on and a host's list order survives as the
 * tiebreak it reads as. The remaining set IS the walk's own state: a
 * requirement is satisfied exactly when its module has left the set, so the
 * fold carries one map where two would stand.
 *
 * The fold refuses before it sorts: a name declared twice, a requirement no
 * declared module answers, and a cycle -- spelled as the chain the walk
 * closed -- are all boot-time refusals; the ordering never silently drops a
 * declaration or breaks a tie by luck.
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
        [$remaining, $requires] = ModuleOrderBook::prepared($declarations);

        $ordered = [];

        while ($remaining !== []) {
            $found = array_find_key(array_intersect_key($requires, $remaining), static fn (array $list): bool => array_all(
                $list,
                static fn (string $required): bool => !isset($remaining[$required]),
            ));
            $picked = \is_string($found) ? $found : null;
            if ($picked === null) {
                throw ModuleOrderRefusals::dependencyCycleFrom($remaining, $requires);
            }

            $ordered[] = $declarations[$remaining[$picked]];
            unset($remaining[$picked]);
        }

        return $ordered;
    }
}
