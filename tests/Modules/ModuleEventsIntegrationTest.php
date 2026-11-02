<?php

declare(strict_types=1);

namespace Zef\Test\Modules;

use InvalidArgumentException;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\ApplicationBootstrapper;
use Zef\Framework\Config\Config;
use Zef\Framework\Config\ConfigBootstrapper;
use Zef\Framework\Config\Schema\EventSchema;
use Zef\Framework\ContainerModuleRegistrar;
use Zef\Framework\Event\EventDispatcher;
use Zef\Framework\Event\EventName;
use Zef\Framework\EventWiring;
use Zef\Framework\FrameworkDefaults;
use Zef\Framework\ModuleBootstrapper;
use Zef\Framework\ModuleClaims;
use Zef\Framework\ModuleContextFactory;
use Zef\Framework\ModuleEventsEntries;
use Zef\Framework\ModuleEventsTable;
use Zef\Framework\ModuleManifestLoader;
use Zef\Framework\ModuleResolver;
use Zef\Framework\ModuleRoster;
use Zef\Framework\Modules\ModuleConfigRefusals;
use Zef\Framework\Modules\ModuleConfigSchema;
use Zef\Framework\Modules\ModuleConstructions;
use Zef\Framework\Modules\ModuleContext;
use Zef\Framework\Modules\ModuleDeclaration;
use Zef\Framework\Modules\ModuleEntries;
use Zef\Framework\Modules\ModuleEventsRefusals;
use Zef\Framework\Modules\ModuleException;
use Zef\Framework\Modules\ModuleManifest;
use Zef\Framework\Modules\ModuleMetadata;
use Zef\Framework\Modules\ModuleName;
use Zef\Framework\Modules\ModuleOrder;
use Zef\Framework\Modules\ModuleOrderBook;
use Zef\Framework\Modules\ModuleOrderRefusals;
use Zef\Framework\Modules\ModuleRequires;
use Zef\Framework\Modules\ModuleSchemaRules;
use Zef\Framework\Modules\ModuleVersion;
use Zef\Test\Modules\Fixtures\EventsModule;
use Zef\Test\Modules\Fixtures\FixtureLog;

/**
 * The event extension point riding the one-call boot: a module's declared
 * events file joins the frozen repository's `events.listen` table
 * (appended after the host's own, the names verbatim), the
 * `framework.config` service answers the same merged repository the
 * kernel carries, the wired dispatcher's reserved registration answers
 * the joined declarations at its first resolution, and a module-less
 * boot rides verbatim.
 *
 * @internal
 */
#[CoversClass(ApplicationBootstrapper::class)]
#[CoversClass(EventWiring::class)]
#[CoversClass(ModuleBootstrapper::class)]
#[CoversClass(ModuleRoster::class)]
#[CoversClass(ModuleResolver::class)]
#[CoversClass(ModuleManifestLoader::class)]
#[CoversClass(ModuleManifest::class)]
#[CoversClass(ModuleDeclaration::class)]
#[CoversClass(ModuleMetadata::class)]
#[CoversClass(ModuleName::class)]
#[CoversClass(ModuleVersion::class)]
#[CoversClass(ModuleRequires::class)]
#[CoversClass(ModuleEntries::class)]
#[CoversClass(ModuleOrder::class)]
#[CoversClass(ModuleOrderBook::class)]
#[CoversClass(ModuleOrderRefusals::class)]
#[CoversClass(ModuleConstructions::class)]
#[CoversClass(ContainerModuleRegistrar::class)]
#[CoversClass(ModuleClaims::class)]
#[CoversClass(ModuleContextFactory::class)]
#[CoversClass(ModuleConfigSchema::class)]
#[CoversClass(ModuleSchemaRules::class)]
#[CoversClass(ModuleConfigRefusals::class)]
#[CoversClass(ModuleContext::class)]
#[CoversClass(ModuleEventsTable::class)]
#[CoversClass(ModuleEventsEntries::class)]
#[CoversClass(ModuleEventsRefusals::class)]
#[CoversClass(ConfigBootstrapper::class)]
#[CoversClass(EventSchema::class)]
#[CoversClass(ModuleException::class)]
final class ModuleEventsIntegrationTest extends TestCase
{
    private const string FIXTURE_MANIFESTS = __DIR__ . '/fixtures';

