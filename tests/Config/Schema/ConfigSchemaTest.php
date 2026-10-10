<?php

declare(strict_types=1);

namespace Zef\Test\Config\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Config\ConfigException;
use Zef\Framework\Config\Schema\ConfigSchema;
use Zef\Framework\Config\Schema\SchemaValueRules;
use Zef\Framework\Config\Schema\SchemaViolation;

/**
 * @internal
 */
#[CoversClass(ConfigSchema::class)]
#[CoversClass(SchemaValueRules::class)]
final class ConfigSchemaTest extends TestCase
{
    public function testAcceptsAnEntryThatSatisfiesEveryRule(): void
    {
        $configSchema = $this->engineSchema();

        $violations = $configSchema->validate([
            'label' => 'primary',
            'tags' => ['cache', 'fast'],
            'limits' => ['requests' => '100', 'window' => '60'],
            'metadata' => ['version' => 2, 'stable' => false, 'note' => null],
        ]);

        self::assertSame([], $this->render($violations));
    }

    public function testReportsANonArrayEntryAsASingleViolation(): void
    {
        $configSchema = $this->engineSchema();

        $violations = $configSchema->validate('not-an-array');

        self::assertSame(['entry: expected an array, got string'], $this->render($violations));
    }

    public function testAssertRendersANonArrayEntryIntoTheException(): void
    {
        $configSchema = $this->engineSchema();

        try {
            $configSchema->assert('not-an-array');
            self::fail('A non-array entry must raise a ConfigException.');
        } catch (ConfigException $configException) {
            self::assertSame(
                'Configuration failed schema validation: entry: expected an array, got string.',
                $configException->getMessage(),
            );
        }
    }

    public function testReportsAMissingRequiredField(): void
    {
        $configSchema = $this->engineSchema();

        $violations = $configSchema->validate([
            'tags' => ['cache'],
            'limits' => ['requests' => '100'],
            'metadata' => ['version' => 2],
        ]);

        self::assertSame(['label: expected a non-empty string, got missing'], $this->render($violations));
    }

    public function testOmitsEveryAbsentOptionalField(): void
    {
        $configSchema = $this->engineSchema();

        $violations = $configSchema->validate(['label' => 'primary']);

        self::assertSame([], $this->render($violations));
    }

    public function testReportsAnEmptyStringValue(): void
    {
        $configSchema = $this->engineSchema();

        $violations = $configSchema->validate(['label' => '']);

        self::assertSame(['label: expected a non-empty string, got an empty string'], $this->render($violations));
    }

    public function testReportsAWronglyTypedStringValue(): void
    {
        $configSchema = $this->engineSchema();

        $violations = $configSchema->validate(['label' => 42]);

        self::assertSame(['label: expected a non-empty string, got int'], $this->render($violations));
    }

    public function testReportsANullStringValue(): void
    {
        $configSchema = $this->engineSchema();

        $violations = $configSchema->validate(['label' => null]);

        self::assertSame(['label: expected a non-empty string, got null'], $this->render($violations));
    }

    public function testAssertCarriesEveryViolationInTheException(): void
    {
        $configSchema = $this->engineSchema();

        try {
            $configSchema->assert(['debug' => true]);
            self::fail('An entry with a missing required field and an unexpected key must raise a ConfigException.');
        } catch (ConfigException $configException) {
            self::assertSame(
                'Configuration failed schema validation: '
                    . 'label: expected a non-empty string, got missing; '
                    . 'debug: expected one of label, tags, limits, metadata, got an unexpected key.',
                $configException->getMessage(),
            );
        }
    }

    public function testReportsAListRuleViolationForAStringValue(): void
    {
        $configSchema = $this->engineSchema();

        $violations = $configSchema->validate(['label' => 'primary', 'tags' => 'cache']);

        self::assertSame(
            ['tags: expected a non-empty list of non-empty strings, got string'],
            $this->render($violations),
        );
    }

    public function testReportsAListRuleViolationForAnEmptyList(): void
    {
        $configSchema = $this->engineSchema();

        $violations = $configSchema->validate(['label' => 'primary', 'tags' => []]);

        self::assertSame(
            ['tags: expected a non-empty list of non-empty strings, got an empty array'],
            $this->render($violations),
        );
    }

    public function testReportsAListRuleViolationForAMap(): void
    {
        $configSchema = $this->engineSchema();

        $violations = $configSchema->validate(['label' => 'primary', 'tags' => ['cache' => 'fast']]);

        self::assertSame(
            ['tags: expected a non-empty list of non-empty strings, got array'],
            $this->render($violations),
        );
    }

    public function testChecksEveryListElementAtItsOffset(): void
    {
        $configSchema = $this->engineSchema();

        $violations = $configSchema->validate(['label' => 'primary', 'tags' => ['cache', 42, '']]);

        self::assertSame(
            [
                'tags.1: expected a non-empty string, got int',
                'tags.2: expected a non-empty string, got an empty string',
            ],
            $this->render($violations),
        );
    }

    public function testReportsAMapRuleViolationForAStringValue(): void
    {
        $configSchema = $this->engineSchema();

        $violations = $configSchema->validate(['label' => 'primary', 'limits' => 'requests']);

        self::assertSame(
            ['limits: expected a non-empty map of non-empty strings, got string'],
            $this->render($violations),
        );
    }

    public function testReportsAMapRuleViolationForAnEmptyMap(): void
    {
        $configSchema = $this->engineSchema();

        $violations = $configSchema->validate(['label' => 'primary', 'limits' => []]);

        self::assertSame(
            ['limits: expected a non-empty map of non-empty strings, got an empty array'],
            $this->render($violations),
        );
    }

