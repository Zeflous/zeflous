<?php

declare(strict_types=1);

namespace Zef\Test\Container;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Container\AliasDefinition;
use Zef\Framework\Container\Container;
use Zef\Framework\Container\ContextualBinding;
use Zef\Framework\Container\ContextualBindings;
use Zef\Framework\Container\ContextualDeclarations;
use Zef\Framework\Container\ContextualMap;
use Zef\Framework\Container\ContextualReference;
use Zef\Framework\Container\ContextualRewrites;
use Zef\Framework\Container\DefinitionCompiler;
use Zef\Framework\Container\DefinitionException;
use Zef\Framework\Container\FactoryDefinition;
use Zef\Framework\Container\InstanceDefinition;
use Zef\Framework\Container\MethodCall;
use Zef\Framework\Container\ServiceDefinition;
use Zef\Framework\Container\ServiceDefinitionGraph;
use Zef\Framework\Container\ServiceLifetime;
use Zef\Framework\Container\ServiceTags;
use Zef\Framework\Container\UnplannedInstanceDefinition;
use Zef\Framework\Container\ValueDefinition;
use Zef\Test\Support\Container\Injector\BillingService;
use Zef\Test\Support\Container\Injector\MockGateway;
use Zef\Test\Support\Container\Injector\PaymentGateway;
use Zef\Test\Support\Container\Injector\ReportingService;
use Zef\Test\Support\Container\Injector\StripeGateway;
use Zef\Test\Support\Container\Injector\TunedWidget;

/**
 * @internal
 */
#[CoversClass(ContextualBindings::class)]
#[CoversClass(ContextualBinding::class)]
#[CoversClass(ContextualRewrites::class)]
#[CoversClass(ContextualDeclarations::class)]
#[CoversClass(ContextualReference::class)]
final class ContextualBindingsTest extends TestCase
{
    private const string VIA = '@contextual:billing|' . PaymentGateway::class;

    public function testAnEmptyMapAnswersTheDefinitionsUnchanged(): void
    {
        $definitions = $this->defaultSet();

        self::assertSame(
            $definitions,
            ContextualBindings::apply($definitions, ContextualMap::parse('contextual', null)),
        );
    }

    public function testABindingRewritesTheConsumerAndAppendsTheSyntheticAlias(): void
    {
        $applied = ContextualBindings::apply($this->defaultSet(), $this->stripeBinding());

        self::assertCount(5, $applied);
        self::assertInstanceOf(InstanceDefinition::class, $applied[0]);
        self::assertSame([['service' => self::VIA]], $applied[0]->arguments);
        self::assertSame(BillingService::class, $applied[0]->class, 'the consumer otherwise rebuilds verbatim');

        $synthetic = $applied[4];
        self::assertInstanceOf(AliasDefinition::class, $synthetic);
        self::assertSame(self::VIA, $synthetic->id);
        self::assertSame('gateway.stripe', $synthetic->target);
    }

    public function testTheRewrittenSetResolvesThroughTheGraphAndTheContainer(): void
    {
        $applied = ContextualBindings::apply($this->defaultSet(), $this->stripeBinding());

        ServiceDefinitionGraph::validate($applied, new Container());

        $definitionCompiler = new DefinitionCompiler();
        $container = new Container();
        foreach ($applied as $definition) {
            $container->set($definition->id, $definitionCompiler->compile($definition), $definition->lifetime);
        }

        $billing = $container->get('billing');
        self::assertInstanceOf(BillingService::class, $billing);
        self::assertInstanceOf(StripeGateway::class, $billing->gateway, 'the binding gives the target');
        self::assertSame('charged:stripe', $billing->charge());
        self::assertInstanceOf(
            MockGateway::class,
            $container->get(PaymentGateway::class),
            'the default alias still hands its own target to everyone else',
        );
    }

