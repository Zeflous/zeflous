<?php

declare(strict_types=1);

namespace Zef\Test\Container\Autowiring;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Container\Autowiring\AutowiringException;
use Zef\Framework\Container\Autowiring\ConstructorFit;
use Zef\Framework\Container\Autowiring\ConstructorSpecs;
use Zef\Framework\Container\Autowiring\ReflectionMetadataExtractor;
use Zef\Framework\Container\InstanceDefinition;
use Zef\Test\Support\Container\Greeter;

/**
 * @internal
 */
#[CoversClass(ConstructorFit::class)]
final class ConstructorFitTest extends TestCase
{
    private const string ID = 'app.house';

    public function testAPlanStoppingOverADefaultedTailPasses(): void
    {
        $instanceDefinition = new InstanceDefinition(self::ID, Greeter::class, ['Hello']);

        ConstructorFit::assertPositional(
            $instanceDefinition,
            ConstructorSpecs::of(Greeter::class, new ReflectionMetadataExtractor()),
        );

        self::assertSame(['Hello'], $instanceDefinition->arguments);
    }

    public function testAPlanStoppingBeforeATrailingRequiredParameterIsRefused(): void
    {
        $instanceDefinition = new InstanceDefinition(self::ID, Greeter::class, []);

        try {
            ConstructorFit::assertPositional(
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

    public function testEveryKnownNameContinuesPastTheUnknownOne(): void
    {
        $specs = ConstructorSpecs::of(Greeter::class, new ReflectionMetadataExtractor());

        try {
            ConstructorFit::assertNames(self::ID, $specs, ['salutation' => 'x', 'stray' => 'y']);
            self::fail('Expected an AutowiringException to be thrown.');
        } catch (AutowiringException $autowiringException) {
            self::assertSame(
                "Cannot autowire 'app.house': the argument 'stray' names no parameter of "
                . 'the planned constructor (parameters: salutation, verbosity).',
                $autowiringException->getMessage(),
            );
        }
    }

    public function testANameKeyedMapOverAParameterlessConstructorNamesNothing(): void
    {
        try {
            ConstructorFit::assertNames(self::ID, [], ['stray' => 'y']);
            self::fail('Expected an AutowiringException to be thrown.');
        } catch (AutowiringException $autowiringException) {
            self::assertSame(
                "Cannot autowire 'app.house': the argument 'stray' names no parameter of "
                . 'the planned constructor (parameters: none).',
                $autowiringException->getMessage(),
            );
        }
    }
}
