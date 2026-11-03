<?php

declare(strict_types=1);

namespace Zef\Framework\Console;

/**
 * The framework's own verb vocabulary: the commands `bin/zef` answers
 * from its builtin branches -- the surface a host or module declaration
 * must not shadow, because the dispatch's builtin branches match first
 * and a shadowed declaration would be unreachable code wearing a
 * registry entry ({@see ConsoleCommands} refuses the collision naming
 * the verb).
 *
 * The vocabulary is the one list three consumers read, so it is never a
 * dead constant: the `bin/zef list` directory renders it, the registry
 * validates declarations against it, and the correspondence test pins
 * it to `bin/zef`'s own branches -- every verb here must be a verb the
 * CLI actually answers, or the pin fails. A builtin added to `bin/zef`
 * without this list grows a silent shadow surface the next declaration
 * collides with; the maintenance contract is spelled in the KDD
 * (`docs/ci-notes/module-cli-registry.md`) and enforced in the pin's
 * direction: removal from `bin/zef` fails the test, addition to
 * `bin/zef` is the maintainer's own step.
 *
 * The descriptions are the directory's own one-liners -- new text the
 * vocabulary owns, distinct from `bin/zef`'s usage strings (a usage
 * line is grammar, a directory line is purpose).
 */
final readonly class FrameworkVerbs
{
    /**
     * The builtin verbs with their one-line purposes, in the order the
     * CLI's own branches spell them.
     */
    private const array VERBS = [
        'errors:replay' => 'Replay one recorded error snapshot against a local target.',
        'container:inventory' => "Inspect the booted container's service inventory.",
        'route:list' => 'List the booted route table with its methods, hosts and middleware.',
        'route:test' => 'Match one method and path against the booted route table.',
        'route:tree' => 'Render the booted radix route tree.',
        'route:openapi' => 'Export the booted route table as an OpenAPI document.',
        'route:postman' => 'Export the booted route table as a Postman collection.',
        'storage:map' => "Answer the storage layout's lane map for a declared root.",
        'migrations:status' => 'Answer the applied, pending and declared migration counts.',
        'migrations:run' => 'Apply the pending migrations through the wired runner.',
        'migrations:rollback' => 'Roll back the applied migrations through the wired runner.',
        'http:dump' => 'Simulate one HTTP request and render the raw exchange.',
        'http:headers' => 'Audit the response headers one HTTP request answers with.',
        'errors:report' => 'Summarise the local JSONL error log by level and code.',
        'errors:clear' => 'Clear the local JSONL error log.',
    ];

    /**
     * @return array<string, string> the builtin verbs with their
     *                               one-line purposes, keyed by verb
     */
    public static function all(): array
    {
        return self::VERBS;
    }
}