    public function testEveryOccurrenceOfTheDependencyIsRewritten(): void
    {
        $definitions = [
            new InstanceDefinition('billing', BillingService::class, [
                ['service' => PaymentGateway::class],
                'billing',
                ['service' => PaymentGateway::class],
            ]),
            new InstanceDefinition('gateway.mock', MockGateway::class),
            new InstanceDefinition('gateway.stripe', StripeGateway::class),
            new AliasDefinition(PaymentGateway::class, 'gateway.mock'),
        ];

        $applied = ContextualBindings::apply($definitions, $this->stripeBinding());

        $consumer = $applied[0];
        self::assertInstanceOf(InstanceDefinition::class, $consumer);
        self::assertSame(
            [['service' => self::VIA], 'billing', ['service' => self::VIA]],
            $consumer->arguments,
        );
    }

    public function testABindingRewritesTheReferencesAMethodCallCarries(): void
    {
        $definitions = [
            new InstanceDefinition(
                'reporting',
                ReportingService::class,
                [],
                ServiceLifetime::SINGLETON,
                new ServiceTags(),
                [
                    new MethodCall('setGateway', [['service' => PaymentGateway::class]]),
                ],
            ),
            new InstanceDefinition('gateway.mock', MockGateway::class),
            new InstanceDefinition('gateway.stripe', StripeGateway::class),
            new AliasDefinition(PaymentGateway::class, 'gateway.mock'),
        ];

        $applied = ContextualBindings::apply(
            $definitions,
            ContextualMap::parse('contextual', [
                'reporting' => [PaymentGateway::class => 'gateway.stripe'],
            ]),
        );

        $consumer = $applied[0];
        self::assertInstanceOf(InstanceDefinition::class, $consumer);
        $call = $consumer->calls[0];
        self::assertSame('setGateway', $call->method);
        self::assertSame([['service' => '@contextual:reporting|' . PaymentGateway::class]], $call->arguments);
    }

    public function testAFactoryShapedConsumerIsRewrittenToo(): void
    {
        $definitions = [
            new FactoryDefinition('billing', 'gateway.factory::for', [
                ['service' => PaymentGateway::class],
            ]),
            new InstanceDefinition('gateway.mock', MockGateway::class),
            new InstanceDefinition('gateway.stripe', StripeGateway::class),
            new AliasDefinition(PaymentGateway::class, 'gateway.mock'),
        ];

        $applied = ContextualBindings::apply($definitions, $this->stripeBinding());

        $consumer = $applied[0];
        self::assertInstanceOf(FactoryDefinition::class, $consumer);
        self::assertSame([['service' => self::VIA]], $consumer->arguments);
        self::assertSame('gateway.factory::for', $consumer->factory);
    }

    public function testTheRewriteTargetsTheConsumersLastOccurrence(): void
    {
        $definitions = [
            new InstanceDefinition('billing', BillingService::class, [
                ['service' => PaymentGateway::class],
            ]),
            new InstanceDefinition('billing', BillingService::class, [
                ['service' => PaymentGateway::class],
                'rebound',
            ]),
            new InstanceDefinition('gateway.mock', MockGateway::class),
            new InstanceDefinition('gateway.stripe', StripeGateway::class),
            new AliasDefinition(PaymentGateway::class, 'gateway.mock'),
        ];

        $applied = ContextualBindings::apply($definitions, $this->stripeBinding());

        $shadowed = $applied[0];
        self::assertInstanceOf(InstanceDefinition::class, $shadowed);
        self::assertSame(
            [['service' => PaymentGateway::class]],
            $shadowed->arguments,
            'the shadowed declaration keeps its own references',
        );
        $effective = $applied[1];
        self::assertInstanceOf(InstanceDefinition::class, $effective);
        self::assertSame(
            [['service' => self::VIA], 'rebound'],
            $effective->arguments,
            'the effective declaration is the one rewritten',
        );
    }

