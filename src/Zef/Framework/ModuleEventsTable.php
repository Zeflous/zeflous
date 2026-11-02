<?php

declare(strict_types=1);

namespace Zef\Framework;

use Zef\Framework\Config\Config;
use Zef\Framework\Config\ConfigException;

/**
 * The event extension point's join fold: every declaring module's event
 * declarations joined into the frozen repository's `events.listen`
 * table, so the dispatcher the wired default answers serves the
 * module's events exactly as if the host had declared them -- the
 * roadmap sketch's "module declares its listeners in the manifest, the
 * kernel registers them to the event bus" sentence, kept honest by the
 * tree's own disciplines.
 *
 * The fold walks the roster's declarations in the boot order they were
 * filed and delegates each declaring module's table to the entry fold
 * ({@see ModuleEventsEntries} -- the load/assert question this class
 * deliberately does not own). The merged entries ride the SAME key the
 * host's own table rides (`events.listen`), appended after the host's
 * own entries, so every consumer downstream -- the wired dispatcher's
 * reader included -- sees one table, never a second source of truth.
 *
 * The augmentation happens at one deterministic seam -- after the module
 * registration phase, before the container's definitions install, the
 * same hinge the routes half rides -- so a configuration-cached boot and
 * a freshly loaded one answer the same merged table, and the empty
 * posture is the honest identity: no module declares events, the
 * repository rides verbatim.
 */
final readonly class ModuleEventsTable
{
    public function __construct(
        private ModuleEventsEntries $moduleEventsEntries = new ModuleEventsEntries(),
    ) {
    }

    /**
     * Answers the repository with every declaring module's event
     * declarations joined under the `events.listen` key -- or the
     * verbatim repository when no module declares events.
     *
     * @throws ConfigException when the host's own `events.listen` value
     *                         is not the array the boot's validation guarantees
     */
    public function augmented(Config $config, ModuleRoster $moduleRoster): Config
    {
        $moduleEntries = [];

        foreach ($moduleRoster->declarations() as $moduleDeclaration) {
            if ($moduleDeclaration->events === null) {
                continue;
            }

            $moduleEntries[] = $this->moduleEventsEntries->loaded(
                $moduleDeclaration->metadata->name,
                $moduleDeclaration->events,
            );
        }

        if ($moduleEntries === []) {
            return $config;
        }

        if ($config->has('events.listen')) {
            $hostEntries = $config->get('events.listen');

            if (!\is_array($hostEntries)) {
                throw new ConfigException(
                    'The "events.listen" configuration must be a list of event declarations.',
                );
            }
        } else {
            $hostEntries = [];
        }

        return $config->withSet('events.listen', array_merge($hostEntries, ...$moduleEntries));
    }
}
