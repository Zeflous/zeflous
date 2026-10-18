<?php

declare(strict_types=1);

namespace Zef\Test\Container;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Container\ContextualMap;
use Zef\Framework\Container\DefinitionException;
use Zef\Test\Support\Container\Injector\PaymentGateway;

/**
 * @internal
 */
#[CoversClass(ContextualMap::class)]
final class ContextualMapTest extends TestCase
{
    public function testAnAbsentKnobIsTheValidNoBindingsBoot(): void
    {
        self::assertSame([], ContextualMap::parse('contextual', null)->bindings());
    }

    public function testAnEmptyMapIsTheValidNoBindingsBoot(): void
    {
        self::assertSame([], ContextualMap::parse('contextual', [])->bindings());
    }

    public function testTheKnobParsesItsConsumersAndOverrides(): void
    {
        $map = ContextualMap::parse('contextual', [
            'billing' => [PaymentGateway::class => 'gateway.stripe'],
            'reporting' => ['app.logger' => 'app.quiet.logger'],
        ]);

        self::assertSame(
            [
                'billing' => [PaymentGateway::class => 'gateway.stripe'],
                'reporting' => ['app.logger' => 'app.quiet.logger'],
            ],
            $map->bindings(),
        );
    }

    public function testANonMapKnobValueIsRejected(): void
    {
        try {
            ContextualMap::parse('contextual', 'nope');
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "The 'contextual' configuration must be a map of consumers to dependency overrides, string given.",
                $definitionException->getMessage(),
            );
        }
    }

    public function testAnEmptyConsumerKeyIsRejected(): void
    {
        try {
            ContextualMap::parse('contextual', ['' => ['app.dep' => 'app.target']]);
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "The 'contextual' keys must be non-empty consumer service identifiers, string given.",
                $definitionException->getMessage(),
            );
        }
    }

    public function testAnIntegerConsumerKeyIsRejected(): void
    {
        try {
            ContextualMap::parse('contextual', [0 => ['app.dep' => 'app.target']]);
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "The 'contextual' keys must be non-empty consumer service identifiers, int given.",
                $definitionException->getMessage(),
            );
        }
    }

    public function testANonMapOverrideEntryIsRejected(): void
    {
        try {
            ContextualMap::parse('contextual', ['billing' => 'gateway.stripe']);
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "The 'contextual' entry for consumer 'billing' must be a map of dependency overrides, string given.",
                $definitionException->getMessage(),
            );
        }
    }

    public function testAnIntegerDependencyKeyIsRejected(): void
    {
        try {
            ContextualMap::parse('contextual', ['billing' => [0 => 'gateway.stripe']]);
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "The 'contextual' overrides for consumer 'billing' must be keyed by non-empty dependency "
                . 'service identifiers, int given.',
                $definitionException->getMessage(),
            );
        }
    }

    public function testAnEmptyDependencyKeyIsRejected(): void
    {
        try {
            ContextualMap::parse('contextual', ['billing' => ['' => 'gateway.stripe']]);
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "The 'contextual' overrides for consumer 'billing' must be keyed by non-empty dependency "
                . 'service identifiers, string given.',
                $definitionException->getMessage(),
            );
        }
    }

    public function testANonStringTargetIsRejected(): void
    {
        try {
            ContextualMap::parse('contextual', ['billing' => ['app.dep' => 7]]);
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "The 'contextual' binding for consumer 'billing' dependency 'app.dep' must name a non-empty "
                . 'target service identifier, int given.',
                $definitionException->getMessage(),
            );
        }
    }

    public function testAnEmptyTargetIsRejected(): void
    {
        try {
            ContextualMap::parse('contextual', ['billing' => ['app.dep' => '']]);
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "The 'contextual' binding for consumer 'billing' dependency 'app.dep' must name a non-empty "
                . 'target service identifier, string given.',
                $definitionException->getMessage(),
            );
        }
    }
}