    public function testTheSyntheticAliasCarriesTheConsumersLifetime(): void
    {
        $definitions = [
            new InstanceDefinition('billing', BillingService::class, [
                ['service' => PaymentGateway::class],
            ], ServiceLifetime::REQUEST),
            new InstanceDefinition('gateway.mock', MockGateway::class),
            new InstanceDefinition('gateway.stripe', StripeGateway::class, [], ServiceLifetime::REQUEST),
            new AliasDefinition(PaymentGateway::class, 'gateway.mock'),
        ];

        $applied = ContextualBindings::apply($definitions, $this->stripeBinding());

        self::assertSame(ServiceLifetime::REQUEST, $applied[4]->lifetime);
    }

    public function testAnUnregisteredConsumerIsRefused(): void
    {
        try {
            ContextualBindings::apply(
                $this->defaultSet(),
                ContextualMap::parse('contextual', ['invoice' => [PaymentGateway::class => 'gateway.stripe']]),
            );
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "Contextual binding: consumer 'invoice' is not a registered service.",
                $definitionException->getMessage(),
            );
        }
    }

    public function testADependencyTheConsumerNeverDeclaresIsRefused(): void
    {
        try {
            ContextualBindings::apply(
                $this->defaultSet(),
                ContextualMap::parse('contextual', ['billing' => ['gateway.mock' => 'gateway.stripe']]),
            );
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "Contextual binding: consumer 'billing' does not declare the dependency 'gateway.mock' "
                . '(a binding overrides a service reference carried by the consumer arguments or calls).',
                $definitionException->getMessage(),
            );
        }
    }

    public function testAnUnplannedConsumerIsRefused(): void
    {
        $definitions = [
            new UnplannedInstanceDefinition('billing', BillingService::class),
            new InstanceDefinition('gateway.mock', MockGateway::class),
            new InstanceDefinition('gateway.stripe', StripeGateway::class),
            new AliasDefinition(PaymentGateway::class, 'gateway.mock'),
        ];

        try {
            ContextualBindings::apply($definitions, $this->stripeBinding());
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "Contextual binding: consumer 'billing' carries an unplanned constructor plan -- "
                . 'run the autowire compiler pass before applying contextual bindings.',
                $definitionException->getMessage(),
            );
        }
    }

    public function testATakenSyntheticIdentifierIsRefused(): void
    {
        $definitions = [
            new InstanceDefinition('billing', BillingService::class, [
                ['service' => PaymentGateway::class],
            ]),
            new InstanceDefinition('gateway.mock', MockGateway::class),
            new InstanceDefinition('gateway.stripe', StripeGateway::class),
            new AliasDefinition(PaymentGateway::class, 'gateway.mock'),
            new ValueDefinition(self::VIA, 'host-owned'),
        ];

        try {
            ContextualBindings::apply($definitions, $this->stripeBinding());
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "Contextual binding: the synthetic identifier '" . self::VIA . "' is already taken "
                . '(reserved for contextual bindings; declare the target under another identifier).',
                $definitionException->getMessage(),
            );
        }
    }

    public function testAMissingTargetFailsTheGraphsUnknownServiceRefusal(): void
    {
        $applied = ContextualBindings::apply(
            $this->defaultSet(),
            ContextualMap::parse('contextual', ['billing' => [PaymentGateway::class => 'gateway.absent']]),
        );

        try {
            ServiceDefinitionGraph::validate($applied, new Container());
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "Service '" . self::VIA . "' references the unknown service 'gateway.absent'.",
                $definitionException->getMessage(),
            );
        }
    }

    public function testASingletonConsumerBindingARequestScopedTargetIsTheCaptiveDependency(): void
    {
        $definitions = [
            new InstanceDefinition('billing', BillingService::class, [
                ['service' => 'gateway.mock'],
            ]),
            new InstanceDefinition('gateway.mock', MockGateway::class),
            new InstanceDefinition('gateway.stripe', StripeGateway::class, [], ServiceLifetime::REQUEST),
        ];

        ServiceDefinitionGraph::validate($definitions, new Container());

        $applied = ContextualBindings::apply(
            $definitions,
            ContextualMap::parse('contextual', ['billing' => ['gateway.mock' => 'gateway.stripe']]),
        );

        try {
            ServiceDefinitionGraph::validate($applied, new Container());
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "Service '@contextual:billing|gateway.mock' (singleton) depends on the request-scoped service "
                . "'gateway.stripe': "
                . "a request-scoped instance cannot be captured beyond its scope -- give 'gateway.stripe' "
                . "a singleton or transient lifetime, or make '@contextual:billing|gateway.mock' request-scoped.",
                $definitionException->getMessage(),
            );
        }
    }

    public function testARequestScopedConsumerBindingARequestScopedTargetStaysLegal(): void
    {
        $definitions = [
            new InstanceDefinition('billing', BillingService::class, [
                ['service' => PaymentGateway::class],
            ], ServiceLifetime::REQUEST),
            new InstanceDefinition('gateway.stripe', StripeGateway::class, [], ServiceLifetime::REQUEST),
        ];

        $applied = ContextualBindings::apply($definitions, $this->stripeBinding());

        ServiceDefinitionGraph::validate($applied, new Container());

        self::assertCount(3, $applied);
        self::assertSame(ServiceLifetime::REQUEST, $applied[2]->lifetime);
    }

    public function testACycleThroughTheBindingIsWalkedByTheCycleDiscipline(): void
    {
        $definitions = [
            new InstanceDefinition('billing', BillingService::class, [
                ['service' => PaymentGateway::class],
            ]),
            new InstanceDefinition('gateway.stripe', StripeGateway::class, [
                ['service' => 'billing'],
            ]),
        ];

        $applied = ContextualBindings::apply($definitions, $this->stripeBinding());

        try {
            ServiceDefinitionGraph::validate($applied, new Container());
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertStringContainsString(
                'circular',
                $definitionException->getMessage(),
            );
        }
    }

    public function testDeclaresAnswersForEveryReferenceRealm(): void
    {
        $withArguments = new InstanceDefinition('a', BillingService::class, [
            ['service' => PaymentGateway::class],
        ]);
        $withCalls = new InstanceDefinition(
            'b',
            ReportingService::class,
            [],
            ServiceLifetime::SINGLETON,
            new ServiceTags(),
            [
                new MethodCall('setGateway', [['service' => PaymentGateway::class]]),
            ],
        );
        $without = new InstanceDefinition('c', TunedWidget::class, ['label']);

        self::assertTrue(ContextualDeclarations::declares($withArguments, PaymentGateway::class));
        self::assertTrue(ContextualDeclarations::declares($withCalls, PaymentGateway::class));
        self::assertFalse(ContextualDeclarations::declares($without, PaymentGateway::class));
        self::assertFalse(
            ContextualDeclarations::declares(new ValueDefinition('d', 'value'), PaymentGateway::class),
        );
    }

    public function testRewritingAShapeThatCarriesNoReferencesIsRefused(): void
    {
        try {
            ContextualRewrites::rewritten(new AliasDefinition('a', 'b'), 'app.dep', 'app.via');
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "Contextual binding: consumer 'a' does not declare the dependency 'app.dep' "
                . '(a binding overrides a service reference carried by the consumer arguments or calls).',
                $definitionException->getMessage(),
            );
        }
    }

    /**
     * @return list<ServiceDefinition>
     */
    private function defaultSet(): array
    {
        return [
            new InstanceDefinition('billing', BillingService::class, [
                ['service' => PaymentGateway::class],
            ]),
            new InstanceDefinition('gateway.mock', MockGateway::class),
            new InstanceDefinition('gateway.stripe', StripeGateway::class),
            new AliasDefinition(PaymentGateway::class, 'gateway.mock'),
        ];
    }

    private function stripeBinding(): ContextualMap
    {
        return ContextualMap::parse('contextual', [
            'billing' => [PaymentGateway::class => 'gateway.stripe'],
        ]);
    }
}
