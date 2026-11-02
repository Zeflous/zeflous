<?php

declare(strict_types=1);

namespace Zef\Test\Modules;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Config\ConfigException;
use Zef\Framework\Config\Schema\ConfigSchema;
use Zef\Framework\Config\Schema\SchemaValueRules;
use Zef\Framework\Config\Schema\SchemaViolation;
use Zef\Framework\Modules\ModuleDeclaration;
use Zef\Framework\Modules\ModuleEventsRefusals;
use Zef\Framework\Modules\ModuleException;
use Zef\Framework\Modules\ModuleExceptionSpelling;
use Zef\Framework\Modules\ModuleManifest;
use Zef\Framework\Modules\ModuleMetadata;
use Zef\Framework\Modules\ModuleName;

/**
 * The event extension point's grammar: the manifest's optional `events`
 * key rides the field schema and resolves against the manifest's own
 * directory (the same rule the config_schema and routes paths ride), and
 * the consumption refusals' sentences.
 *
 * @internal
 */
#[CoversClass(ModuleManifest::class)]
#[CoversClass(ModuleDeclaration::class)]
#[CoversClass(ModuleMetadata::class)]
#[CoversClass(ModuleName::class)]
#[CoversClass(ModuleExceptionSpelling::class)]
#[CoversClass(ModuleEventsRefusals::class)]
#[CoversClass(ConfigSchema::class)]
#[CoversClass(SchemaValueRules::class)]
#[CoversClass(SchemaViolation::class)]
#[CoversClass(ConfigException::class)]
#[CoversClass(ModuleException::class)]
final class ModuleEventsExtensionTest extends TestCase
{
    public function testTheEventsKeyRidesTheSchemaAndResolvesAgainstTheManifestDirectory(): void
    {
        $payload = $this->payload(['events' => './config/events.php']);
        $manifest = $this->stagedManifest($payload);

        $declaration = ModuleManifest::parsed($payload, $manifest);

        self::assertSame(\dirname($manifest) . '/config/events.php', $declaration->events);
    }

    public function testARootedEventsSpellingRidesVerbatim(): void
    {
        $payload = $this->payload(['events' => '/abs/events.php']);

        $declaration = ModuleManifest::parsed($payload, $this->stagedManifest($payload));

        self::assertSame('/abs/events.php', $declaration->events);
    }

    public function testARelativeEventsSpellingJoinsTheManifestDirectory(): void
    {
        $payload = $this->payload(['events' => 'events.php']);
        $manifest = $this->stagedManifest($payload);

        $declaration = ModuleManifest::parsed($payload, $manifest);

        self::assertSame(\dirname($manifest) . '/events.php', $declaration->events);
    }

    public function testANonStringEventsKeyIsASchemaViolation(): void
    {
        $payload = $this->payload(['events' => []]);

        $this->expectException(ModuleException::class);
        $this->expectExceptionMessageIsOrContains('violations');

        ModuleManifest::parsed($payload, $this->stagedManifest($payload));
    }

    public function testTheDeclarationCarriesAllThreeOptionalPaths(): void
    {
        $moduleDeclaration = new ModuleDeclaration(
            Fixtures\EventsModule::class,
            new ModuleMetadata('test/shop', '1.0.0'),
        );

        self::assertNull($moduleDeclaration->configSchema);
        self::assertNull($moduleDeclaration->routes);
        self::assertNull($moduleDeclaration->events);

        $withRoutes = $moduleDeclaration->withRoutes('/abs/routes.php');

        self::assertSame('/abs/routes.php', $withRoutes->routes);
        self::assertNull($withRoutes->events);
        self::assertNull($moduleDeclaration->routes);

        $withEvents = $withRoutes->withEvents('/abs/events.php');

        self::assertSame('/abs/events.php', $withEvents->events);
        self::assertSame('/abs/routes.php', $withEvents->routes);
        self::assertNull($withEvents->configSchema);
    }

    public function testAPathRefinementKeepsTheSiblingsItDidNotAnswer(): void
    {
        $moduleDeclaration = new ModuleDeclaration(
            Fixtures\EventsModule::class,
            new ModuleMetadata('test/shop', '1.0.0'),
            '/abs/schema.php',
            '/abs/routes.php',
            '/abs/events.php',
        );

        $refined = $moduleDeclaration->withConfigSchema(null);

        self::assertNull($refined->configSchema);
        self::assertSame('/abs/routes.php', $refined->routes);
        self::assertSame('/abs/events.php', $refined->events);
    }

    public function testTheUnloadableRefusalNamesTheModuleFirst(): void
    {
        $moduleException = new ModuleException("The module events file '/abs/nope.php' does not exist.");

        $wrapped = ModuleEventsRefusals::unloadable('test/shop', $moduleException);

        self::assertSame(
            "The module 'test/shop' declares the events file its boot cannot "
            . "load: The module events file '/abs/nope.php' does not exist.",
            $wrapped->getMessage(),
        );
        self::assertSame($moduleException, $wrapped->getPrevious());
    }

    public function testTheInvalidRefusalNamesTheModuleFirst(): void
    {
        $configException = new ConfigException('The event entry field "names" is unknown.');

        $wrapped = ModuleEventsRefusals::invalid('test/shop', $configException);

        self::assertSame(
            "The module 'test/shop' declares an events file the Standard "
            . 'Events Schema refuses: The event entry field "names" is unknown.',
            $wrapped->getMessage(),
        );
        self::assertSame($configException, $wrapped->getPrevious());
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'name' => 'test/shop',
            'version' => '1.0.0',
            'class' => Fixtures\EventsModule::class,
            ...$overrides,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function stagedManifest(array $payload): string
    {
        $root = sys_get_temp_dir() . '/zef-events-grammar-' . uniqid('', false);
        mkdir($root, 0o777, true);

        $manifest = $root . '/module.php';
        file_put_contents(
            $manifest,
            '<?php return ' . var_export($payload, true) . ';',
        );

        return $manifest;
    }
}
