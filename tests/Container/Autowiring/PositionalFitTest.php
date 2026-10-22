<?php

declare(strict_types=1);

namespace Zef\Test\Container\Autowiring;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Container\Autowiring\AutowiringException;
use Zef\Framework\Container\Autowiring\ConstructorFitRefusals;
use Zef\Framework\Container\Autowiring\ConstructorSpecs;
use Zef\Framework\Container\Autowiring\PositionalFit;
use Zef\Framework\Container\Autowiring\ReflectionMetadataExtractor;
use Zef\Framework\Container\InstanceDefinition;
use Zef\Test\Support\Container\Autowiring\VariadicHouse;
use Zef\Test\Support\Container\Greeter;

/**
 * @internal
 */
#[CoversClass(PositionalFit::class)]
#[CoversClass(ConstructorFitRefusals::class)]
final class PositionalFitTest extends TestCase
{
    private const string ID = 'app.house';

    public function testAPlanStoppingOverADefaultedTailPasses(): void
    {
        $instanceDefinition = new InstanceDefinition(self::ID, Greeter::class, ['Hello']);

        PositionalFit::assert(
            $instanceDefinition,
            ConstructorSpecs::of(Greeter::class, new ReflectionMetadataExtractor()),
        );

        self::assertSame(['Hello'], $instanceDefinition->arguments);
    }

    /**
     * A trailing variadic parameter accepts an unbounded positional
     * tail -- `newInstanceArgs(['a', 'b'])` over `__construct(...$notes)`
     * is valid PHP, so the arity verdict must not count the variadic
     * as one slot.
     */
    public function testAVariadicTailAcceptsThePlansFullDeclaredList(): void
    {
        $instanceDefinition = new InstanceDefinition(self::ID, VariadicHouse::class, ['a', 'b']);

        PositionalFit::assert(
            $instanceDefinition,
            ConstructorSpecs::of(VariadicHouse::class, new ReflectionMetadataExtractor()),
        );

        self::assertSame(['a', 'b'], $instanceDefinition->arguments);
    }

    public function testAPlanStoppingBeforeATrailingRequiredParameterIsRefused(): void
    {
        $instanceDefinition = new InstanceDefinition(self::ID, Greeter::class, []);

        try {
            PositionalFit::assert(
                $instanceDefinition,
                ConstructorSpecs::of(Greeter::class, new ReflectionMetadataExtractor()),
            );
            self::fail('Expected an AutowiringException to be thrown.');
        } catch (AutowiringException $autowiringException) {
            self::assertSame(
                "Cannot construct 'app.house': the declared arguments stop before the required parameter "
                . "'\$salutation' of " . Greeter::class
                . '::__construct -- declare the argument, or plan the constructor '
                . "(the name-keyed or 'autowire' forms).",
                $autowiringException->getMessage(),
            );
        }
    }
}