    #[Override]
    protected function setUp(): void
    {
        FixtureLog::reset();
    }

    public function testAModuleEventsFileJoinsTheBootedRepositoryAfterTheHostEntries(): void
    {
        $root = $this->stageConfigs([
            'modules.php' => [self::FIXTURE_MANIFESTS . '/with-events/module.php'],
            'events.php' => [
                'deferred' => ['store' => '', 'path' => '', 'capacity' => 1024],
                'listen' => [
                    ['name' => 'host.pinged', 'fields' => ['count' => 'int']],
                ],
            ],
        ]);

        $kernel = new ApplicationBootstrapper()->boot($root);

        self::assertSame(
            [
                ['name' => 'host.pinged', 'fields' => ['count' => 'int']],
                [
                    'name' => 'shop.order.paid',
                    'fields' => ['orderId' => 'int', 'amount' => 'int', 'currency' => 'string'],
                    'listeners' => ['test/shop/order-paid.listener'],
                ],
                ['name' => 'shop.order.dispatched', 'fields' => ['carrier' => 'string']],
            ],
            $kernel->config()->get('events.listen'),
        );

        $serviceConfig = $kernel->container()->get(FrameworkDefaults::CONFIG_SERVICE);
        self::assertInstanceOf(Config::class, $serviceConfig);
        self::assertSame(
            $kernel->config()->get('events.listen'),
            $serviceConfig->get('events.listen'),
        );
    }

    public function testTheWiredDispatcherAnswersTheJoinedDeclarationsAndDelivers(): void
    {
        $root = $this->stageConfigs([
            'modules.php' => [self::FIXTURE_MANIFESTS . '/with-events/module.php'],
        ]);

        $kernel = new ApplicationBootstrapper()->boot($root);
        $eventDispatcher = $kernel->container()->get(EventWiring::DISPATCHER_SERVICE);

        self::assertInstanceOf(EventDispatcher::class, $eventDispatcher);

        $outcome = $eventDispatcher->dispatch(
            new EventName('shop.order.paid'),
            ['orderId' => 7, 'amount' => 1250, 'currency' => 'EUR'],
        );

        self::assertSame(1, $outcome->deliveredTo());
        self::assertSame(
            ['listener:shop.order.paid@{"orderId":7,"amount":1250,"currency":"EUR"}'],
            FixtureLog::events(),
        );
    }

    public function testTheWiredDispatcherAnswersTheSameInstanceOnEveryResolution(): void
    {
        $root = $this->stageConfigs([
            'modules.php' => [self::FIXTURE_MANIFESTS . '/with-events/module.php'],
        ]);

        $kernel = new ApplicationBootstrapper()->boot($root);

        $first = $kernel->container()->get(EventWiring::DISPATCHER_SERVICE);
        $second = $kernel->container()->get(EventWiring::DISPATCHER_SERVICE);

        self::assertSame($first, $second);
    }

    public function testADefinitionOnlyModuleEventAnswersZeroDeliveries(): void
    {
        $root = $this->stageConfigs([
            'modules.php' => [self::FIXTURE_MANIFESTS . '/with-events/module.php'],
        ]);

        $kernel = new ApplicationBootstrapper()->boot($root);
        $eventDispatcher = $kernel->container()->get(EventWiring::DISPATCHER_SERVICE);
        self::assertInstanceOf(EventDispatcher::class, $eventDispatcher);

        $outcome = $eventDispatcher->dispatch(new EventName('shop.order.dispatched'), ['carrier' => 'DHL']);

        self::assertSame(0, $outcome->deliveredTo());
        self::assertSame([], FixtureLog::events());
    }

    public function testAModulelessBootAnswersABareWiredDispatcher(): void
    {
        $root = $this->stageConfigs(['routes.php' => []]);

        $kernel = new ApplicationBootstrapper()->boot($root);
        $eventDispatcher = $kernel->container()->get(EventWiring::DISPATCHER_SERVICE);

        self::assertInstanceOf(EventDispatcher::class, $eventDispatcher);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains("'shop.order.paid'");

        $eventDispatcher->dispatch(new EventName('shop.order.paid'), ['orderId' => 1]);
    }

