<?php

declare(strict_types=1);

namespace Zef\Test\Modules;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Config\Config;
use Zef\Framework\Config\ConfigException;
use Zef\Framework\Config\Schema\RouteSchema;
use Zef\Framework\ModuleManifestLoader;
use Zef\Framework\ModuleRoster;
use Zef\Framework\ModuleRouteEntries;
use Zef\Framework\ModuleRouteTable;
use Zef\Framework\Modules\ModuleDeclaration;
use Zef\Framework\Modules\ModuleException;
use Zef\Framework\Modules\ModuleMetadata;
use Zef\Framework\Modules\ModuleName;
use Zef\Framework\Modules\ModuleRouteRefusals;
use Zef\Test\Modules\Fixtures\RoutesModule;

/**
 * The route extension point's consumption fold: the merged table, the
 * per-module prefix, the honest identity posture and every refusal's
 * sentence.
 *
 * @internal
 */
#[CoversClass(ModuleRouteTable::class)]
#[CoversClass(ModuleRouteEntries::class)]
#[CoversClass(ModuleRouteRefusals::class)]
#[CoversClass(ModuleRoster::class)]
#[CoversClass(ModuleManifestLoader::class)]
#[CoversClass(ModuleDeclaration::class)]
#[CoversClass(ModuleMetadata::class)]
#[CoversClass(ModuleName::class)]
#[CoversClass(RouteSchema::class)]
#[CoversClass(ConfigException::class)]
#[CoversClass(ModuleException::class)]
final class ModuleRouteTableTest extends TestCase
{
    public function testARosterWithoutDeclaredRoutesLeavesTheRepositoryVerbatim(): void
    {
        $config = new Config(['routes' => [['path' => '/host', 'method' => ['GET'], 'handler' => 'H@h']]]);

        $augmented = new ModuleRouteTable()->augmented($config, $this->roster([]));

        self::assertSame($config, $augmented);
    }

    public function testTheModuleEntriesJoinPrefixedAfterTheHostTable(): void
    {
        $config = new Config(['routes' => [['path' => '/host', 'method' => ['GET'], 'handler' => 'H@h']]]);

        $augmented = new ModuleRouteTable()->augmented($config, $this->roster([
            $this->stagedRoutes([['path' => '/orders', 'method' => ['GET'], 'handler' => 'S@i']]),
        ]));

        self::assertSame(
            [
                ['path' => '/host', 'method' => ['GET'], 'handler' => 'H@h'],
                ['path' => '/shop/orders', 'method' => ['GET'], 'handler' => 'S@i'],
            ],
            $augmented->get('routes'),
        );
    }

    public function testAPathWithoutItsLeadingSlashIsPrefixedCleanly(): void
    {
        $augmented = new ModuleRouteTable()->augmented(new Config(), $this->roster([
            $this->stagedRoutes([['path' => 'cart', 'method' => ['POST'], 'handler' => 'C@s']]),
        ]));

        self::assertSame(
            [['path' => '/shop/cart', 'method' => ['POST'], 'handler' => 'C@s']],
            $augmented->get('routes'),
        );
    }

    public function testTheRoutesKeyIsCreatedWhenTheHostDeclaresNone(): void
    {
        $augmented = new ModuleRouteTable()->augmented(new Config(), $this->roster([
            $this->stagedRoutes([['path' => '/orders', 'method' => ['GET'], 'handler' => 'S@i']]),
        ]));

        self::assertSame(
            [['path' => '/shop/orders', 'method' => ['GET'], 'handler' => 'S@i']],
            $augmented->get('routes'),
        );
    }

