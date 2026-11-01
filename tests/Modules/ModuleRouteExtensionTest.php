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
use Zef\Framework\Modules\ModuleException;
use Zef\Framework\Modules\ModuleExceptionSpelling;
use Zef\Framework\Modules\ModuleManifest;
use Zef\Framework\Modules\ModuleMetadata;
use Zef\Framework\Modules\ModuleName;
use Zef\Framework\Modules\ModuleRouteRefusals;

/**
 * The route extension point's grammar: the manifest's optional `routes`
 * key rides the field schema and resolves against the manifest's own
 * directory (the same rule the config_schema path rides), the suffix word
 * the prefix derives from, and the consumption refusals' sentences.
 *
 * @internal
 */
#[CoversClass(ModuleManifest::class)]
#[CoversClass(ModuleDeclaration::class)]
#[CoversClass(ModuleMetadata::class)]
#[CoversClass(ModuleName::class)]
#[CoversClass(ModuleExceptionSpelling::class)]
#[CoversClass(ModuleRouteRefusals::class)]
#[CoversClass(ConfigSchema::class)]
#[CoversClass(SchemaValueRules::class)]
#[CoversClass(SchemaViolation::class)]
#[CoversClass(ConfigException::class)]
#[CoversClass(ModuleException::class)]
final class ModuleRouteExtensionTest extends TestCase
{
    public function testTheRoutesKeyRidesTheSchemaAndResolvesAgainstTheManifestDirectory(): void
    {
        $payload = $this->payload(['routes' => './config/routes.php']);
        $manifest = $this->stagedManifest($payload);

        $declaration = ModuleManifest::parsed($payload, $manifest);

        self::assertSame(\dirname($manifest) . '/config/routes.php', $declaration->routes);
    }

    public function testARootedRoutesSpellingRidesVerbatim(): void
    {
        $payload = $this->payload(['routes' => '/abs/routes.php']);

        $declaration = ModuleManifest::parsed($payload, $this->stagedManifest($payload));

        self::assertSame('/abs/routes.php', $declaration->routes);
    }

    public function testARelativeRoutesSpellingJoinsTheManifestDirectory(): void
    {
        $payload = $this->payload(['routes' => 'routes.php']);
        $manifest = $this->stagedManifest($payload);

        $declaration = ModuleManifest::parsed($payload, $manifest);

        self::assertSame(\dirname($manifest) . '/routes.php', $declaration->routes);
    }

    public function testANonStringRoutesKeyIsASchemaViolation(): void
    {
        $payload = $this->payload(['routes' => []]);

        $this->expectException(ModuleException::class);
        $this->expectExceptionMessageIsOrContains('violations');

        ModuleManifest::parsed($payload, $this->stagedManifest($payload));
    }

    public function testAnUnknownManifestKeyIsStillRefused(): void
    {
        $payload = $this->payload(['routess' => '/abs/r.php']);

        $this->expectException(ModuleException::class);
        $this->expectExceptionMessageIsOrContains('violations');

        ModuleManifest::parsed($payload, $this->stagedManifest($payload));
    }

    public function testTheDeclarationCarriesBothOptionalPaths(): void
    {
        $moduleDeclaration = new ModuleDeclaration(
            Fixtures\RoutesModule::class,
            new ModuleMetadata('test/shop', '1.0.0'),
        );

        self::assertNull($moduleDeclaration->configSchema);
        self::assertNull($moduleDeclaration->routes);

        $withRoutes = $moduleDeclaration->withRoutes('/abs/routes.php');

        self::assertSame('/abs/routes.php', $withRoutes->routes);
        self::assertNull($withRoutes->configSchema);
        self::assertNull($moduleDeclaration->routes);
    }

    public function testTheSuffixIsTheNameOwnSecondSegment(): void
    {
        self::assertSame('payment', ModuleName::suffix('zef/payment'));
    }

    public function testTheSuffixRefusesABrokenSpelling(): void
    {
        $this->expectException(ModuleException::class);

        ModuleName::suffix('zef-payment');
    }

    public function testTheUnloadableRefusalNamesTheModuleFirst(): void
    {
        $moduleException = new ModuleException("The module routes file '/abs/nope.php' does not exist.");

        $wrapped = ModuleRouteRefusals::unloadable('test/shop', $moduleException);

        self::assertSame(
            "The module 'test/shop' declares the routes file its boot cannot "
            . "load: The module routes file '/abs/nope.php' does not exist.",
            $wrapped->getMessage(),
        );
        self::assertSame($moduleException, $wrapped->getPrevious());
    }

    public function testTheInvalidRefusalNamesTheModuleFirst(): void
    {
        $configException = new ConfigException('The route entry field "paths" is unknown.');

        $wrapped = ModuleRouteRefusals::invalid('test/shop', $configException);

        self::assertSame(
            "The module 'test/shop' declares a routes file the Standard "
            . 'Route Schema refuses: The route entry field "paths" is unknown.',
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
            'class' => Fixtures\RoutesModule::class,
            ...$overrides,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function stagedManifest(array $payload): string
    {
        $root = sys_get_temp_dir() . '/zef-route-grammar-' . uniqid('', false);
        mkdir($root, 0o777, true);

        $manifest = $root . '/module.php';
        file_put_contents(
            $manifest,
            '<?php return ' . var_export($payload, true) . ';',
        );

        return $manifest;
    }
}
