<?php

declare(strict_types=1);

namespace Zef\Framework;

use Zef\Framework\Config\Config;
use Zef\Framework\Config\ConfigException;

/**
 * The CLI extension point's join fold: every declaring module's
 * command declarations joined into the frozen repository's
 * `console.commands` table, so the registry the wired reader answers
 * serves the module's commands exactly as if the host had declared
 * them -- the roadmap sketch's "the module registers its commands to
 * the CLI registry, and `bin/zef list` shows every active module's
 * commands" sentence, kept honest by the tree's own disciplines.
 *
 * The fold walks the roster's declarations in the boot order they were
 * filed and delegates each declaring module's table to the entry fold
 * ({@see ModuleCliEntries} -- the load/assert question this class
 * deliberately does not own). The merged entries ride the SAME key the
 * host's own table rides (`console.commands`), appended after the
 * host's own entries, so every consumer downstream -- the registry's
 * reader included -- sees one table, never a second source of truth.
 *
 * The augmentation happens at one deterministic seam -- after the
 * module registration phase, before the container's definitions
 * install, the same hinge the routes and events halves ride -- so a
 * configuration-cached boot and a freshly loaded one answer the same
 * merged table, and the empty posture is the honest identity: no
 * module declares commands, the repository rides verbatim.
 */
final readonly class ModuleCliTable
{
    public function __construct(
        private ModuleCliEntries $moduleCliEntries = new ModuleCliEntries(),
    ) {
    }

    /**
     * Answers the repository with every declaring module's command
     * declarations joined under the `console.commands` key -- or the
     * verbatim repository when no module declares commands.
     *
     * @throws ConfigException when the host's own `console.commands`
     *                         value is not the array the boot's validation guarantees
     */
    public function augmented(Config $config, ModuleRoster $moduleRoster): Config
    {
        $moduleEntries = [];

        foreach ($moduleRoster->declarations() as $moduleDeclaration) {
            if ($moduleDeclaration->cli === null) {
                continue;
            }

            $moduleEntries[] = $this->moduleCliEntries->loaded(
                $moduleDeclaration->metadata->name,
                $moduleDeclaration->cli,
            );
        }

        if ($moduleEntries === []) {
            return $config;
        }

        if ($config->has('console.commands')) {
            $hostEntries = $config->get('console.commands');

            if (!\is_array($hostEntries)) {
                throw new ConfigException(
                    'The "console.commands" configuration must be a list of command declarations.',
                );
            }
        } else {
            $hostEntries = [];
        }

        return $config->withSet('console.commands', array_merge($hostEntries, ...$moduleEntries));
    }
}
