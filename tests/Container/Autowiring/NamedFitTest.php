<?php

declare(strict_types=1);

namespace Zef\Test\Container\Autowiring;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Container\Autowiring\AutowiringException;
use Zef\Framework\Container\Autowiring\ConstructorFitRefusals;
use Zef\Framework\Container\Autowiring\ConstructorSpecs;
use Zef\Framework\Container\Autowiring\NamedFit;
use Zef\Framework\Container\Autowiring\ReflectionMetadataExtractor;
use Zef\Test\Support\Container\Greeter;

/**
 * @internal
 */
#[CoversClass(NamedFit::class)]
#[CoversClass(ConstructorFitRefusals::class)]
final class NamedFitTest extends TestCase
{
    private const string ID = 'app.house';

    public function testEveryKnownNameContinuesPastTheUnknownOne(): void
    {
        $specs = ConstructorSpecs::of(Greeter::class, new ReflectionMetadataExtractor());

        try {
            NamedFit::assert(self::ID, $specs, ['salutation' => 'x', 'stray' => 'y']);
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
            NamedFit::assert(self::ID, [], ['stray' => 'y']);
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
