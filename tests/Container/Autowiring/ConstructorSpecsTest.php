<?php

declare(strict_types=1);

namespace Zef\Test\Container\Autowiring;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Container\Autowiring\AutowiringException;
use Zef\Framework\Container\Autowiring\ConstructorSpecs;
use Zef\Framework\Container\Autowiring\ReflectionMetadataExtractor;
use Zef\Test\Support\Container\Autowiring\FileLogger;
use Zef\Test\Support\Container\Autowiring\LoggerInterface;
use Zef\Test\Support\Container\Autowiring\ParameterlessHouse;
use Zef\Test\Support\Container\Autowiring\UninstantiableHouse;

/**
 * @internal
 */
#[CoversClass(ConstructorSpecs::class)]
final class ConstructorSpecsTest extends TestCase
{
    public function testTheInstantiableVerdictSeparatesTheDeclarableFromTheRest(): void
    {
        self::assertTrue(ConstructorSpecs::instantiable(FileLogger::class));
        self::assertFalse(ConstructorSpecs::instantiable(LoggerInterface::class));
        self::assertFalse(ConstructorSpecs::instantiable(UninstantiableHouse::class));
    }

    public function testAnUnknownClassIsRefusedByName(): void
    {
        try {
            ConstructorSpecs::instantiable('No\Such\Class');
            self::fail('Expected an AutowiringException to be thrown.');
        } catch (AutowiringException $autowiringException) {
            self::assertStringContainsString(
                "Cannot plan the constructor of the unknown class 'No\\Such\\Class'.",
                $autowiringException->getMessage(),
            );
        }
    }

    public function testOnlyClassesMayBeBirthed(): void
    {
        self::assertTrue(ConstructorSpecs::birthable(FileLogger::class));
        self::assertFalse(ConstructorSpecs::birthable(LoggerInterface::class));
        self::assertFalse(ConstructorSpecs::birthable('app.not_a_class'));
    }

    public function testTheSpecsAnswerTheConstructorsOwnPlan(): void
    {
        $specs = ConstructorSpecs::of(FileLogger::class, new ReflectionMetadataExtractor());

        self::assertCount(1, $specs);
        self::assertSame('path', $specs[0]->name);
    }

    public function testAParameterlessConstructorAnswersTheEmptyPlan(): void
    {
        self::assertSame(
            [],
            ConstructorSpecs::of(ParameterlessHouse::class, new ReflectionMetadataExtractor()),
        );
    }

    public function testANonInstantiableClassIsRefusedWithTheReason(): void
    {
        try {
            ConstructorSpecs::of(UninstantiableHouse::class, new ReflectionMetadataExtractor());
            self::fail('Expected an AutowiringException to be thrown.');
        } catch (AutowiringException $autowiringException) {
            self::assertStringContainsString(
                "Cannot construct the service for '" . UninstantiableHouse::class . "': "
                . 'the class is not instantiable (an interface, abstract class, enum, or private constructor).',
                $autowiringException->getMessage(),
            );
        }
    }
}
