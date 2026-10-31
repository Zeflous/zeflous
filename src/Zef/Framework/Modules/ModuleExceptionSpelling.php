<?php

declare(strict_types=1);

namespace Zef\Framework\Modules;

/**
 * The refusals the module grammars raise, each naming the offending
 * spelling and the rule it broke.
 *
 * The messages live beside the engine's own vocabulary -- one fold, so a
 * grammar's refusal is spelled once and every caller (the metadata
 * validation, the manifest parse, the entry classification) carries the
 * same sentence for the same defect.
 */
final class ModuleExceptionSpelling
{
    /**
     * @throws ModuleException always
     */
    public static function notTwoSegments(string $name): never
    {
        throw new ModuleException(
            "The module name '{$name}' must be exactly two '/'-joined segments "
            . "(the 'vendor/name' spelling, e.g. 'zef/payment').",
        );
    }

    /**
     * @throws ModuleException always
     */
    public static function segmentCeiling(string $segment, string $name): never
    {
        $length = mb_strlen($segment);

        throw new ModuleException(
            "The module name segment '{$segment}' is {$length} bytes; a segment "
            . "spans 1-32 bytes (name: '{$name}').",
        );
    }

    /**
     * @throws ModuleException always
     */
    public static function segmentGrammar(string $segment, string $name): never
    {
        throw new ModuleException(
            "The module name segment '{$segment}' breaks the grammar: 1-32 bytes, "
            . "starting and ending with [a-z0-9], with '-' and '_' allowed inside "
            . "(name: '{$name}').",
        );
    }

    /**
     * @throws ModuleException always
     */
    public static function versionGrammar(string $version): never
    {
        throw new ModuleException(
            "The module version '{$version}' breaks the grammar: an optional "
            . 'constraint operator (^, ~, >=, >, <=, <, =), then two or three '
            . 'dot-separated numbers, then an optional pre-release or build '
            . 'suffix, spanning at most 32 bytes.',
        );
    }

    /**
     * @throws ModuleException always
     */
    public static function requirementNotAList(mixed $requires): never
    {
        throw new ModuleException(
            'The module requires list must be a list of module names; '
            . get_debug_type($requires) . ' arrived.',
        );
    }

    /**
     * @throws ModuleException always
     */
    public static function requirementNotAName(int $index, mixed $entry): never
    {
        throw new ModuleException(
            "The module requires entry at index {$index} is not a module name "
            . 'spelling: ' . (\is_string($entry) ? "'{$entry}'" : get_debug_type($entry))
            . ' arrived.',
        );
    }

    /**
     * @throws ModuleException always
     */
    public static function requirementDuplicated(string $name): never
    {
        throw new ModuleException(
            "The module requires list names '{$name}' twice; a requirement is "
            . 'declared once.',
        );
    }

    /**
     * @throws ModuleException always
     */
    public static function requirementSelf(string $name): never
    {
        throw new ModuleException(
            "The module '{$name}' cannot require itself; a self-requirement is a "
            . 'cycle of one.',
        );
    }
}
