<?php

declare(strict_types=1);

namespace Zef\Test\Config\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Config\Schema\SchemaViolation;

/**
 * @internal
 */
#[CoversClass(SchemaViolation::class)]
final class SchemaViolationTest extends TestCase
{
    public function testExposesItsPathExpectedAndActual(): void
    {
        $schemaViolation = new SchemaViolation('constraints.id', 'a non-empty string', 'integer');

        self::assertSame('constraints.id', $schemaViolation->path);
        self::assertSame('a non-empty string', $schemaViolation->expected);
        self::assertSame('integer', $schemaViolation->actual);
    }

    public function testRendersAsAReadableSentence(): void
    {
        $schemaViolation = new SchemaViolation('method.0', 'a non-empty string', 'int');

        self::assertSame('method.0: expected a non-empty string, got int', (string) $schemaViolation);
    }

    public function testRendersTheEntryLevelViolation(): void
    {
        $schemaViolation = new SchemaViolation('entry', 'an array', 'string');

        self::assertSame('entry: expected an array, got string', (string) $schemaViolation);
    }

    public function testBuildsTheNotAnArrayViolation(): void
    {
        self::assertSame(
            'entry: expected an array, got string',
            (string) SchemaViolation::notAnArray('not-an-array'),
        );
    }

    public function testBuildsTheMissingFieldViolation(): void
    {
        self::assertSame(
            'method: expected a non-empty list of non-empty strings, got missing',
            (string) SchemaViolation::missingField('method', 'strings'),
        );
    }

    public function testBuildsTheUnexpectedKeyViolation(): void
    {
        $schemaViolation = SchemaViolation::unexpectedKey('metod', ['path', 'method', 'handler']);

        self::assertSame('metod', $schemaViolation->path);
        self::assertSame('one of path, method, handler', $schemaViolation->expected);
        self::assertSame('an unexpected key', $schemaViolation->actual);
        self::assertSame(
            'metod: expected one of path, method, handler, got an unexpected key',
            (string) $schemaViolation,
        );
    }

    public function testBuildsTheUnexpectedKeyViolationForAnIntegerKey(): void
    {
        self::assertSame(
            '0: expected one of path, got an unexpected key',
            (string) SchemaViolation::unexpectedKey(0, ['path']),
        );
    }

    public function testCollectsTheUnexpectedKeyViolationsInEntryOrder(): void
    {
        $violations = SchemaViolation::unexpectedViolations(
            ['path' => '/users', 'metod' => 'GET', 'hndler' => 'x'],
            ['path' => ['required' => true], 'method' => ['required' => true]],
        );

        self::assertSame(
            [
                'metod: expected one of path, method, got an unexpected key',
                'hndler: expected one of path, method, got an unexpected key',
            ],
            array_map(static fn (SchemaViolation $schemaViolation): string => (string) $schemaViolation, $violations),
        );
    }
}
