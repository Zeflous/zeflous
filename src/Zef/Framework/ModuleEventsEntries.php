<?php

declare(strict_types=1);

namespace Zef\Framework;

use Zef\Framework\Config\ConfigException;
use Zef\Framework\Config\Schema\EventSchema;
use Zef\Framework\Modules\ModuleEventsRefusals;
use Zef\Framework\Modules\ModuleException;

/**
 * The event extension point's entry fold: one module's declared events
 * file loaded and asserted -- the whole table or nothing, every failure
 * carrying the module's name first.
 *
 * The fold owns one question (the per-module table's consumption) so the
 * join fold beside it ({@see ModuleEventsTable}) stays the walk over the
 * roster and the repository -- the lane's one-fold seam pattern the
 * construction guard, the rule-shape judgment and the routes half
 * ({@see ModuleRouteEntries}) already carry. Each entry is asserted
 * through the Standard Events Schema exactly as the host's own
 * `events.listen` table is ({@see EventSchema}), and nothing is
 * rewritten: an event's `name` stays the module author's own spelling,
 * because the name's grammar is the event lane's own judgement and a
 * name the whole application addresses an event by is the module's
 * declared contract, not a path the boot could prefix -- a duplicate
 * name joined behind another declaration's is the reader's own
 * already-defined refusal, fail-closed, never a silent rewrite here.
 */
final readonly class ModuleEventsEntries
{
    public function __construct(
        private ModuleManifestLoader $moduleManifestLoader = new ModuleManifestLoader(),
        private EventSchema $eventSchema = new EventSchema(),
    ) {
    }

    /**
     * Answers the module's event declarations, each entry schema-asserted
     * and otherwise verbatim.
     *
     * @return list<array<array-key, mixed>> the module's event
     *                                       declarations, each satisfying the Standard Events Schema
     *
     * @throws ModuleException when the module's events file cannot be
     *                         loaded or fails the Standard Events Schema
     */
    public function loaded(string $module, string $eventsPath): array
    {
        try {
            $entries = $this->moduleManifestLoader->load($eventsPath, 'module events file');
        } catch (ModuleException $moduleException) {
            throw ModuleEventsRefusals::unloadable($module, $moduleException);
        }

        $loaded = [];

        foreach ($entries as $entry) {
            try {
                $this->eventSchema->assert($entry);
            } catch (ConfigException $verdict) {
                throw ModuleEventsRefusals::invalid($module, $verdict);
            }

            /*
             * @var array<array-key, mixed> $entry the schema-asserted event
             *                              declaration -- the assertion above
             *                              checked the shape this reading
             *                              trusts
             */
            $loaded[] = $entry;
        }

        return $loaded;
    }
}
