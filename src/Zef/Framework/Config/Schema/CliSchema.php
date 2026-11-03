<?php

declare(strict_types=1);

namespace Zef\Framework\Config\Schema;

use Zef\Framework\Config\ConfigException;

/**
 * The Standard CLI Schema: the field contract every command declaration
 * in a `console.commands` table must satisfy -- the "bahasa induk" the
 * module system's CLI extension point compiles its joined tables down
 * to before the console lane's reader consumes them (`ROADMAP.md`,
 * "Module Integration System", the CLI command extension point), the
 * same shape the host's own `console.php` spells under its `commands`
 * key.
 *
 * The three fields and their rules:
 *
 * - `name` (required, non-empty string) -- the command's own verb, the
 *   spelling an operator types after `bin/zef` (e.g.
 *   `migrations:status`). The name's grammar is the console lane's own
 *   judgement ({@see \Zef\Framework\Console\CliCommandName}), exactly
 *   like host-pattern semantics are the routing compiler's: the schema
 *   validates shape, the engine validates the grammar.
 * - `description` (required, non-empty string) -- the one-line purpose
 *   the `bin/zef list` directory renders beside the name. The display
 *   safety bounds (control bytes, length) are the entry fold's own
 *   judgement ({@see \Zef\Framework\Console\ConsoleCommandEntry}),
 *   refined the same way the name is.
 * - `service` (required, non-empty string) -- the container service
 *   identifier the dispatch resolves the command's handler through. A
 *   service that is not registered, or does not answer the
 *   {@see \Zef\Framework\Console\CommandHandler} contract, is the
 *   dispatch fold's own fail-closed judgement, exactly like a listener
 *   service that does not implement EventListener is the events
 *   wiring's.
 *
 * The schema validates *shape*, not command semantics: a name joined
 * twice, or a name that collides with one of the framework's own verbs,
 * is the registry fold's own refusal ({@see
 * \Zef\Framework\Console\ConsoleCommands}), because only the joined
 * table can see both declarations. Validation is fail-closed (KDD-5's
 * doctrine): an invalid declaration is reported with its full path
 * context, never silently dropped, and a key outside the three fields
 * is a violation so a typo cannot produce a command that half-exists.
 *
 * The class is a thin definition over the generic {@see ConfigSchema}
 * engine exactly as {@see EventSchema} and {@see RouteSchema} are: the
 * rules are data, and the engine owns every check, so a module CLI
 * file's validation is a rule map rather than new validation code.
 *
 * @api
 */
final readonly class CliSchema
{
    /**
     * Field rules in the documentation order above; the order is also the
     * order violations are reported in.
     */
    private const array RULES = [
        'name' => ['required' => true, 'kind' => 'string'],
        'description' => ['required' => true, 'kind' => 'string'],
        'service' => ['required' => true, 'kind' => 'string'],
    ];

    private ConfigSchema $configSchema;

    public function __construct()
    {
        $this->configSchema = new ConfigSchema(self::RULES);
    }

    /**
     * Returns every expectation the command declaration fails to satisfy,
     * in deterministic order; a valid declaration yields an empty list.
     *
     * @return list<SchemaViolation>
     */
    public function validate(mixed $command): array
    {
        return $this->configSchema->validate($command);
    }

    /**
     * Asserts that the command declaration satisfies the Standard CLI
     * Schema; otherwise throws a `ConfigException` whose message carries
     * every violation's path context.
     */
    public function assert(mixed $command): void
    {
        $this->configSchema->assert($command);
    }

    /**
     * Asserts that a `console.commands` configuration value is an array
     * whose every entry satisfies the Standard CLI Schema -- the shape a
     * module's CLI file or a host's own `commands` table must have
     * before the reader consumes it.
     *
     * A value that is not an array at all (a scalar, `null`) is rejected
     * with its own message; each entry is then asserted individually, so
     * the first violating entry halts with its full path context. Both a
     * list of entries and a map of named entries validate: the schema
     * governs the entries, not the addressing.
     */
    public function assertList(mixed $commands): void
    {
        if (!\is_array($commands)) {
            throw new ConfigException(
                'The "console.commands" configuration must be a list of command declarations.',
            );
        }

        foreach (array_keys($commands) as $entry) {
            $this->assert($commands[$entry]);
        }
    }
}
