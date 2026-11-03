<?php

declare(strict_types=1);

namespace Zef\Framework;

use Zef\Framework\Config\ConfigException;
use Zef\Framework\Config\Schema\CliSchema;
use Zef\Framework\Modules\ModuleCliRefusals;
use Zef\Framework\Modules\ModuleException;

/**
 * The CLI extension point's entry fold: one module's declared commands
 * file loaded and asserted -- the whole table or nothing, every
 * failure carrying the module's name first.
 *
 * The fold owns one question (the per-module table's consumption) so
 * the join fold beside it ({@see ModuleCliTable}) stays the walk over
 * the roster and the repository -- the lane's one-fold seam pattern the
 * routes half ({@see ModuleRouteEntries}) and the events half ({@see
 * ModuleEventsEntries}) already carry. The table is asserted through
 * the Standard CLI Schema exactly as the host's own `console.commands`
 * table is ({@see CliSchema}), and nothing is rewritten: a command's
 * `name` stays the module author's own spelling, because the name's
 * grammar is the console lane's own judgement and a verb the operator
 * types is the module's declared contract, not a path the boot could
 * prefix -- a duplicate verb joined behind another declaration's is
 * the registry fold's own refusal ({@see Console\ConsoleCommands}),
 * fail-closed, never a silent rewrite here.
 */
final readonly class ModuleCliEntries
{
    public function __construct(
        private ModuleManifestLoader $moduleManifestLoader = new ModuleManifestLoader(),
        private CliSchema $cliSchema = new CliSchema(),
    ) {
    }

    /**
     * Answers the module's command declarations, each entry
     * schema-asserted and otherwise verbatim.
     *
     * @return list<array<array-key, mixed>> the module's command
     *                                       declarations, each satisfying the Standard CLI Schema
     *
     * @throws ModuleException when the module's cli file cannot be
     *                         loaded or fails the Standard CLI Schema
     */
    public function loaded(string $module, string $cliPath): array
    {
        try {
            $entries = $this->moduleManifestLoader->load($cliPath, 'module cli file');
        } catch (ModuleException $moduleException) {
            throw ModuleCliRefusals::unloadable($module, $moduleException);
        }

        try {
            $this->cliSchema->assertList($entries);
        } catch (ConfigException $configException) {
            throw ModuleCliRefusals::invalid($module, $configException);
        }

        /*
         * The schema's verdict is the table's own shape proof -- the
         * assertion above checked every entry this return trusts, the
         * same whole-table-or-nothing posture the events half carries
         * per entry and this half carries at the list level, because
         * the Standard CLI Schema's list contract is the host's own
         * spelling too. The addressing is discarded here -- a map-keyed
         * commands file rides positionally so a repeated verb reaches
         * the registry fold's own duplicate refusal, never a silent
         * key collapse.
         */
        $loaded = [];

        foreach ($entries as $entry) {
            \assert(\is_array($entry));

            $loaded[] = $entry;
        }

        return $loaded;
    }
}
