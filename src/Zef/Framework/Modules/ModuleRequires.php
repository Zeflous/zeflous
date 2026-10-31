<?php

declare(strict_types=1);

namespace Zef\Framework\Modules;

/**
 * The module requires-list grammar: a list of module names, each spelled by
 * the {@see ModuleName} grammar, declared once and never the module's own
 * name.
 *
 * The self-requirement refusal lives here rather than in the ordering fold
 * because it is a property of the declaration, not of the set: a module
 * that names itself requires no graph to prove the cycle of one it just
 * spelled.
 */
final class ModuleRequires
{
    /**
     * Validates the raw requires value and returns it as the normalized
     * name list.
     *
     * @return list<string>
     *
     * @throws ModuleException when the value is not a list, an entry is not
     *                         a name spelling, a name is declared twice, or
     *                         the module names itself
     */
    public static function validated(mixed $requires, string $self): array
    {
        if (!\is_array($requires) || !array_is_list($requires)) {
            throw ModuleExceptionSpelling::requirementNotAList($requires);
        }

        $validated = [];
        $seen = [];

        foreach ($requires as $index => $entry) {
            if (!\is_string($entry)) {
                throw ModuleExceptionSpelling::requirementNotAName($index, $entry);
            }

            new ModuleName($entry);

            if (isset($seen[$entry])) {
                throw ModuleExceptionSpelling::requirementDuplicated($entry);
            }

            if ($entry === $self) {
                throw ModuleExceptionSpelling::requirementSelf($self);
            }

            $seen[$entry] = true;
            $validated[] = $entry;
        }

        return $validated;
    }
}
