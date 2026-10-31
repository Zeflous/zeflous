<?php

declare(strict_types=1);

namespace Zef\Test\Modules;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Config\Config;
use Zef\Framework\Config\ConfigException;
use Zef\Framework\Config\Schema\ConfigSchema;
use Zef\Framework\Config\Schema\SchemaValueRules;
use Zef\Framework\Config\Schema\SchemaViolation;
use Zef\Framework\ModuleContextFactory;
use Zef\Framework\ModuleManifestLoader;
use Zef\Framework\Modules\ModuleConfigSchema;
use Zef\Framework\Modules\ModuleContext;
use Zef\Framework\Modules\ModuleDeclaration;
use Zef\Framework\Modules\ModuleException;
use Zef\Framework\Modules\ModuleMetadata;

/**
 * The context assembler: the block the module's dotted name derives is the
 * only block the module sees, a block without a declared schema is
 * refused, a declared schema without a block asserts the empty block, and
 * the schema's own verdicts arrive wrapped with the module named first --
 * the namespacing rule as two enforceable refusals, not a wish.
 *
 * @internal
 */
#[CoversClass(ModuleContextFactory::class)]
#[CoversClass(ModuleManifestLoader::class)]
#[CoversClass(ModuleConfigSchema::class)]
#[CoversClass(ModuleContext::class)]
#[CoversClass(ConfigSchema::class)]
#[CoversClass(SchemaValueRules::class)]
#[CoversClass(SchemaViolation::class)]
#[CoversClass(ConfigException::class)]
#[CoversClass(ModuleException::class)]
final class ModuleContextFactoryTest extends TestCase
{
    private const string FIXTURES = __DIR__ . '/fixtures';

    private ModuleContextFactory $moduleContextFactory;

    #[Override]
    protected function setUp(): void
    {
        $this->moduleContextFactory = new ModuleContextFactory();
    }

    public function testAConfigFreeModuleAnswersTheEmptyBlock(): void
    {
        $context = $this->moduleContextFactory->context($this->declaration('test/plain'), new Config([]));

        self::assertSame('test/plain', $context->name);
        self::assertSame('1.0.0', $context->version);
        self::assertSame([], $context->config);
    }

    public function testABlockWithoutASchemaIsRefused(): void
    {
        $config = new Config(['test' => ['plain' => ['endpoint' => 'http://x']]]);

        $this->expectException(ModuleException::class);
        $this->expectExceptionMessageIsOrContains(
            "The module 'test/plain' carries the configuration block 'test.plain' "
            . 'but declares no config_schema',
        );

        $this->moduleContextFactory->context($this->declaration('test/plain'), $config);
    }

    public function testAValidatedBlockAnswersThroughTheContext(): void
    {
        $config = new Config(['test' => ['echo' => ['endpoint' => 'http://x', 'retries' => ['one']]]]);
        $declaration = $this->declaration('test/echo', self::FIXTURES . '/with-schema/config/schema.php');

        $context = $this->moduleContextFactory->context($declaration, $config);

        self::assertTrue($context->has('endpoint'));
        self::assertSame('http://x', $context->get('endpoint'));
        self::assertSame(['one'], $context->get('retries'));
    }

    public function testADeclaredSchemaWithoutABlockAssertsTheEmptyBlock(): void
    {
        $declaration = $this->declaration('test/echo', self::FIXTURES . '/with-schema/config/schema.php');

        $this->expectException(ModuleException::class);
        $this->expectExceptionMessageIsOrContains(
            "The module 'test/echo' failed its configuration schema: Configuration failed "
            . 'schema validation: endpoint: expected a non-empty string, got missing.',
        );

        $this->moduleContextFactory->context($declaration, new Config([]));
    }

    public function testABlockThatFailsItsSchemaIsRefusedWithTheFieldNamed(): void
    {
        $config = new Config(['test' => ['echo' => ['retries' => ['one']]]]);
        $declaration = $this->declaration('test/echo', self::FIXTURES . '/with-schema/config/schema.php');

        $this->expectException(ModuleException::class);
        $this->expectExceptionMessageIsOrContains(
            "The module 'test/echo' failed its configuration schema",
        );

        $this->moduleContextFactory->context($declaration, $config);
    }

    public function testANonArrayBlockIsRefusedWithItsType(): void
    {
        $config = new Config(['test' => ['plain' => 'nope']]);

        $this->expectException(ModuleException::class);
        $this->expectExceptionMessageIsOrContains(
            "The configuration block 'test.plain' of the module 'test/plain' must be an array; string arrived.",
        );

        $this->moduleContextFactory->context($this->declaration('test/plain'), $config);
    }

    public function testAListShapedBlockIsRefusedAsTheIntegerFieldItIs(): void
    {
        $config = new Config(['test' => ['echo' => ['one', 'two']]]);

        $this->expectException(ModuleException::class);
        $this->expectExceptionMessageIsOrContains(
            "The configuration block 'test.echo' of the module 'test/echo' must "
            . 'be a map of string fields; an int field arrived.',
        );

        $this->moduleContextFactory->context($this->declaration('test/echo'), $config);
    }

    public function testAMissingSchemaFileIsRefusedByTheLoader(): void
    {
        $declaration = $this->declaration('test/echo', self::FIXTURES . '/nowhere/schema.php');

        $this->expectException(ModuleException::class);
        $this->expectExceptionMessageIsOrContains('does not exist');

        $this->moduleContextFactory->context($declaration, new Config([]));
    }

    public function testAMalformedRuleMapIsRefusedNamingTheModuleAndField(): void
    {
        $declaration = $this->declaration('test/echo', self::FIXTURES . '/ugly-schema/config/schema.php');

        $this->expectException(ModuleException::class);
        $this->expectExceptionMessageIsOrContains(
            "The configuration schema of the module 'test/echo' at '"
            . self::FIXTURES . "/ugly-schema/config/schema.php' carries the malformed rule "
            . "for the field 'endpoint'",
        );

        $this->moduleContextFactory->context($declaration, new Config([]));
    }

    /**
     * @param non-empty-string $schema
     */
    private function declaration(string $name, ?string $schema = null): ModuleDeclaration
    {
        $class = Fixtures\EchoContextModule::class;

        $moduleDeclaration = new ModuleDeclaration($class, new ModuleMetadata($name, '1.0.0'));

        return $moduleDeclaration->withConfigSchema($schema);
    }
}
