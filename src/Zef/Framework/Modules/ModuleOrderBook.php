<?php

declare(strict_types=1);

namespace Zef\Framework\Modules;

/**
 * The walk's prepared state: the declarations indexed by name (the
 * duplicate refusal's own pass) beside their requirement lists (the
 * unknown-requirement refusal's own pass) -- one structure the boot order
 * reads, so the walk itself never re-walks the metadata chains.
 */
final class ModuleOrderBook
{
    /**
     * @param list<ModuleDeclaration> $declarations
     *
     * @return array{array<string, int>, array<string, list<string>>}
     *
     * @throws ModuleException on a duplicate name or an unknown requirement
     */
    public static function prepared(array $declarations): array
    {
        $remaining = [];
        $requires = [];

        foreach ($declarations as $at => $declaration) {
            $name = $declaration->metadata->name;

            if (isset($remaining[$name])) {
                ModuleOrderRefusals::duplicateName($name, $remaining[$name], $at);
            }

            $remaining[$name] = $at;
            $requires[$name] = $declaration->metadata->requires;
        }

        foreach ($requires as $name => $list) {
            foreach ($list as $required) {
                if (isset($remaining[$required])) {
                    continue;
                }

                ModuleOrderRefusals::requirementUnknown(
                    $name,
                    $required,
                    array_keys($remaining),
                );
            }
        }

        return [$remaining, $requires];
    }
}
