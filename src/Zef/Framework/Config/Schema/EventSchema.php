<?php

declare(strict_types=1);

namespace Zef\Framework\Config\Schema;

use Zef\Framework\Config\ConfigException;

/**
 * The Standard Events Schema: the field contract every event declaration
 * in an `events.listen` table must satisfy -- the "bahasa induk" the
 * module system's event extension point compiles its joined tables down
 * to before the event lane's reader consumes them (`ROADMAP.md`,
 * "Module Integration System", the event listener extension point), the
 * same shape the host's own `events.php` spells under its `listen` key.
 *
 * The three fields and their rules:
 *
 * - `name` (required, non-empty string) -- the event's own name, the
 *   spelling a definition, a subscription and a dispatch all address the
 *   event by (e.g. `order.paid`). The name's grammar is the event lane's
 *   own judgement ({@see \Zef\Framework\Event\EventName}), exactly like
 *   host-pattern semantics are the routing compiler's: the schema
 *   validates shape, the engine validates the grammar.
 * - `fields` (required, non-empty map of non-empty strings) -- the
 *   payload contract's field spellings, keyed by field name --
 *   `'amount' => 'int'`, `'email' => '?string'`. The kinds the spellings
 *   name are the event lane's own vocabulary
 *   ({@see \Zef\Framework\Event\EventShape}), refined at the reader the
 *   same way the name is.
 * - `listeners` (optional, non-empty list of non-empty strings) -- the
 *   service identifiers the declaration subscribes to the event, in
 *   their declaration order. A declaration without the key is a
 *   definition-only event: the contract is declared, and the module's
 *   own code (or the host's) subscribes at runtime through the
 *   dispatcher's own vocabulary -- the runtime-modification doctrine
 *   the lane carries. An omitted key is that posture; a present but
 *   empty list is a degenerate declaration the kind refuses, never a
 *   default.
 *
 * The schema validates *shape*, not event semantics: a listener service
 * that is not registered, or does not answer the {@see
 * \Zef\Framework\Event\EventListener} contract, is the reader's own
 * fail-closed judgement, exactly like a route handler that does not
 * exist is the routing compiler's. Validation is fail-closed (KDD-5's
 * doctrine): an invalid declaration is reported with its full path
 * context, never silently dropped, and a key outside the three fields
 * is a violation so a typo cannot produce an event that half-exists.
 *
 * The class is a thin definition over the generic {@see ConfigSchema}
 * engine exactly as {@see RouteSchema} is: the rules are data, and the
 * engine owns every check, so the module events file's validation is a
 * rule map rather than new validation code.
 *
 * @api
 */
final readonly class EventSchema
{
    /**
     * Field rules in the documentation order above; the order is also the
     * order violations are reported in.
     */
    private const array RULES = [
        'name' => ['required' => true, 'kind' => 'string'],
        'fields' => ['required' => true, 'kind' => 'string-map'],
        'listeners' => ['required' => false, 'kind' => 'strings'],
    ];

    private ConfigSchema $configSchema;

    public function __construct()
    {
        $this->configSchema = new ConfigSchema(self::RULES);
    }

    /**
     * Returns every expectation the event declaration fails to satisfy,
     * in deterministic order; a valid declaration yields an empty list.
     *
     * @return list<SchemaViolation>
     */
    public function validate(mixed $event): array
    {
        return $this->configSchema->validate($event);
    }

    /**
     * Asserts that the event declaration satisfies the Standard Events
     * Schema; otherwise throws a `ConfigException` whose message carries
     * every violation's path context.
     */
    public function assert(mixed $event): void
    {
        $this->configSchema->assert($event);
    }

    /**
     * Asserts that an `events.listen` configuration value is an array
     * whose every entry satisfies the Standard Events Schema -- the shape
     * a module's events file or a host's own `listen` table must have
     * before the reader consumes it.
     *
     * A value that is not an array at all (a scalar, `null`) is rejected
     * with its own message; each entry is then asserted individually, so
     * the first violating entry halts with its full path context. Both a
     * list of entries and a map of named entries validate: the schema
     * governs the entries, not the addressing.
     */
    public function assertList(mixed $events): void
    {
        if (!\is_array($events)) {
            throw new ConfigException(
                'The "events.listen" configuration must be a list of event declarations.',
            );
        }

        foreach (array_keys($events) as $entry) {
            $this->assert($events[$entry]);
        }
    }
}
