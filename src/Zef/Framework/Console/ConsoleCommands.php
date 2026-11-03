<?php

declare(strict_types=1);

namespace Zef\Framework\Console;

use InvalidArgumentException;
use Zef\Framework\Config\ConfigException;

/**
 * The console family's command registry: the joined `console.commands`
 * table judged once -- every entry schema-asserted, its fields' bounds
 * refined, and the two judgements only the joined table can make: a
 * name joined twice is a refusal (the host's table and every module's
 * file feed the same table, so only the table sees both declarations),
 * and a name colliding with one of the framework's own verbs is a
 * refusal (the CLI's builtin branches match first, so a shadowed
 * declaration would be unreachable code wearing a registry entry).
 *
 * The registry is the single judgement point the two feeders ride:
 * the host's own `console.php` under its `commands` key, and the
 * module system's CLI extension point joining its declaring modules'
 * files behind the host's entries ({@see \Zef\Framework\ModuleCliTable})
 * -- one `console.commands` key, never a second source of truth, the
 * events lane's doctrine replayed. The judgement walk itself is the
 * entry fold's ({@see ConsoleTableEntries}) -- the one question this
 * registry deliberately does not own, the house's own fold split.
 *
 * The empty posture is the honest identity: a table with no entries
 * answers an empty registry (a command-less configuration is valid,
 * exactly the way a route-less one is), and the directory renders the
 * honest empty rather than a failure.
 *
 * @see CliSchema for the shape question (the three fields, present and
 *     strings) this fold judges before its own bounds and collisions
 */
final readonly class ConsoleCommands
{
    /**
     * @var list<ConsoleCommandEntry> the judged entries, in their
     *                                declaration order
     */
    private array $entries;

    /**
     * @var array<string, ConsoleCommandEntry> the entries keyed by verb,
     *                                         the dispatch's single-comparison surface
     */
    private array $byVerb;

    /**
     * Judges the joined `console.commands` table and answers its
     * registry.
     *
     * @param mixed $table the joined table as the frozen repository
     *                     carries it: a list (or map) of Standard CLI Schema
     *                     declarations, or null when the whole key is absent
     *
     * @throws ConfigException          when the table is not
     *                                  an array or an entry fails the Standard CLI Schema
     * @throws InvalidArgumentException when an entry's fields fail their
     *                                  own bounds, a verb is joined twice, or a verb collides
     *                                  with one of the framework's own
     */
    public function __construct(mixed $table)
    {
        $entries = new ConsoleTableEntries()->listed($table);

        $byVerb = [];

        foreach ($entries as $entry) {
            $byVerb[$entry->name->toString()] = $entry;
        }

        $this->entries = $entries;
        $this->byVerb = $byVerb;
    }

    /**
     * @return list<ConsoleCommandEntry> the judged entries in their
     *                                   declaration order -- the directory's rendering order
     */
    public function entries(): array
    {
        return $this->entries;
    }

    /**
     * Answers the entry the given verb declared, or null when no
     * declaration carries it -- the dispatch's lookup, a single
     * comparison over the joined table.
     */
    public function named(string $verb): ?ConsoleCommandEntry
    {
        return $this->byVerb[$verb] ?? null;
    }

    /**
     * Answers the judged declarations' count -- the honest number the
     * directory's header and the JSON renderer both carry.
     */
    public function count(): int
    {
        return \count($this->entries);
    }
}
