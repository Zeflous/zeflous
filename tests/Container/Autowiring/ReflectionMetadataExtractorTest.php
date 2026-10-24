<?php

declare(strict_types=1);

namespace Zef\Test\Container\Autowiring;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Container\Autowiring\AutowireAttributes;
use Zef\Framework\Container\Autowiring\AutowireParameterTypes;
use Zef\Framework\Container\Autowiring\AutowireSpecFactory;
use Zef\Framework\Container\Autowiring\AutowiringException;
use Zef\Framework\Container\Autowiring\ReflectionMetadataExtractor;
use Zef\Test\Support\Container\Autowiring\DoubleInjectHouse;
use Zef\Test\Support\Container\Autowiring\DoubleTargetHouse;
use Zef\Test\Support\Container\Autowiring\FileLogger;
use Zef\Test\Support\Container\Autowiring\InjectedHouse;
use Zef\Test\Support\Container\Autowiring\InterfaceHouse;
use Zef\Test\Support\Container\Autowiring\IntersectionHouse;
use Zef\Test\Support\Container\Autowiring\LoggerInterface;
use Zef\Test\Support\Container\Autowiring\NullableDefaultedHouse;
use Zef\Test\Support\Container\Autowiring\ParameterlessHouse;
use Zef\Test\Support\Container\Autowiring\TargetedHouse;
use Zef\Test\Support\Container\Autowiring\TypedVariadicHouse;
use Zef\Test\Support\Container\Autowiring\UnionHouse;
use Zef\Test\Support\Container\Autowiring\UntypedHouse;
use Zef\Test\Support\Container\Greeter;

/**
 * @internal
 */
#[CoversClass(ReflectionMetadataExtractor::class)]
#[CoversClass(AutowireAttributes::class)]
#[CoversClass(AutowireParameterTypes::class)]
#[CoversClass(AutowireSpecFactory::class)]
final class ReflectionMetadataExtractorTest extends TestCase
{
    private ReflectionMetadataExtractor $reflectionMetadataExtractor;

    #[Override]
    protected function setUp(): void
    {
        $this->reflectionMetadataExtractor = new ReflectionMetadataExtractor();
    }

    public function testAClassWithoutAConstructorAnswersTheEmptyPlan(): void
    {
        self::assertSame([], $this->reflectionMetadataExtractor->extract(ParameterlessHouse::class));
    }

    public function testTheSnapshotCarriesTheDeclaredShape(): void
    {
        $specs = $this->reflectionMetadataExtractor->extract(InterfaceHouse::class);

        self::assertCount(1, $specs);
        self::assertSame('logger', $specs[0]->name);
        self::assertSame(LoggerInterface::class, $specs[0]->className);
        self::assertFalse($specs[0]->isVariadic);
        self::assertFalse($specs[0]->isOptional);
        self::assertFalse($specs[0]->hasDefault);
        self::assertNull($specs[0]->injectId);
        self::assertNull($specs[0]->targetClass);
        self::assertNull($specs[0]->unsupportedTypeReason);
    }

    public function testABuiltinTypeAnswersNoClassName(): void
    {
        $specs = $this->reflectionMetadataExtractor->extract(UnionHouse::class);

        self::assertNull($specs[0]->className);
        self::assertSame('a union type names no single binding', $specs[0]->unsupportedTypeReason);
    }

    public function testAnIntersectionTypeAnswersItsOwnReason(): void
    {
        $specs = $this->reflectionMetadataExtractor->extract(IntersectionHouse::class);

        self::assertSame('an intersection type names no single binding', $specs[0]->unsupportedTypeReason);
    }

    public function testAMixedTypeIsAScalarArmParameter(): void
    {
        $specs = $this->reflectionMetadataExtractor->extract(UntypedHouse::class);

        self::assertNull($specs[0]->className);
        self::assertNull($specs[0]->unsupportedTypeReason);
    }

    public function testADefaultedNullableParameterCarriesItsDefault(): void
    {
        $specs = $this->reflectionMetadataExtractor->extract(NullableDefaultedHouse::class);

        self::assertTrue($specs[0]->isOptional);
        self::assertTrue($specs[0]->hasDefault);
        self::assertNull($specs[0]->defaultValue);
    }

    public function testAVariadicParameterIsRecognisedAsTheCollectionItIs(): void
    {
        $specs = $this->reflectionMetadataExtractor->extract(TypedVariadicHouse::class);

        self::assertTrue($specs[0]->isVariadic);
        self::assertTrue($specs[0]->isOptional);
        self::assertSame(Greeter::class, $specs[0]->className);
    }

    public function testTheInjectAttributeIsReadIntoTheSpec(): void
    {
        $specs = $this->reflectionMetadataExtractor->extract(InjectedHouse::class);

        self::assertSame('app.clock', $specs[0]->injectId);
        self::assertNull($specs[0]->targetClass);
    }

    public function testTheTargetAttributeIsReadIntoTheSpec(): void
    {
        $specs = $this->reflectionMetadataExtractor->extract(TargetedHouse::class);

        self::assertNull($specs[0]->injectId);
        self::assertSame(FileLogger::class, $specs[0]->targetClass);
    }

    public function testADoubledAttributeIsSurfacedNotGuessed(): void
    {
        try {
            $this->reflectionMetadataExtractor->extract(DoubleInjectHouse::class);
            self::fail('Expected an AutowiringException to be thrown.');
        } catch (AutowiringException $autowiringException) {
            self::assertStringContainsString(
                "Parameter '\$clock' carries more than one Inject attribute.",
                $autowiringException->getMessage(),
            );
        }
    }

    public function testAnUnknownClassIsRefusedByName(): void
    {
        try {
            $this->reflectionMetadataExtractor->extract('No\Such\Class');
            self::fail('Expected an AutowiringException to be thrown.');
        } catch (AutowiringException $autowiringException) {
            self::assertStringContainsString(
                "Cannot plan the constructor of the unknown class 'No\\Such\\Class'.",
                $autowiringException->getMessage(),
            );
        }
    }

    public function testADoubledTargetAttributeIsSurfacedNotGuessed(): void
    {
        try {
            $this->reflectionMetadataExtractor->extract(DoubleTargetHouse::class);
            self::fail('Expected an AutowiringException to be thrown.');
        } catch (AutowiringException $autowiringException) {
            self::assertStringContainsString(
                "Parameter '\$logger' carries more than one Target attribute.",
                $autowiringException->getMessage(),
            );
        }
    }
}
