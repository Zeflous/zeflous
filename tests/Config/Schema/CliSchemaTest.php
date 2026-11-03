<?php

declare(strict_types=1);

namespace Zef\Test\Config\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Config\ConfigException;
use Zef\Framework\Config\Schema\CliSchema;
use Zef\Framework\Config\Schema\SchemaViolation;

/**
 * The Standard CLI Schema's own contract: the three fields, the
 * fail-closed verdicts, and the list-level posture the host's own table
 * and every module's commands file both ride.
 *
 * @internal
 */
#[CoversClass(CliSchema::class)]
#[CoversClass(ConfigException::class)]
final class CliSchemaTest extends TestCase
{
    public function testAMinimalDeclarationSatisfiesTheSchema(): void
    {
        $declaration = [
            'name' => 'shop:ping',
            'description' => 'Answer the health probe.',
            'service' => 'test/shop/ping.handler',
        ];

        self::assertSame([], new CliSchema()->validate($declaration));
    }

    public function testASchemaVerdictCarriesEveryViolationInOrder(): void
    {
        $violations = new CliSchema()->validate(['name' => 7]);

        self::assertSame(
            ['name', 'description', 'service'],
            array_map(
                static fn (SchemaViolation $schemaViolation): string => $schemaViolation->path,
                $violations,
            ),
        );
    }

    #[DataProvider('provideTheSchemaRefusesEveryWrongShapeCases')]
    public function testTheSchemaRefusesEveryWrongShape(mixed $declaration): void
    {
        self::assertNotSame([], new CliSchema()->validate($declaration));
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function provideTheSchemaRefusesEveryWrongShapeCases(): iterable
    {
        yield 'a scalar' => ['nope'];

        yield 'null' => [null];

        yield 'an unknown key' => [['name' => 'shop:ping', 'description' => 'd', 'service' => 's', 'extra' => 1]];

        yield 'a missing service' => [['name' => 'shop:ping', 'description' => 'd']];

        yield 'a missing description' => [['name' => 'shop:ping', 'service' => 's']];

        yield 'a missing name' => [['description' => 'd', 'service' => 's']];

        yield 'a map-kind name' => [['name' => [], 'description' => 'd', 'service' => 's']];

        yield 'a list-kind description' => [['name' => 'shop:ping', 'description' => [], 'service' => 's']];

        yield 'a null service' => [['name' => 'shop:ping', 'description' => 'd', 'service' => null]];

        yield 'an empty string name' => [['name' => '', 'description' => 'd', 'service' => 's']];
    }

    public function testAssertThrowsWithTheViolationsPathContext(): void
    {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessageIsOrContains('name');

        new CliSchema()->assert(['description' => 'd', 'service' => 's']);
    }

    public function testAssertListRefusesANonArrayWithItsOwnMessage(): void
    {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessageIsOrContains(
            'The "console.commands" configuration must be a list of command declarations.',
        );

        new CliSchema()->assertList('nope');
    }

    public function testAssertListHaltsOnTheFirstViolatingEntry(): void
    {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessageIsOrContains('service');

        new CliSchema()->assertList([
            ['name' => 'shop:ping', 'description' => 'd', 'service' => 's'],
            ['name' => 'shop:report', 'description' => 'd'],
        ]);
    }

    public function testAssertListAcceptsAMapOfNamedEntries(): void
    {
        new CliSchema()->assertList([
            'ping' => ['name' => 'shop:ping', 'description' => 'd', 'service' => 's'],
        ]);

        $this->expectNotToPerformAssertions();
    }
}
