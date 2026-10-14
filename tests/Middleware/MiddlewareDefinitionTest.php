<?php

declare(strict_types=1);

namespace Zef\Test\Middleware;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Middleware\MiddlewareDefinition;
use Zef\Framework\Middleware\MiddlewareException;

/**
 * @internal
 */
#[CoversClass(MiddlewareDefinition::class)]
#[CoversClass(MiddlewareException::class)]
final class MiddlewareDefinitionTest extends TestCase
{
    public function testAStringEntryIsAShorthandForAPriorityZeroService(): void
    {
        $definition = MiddlewareDefinition::fromArray('app.logging');

        self::assertSame('app.logging', $definition->serviceId);
        self::assertSame(0, $definition->priority);
    }

    public function testAMapEntryCarriesItsServiceAndPriority(): void
    {
        $definition = MiddlewareDefinition::fromArray(['service' => 'app.auth', 'priority' => 500]);

        self::assertSame('app.auth', $definition->serviceId);
        self::assertSame(500, $definition->priority);
    }

    public function testAnIdKeySpellsTheServiceToo(): void
    {
        $definition = MiddlewareDefinition::fromArray(['id' => 'app.auth']);

        self::assertSame('app.auth', $definition->serviceId);
        self::assertSame(0, $definition->priority);
    }

    public function testTheServiceKeyWinsWhenBothServiceAndIdArePresent(): void
    {
        // The reference grammar reads 'service' first and 'id' only as its
        // alias: an entry carrying both resolves the 'service' spelling,
        // never the swapped order.
        $definition = MiddlewareDefinition::fromArray(['id' => 'app.first', 'service' => 'app.second']);

        self::assertSame('app.second', $definition->serviceId);
    }

    #[DataProvider('provideNumericPrioritiesAreAcceptedCases')]
    public function testNumericPrioritiesAreAccepted(float|int|string $priority, int $expected): void
    {
        $definition = MiddlewareDefinition::fromArray(['service' => 'app.auth', 'priority' => $priority]);

        self::assertSame($expected, $definition->priority);
    }

    /**
     * @return iterable<string, array{float|int|string, int}>
     */
    public static function provideNumericPrioritiesAreAcceptedCases(): iterable
    {
        yield 'int' => [500, 500];

        yield 'negative int' => [-10, -10];

        yield 'float' => [99.9, 99];

        yield 'numeric string' => ['500', 500];
    }

    public function testAnEmptyStringEntryIsRejected(): void
    {
        try {
            MiddlewareDefinition::fromArray('');
            self::fail('Expected a MiddlewareException to be thrown.');
        } catch (MiddlewareException $middlewareException) {
            self::assertStringContainsString('non-empty service identifier', $middlewareException->getMessage());
        }
    }

    #[DataProvider('provideNonStringNonMapEntriesAreRejectedCases')]
    public function testNonStringNonMapEntriesAreRejected(mixed $entry, string $type): void
    {
        try {
            MiddlewareDefinition::fromArray($entry);
            self::fail('Expected a MiddlewareException to be thrown.');
        } catch (MiddlewareException $middlewareException) {
            self::assertSame(
                "A middleware stack entry must be a service identifier string or a definition map, {$type} given.",
                $middlewareException->getMessage(),
            );
        }
    }

    /**
     * @return iterable<string, array{mixed, string}>
     */
    public static function provideNonStringNonMapEntriesAreRejectedCases(): iterable
    {
        yield 'int' => [42, 'int'];

        yield 'bool' => [true, 'bool'];

        yield 'null' => [null, 'null'];
    }

    public function testAMapEntryWithoutAServiceKeyIsRejected(): void
    {
        try {
            MiddlewareDefinition::fromArray(['priority' => 500]);
            self::fail('Expected a MiddlewareException to be thrown.');
        } catch (MiddlewareException $middlewareException) {
            self::assertSame(
                "A middleware stack entry requires a non-empty 'service' (or 'id') key.",
                $middlewareException->getMessage(),
            );
        }
    }

    public function testAnEmptyServiceKeyIsRejected(): void
    {
        try {
            MiddlewareDefinition::fromArray(['service' => '']);
            self::fail('Expected a MiddlewareException to be thrown.');
        } catch (MiddlewareException $middlewareException) {
            self::assertSame(
                "A middleware stack entry requires a non-empty 'service' (or 'id') key.",
                $middlewareException->getMessage(),
            );
        }
    }

    #[DataProvider('provideNonNumericPrioritiesAreRejectedCases')]
    public function testNonNumericPrioritiesAreRejected(mixed $priority): void
    {
        try {
            MiddlewareDefinition::fromArray(['service' => 'app.auth', 'priority' => $priority]);
            self::fail('Expected a MiddlewareException to be thrown.');
        } catch (MiddlewareException $middlewareException) {
            self::assertSame(
                "Middleware 'app.auth' priority must be numeric.",
                $middlewareException->getMessage(),
            );
        }
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function provideNonNumericPrioritiesAreRejectedCases(): iterable
    {
        yield 'bool' => [true];

        yield 'array' => [[500]];

        yield 'non-numeric string' => ['highest'];
    }

    public function testAnExplicitlyNullPriorityKeepsTheDefault(): void
    {
        $definition = MiddlewareDefinition::fromArray(['service' => 'app.auth', 'priority' => null]);

        self::assertSame(0, $definition->priority);
    }
}
