<?php

declare(strict_types=1);

namespace Zef\Test\Modules;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Config\Schema\ConfigSchema;
use Zef\Framework\Config\Schema\SchemaValueRules;
use Zef\Framework\Config\Schema\SchemaViolation;
use Zef\Framework\Modules\ModuleConfigSchema;
use Zef\Framework\Modules\ModuleException;

/**
 * The module configuration schema's own map: the module-authored rule file
 * is validated fail-closed before it becomes a schema engine -- a
 * non-array or empty payload, a field that is not a name, a rule that is
 * not exactly the required/kind pair, and a kind outside the engine's
 * closed vocabulary are all refusals naming the module, the path and the
 * field. What survives builds the real engine, proven by asserting a
 * block through it.
 *
 * @internal
 */
#[CoversClass(ModuleConfigSchema::class)]
#[CoversClass(ConfigSchema::class)]
#[CoversClass(SchemaValueRules::class)]
#[CoversClass(SchemaViolation::class)]
#[CoversClass(ModuleException::class)]
final class ModuleConfigSchemaTest extends TestCase
{
    public function testAValidMapBuildsTheEngineAndAssertsABlock(): void
    {
        $schema = ModuleConfigSchema::validated(
            [
                'endpoint' => ['required' => true, 'kind' => 'string'],
                'retries' => ['required' => false, 'kind' => 'values'],
            ],
            'test/echo',
            '/modules/echo/config/schema.php',
        );

        $schema->assert(['endpoint' => 'http://x', 'retries' => ['a' => 1]]);

        self::assertSame(
            [],
            $schema->validate(['endpoint' => 'http://x', 'retries' => ['a' => 1]]),
        );
    }

    public function testANonArrayPayloadIsRefusedWithItsType(): void
    {
        $this->expectException(ModuleException::class);
        $this->expectExceptionMessageIsOrContains(
            "The configuration schema of the module 'test/echo' at '/x/schema.php' "
            . 'must be a non-empty field-rule map; string arrived.',
        );

        ModuleConfigSchema::validated('nope', 'test/echo', '/x/schema.php');
    }

    public function testAnEmptyMapIsRefused(): void
    {
        $this->expectException(ModuleException::class);
        $this->expectExceptionMessageIsOrContains('must be a non-empty field-rule map; an empty map arrived.');

        ModuleConfigSchema::validated([], 'test/echo', '/x/schema.php');
    }

    public function testAFieldThatIsNotANameIsRefused(): void
    {
        $this->expectException(ModuleException::class);
        $this->expectExceptionMessageIsOrContains(
            'carries a field whose name is not a non-empty string: int arrived.',
        );

        ModuleConfigSchema::validated(
            [7 => ['required' => true, 'kind' => 'string']],
            'test/echo',
            '/x/schema.php',
        );
    }

    #[DataProvider('provideAMalformedRuleIsRefusedNamingTheFieldCases')]
    public function testAMalformedRuleIsRefusedNamingTheField(mixed $rule): void
    {
        $this->expectException(ModuleException::class);
        $this->expectExceptionMessageIsOrContains(
            "carries the malformed rule for the field 'endpoint'",
        );

        ModuleConfigSchema::validated(
            ['endpoint' => $rule],
            'test/echo',
            '/x/schema.php',
        );
    }

    /**
     * @return iterable<non-empty-string, array{mixed}>
     */
    public static function provideAMalformedRuleIsRefusedNamingTheFieldCases(): iterable
    {
        yield 'unknown kind' => [['required' => true, 'kind' => 'boolean']];

        yield 'missing required' => [['kind' => 'string']];

        yield 'missing kind' => [['required' => true]];

        yield 'non-boolean required' => [['required' => 'yes', 'kind' => 'string']];

        yield 'non-string kind' => [['required' => true, 'kind' => 7]];

        yield 'a stray key' => [['required' => true, 'kind' => 'string', 'default' => 'x']];

        yield 'not an array at all' => ['string'];
    }
}
