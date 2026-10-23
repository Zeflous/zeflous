<?php

declare(strict_types=1);

namespace Zef\Test\Tooling;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Router\Router;
use Zef\Test\Support\Tooling\RouteListJson;
use Zef\Test\Support\Tooling\RouteListJsonRecord;
use Zef\Test\Support\Tooling\RouteListLine;
use Zef\Test\Support\Tooling\RouteListText;

/**
 * The route list command's presentation family: the per-route line
 * ({@see RouteListLine}), the table form ({@see RouteListText}), the
 * JSON form ({@see RouteListJson}) over the stable record shape
 * ({@see RouteListJsonRecord}) -- every field read straight off the
 * compiled entry, in the tree's declaration order.
 *
 * @internal
 */
#[CoversClass(RouteListLine::class)]
#[CoversClass(RouteListText::class)]
#[CoversClass(RouteListJson::class)]
#[CoversClass(RouteListJsonRecord::class)]
final class RouteListTest extends TestCase
{
    /**
     * The line spells the entry's own declarations: the name, the
     * canonical method set, the pattern, the handler -- and every
     * optional declaration in its own tagged tail.
     */
    public function testTheLineSpellsEveryDeclarationTheEntryCarries(): void
    {
        $router = $this->router();

        $lines = [];

        foreach ($router->tree()->routes() as $compiledRoute) {
            $lines[] = RouteListLine::rendered($compiledRoute);
        }

        self::assertSame(
            [
                'users.show  GET  /users/{id}  UserController::show',
                'users.index  GET,POST  /users  UserController::index',
                'reports.show  GET  /admin/reports @admin.example.com  ReportController::show'
                    . '  version: 2  produces: application/json  middleware: auth.session',
                'status.legacy  GET  /legacy/status  StatusController::show'
                    . '  deprecated: 2026-01-15, sunset: 2026-06-30, link: /docs/migration',
                '(unnamed)  GET  /ping  PingController::pong',
            ],
            $lines,
        );
    }

    /**
     * The table is the size-headed list of the table's own lines, in
     * the declaration order -- the zero-routing table answering its
     * honest empty form.
     */
    public function testTheTableAnswersTheHeadedList(): void
    {
        self::assertSame(
            "Route list -- 5 routes\n"
            . "  users.show  GET  /users/{id}  UserController::show\n"
            . "  users.index  GET,POST  /users  UserController::index\n"
            . '  reports.show  GET  /admin/reports @admin.example.com  ReportController::show'
            . "  version: 2  produces: application/json  middleware: auth.session\n"
            . '  status.legacy  GET  /legacy/status  StatusController::show'
            . "  deprecated: 2026-01-15, sunset: 2026-06-30, link: /docs/migration\n"
            . "  (unnamed)  GET  /ping  PingController::pong\n",
            RouteListText::table($this->router()),
        );
    }

    public function testTheZeroRoutingTableAnswersTheHonestEmptyForm(): void
    {
        self::assertSame(
            "Route list -- 0 routes\n",
            RouteListText::table(Router::fromRoutes([])),
        );
    }

    /**
     * The JSON document is the tool-integration view: every record
     * carries the stable nine-field schema (the absent optionals
     * spelled as their own empty answers), the deprecation instants
     * rendered as RFC 3339 UTC, and the table closed by its own size.
     */
    public function testTheJsonDocumentAnswersTheStableRecordSchema(): void
    {
        $document = $this->decoded(RouteListJson::rendered($this->router()));

        self::assertSame(5, $document['count']);
        self::assertCount(5, $this->mapAt($document['routes']));

        self::assertSame(
            [
                'name' => 'users.show',
                'methods' => ['GET'],
                'path' => '/users/{id}',
                'host' => null,
                'version' => null,
                'produces' => [],
                'middleware' => [],
                'handler' => 'UserController::show',
                'deprecation' => null,
            ],
            $this->mapAt($this->mapAt($document['routes'])[0]),
        );

        self::assertSame(
            [
                'name' => 'reports.show',
                'methods' => ['GET'],
                'path' => '/admin/reports',
                'host' => 'admin.example.com',
                'version' => '2',
                'produces' => ['application/json'],
                'middleware' => ['auth.session'],
                'handler' => 'ReportController::show',
                'deprecation' => null,
            ],
            $this->mapAt($this->mapAt($document['routes'])[2]),
        );

        self::assertSame(
            [
                'name' => 'status.legacy',
                'methods' => ['GET'],
                'path' => '/legacy/status',
                'host' => null,
                'version' => null,
                'produces' => [],
                'middleware' => [],
                'handler' => 'StatusController::show',
                'deprecation' => [
                    'date' => '2026-01-15T00:00:00Z',
                    'sunset' => '2026-06-30T00:00:00Z',
                    'link' => '/docs/migration',
                ],
            ],
            $this->mapAt($this->mapAt($document['routes'])[3]),
        );

        $unnamed = $this->mapAt($this->mapAt($document['routes'])[4]);

        self::assertNull($unnamed['name']);
        self::assertSame('/ping', $unnamed['path']);
    }

    /**
     * The record's deprecation tail spells a sunset-less declaration
     * with its own null -- the schema stays stable across the
     * declaration grammar's optional halves.
     */
    public function testTheRecordSpellsADateOnlyDeprecationWithItsOwnNulls(): void
    {
        $router = Router::fromRoutes([
            [
                'path' => '/legacy/ping',
                'method' => ['GET'],
                'handler' => 'PingController::legacy',
                'deprecation' => ['date' => '2025-12-01'],
            ],
        ]);

        $document = $this->decoded(RouteListJson::rendered($router));

        self::assertSame(
            [
                'date' => '2025-12-01T00:00:00Z',
                'sunset' => null,
                'link' => null,
            ],
            $this->mapAt($this->mapAt($document['routes'])[0])['deprecation'],
        );
    }

    private function router(): Router
    {
        return Router::fromRoutes($this->table());
    }

    /**
     * The fixture's own route table, shaped to the list the facade
     * consumes before the facade consumes it.
     *
     * @return array<mixed>
     */
    private function table(): array
    {
        $table = require \dirname(__DIR__, 2) . '/tests/Scripts/fixtures/configs/routes.php';

        if (!\is_array($table)) {
            self::fail('The fixture route table did not answer a list.');
        }

        return $table;
    }

    /**
     * The rendered document, decoded and shaped to the map the
     * assertions read through.
     *
     * @return array<array-key, mixed>
     */
    private function decoded(string $json): array
    {
        $decoded = json_decode($json, true, 512, \JSON_THROW_ON_ERROR);

        if (!\is_array($decoded)) {
            self::fail('The rendered document did not decode to an object.');
        }

        return $decoded;
    }

    /**
     * One document node the assertions read containers through,
     * shaped to the map it must be before any offset touches it.
     *
     * @return array<array-key, mixed>
     */
    private function mapAt(mixed $node): array
    {
        if (!\is_array($node)) {
            self::fail('The document node the assertion read is not an object.');
        }

        return $node;
    }
}
