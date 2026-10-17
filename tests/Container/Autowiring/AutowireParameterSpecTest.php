<?php

declare(strict_types=1);

namespace Zef\Test\Container\Autowiring;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Container\Autowiring\AutowireParameterSpec;
use Zef\Framework\Container\Autowiring\AutowiringException;
use Zef\Test\Support\Container\Autowiring\FileLogger;
use Zef\Test\Support\Container\Autowiring\LoggerInterface;

/**
 * @internal
 */
#[CoversClass(AutowireParameterSpec::class)]
final class AutowireParameterSpecTest extends TestCase
{
    public function testCarriesThePureSnapshot(): void
    {
        $autowireParameterSpec = new AutowireParameterSpec(
            name: 'logger',
            isVariadic: false,
            isOptional: false,
            hasDefault: false,
            className: LoggerInterface::class,
        );

        self::assertSame('logger', $autowireParameterSpec->name);
        self::assertNull($autowireParameterSpec->injectId);
        self::assertNull($autowireParameterSpec->targetClass);
        self::assertNull($autowireParameterSpec->unsupportedTypeReason);
    }

    public function testAttributeBindingsAreRecognised(): void
    {
        $inject = new AutowireParameterSpec(
            name: 'clock',
            isVariadic: false,
            isOptional: false,
            hasDefault: false,
            injectId: 'app.clock',
        );
        $target = new AutowireParameterSpec(
            name: 'logger',
            isVariadic: false,
            isOptional: false,
            hasDefault: false,
            targetClass: FileLogger::class,
        );

        self::assertSame('app.clock', $inject->injectId);
        self::assertSame(FileLogger::class, $target->targetClass);
    }

    public function testInjectAndTargetCannotCombine(): void
    {
        try {
            new AutowireParameterSpec(
                name: 'clock',
                isVariadic: false,
                isOptional: false,
                hasDefault: false,
                injectId: 'app.clock',
                targetClass: FileLogger::class,
            );
            self::fail('Expected an AutowiringException to be thrown.');
        } catch (AutowiringException $autowiringException) {
            self::assertStringContainsString(
                "Parameter '\$clock' cannot combine #[Inject] and #[Target] "
                . '(one parameter carries one binding intent).',
                $autowiringException->getMessage(),
            );
        }
    }

    public function testInjectCannotRideAVariadicParameter(): void
    {
        try {
            new AutowireParameterSpec(
                name: 'clocks',
                isVariadic: true,
                isOptional: true,
                hasDefault: false,
                injectId: 'app.clock',
            );
            self::fail('Expected an AutowiringException to be thrown.');
        } catch (AutowiringException $autowiringException) {
            self::assertStringContainsString(
                "Parameter '\$clocks' cannot carry #[Inject] on a variadic parameter "
                . '(a variadic is a collection, not a single reference).',
                $autowiringException->getMessage(),
            );
        }
    }

    public function testTargetCannotRideAVariadicParameter(): void
    {
        try {
            new AutowireParameterSpec(
                name: 'loggers',
                isVariadic: true,
                isOptional: true,
                hasDefault: false,
                targetClass: FileLogger::class,
            );
            self::fail('Expected an AutowiringException to be thrown.');
        } catch (AutowiringException $autowiringException) {
            self::assertStringContainsString(
                "Parameter '\$loggers' cannot carry #[Target] on a variadic parameter "
                . "(collection binding is the tags lane's concern).",
                $autowiringException->getMessage(),
            );
        }
    }

    public function testTheBindingPredicatesRouteThePlannersTiers(): void
    {
        $plain = new AutowireParameterSpec(
            name: 'salutation',
            isVariadic: false,
            isOptional: false,
            hasDefault: false,
        );
        $typed = new AutowireParameterSpec(
            name: 'logger',
            isVariadic: false,
            isOptional: false,
            hasDefault: false,
            className: LoggerInterface::class,
        );
        $injected = new AutowireParameterSpec(
            name: 'clock',
            isVariadic: false,
            isOptional: false,
            hasDefault: false,
            injectId: 'app.clock',
        );

        self::assertFalse($plain->carriesBinding());
        self::assertTrue($typed->carriesBinding());
        self::assertTrue($injected->carriesBinding());
        self::assertSame('app.clock', $injected->identifier());
        self::assertSame(LoggerInterface::class, $typed->identifier());
        self::assertSame('', $plain->identifier());
    }
}
