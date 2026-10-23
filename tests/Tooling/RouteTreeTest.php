<?php

declare(strict_types=1);

namespace Zef\Test\Tooling;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Router\Router;
use Zef\Test\Support\Tooling\RouteTreeText;

/**
 * The route tree command's presentation: the compiled graph spelled
 * for the terminal -- one node per line, indented by depth, over the
 * tree's own structural description.
 *
 * @internal
 */
#[CoversClass(RouteTreeText::class)]
final class RouteTreeTest extends TestCase
{
    public function testTheTreeSpellsTheCompiledGraphIndentedByDepth(): void
    {
        self::assertSame(
            "Route tree -- the compiled graph\n"
            . "(root)\n"
            . "  /\n"
            . "    users\n"
            . "      route /users\n"
            . "      /\n"
            . "        {parameter}\n"
            . "          route /users/{id}\n"
            . "    admin/reports\n"
            . "      route /admin/reports\n"
            . "    legacy/status\n"
            . "      route /legacy/status\n"
            . "    ping\n"
            . "      route /ping\n",
            RouteTreeText::rendered($this->router()),
        );
    }

    public function testTheZeroRoutingTreeIsTheBareRoot(): void
    {
        self::assertSame(
            "Route tree -- the compiled graph\n"
            . "(root)\n",
            RouteTreeText::rendered(Router::fromRoutes([])),
        );
    }

    /**
     * The text after a parameter settles as the parameter edge's own
     * static child row -- the trailing run spells itself at the next
     * depth, beside the route that owns the whole pattern.
     */
    public function testTheRunAfterAParameterSpellsAsTheEdgesOwnChildRow(): void
    {
        $router = Router::fromRoutes([
            [
                'path' => '/files/{path}/download',
                'method' => ['GET'],
                'handler' => 'FileController::download',
            ],
        ]);

        self::assertSame(
            "Route tree -- the compiled graph\n"
            . "(root)\n"
            . "  /files/\n"
            . "    {parameter}\n"
            . "      /download\n"
            . "        route /files/{path}/download\n",
            RouteTreeText::rendered($router),
        );
    }

    private function router(): Router
    {
        $table = require \dirname(__DIR__, 2) . '/tests/Scripts/fixtures/configs/routes.php';

        if (!\is_array($table)) {
            self::fail('The fixture route table did not answer a list.');
        }

        return Router::fromRoutes($table);
    }
}
