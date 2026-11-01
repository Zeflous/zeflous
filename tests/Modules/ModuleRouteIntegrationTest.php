<?php

declare(strict_types=1);

namespace Zef\Test\Modules;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\ApplicationBootstrapper;
use Zef\Framework\Config\Config;
use Zef\Framework\FrameworkDefaults;
use Zef\Framework\ModuleRouteTable;
use Zef\Test\Modules\Fixtures\RoutesModule;

/**
 * The route extension point riding the one-call boot: a module's declared
 * routes file joins the frozen repository's `routes` table (prefixed with
 * the module name's own segment, appended after the host's), the
 * `framework.config` service answers the same merged repository the
 * kernel carries, and a module-less boot rides verbatim.
 *
 * @internal
 */
#[CoversClass(ApplicationBootstrapper::class)]
#[CoversClass(ModuleRouteTable::class)]
#[CoversClass(RoutesModule::class)]
final class ModuleRouteIntegrationTest extends TestCase
{
    private const string FIXTURE_MANIFESTS = __DIR__ . '/fixtures';

    public function testAModuleRoutesFileJoinsTheBootedRepositoryUnderItsOwnPrefix(): void
    {
        $root = $this->stageConfigs([
            'modules.php' => [self::FIXTURE_MANIFESTS . '/with-routes/module.php'],
            'routes.php' => [['path' => '/host', 'method' => ['GET'], 'handler' => 'H@h']],
        ]);

        $kernel = new ApplicationBootstrapper()->boot($root);

        self::assertSame(
            [
                ['path' => '/host', 'method' => ['GET'], 'handler' => 'H@h'],
                [
                    'path' => '/shop/orders',
                    'method' => ['GET'],
                    'handler' => 'ShopOrdersController@index',
                    'name' => 'shop.orders',
                ],
                ['path' => '/shop/cart', 'method' => ['POST'], 'handler' => 'ShopCartController@store'],
            ],
            $kernel->config()->get('routes'),
        );
        $serviceConfig = $kernel->container()->get(FrameworkDefaults::CONFIG_SERVICE);
        self::assertInstanceOf(Config::class, $serviceConfig);
        self::assertSame(
            $kernel->config()->get('routes'),
            $serviceConfig->get('routes'),
        );
    }

    public function testAModulelessBootLeavesTheRoutesTableVerbatim(): void
    {
        $host = [['path' => '/host', 'method' => ['GET'], 'handler' => 'H@h']];
        $root = $this->stageConfigs(['routes.php' => $host]);

        $kernel = new ApplicationBootstrapper()->boot($root);

        self::assertSame($host, $kernel->config()->get('routes'));
    }

    /**
     * Stages a throwaway configuration directory whose every file returns
     * the given array -- the file-equals-key convention's own spelling.
     *
     * @param array<non-empty-string, mixed> $files
     *
     * @return non-empty-string
     */
    private function stageConfigs(array $files): string
    {
        $root = sys_get_temp_dir() . '/zef-route-boot-' . uniqid('', false);
        mkdir($root, 0o777, true);

        foreach ($files as $name => $payload) {
            file_put_contents(
                $root . '/' . $name,
                '<?php return ' . var_export($payload, true) . ';',
            );
        }

        return $root;
    }
}
