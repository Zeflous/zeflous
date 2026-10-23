<?php

declare(strict_types=1);

namespace Zef\Test\Tooling;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Config\ConfigException;
use Zef\Framework\Config\Routing\CompiledRoute;
use Zef\Test\Support\Tooling\RouteInspectionBoot;

/**
 * The route inspection commands' boot: the one seam the three CLI
 * commands share -- a configuration directory becomes the router the
 * commands answer from, through the same application orchestrator
 * the runtime rides. These tests pin the honest boot (the fixture's
 * own route table, validated by the configuration system, in the
 * tree's declaration order), the typed refusal a defective table
 * surfaces, and the throwaway artifact's own cleanup.
 *
 * @internal
 */
#[CoversClass(RouteInspectionBoot::class)]
final class RouteInspectionBootTest extends TestCase
{
    /**
     * The boot answers the fixture's own route table: five entries,
     * the declaration order the fixture spells (the unnamed ping
     * route last, exactly as declared).
     */
    public function testTheBootAnswersTheFixtureRouteTable(): void
    {
        $router = RouteInspectionBoot::router($this->fixtureDirectory());

        self::assertSame(
            ['/users/{id}', '/users', '/admin/reports', '/legacy/status', '/ping'],
            array_map(
                static fn (CompiledRoute $compiledRoute): string => $compiledRoute->path()->pattern(),
                $router->tree()->routes(),
            ),
        );
    }

    /**
     * A defective route table surfaces the configuration system's own
     * typed refusal -- the boot is the same validation the runtime
     * rides, never a parallel parse with its own error shape.
     */
    public function testADefectiveTableSurfacesTheTypedRefusal(): void
    {
        $configDirectory = sys_get_temp_dir() . '/zef-route-boot-' . uniqid('', false);
        mkdir($configDirectory, 0o777, true);
        file_put_contents(
            $configDirectory . '/routes.php',
            '<?php return [["path" => "no-slash", "method" => ["GET"], "handler" => "X::y"]];',
        );

        try {
            RouteInspectionBoot::router($configDirectory);
            self::fail("A defective route table must surface the boot's typed refusal.");
        } catch (ConfigException $configException) {
            self::assertStringContainsString(
                'The route path "no-slash" must start with a slash.',
                $configException->getMessage(),
            );
        } finally {
            unlink($configDirectory . '/routes.php');
            rmdir($configDirectory);
        }
    }

    /**
     * The throwaway container artifact never outlives the boot: the
     * temporary directory carries no analysis artifact afterwards,
     * whether the boot answered or refused.
     */
    public function testTheThrowawayArtifactNeverOutlivesTheBoot(): void
    {
        $before = glob(sys_get_temp_dir() . '/zef-container-inventory-*');
        self::assertIsArray($before);

        RouteInspectionBoot::router($this->fixtureDirectory());

        $after = glob(sys_get_temp_dir() . '/zef-container-inventory-*');
        self::assertIsArray($after);
        self::assertSame($before, $after);
    }

    /**
     * An AOT-enabled boot dumps the artifact the boot itself wired,
     * and the boot's cleanup still removes it -- the finally runs
     * after the answer, not before it (the container inventory
     * lane's own discipline, spelled for the route boot).
     */
    public function testAnAotDumpingBootStillCleansItsOwnArtifactUp(): void
    {
        $configDirectory = sys_get_temp_dir() . '/zef-route-boot-aot-' . uniqid('', false);
        mkdir($configDirectory, 0o777, true);
        file_put_contents(
            $configDirectory . '/routes.php',
            '<?php return [["path" => "/ping", "method" => ["GET"], "handler" => "PingController::pong"]];',
        );
        file_put_contents(
            $configDirectory . '/aot.php',
            '<?php return ["enabled" => true];',
        );

        $before = glob(sys_get_temp_dir() . '/zef-container-inventory-*');
        self::assertIsArray($before);

        try {
            $router = RouteInspectionBoot::router($configDirectory);

            self::assertSame(
                ['/ping'],
                array_map(
                    static fn (CompiledRoute $compiledRoute): string => $compiledRoute->path()->pattern(),
                    $router->tree()->routes(),
                ),
            );

            $after = glob(sys_get_temp_dir() . '/zef-container-inventory-*');
            self::assertIsArray($after);
            self::assertSame($before, $after);
        } finally {
            unlink($configDirectory . '/routes.php');
            unlink($configDirectory . '/aot.php');
            rmdir($configDirectory);
        }
    }

    /**
     * @return non-empty-string the fixture configuration directory
     */
    private function fixtureDirectory(): string
    {
        return \dirname(__DIR__, 2) . '/tests/Scripts/fixtures/configs';
    }
}