    public function testAHostsOwnListenDeclarationsRideTheWiredDispatcher(): void
    {
        $root = $this->stageConfigs([
            'events.php' => [
                'listen' => [
                    [
                        'name' => 'host.pinged',
                        'fields' => ['count' => 'int'],
                        'listeners' => ['host.ping.listener'],
                    ],
                ],
            ],
            'services.php' => [
                'host.ping.listener' => [
                    'class' => Fixtures\OrderPaidListener::class,
                ],
            ],
        ]);

        $kernel = new ApplicationBootstrapper()->boot($root);
        $eventDispatcher = $kernel->container()->get(EventWiring::DISPATCHER_SERVICE);
        self::assertInstanceOf(EventDispatcher::class, $eventDispatcher);

        $outcome = $eventDispatcher->dispatch(new EventName('host.pinged'), ['count' => 3]);

        self::assertSame(1, $outcome->deliveredTo());
        self::assertSame(
            ['listener:host.pinged@{"count":3}'],
            FixtureLog::events(),
        );
    }

    public function testAHostRebindOfTheDispatcherServiceWins(): void
    {
        $root = $this->stageConfigs([
            'modules.php' => [self::FIXTURE_MANIFESTS . '/with-events/module.php'],
            'services.php' => [
                EventWiring::DISPATCHER_SERVICE => [
                    'factory' => \Zef\Test\Support\Events\HostComposedDispatcherFactory::class . '::compose',
                    'arguments' => [],
                ],
            ],
        ]);

        $kernel = new ApplicationBootstrapper()->boot($root);
        $eventDispatcher = $kernel->container()->get(EventWiring::DISPATCHER_SERVICE);

        self::assertInstanceOf(EventDispatcher::class, $eventDispatcher);

        // The host's own composition won over the boot's wired
        // registration: the module's joined declaration is NOT answered
        // -- the reserved-services precedence the composition root's
        // registration rides (a host's same-identifier rebind wins).
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains("'shop.order.paid'");

        $eventDispatcher->dispatch(new EventName('shop.order.paid'), [
            'orderId' => 7,
            'amount' => 1250,
            'currency' => 'EUR',
        ]);
    }

    public function testAHostsMalformedListenTableHaltsTheBoot(): void
    {
        $root = $this->stageConfigs([
            'events.php' => [
                'listen' => [
                    ['name' => 'host.pinged', 'fieldss' => ['count' => 'int']],
                ],
            ],
        ]);

        $this->expectException(\Zef\Framework\Config\ConfigException::class);
        $this->expectExceptionMessageIsOrContains('fieldss');

        new ApplicationBootstrapper()->boot($root);
    }

    public function testAHostsScalarListenValueHaltsTheBoot(): void
    {
        $root = $this->stageConfigs([
            'events.php' => ['listen' => 'nope'],
        ]);

        $this->expectException(\Zef\Framework\Config\ConfigException::class);
        $this->expectExceptionMessageIsOrContains(
            'The "events.listen" configuration must be a list of event declarations.',
        );

        new ApplicationBootstrapper()->boot($root);
    }

    public function testAListenerTheModuleNeverRegisteredRefusesAtTheResolution(): void
    {
        $moduleRoot = sys_get_temp_dir() . '/zef-events-unregistered-' . uniqid('', false);
        mkdir($moduleRoot . '/config', 0o777, true);

        file_put_contents(
            $moduleRoot . '/module.php',
            '<?php return '
            . var_export([
                'name' => 'test/shop',
                'version' => '1.0.0',
                'class' => EventsModule::class,
                'events' => './config/events.php',
            ], true)
            . ';',
        );
        file_put_contents(
            $moduleRoot . '/config/events.php',
            '<?php return [['
            . "'name' => 'shop.order.paid', "
            . "'fields' => ['orderId' => 'int'], "
            . "'listeners' => ['test/shop/never-registered']"
            . ']];',
        );

        $root = $this->stageConfigs([
            'modules.php' => [$moduleRoot . '/module.php'],
        ]);

        $kernel = new ApplicationBootstrapper()->boot($root);

        $this->expectException(\Zef\Framework\Container\NotFoundException::class);
        $this->expectExceptionMessageIsOrContains('test/shop/never-registered');

        $kernel->container()->get(EventWiring::DISPATCHER_SERVICE);
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
        $root = sys_get_temp_dir() . '/zef-events-boot-' . uniqid('', false);
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
