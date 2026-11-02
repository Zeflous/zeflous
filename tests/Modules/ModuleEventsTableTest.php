<?php

declare(strict_types=1);

namespace Zef\Test\Modules;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Config\Config;
use Zef\Framework\Config\ConfigException;
use Zef\Framework\Config\Schema\EventSchema;
use Zef\Framework\EventFamilies;
use Zef\Framework\ModuleEventsEntries;
use Zef\Framework\ModuleEventsTable;
use Zef\Framework\ModuleManifestLoader;
use Zef\Framework\ModuleRoster;
use Zef\Framework\Modules\ModuleDeclaration;
use Zef\Framework\Modules\ModuleEventsRefusals;
use Zef\Framework\Modules\ModuleException;
use Zef\Framework\Modules\ModuleMetadata;
use Zef\Test\Modules\Fixtures\EventsModule;

/**
 * The event extension point's consumption fold: the merged table, the
 * honest identity posture, the host-first join order and every refusal's
 * sentence.
 *
 * @internal
 */
#[CoversClass(ModuleEventsTable::class)]
#[CoversClass(ModuleEventsEntries::class)]
#[CoversClass(ModuleEventsRefusals::class)]
#[CoversClass(ModuleRoster::class)]
#[CoversClass(ModuleManifestLoader::class)]
#[CoversClass(ModuleDeclaration::class)]
#[CoversClass(ModuleMetadata::class)]
#[CoversClass(EventSchema::class)]
#[CoversClass(EventFamilies::class)]
#[CoversClass(ConfigException::class)]
#[CoversClass(ModuleException::class)]
final class ModuleEventsTableTest extends TestCase
{
    public function testARosterWithoutDeclaredEventsLeavesTheRepositoryVerbatim(): void
    {
        $config = new Config([
            'events' => [
                'deferred' => ['store' => '', 'path' => '', 'capacity' => 1024],
                'listen' => [$this->declaration('host.pinged', ['count' => 'int'])],
            ],
        ]);

        $augmented = new ModuleEventsTable()->augmented($config, $this->roster([]));

        self::assertSame($config, $augmented);
    }

    public function testTheModuleEntriesJoinAfterTheHostEntriesUnderTheOneKey(): void
    {
        $config = new Config([
            'events' => [
                'deferred' => ['store' => '', 'path' => '', 'capacity' => 1024],
                'listen' => [$this->declaration('host.pinged', ['count' => 'int'])],
            ],
        ]);

        $augmented = new ModuleEventsTable()->augmented($config, $this->roster([
            $this->stagedEvents([
                $this->declaration('shop.order.paid', ['orderId' => 'int']),
            ]),
        ]));

        self::assertSame(
            [
                $this->declaration('host.pinged', ['count' => 'int']),
                $this->declaration('shop.order.paid', ['orderId' => 'int']),
            ],
            $augmented->get('events.listen'),
        );
        self::assertSame(
            ['store' => '', 'path' => '', 'capacity' => 1024],
            $augmented->get('events.deferred'),
        );
    }

    public function testTheListenKeyIsCreatedWhenTheHostDeclaresNone(): void
    {
        $augmented = new ModuleEventsTable()->augmented(new Config(), $this->roster([
            $this->stagedEvents([
                $this->declaration('shop.order.paid', ['orderId' => 'int']),
            ]),
        ]));

        self::assertSame(
            [$this->declaration('shop.order.paid', ['orderId' => 'int'])],
            $augmented->get('events.listen'),
        );
    }

    public function testTheListenKeyIsCreatedWhenTheHostDeclaresOnlyTheDeferredFamily(): void
    {
        $config = new Config([
            'events' => ['deferred' => ['store' => '', 'path' => '', 'capacity' => 1024]],
        ]);

        $augmented = new ModuleEventsTable()->augmented($config, $this->roster([
            $this->stagedEvents([
                $this->declaration('shop.order.paid', ['orderId' => 'int']),
            ]),
        ]));

        self::assertSame(
            [$this->declaration('shop.order.paid', ['orderId' => 'int'])],
            $augmented->get('events.listen'),
        );
        self::assertSame(
            ['store' => '', 'path' => '', 'capacity' => 1024],
            $augmented->get('events.deferred'),
        );
    }

    public function testASecondModuleJoinsInRosterOrder(): void
    {
        $augmented = new ModuleEventsTable()->augmented(new Config(), $this->roster([
            $this->stagedEvents([
                $this->declaration('shop.order.paid', ['orderId' => 'int']),
            ]),
            $this->stagedEvents([
                $this->declaration('audit.trail.appended', ['entry' => 'string']),
            ], 'test/audit'),
        ]));

        self::assertSame(
            [
                $this->declaration('shop.order.paid', ['orderId' => 'int']),
                $this->declaration('audit.trail.appended', ['entry' => 'string']),
            ],
            $augmented->get('events.listen'),
        );
    }

