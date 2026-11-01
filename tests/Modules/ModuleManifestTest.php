<?php

declare(strict_types=1);

namespace Zef\Test\Modules;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Config\Schema\ConfigSchema;
use Zef\Framework\Config\Schema\SchemaValueRules;
use Zef\Framework\Config\Schema\SchemaViolation;
use Zef\Framework\Modules\ModuleDeclaration;
use Zef\Framework\Modules\ModuleException;
use Zef\Framework\Modules\ModuleExceptionSpelling;
use Zef\Framework\Modules\ModuleManifest;
use Zef\Framework\Modules\ModuleMetadata;

/**
 * The manifest grammar: the `module.php` payload validated by the
 * configuration layer's own schema engine -- the reuse that engine's
 * docblock promised -- with the engine's own grammars refining the kinds.
 * An unknown key or a wrong kind is a violation naming the field; a
 * well-shaped payload whose version or name breaks the stricter grammar is
 * refused by the identity's own construction; and the raw `config_schema`
 * spelling rides the declaration untouched, because only the discovery
 * fold knows the directory it resolves against.
 *
 * @internal
 */
#[CoversClass(ModuleManifest::class)]
#[CoversClass(ModuleDeclaration::class)]
#[CoversClass(ModuleMetadata::class)]
#[CoversClass(ConfigSchema::class)]
#[CoversClass(SchemaValueRules::class)]
#[CoversClass(SchemaViolation::class)]
#[CoversClass(ModuleExceptionSpelling::class)]
#[CoversClass(ModuleException::class)]
final class ModuleManifestTest extends TestCase
{
    public function testTheRoadmapSketchParses(): void
    {
        $declaration = ModuleManifest::parse([
            'name' => 'zef/payment',
            'version' => '^1.0',
            'requires' => ['zef/database', 'zef/events'],
            'class' => 'Zef\Modules\Payment\PaymentModule',
            'config_schema' => './config/schema.php',
        ]);

        self::assertSame('Zef\Modules\Payment\PaymentModule', $declaration->class);
        self::assertSame('zef/payment', $declaration->metadata->name);
        self::assertSame('^1.0', $declaration->metadata->version);
        self::assertSame(['zef/database', 'zef/events'], $declaration->metadata->requires);
        self::assertSame('./config/schema.php', $declaration->configSchema);
    }

    public function testAMinimalManifestParsesWithoutSchemaOrRequires(): void
    {
        $declaration = ModuleManifest::parse([
            'name' => 'zef/payment',
            'version' => '1.0.0',
            'class' => 'Zef\Modules\Payment\PaymentModule',
        ]);

        self::assertSame([], $declaration->metadata->requires);
        self::assertNull($declaration->configSchema);
    }

    public function testAPayloadThatIsNotAnArrayIsRefused(): void
    {
        $this->expectException(ModuleException::class);
        $this->expectExceptionMessageIsOrContains('The module manifest must return an array; string arrived.');

        ModuleManifest::parse('not-an-array');
    }

    public function testAMissingNameIsRefusedByField(): void
    {
        $this->expectException(ModuleException::class);
        $this->expectExceptionMessageIs(
            'The module manifest carries violations: name: expected a non-empty string, got missing.',
        );

        ModuleManifest::parse([
            'version' => '^1.0',
            'class' => 'Zef\Modules\Payment\PaymentModule',
        ]);
    }

    public function testAMissingVersionIsRefusedByField(): void
    {
        $this->expectException(ModuleException::class);
        $this->expectExceptionMessageIs(
            'The module manifest carries violations: version: expected a non-empty string, got missing.',
        );

        ModuleManifest::parse([
            'name' => 'zef/payment',
            'class' => 'Zef\Modules\Payment\PaymentModule',
        ]);
    }

    public function testAMissingClassIsRefusedByField(): void
    {
        $this->expectException(ModuleException::class);
        $this->expectExceptionMessageIs(
            'The module manifest carries violations: class: expected a non-empty string, got missing.',
        );

        ModuleManifest::parse([
            'name' => 'zef/payment',
            'version' => '^1.0',
        ]);
    }

    public function testAnUnknownKeyIsRefusedWithTheKnownList(): void
    {
        $this->expectException(ModuleException::class);
        $this->expectExceptionMessageIs(
            'The module manifest carries violations: routess: expected one of '
            . 'name, version, requires, class, config_schema, routes, got an unexpected key.',
        );

        ModuleManifest::parse([
            'name' => 'zef/payment',
            'version' => '^1.0',
            'class' => 'Zef\Modules\Payment\PaymentModule',
            'routess' => [],
        ]);
    }

    public function testTheRoutesKeyRidesTheSchemaAsAnOptionalString(): void
    {
        $declaration = ModuleManifest::parse([
            'name' => 'zef/payment',
            'version' => '^1.0',
            'class' => 'Zef\Modules\Payment\PaymentModule',
            'routes' => './config/routes.php',
        ]);

        self::assertSame('./config/routes.php', $declaration->routes);
        self::assertNull($declaration->configSchema);
    }

    public function testARequiresValueThatIsNotAListIsRefusedByKind(): void
    {
        $this->expectException(ModuleException::class);
        $this->expectExceptionMessageIsOrContains('The module manifest carries violations');

        ModuleManifest::parse([
            'name' => 'zef/payment',
            'version' => '^1.0',
            'requires' => 'zef/database',
            'class' => 'Zef\Modules\Payment\PaymentModule',
        ]);
    }

    public function testAVersionBeyondTheKindIsRefusedByTheGrammar(): void
    {
        $this->expectException(ModuleException::class);
        $this->expectExceptionMessageIsOrContains("The module version 'one' breaks the grammar");

        ModuleManifest::parse([
            'name' => 'zef/payment',
            'version' => 'one',
            'class' => 'Zef\Modules\Payment\PaymentModule',
        ]);
    }

    public function testAnUnqualifiedClassIsRefusedByTheDeclaration(): void
    {
        $this->expectException(ModuleException::class);
        $this->expectExceptionMessageIsOrContains("The module class spelling 'PaymentModule' must be a qualified");

        ModuleManifest::parse([
            'name' => 'zef/payment',
            'version' => '^1.0',
            'class' => 'PaymentModule',
        ]);
    }
}
