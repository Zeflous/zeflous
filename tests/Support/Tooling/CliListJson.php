<?php

declare(strict_types=1);

namespace Zef\Test\Support\Tooling;

use Zef\Framework\Console\ConsoleCommands;
use Zef\Framework\Console\FrameworkVerbs;

/**
 * The CLI directory's JSON view: the machine-readable answer `bin/zef
 * list --json` renders -- the framework's verb list and the declared
 * command table, the same two sections the text view spells, encoded
 * over the inspection commands' shared JSON spelling ({@see
 * RouteInspectionJson}).
 */
final readonly class CliListJson
{
    /**
     * The whole directory as one JSON document.
     *
     * @param ConsoleCommands $consoleCommands the joined command registry the
     *                                         boot answered
     *
     * @return non-empty-string the encoded document
     */
    public static function rendered(ConsoleCommands $consoleCommands): string
    {
        $declared = [];

        foreach ($consoleCommands->entries() as $consoleCommandEntry) {
            $declared[] = [
                'name' => $consoleCommandEntry->name->toString(),
                'description' => $consoleCommandEntry->description,
                'service' => $consoleCommandEntry->service,
            ];
        }

        return RouteInspectionJson::encode([
            'framework' => FrameworkVerbs::all(),
            'commands' => $declared,
            'count' => $consoleCommands->count(),
        ]);
    }
}
