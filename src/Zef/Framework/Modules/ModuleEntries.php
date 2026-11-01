<?php

declare(strict_types=1);

namespace Zef\Framework\Modules;

/**
 * The `modules` knob's own grammar: the configuration entry the boot reads
 * (the list of module entries) and the classification of one entry's
 * spelling.
 *
 * The knob is a whitelist, exactly the way the roadmap spells discovery:
 * a list of entries, each either a qualified class name (a module the
 * autoloader already answers -- the composer-package spelling) or an
 * absolute `module.php` path (the folder-module spelling). Anything else
 * is a refusal that names the entry, never a skipped line: a typo'd entry
 * that silently loaded nothing would leave a deployment believing a module
 * is live while its services are not.
 *
 * The two spellings are told apart by shape -- a namespace separator marks
 * the class spelling, a rooted `.php` path the manifest spelling -- so no
 * convention beyond the two shapes has to be remembered.
 */
final class ModuleEntries
{
    public const string KIND_CLASS = 'class';

    public const string KIND_MANIFEST = 'manifest';

    /**
     * Validates the raw knob value and returns it as the entry list.
     *
     * @return list<string>
     *
     * @throws ModuleException when the knob is not a list or an entry is
     *                         not a non-empty string
     */
    public static function parse(mixed $entries): array
    {
        if (!\is_array($entries) || !array_is_list($entries)) {
            throw new ModuleException(
                "The 'modules' configuration must be a list of module entries; "
                . get_debug_type($entries) . ' arrived.',
            );
        }

        $parsed = [];

        foreach ($entries as $index => $entry) {
            if (!\is_string($entry) || $entry === '') {
                throw new ModuleException(
                    "The 'modules' entry at index {$index} is not a module "
                    . 'spelling: ' . (\is_string($entry) ? "''" : get_debug_type($entry))
                    . ' arrived.',
                );
            }

            $parsed[] = $entry;
        }

        return $parsed;
    }

    /**
     * Classifies one entry's spelling: the class spelling or the manifest
     * spelling.
     *
     * @return self::KIND_*
     *
     * @throws ModuleException when the entry is neither spelling
     */
    public static function classify(string $entry): string
    {
        if (str_contains($entry, '\\')) {
            return self::KIND_CLASS;
        }

        if (str_ends_with($entry, '.php') && str_starts_with($entry, '/')) {
            return self::KIND_MANIFEST;
        }

        throw new ModuleException(
            "The module entry '{$entry}' is neither a qualified class name nor "
            . 'an absolute module.php path (a class spelling carries the '
            . "namespace separator; a manifest spelling is a rooted '.php' path).",
        );
    }
}