    public function testTheHostMapAddressingRidesIntoTheMergedTable(): void
    {
        $config = new Config(['routes' => ['host' => ['path' => '/host', 'method' => ['GET'], 'handler' => 'H@h']]]);

        $augmented = new ModuleRouteTable()->augmented($config, $this->roster([
            $this->stagedRoutes([['path' => '/orders', 'method' => ['GET'], 'handler' => 'S@i']]),
        ]));

        self::assertSame(
            [
                'host' => ['path' => '/host', 'method' => ['GET'], 'handler' => 'H@h'],
                ['path' => '/shop/orders', 'method' => ['GET'], 'handler' => 'S@i'],
            ],
            $augmented->get('routes'),
        );
    }

    public function testASecondModuleJoinsInRosterOrderUnderItsOwnPrefix(): void
    {
        $augmented = new ModuleRouteTable()->augmented(new Config(), $this->roster([
            $this->stagedRoutes([['path' => '/orders', 'method' => ['GET'], 'handler' => 'S@i']]),
            $this->stagedRoutes([['path' => '/audit', 'method' => ['GET'], 'handler' => 'A@i']], 'test/audit'),
        ]));

        self::assertSame(
            [
                ['path' => '/shop/orders', 'method' => ['GET'], 'handler' => 'S@i'],
                ['path' => '/audit/audit', 'method' => ['GET'], 'handler' => 'A@i'],
            ],
            $augmented->get('routes'),
        );
    }

    public function testAHostRoutesTableThatIsNotAnArrayRefusesTheFold(): void
    {
        $config = new Config(['routes' => 'nope']);

        $this->expectException(ConfigException::class);
        $this->expectExceptionMessageIsOrContains('must be a list of route entries');

        new ModuleRouteTable()->augmented($config, $this->roster([
            $this->stagedRoutes([['path' => '/orders', 'method' => ['GET'], 'handler' => 'S@i']]),
        ]));
    }

    public function testAMissingRoutesFileRefusesWithTheModuleNamed(): void
    {
        $missing = sys_get_temp_dir() . '/zef-route-missing-' . uniqid('', false) . '.php';

        $this->expectException(ModuleException::class);
        $this->expectExceptionMessageIsOrContains(
            "The module 'test/shop' declares the routes file its boot cannot load",
        );

        new ModuleRouteTable()->augmented(new Config(), $this->roster([
            new ModuleDeclaration(
                RoutesModule::class,
                new ModuleMetadata('test/shop', '1.0.0'),
                null,
                $missing,
            ),
        ]));
    }

    public function testASchemaViolatingEntryRefusesWithTheModuleNamed(): void
    {
        $bad = sys_get_temp_dir() . '/zef-route-bad-' . uniqid('', false) . '.php';
        file_put_contents(
            $bad,
            "<?php return [['path' => '/orders', 'methods' => ['GET'], 'handler' => 'S@i']];",
        );

        $this->expectException(ModuleException::class);
        $this->expectExceptionMessageIsOrContains(
            "The module 'test/shop' declares a routes file the Standard Route Schema refuses",
        );

        new ModuleRouteTable()->augmented(new Config(), $this->roster([
            new ModuleDeclaration(
                RoutesModule::class,
                new ModuleMetadata('test/shop', '1.0.0'),
                null,
                $bad,
            ),
        ]));
    }

    /**
     * @param list<ModuleDeclaration> $declarations
     */
    private function roster(array $declarations): ModuleRoster
    {
        $moduleRoster = new ModuleRoster(new Config());

        foreach ($declarations as $declaration) {
            $moduleRoster->add($declaration, new RoutesModule());
        }

        return $moduleRoster;
    }

    /**
     * @param list<array<string, mixed>> $entries
     */
    private function stagedRoutes(array $entries, string $module = 'test/shop'): ModuleDeclaration
    {
        $root = sys_get_temp_dir() . '/zef-route-table-' . uniqid('', false);
        mkdir($root, 0o777, true);

        $routes = $root . '/routes.php';
        file_put_contents($routes, '<?php return ' . var_export($entries, true) . ';');

        return new ModuleDeclaration(
            RoutesModule::class,
            new ModuleMetadata($module, '1.0.0'),
            null,
            $routes,
        );
    }
}
