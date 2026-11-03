<?php

declare(strict_types=1);

namespace Zef\Framework\Console;

use InvalidArgumentException;
use Zef\Framework\Config\ConfigException;
use Zef\Framework\Config\Schema\CliSchema;

/**
 * The joined `console.commands` table's entry fold: the whole table
 * judged or nothing -- the Standard CLI Schema's list-level assertion,
 * the bounds the entry construction carries, and the two refusals only
 * the joined table can make (a verb joined twice is a refusal, because
 * the host's table and every module's file feed the same table and
 * only the table sees both declarations; a verb colliding with one of
 * the framework's own is a refusal, because the CLI's builtin branches
 * match first and a shadowed declaration would be unreachable code
 * wearing a registry entry).
 *
 * The fold answers the judged entry list in its declaration order; the
 * registry ({@see ConsoleCommands}) is the lookup surface over what
 * this fold answered -- the one-question split the migrations lane's
 * own settle planted and the archeology ceiling keeps asking for.
 */
final readonly class ConsoleTableEntries
{
    /**
     * Judges the joined table and answers its entry list.
     *
     * @param mixed $table the joined table as the frozen repository
     *                     carries it: a list (or map) of Standard CLI Schema
     *                     declarations, or null when the whole key is absent
     *
     * @return list<ConsoleCommandEntry> the judged entries in their
     *                                   declaration order
     *
     * @throws ConfigException          when the table is not an array or an
     *                                  entry fails the Standard CLI Schema
     * @throws InvalidArgumentException when an entry's fields fail their
     *                                  own bounds, a verb is joined twice, or a verb
     *                                  collides with one of the framework's own
     */
    public function listed(mixed $table): array
    {
        if ($table === null) {
            return [];
        }

        new CliSchema()->assertList($table);

        /*
         * The schema's verdict is the table's own shape proof -- the
         * list-level assertion above already refused a non-array table
         * (its own ConfigException, the identical spelling), and this
         * re-read narrows the value for every consumer below -- the
         * same narrowing the events entries fold carries for its
         * per-entry reads, a one-line proof the suite's assertions
         * cover rather than an unreachable duplicate refusal.
         */
        \assert(\is_array($table));

        $entries = [];
        $seen = [];

        foreach ($table as $declaration) {
            \assert(\is_array($declaration));

            /**
             * The schema's verdict is the entry's own shape proof -- the
             * list-level assertion above checked every entry this reading
             * trusts (the same spelling the events wiring carries for its
             * listener reads).
             *
             * @var array<string, string> $declaration
             */
            $entry = ConsoleCommandEntry::fromDeclaration($declaration);
            $verb = $entry->name->toString();

            if (isset($seen[$verb])) {
                throw new InvalidArgumentException(\sprintf(
                    "The command '%s' is declared twice; a verb answers one command.",
                    $verb,
                ));
            }

            if (isset(FrameworkVerbs::all()[$verb])) {
                throw new InvalidArgumentException(\sprintf(
                    "The command '%s' collides with the framework's own verb;"
                    . " the CLI's builtin branch would shadow the declaration.",
                    $verb,
                ));
            }

            $seen[$verb] = $entry;
            $entries[] = $entry;
        }

        return $entries;
    }
}