    public function testReportsAMapRuleViolationForAList(): void
    {
        $configSchema = $this->engineSchema();

        $violations = $configSchema->validate(['label' => 'primary', 'limits' => ['100', '60']]);

        self::assertSame(
            ['limits: expected a non-empty map of non-empty strings, got array'],
            $this->render($violations),
        );
    }

    public function testChecksEveryMapValueAtItsKey(): void
    {
        $configSchema = $this->engineSchema();

        $violations = $configSchema->validate([
            'label' => 'primary',
            'limits' => ['requests' => '100', 'window' => 60],
        ]);

        self::assertSame(
            ['limits.window: expected a non-empty string, got int'],
            $this->render($violations),
        );
    }

    public function testChecksEveryMapValueWhenSeveralAreWrong(): void
    {
        $configSchema = $this->engineSchema();

        $violations = $configSchema->validate([
            'label' => 'primary',
            'limits' => ['requests' => 100, 'window' => 60],
        ]);

        self::assertSame(
            [
                'limits.requests: expected a non-empty string, got int',
                'limits.window: expected a non-empty string, got int',
            ],
            $this->render($violations),
        );
    }

    public function testReportsFieldViolationsTogetherWithElementViolations(): void
    {
        $configSchema = $this->engineSchema();

        $violations = $configSchema->validate(['tags' => [42, '']]);

        self::assertSame(
            [
                'label: expected a non-empty string, got missing',
                'tags.0: expected a non-empty string, got int',
                'tags.1: expected a non-empty string, got an empty string',
            ],
            $this->render($violations),
        );
    }

    public function testAcceptsAMapWithMixedKeys(): void
    {
        $configSchema = $this->engineSchema();

        $violations = $configSchema->validate(['label' => 'primary', 'limits' => ['requests' => '100', 0 => '60']]);

        self::assertSame([], $this->render($violations));
    }

    public function testReportsAValueMapRuleViolationForAStringValue(): void
    {
        $configSchema = $this->engineSchema();

        $violations = $configSchema->validate(['label' => 'primary', 'metadata' => 'version']);

        self::assertSame(
            ['metadata: expected a non-empty map, got string'],
            $this->render($violations),
        );
    }

    public function testReportsAValueMapRuleViolationForAnEmptyMap(): void
    {
        $configSchema = $this->engineSchema();

        $violations = $configSchema->validate(['label' => 'primary', 'metadata' => []]);

        self::assertSame(
            ['metadata: expected a non-empty map, got an empty array'],
            $this->render($violations),
        );
    }

    public function testReportsAValueMapRuleViolationForAList(): void
    {
        $configSchema = $this->engineSchema();

        $violations = $configSchema->validate(['label' => 'primary', 'metadata' => ['v2', false]]);

        self::assertSame(
            ['metadata: expected a non-empty map, got array'],
            $this->render($violations),
        );
    }

    public function testAcceptsFreeFormValuesInAValueMap(): void
    {
        $configSchema = $this->engineSchema();

        $violations = $configSchema->validate([
            'label' => 'primary',
            'metadata' => ['version' => 2, 'stable' => false, 'note' => null, 'tags' => ['a', 'b']],
        ]);

        self::assertSame([], $this->render($violations));
    }

    public function testReportsAnUnexpectedKeyNamingTheSchemaFields(): void
    {
        $configSchema = $this->engineSchema();

        $violations = $configSchema->validate(['label' => 'primary', 'deubg' => true]);

        self::assertSame(
            ['deubg: expected one of label, tags, limits, metadata, got an unexpected key'],
            $this->render($violations),
        );
    }

    public function testValidateIsPureAndLeavesTheEntryUntouched(): void
    {
        $configSchema = $this->engineSchema();

        // The declared shape is deliberately wider than the literal below: the
        // assertion proves at runtime that validate() does not touch the
        // entry, which the analyser cannot narrow away for us.
        /** @var array<array-key, mixed> $entry */
        $entry = ['label' => 'primary', 'tags' => ['cache'], 'deubg' => true];

        $first = $this->render($configSchema->validate($entry));
        $second = $this->render($configSchema->validate($entry));

        self::assertSame($first, $second);
        self::assertSame(['label' => 'primary', 'tags' => ['cache'], 'deubg' => true], $entry);
    }

    public function testAssertAcceptsAnEntryThatSatisfiesEveryRule(): void
    {
        $configSchema = $this->engineSchema();

        $configSchema->assert(['label' => 'primary']);

        self::assertSame([], $configSchema->validate(['label' => 'primary']));
    }

    /**
     * The engine under test: one required string, one optional string list,
     * one optional string map and one optional free-form value map, so every
     * kind and both requirednesses are exercised independently of the route
     * schema.
     */
    private function engineSchema(): ConfigSchema
    {
        return new ConfigSchema([
            'label' => ['required' => true, 'kind' => 'string'],
            'tags' => ['required' => false, 'kind' => 'strings'],
            'limits' => ['required' => false, 'kind' => 'string-map'],
            'metadata' => ['required' => false, 'kind' => 'values'],
        ]);
    }

    /**
     * @param list<SchemaViolation> $violations
     *
     * @return list<string>
     */
    private function render(array $violations): array
    {
        return array_map(
            static fn (SchemaViolation $schemaViolation): string => (string) $schemaViolation,
            $violations,
        );
    }
}
