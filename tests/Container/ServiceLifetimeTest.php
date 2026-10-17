<?php

declare(strict_types=1);

namespace Zef\Test\Container;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Container\DefinitionException;
use Zef\Framework\Container\ServiceDefinition;
use Zef\Framework\Container\ServiceLifetime;
use Zef\Test\Support\Container\ExternalFactory;
use Zef\Test\Support\Container\Greeter;

/**
 * @internal
 */
#[CoversClass(ServiceLifetime::class)]
final class ServiceLifetimeTest extends TestCase
{
    public function testAnAbsentKeyAnswersNullForTheCallersDefault(): void
    {
        self::assertNull(ServiceLifetime::fromKey('app.x', null));
    }

    public function testEachSpellingParsesToItsCase(): void
    {
        self::assertSame(ServiceLifetime::SINGLETON, ServiceLifetime::fromKey('app.x', 'singleton'));
        self::assertSame(ServiceLifetime::REQUEST, ServiceLifetime::fromKey('app.x', 'request'));
        self::assertSame(ServiceLifetime::TRANSIENT, ServiceLifetime::fromKey('app.x', 'transient'));
    }

    public function testAnUnknownWordIsRefusedWithTheVocabulary(): void
    {
        try {
            ServiceLifetime::fromKey('app.x', 'shared');
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "Service 'app.x' carries the unknown lifetime 'shared' "
                . "(one of 'singleton', 'request', 'transient' is required).",
                $definitionException->getMessage(),
            );
        }
    }

    public function testANonStringIsRefusedWithTheGivenType(): void
    {
        try {
            ServiceLifetime::fromKey('app.x', true);
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "Service 'app.x' lifetime must be one of 'singleton', 'request', 'transient', bool given.",
                $definitionException->getMessage(),
            );
        }
    }

    public function testTheClassShapeCarriesItsLifetime(): void
    {
        $definition = ServiceDefinition::fromArray('app.x', ['class' => Greeter::class, 'lifetime' => 'request']);

        self::assertSame(ServiceLifetime::REQUEST, $definition->lifetime);
    }

    public function testTheFactoryShapeCarriesItsLifetime(): void
    {
        $definition = ServiceDefinition::fromArray(
            'app.x',
            ['factory' => ExternalFactory::class . '::make', 'lifetime' => 'transient'],
        );

        self::assertSame(ServiceLifetime::TRANSIENT, $definition->lifetime);
    }

    public function testAnAbsentLifetimeDefaultsToSingleton(): void
    {
        $definition = ServiceDefinition::fromArray('app.x', ['class' => Greeter::class]);

        self::assertSame(ServiceLifetime::SINGLETON, $definition->lifetime);
    }

    public function testTheNameKeyedHybridCarriesItsLifetimeThroughThePass(): void
    {
        $definition = ServiceDefinition::fromArray(
            'app.x',
            ['class' => Greeter::class, 'arguments' => ['salutation' => 'Hello'], 'lifetime' => 'request'],
        );

        self::assertSame(ServiceLifetime::REQUEST, $definition->lifetime);
    }

    public function testTheWholeConstructorRequestCarriesItsLifetime(): void
    {
        $definition = ServiceDefinition::fromArray(
            'app.x',
            ['class' => Greeter::class, 'autowire' => true, 'lifetime' => 'transient'],
        );

        self::assertSame(ServiceLifetime::TRANSIENT, $definition->lifetime);
    }

    public function testALifetimeOnAnAliasIsRefused(): void
    {
        try {
            ServiceDefinition::fromArray('app.x', ['alias' => 'app.y', 'lifetime' => 'request']);
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "Service 'app.x' carries a 'lifetime' on a 'alias' definition "
                . '(an alias is transparent: it rides the lifetime of its target).',
                $definitionException->getMessage(),
            );
        }
    }

    public function testALifetimeOnAValueIsRefused(): void
    {
        try {
            ServiceDefinition::fromArray('app.x', ['value' => 'a constant', 'lifetime' => 'transient']);
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "Service 'app.x' carries a 'lifetime' on a 'value' definition "
                . '(a value is a constant: it is born once and never built again).',
                $definitionException->getMessage(),
            );
        }
    }
}