    public function testAModuleWithoutEventsDoesNotStopTheDeclarationsBehindIt(): void
    {
        $augmented = new ModuleEventsTable()->augmented(new Config(), $this->roster([
            new ModuleDeclaration(EventsModule::class, new ModuleMetadata('test/silent', '1.0.0')),
            $this->stagedEvents([
                $this->declaration('shop.order.paid', ['orderId' => 'int']),
            ]),
        ]));

        self::assertSame(
            [$this->declaration('shop.order.paid', ['orderId' => 'int'])],
            $augmented->get('events.listen'),
        );
    }

    public function testTheModuleEntriesRideVerbatimNamesAndAll(): void
    {
        $declared = [
            'name' => 'shop.order.paid',
            'fields' => ['orderId' => 'int', 'currency' => '?string'],
            'listeners' => ['test/shop/order-paid.listener'],
        ];

        $augmented = new ModuleEventsTable()->augmented(new Config(), $this->roster([
            $this->stagedEvents([$declared]),
        ]));

        self::assertSame([$declared], $augmented->get('events.listen'));
    }

    public function testAHostListenTableThatIsNotAnArrayRefusesTheFold(): void
    {
        $config = new Config(['events' => ['listen' => 'nope']]);

        $this->expectException(ConfigException::class);
        $this->expectExceptionMessageIsOrContains('must be a list of event declarations');

        new ModuleEventsTable()->augmented($config, $this->roster([
            $this->stagedEvents([
                $this->declaration('shop.order.paid', ['orderId' => 'int']),
            ]),
        ]));
    }

    public function testAMissingEventsFileRefusesWithTheModuleNamed(): void
    {
        $missing = sys_get_temp_dir() . '/zef-events-missing-' . uniqid('', false) . '.php';

        $this->expectException(ModuleException::class);
        $this->expectExceptionMessageIsOrContains(
            "The module 'test/shop' declares the events file its boot cannot load",
        );

        new ModuleEventsTable()->augmented(new Config(), $this->roster([
            new ModuleDeclaration(
                EventsModule::class,
                new ModuleMetadata('test/shop', '1.0.0'),
                null,
                null,
                $missing,
            ),
        ]));
    }

    public function testASchemaViolatingEntryRefusesWithTheModuleNamed(): void
    {
        $bad = sys_get_temp_dir() . '/zef-events-bad-' . uniqid('', false) . '.php';
        file_put_contents(
            $bad,
            "<?php return [['name' => 'shop.order.paid', 'fieldss' => ['orderId' => 'int']]];",
        );

        $this->expectException(ModuleException::class);
        $this->expectExceptionMessageIsOrContains(
            "The module 'test/shop' declares an events file the Standard Events Schema refuses",
        );

        new ModuleEventsTable()->augmented(new Config(), $this->roster([
            new ModuleDeclaration(
                EventsModule::class,
                new ModuleMetadata('test/shop', '1.0.0'),
                null,
                null,
                $bad,
            ),
        ]));
    }

    public function testAnEventsFileThatIsNotAnArrayRefusesWithTheModuleNamed(): void
    {
        $notAnArray = sys_get_temp_dir() . '/zef-events-scalar-' . uniqid('', false) . '.php';
        file_put_contents($notAnArray, '<?php return "not-an-array";');

        $this->expectException(ModuleException::class);
        $this->expectExceptionMessageIsOrContains(
            "The module 'test/shop' declares the events file its boot cannot load",
        );

        new ModuleEventsTable()->augmented(new Config(), $this->roster([
            new ModuleDeclaration(
                EventsModule::class,
                new ModuleMetadata('test/shop', '1.0.0'),
                null,
                null,
                $notAnArray,
            ),
        ]));
    }

    /**
     * One event declaration in its minimal spelling.
     *
     * @param array<string, string> $fields
     *
     * @return array<string, mixed>
     */
    private function declaration(string $name, array $fields): array
    {
        return ['name' => $name, 'fields' => $fields];
    }

    /**
     * @param list<array<string, mixed>> $entries
     */
    private function stagedEvents(array $entries, string $module = 'test/shop'): ModuleDeclaration
    {
        $root = sys_get_temp_dir() . '/zef-events-table-' . uniqid('', false);
        mkdir($root, 0o777, true);

        $events = $root . '/events.php';
        file_put_contents($events, '<?php return ' . var_export($entries, true) . ';');

        return new ModuleDeclaration(
            EventsModule::class,
            new ModuleMetadata($module, '1.0.0'),
            null,
            null,
            $events,
        );
    }

    /**
     * @param list<ModuleDeclaration> $declarations
     */
    private function roster(array $declarations): ModuleRoster
    {
        $moduleRoster = new ModuleRoster(new Config());

        foreach ($declarations as $declaration) {
            $moduleRoster->add($declaration, new EventsModule());
        }

        return $moduleRoster;
    }
}
