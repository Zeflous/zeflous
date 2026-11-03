<?php

declare(strict_types=1);

namespace Zef\Test\Tooling;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Console\ConsoleCommands;
use Zef\Test\Support\Tooling\CliListText;

/**
 * The CLI directory command's text view ({@see CliListText}): the
 * whole table pinned line by line -- the size-headed header, the
 * framework's own fifteen verbs with their one-line purposes in the
 * vocabulary's own order, the declared section spelled even when
 * empty, and every line's two-space indent and two-space verb
 * separator carried verbatim, so the rendering is a surface a script
 * can diff (the route list table's own doctrine, replayed for the
 * directory).
 *
 * @internal
 */
#[CoversClass(CliListText::class)]
final class CliListTest extends TestCase
{
    /**
     * The table over a declared registry: the header counts both
     * surfaces, the framework section answers the whole builtin
     * vocabulary, and the declared section carries the host's and the
     * modules' entries in their declaration order.
     */
    public function testTheTableAnswersTheHeadedDirectory(): void
    {
        $consoleCommands = new ConsoleCommands([
            ['name' => 'shop:ping', 'description' => 'Answer the health probe.', 'service' => 'a'],
            ['name' => 'shop:report', 'description' => 'Render the report.', 'service' => 'b'],
        ]);

        self::assertSame(
            "Zef commands -- 15 framework, 2 declared\n"
            . "framework:\n"
            . "  errors:replay  Replay one recorded error snapshot against a local target.\n"
            . "  container:inventory  Inspect the booted container's service inventory.\n"
            . '  route:list  List the booted route table with its methods, hosts and middleware.' . "\n"
            . "  route:test  Match one method and path against the booted route table.\n"
            . "  route:tree  Render the booted radix route tree.\n"
            . "  route:openapi  Export the booted route table as an OpenAPI document.\n"
            . "  route:postman  Export the booted route table as a Postman collection.\n"
            . "  storage:map  Answer the storage layout's lane map for a declared root.\n"
            . "  migrations:status  Answer the applied, pending and declared migration counts.\n"
            . "  migrations:run  Apply the pending migrations through the wired runner.\n"
            . "  migrations:rollback  Roll back the applied migrations through the wired runner.\n"
            . "  http:dump  Simulate one HTTP request and render the raw exchange.\n"
            . "  http:headers  Audit the response headers one HTTP request answers with.\n"
            . "  errors:report  Summarise the local JSONL error log by level and code.\n"
            . "  errors:clear  Clear the local JSONL error log.\n"
            . "declared:\n"
            . "  shop:ping  Answer the health probe.\n"
            . "  shop:report  Render the report.\n",
            CliListText::table($consoleCommands),
        );
    }

    /**
     * The honest empty: a command-less configuration still answers the
     * whole framework half and the declared section's own heading --
     * a shape a script can diff, never a missing section that reads
     * as a failure.
     */
    public function testTheEmptyRegistryAnswersTheHonestEmptyDirectory(): void
    {
        self::assertSame(
            "Zef commands -- 15 framework, 0 declared\n"
            . "framework:\n"
            . "  errors:replay  Replay one recorded error snapshot against a local target.\n"
            . "  container:inventory  Inspect the booted container's service inventory.\n"
            . '  route:list  List the booted route table with its methods, hosts and middleware.' . "\n"
            . "  route:test  Match one method and path against the booted route table.\n"
            . "  route:tree  Render the booted radix route tree.\n"
            . "  route:openapi  Export the booted route table as an OpenAPI document.\n"
            . "  route:postman  Export the booted route table as a Postman collection.\n"
            . "  storage:map  Answer the storage layout's lane map for a declared root.\n"
            . "  migrations:status  Answer the applied, pending and declared migration counts.\n"
            . "  migrations:run  Apply the pending migrations through the wired runner.\n"
            . "  migrations:rollback  Roll back the applied migrations through the wired runner.\n"
            . "  http:dump  Simulate one HTTP request and render the raw exchange.\n"
            . "  http:headers  Audit the response headers one HTTP request answers with.\n"
            . "  errors:report  Summarise the local JSONL error log by level and code.\n"
            . "  errors:clear  Clear the local JSONL error log.\n"
            . "declared:\n",
            CliListText::table(new ConsoleCommands(null)),
        );
    }
}
