<?php

declare(strict_types=1);

namespace Zef\Test\Support\Tooling;

use Zef\Framework\Console\ConsoleCommands;
use Zef\Framework\Console\FrameworkVerbs;

/**
 * The CLI directory's text view: the answer `bin/zef list` renders --
 * the framework's own verbs first (the builtin surface an operator can
 * rely on without a configuration), then the declared commands (the
 * host's and every active module's, one table in their declaration
 * order, each carrying its description).
 *
 * The declared section is spelled even when empty: an honest empty (a
 * command-less configuration) is a shape a script can diff, never a
 * missing section that reads as a failure -- the migrations status
 * table's own doctrine, replayed for the directory.
 */
final readonly class CliListText
{
    /**
     * The whole directory, one line per command.
     *
     * @param ConsoleCommands $consoleCommands the joined command registry the
     *                                         boot answered
     */
    public static function table(ConsoleCommands $consoleCommands): string
    {
        $lines = [
            \sprintf(
                'Zef commands -- %d framework, %d declared',
                \count(FrameworkVerbs::all()),
                $consoleCommands->count(),
            ),
            'framework:',
        ];

        foreach (FrameworkVerbs::all() as $verb => $purpose) {
            $lines[] = '  ' . $verb . '  ' . $purpose;
        }

        $lines[] = 'declared:';

        foreach ($consoleCommands->entries() as $consoleCommandEntry) {
            $lines[] = '  ' . $consoleCommandEntry->name->toString() . '  ' . $consoleCommandEntry->description;
        }

        return implode("\n", $lines) . "\n";
    }
}
