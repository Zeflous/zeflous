<?php

declare(strict_types=1);

namespace Zef\Test;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Config\Config;
use Zef\Framework\Config\Merge\EnvSource;
use Zef\Framework\Config\Merge\LayeredConfigBuilder;
use Zef\Framework\Container\Autowiring\AutowireValueMaterialisations;
use Zef\Framework\Container\Autowiring\AutowireValueServices;
use Zef\Framework\Container\Autowiring\AutowiringException;
use Zef\Framework\Container\Autowiring\SyntheticValueGuard;
use Zef\Framework\Container\Autowiring\Value;
use Zef\Framework\Container\Container;
use Zef\Framework\Container\DefinitionException;
use Zef\Framework\Container\DefinitionMap;
use Zef\Framework\Container\ParameterInjectability;
use Zef\Framework\Container\ParameterKeys;
use Zef\Framework\Container\ParameterLiterals;
use Zef\Framework\Container\ParameterMap;
use Zef\Framework\Container\ParameterProviderAnswers;
use Zef\Framework\Container\ParameterProviderCheck;
use Zef\Framework\Container\ParameterProviderConstruction;
use Zef\Framework\Container\ParameterProviders;
use Zef\Framework\Container\ParameterRefusals;
use Zef\Framework\Container\ParameterValues;
use Zef\Framework\Container\RequestScopeException;
use Zef\Framework\ContainerBootstrapper;
use Zef\Test\Support\Container\Announcer;
use Zef\Test\Support\Container\Autowiring\DependentHouse;
use Zef\Test\Support\Container\Autowiring\SimpleDep;
use Zef\Test\Support\Container\Autowiring\TypedHouse;
use Zef\Test\Support\Container\ExternalFactory;
use Zef\Test\Support\Container\Greeter;
use Zef\Test\Support\Container\Injector\BillingService;
use Zef\Test\Support\Container\Injector\FactoryProduct;
use Zef\Test\Support\Container\Injector\MockGateway;
use Zef\Test\Support\Container\Injector\PaymentGateway;
use Zef\Test\Support\Container\Injector\ReportingService;
use Zef\Test\Support\Container\Injector\StripeGateway;
use Zef\Test\Support\Container\Injector\TunedWidget;
use Zef\Test\Support\Container\Parameters\ConnectionHouse;
use Zef\Test\Support\Container\Parameters\PoolHouse;
use Zef\Test\Support\Container\Parameters\SharedFlagAudit;
use Zef\Test\Support\Container\Parameters\SharedFlagHouse;
use Zef\Test\Support\Container\Parameters\VersionProvider;

/**
 * @internal
 */
#[CoversClass(ContainerBootstrapper::class)]
#[CoversClass(DefinitionMap::class)]
#[CoversClass(ParameterMap::class)]
#[CoversClass(ParameterInjectability::class)]
#[CoversClass(ParameterProviderCheck::class)]
#[CoversClass(ParameterProviderConstruction::class)]
#[CoversClass(ParameterProviderAnswers::class)]
#[CoversClass(SyntheticValueGuard::class)]
#[CoversClass(AutowireValueMaterialisations::class)]
#[CoversClass(ParameterValues::class)]
#[CoversClass(ParameterLiterals::class)]
#[CoversClass(ParameterKeys::class)]
#[CoversClass(ParameterProviders::class)]
#[CoversClass(ParameterRefusals::class)]
#[CoversClass(AutowireValueServices::class)]
#[CoversClass(Value::class)]
final class ContainerBootstrapperTest extends TestCase
{
    public function testAConfigurationWithoutAServicesKeyLeavesTheContainerUntouched(): void
    {
        $container = new Container();

        $result = new ContainerBootstrapper()->bootstrap(new Config([]), $container);

        self::assertSame($container, $result);
        self::assertFalse($container->has('anything'));
    }

    public function testAExplicitlyNullServicesKeyIsAValidEmptyServiceSet(): void
    {
        $container = new Container();

        $result = new ContainerBootstrapper()->bootstrap(
            new Config(['services' => null]),
            $container,
        );

        self::assertSame($container, $result);
        self::assertFalse($container->has('anything'));
    }

    public function testANonMapServicesValueIsRejected(): void
    {
        try {
            new ContainerBootstrapper()->bootstrap(new Config(['services' => 'nope']), new Container());
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "The 'services' configuration must be a map of definitions, string given.",
                $definitionException->getMessage(),
            );
        }
    }

    public function testANonStringKeyIsRejected(): void
    {
        try {
            new ContainerBootstrapper()->bootstrap(new Config(['services' => [0 => ['value' => 1]]]), new Container());
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertStringContainsString(
                "The 'services' configuration keys must be non-empty service identifiers, int given.",
                $definitionException->getMessage(),
            );
        }
    }

    public function testEveryDeclarativeShapeInstallsThroughTheContainersSurface(): void
    {
        $container = new Container();
        $container->set('app.builder', static fn (): ExternalFactory => new ExternalFactory('built'));

        new ContainerBootstrapper()->bootstrap(
            new Config([
                'services' => [
                    'app.salutation' => ['value' => 'Hello'],
                    'app.greeter' => [
                        'class' => Greeter::class,
                        'arguments' => [['service' => 'app.salutation'], 2],
                    ],
                    'app.announcer' => [
                        'class' => Announcer::class,
                        'arguments' => [['service' => 'app.greeter'], 'ops'],
                    ],
                    'app.label' => [
                        'factory' => 'app.builder::build',
                        'arguments' => ['widget'],
                    ],
                    'app.label.static' => [
                        'factory' => ExternalFactory::class . '::make',
                        'arguments' => ['gadget'],
                    ],
                    Greeter::class => ['alias' => 'app.greeter'],
                ],
            ]),
            $container,
        );

        $greeter = $container->get('app.greeter');
        self::assertInstanceOf(Greeter::class, $greeter);
        self::assertSame('Hello', $greeter->salutation);

        $announcer = $container->get('app.announcer');
        self::assertInstanceOf(Announcer::class, $announcer);
        self::assertSame('[ops] Hello Zef!', $announcer->announce('Zef'));
        self::assertSame($greeter, $announcer->greeter, 'the reference resolves to the memoised singleton');
        self::assertSame($greeter, $container->get(Greeter::class), 'the alias answers the same instance');

        self::assertSame('built:widget', $container->get('app.label'));
        self::assertSame('made:gadget', $container->get('app.label.static'));
    }

    public function testDefinitionsAreValidatedBeforeAnyIsInstalled(): void
    {
        $container = new Container();

        try {
            new ContainerBootstrapper()->bootstrap(
                new Config([
                    'services' => [
                        'app.greeter' => ['class' => Greeter::class, 'arguments' => ['Hello']],
                        'app.broken' => ['class' => Greeter::class, 'arguments' => [['service' => 'app.missing']]],
                    ],
                ]),
                $container,
            );
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertStringContainsString(
                "references the unknown service 'app.missing'",
                $definitionException->getMessage(),
            );
        }

        self::assertFalse(
            $container->has('app.greeter'),
            'the valid definition is not installed when a sibling fails validation',
        );
    }

    public function testACircularSetIsRejectedAndNothingIsInstalled(): void
    {
        $container = new Container();

        try {
            new ContainerBootstrapper()->bootstrap(
                new Config([
                    'services' => [
                        'app.a' => ['class' => Greeter::class, 'arguments' => [['service' => 'app.b']]],
                        'app.b' => ['class' => Greeter::class, 'arguments' => [['service' => 'app.a']]],
                    ],
                ]),
                $container,
            );
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertStringContainsString('circular reference chain', $definitionException->getMessage());
        }

        self::assertFalse($container->has('app.a'));
        self::assertFalse($container->has('app.b'));
    }

    public function testAReferenceIntoAHostRegisterServiceValidates(): void
    {
        $container = new Container();
        $container->set('app.salutation', static fn (): string => 'Hello');

        new ContainerBootstrapper()->bootstrap(
            new Config([
                'services' => [
                    'app.greeter' => [
                        'class' => Greeter::class,
                        'arguments' => [['service' => 'app.salutation']],
                    ],
                ],
            ]),
            $container,
        );

        $greeter = $container->get('app.greeter');

        self::assertInstanceOf(Greeter::class, $greeter);
        self::assertSame('Hello', $greeter->salutation);
    }

    public function testADeclarativeServiceCanBeOverriddenByTheHost(): void
    {
        $container = new Container();

        new ContainerBootstrapper()->bootstrap(
            new Config(['services' => ['app.debug' => ['value' => true]]]),
            $container,
        );

        $container->set('app.debug', static fn (): bool => false);

        self::assertFalse($container->get('app.debug'));
    }

    public function testTheFrameworkServicesMapInstallsAlongsideTheHostMap(): void
    {
        $container = new Container();

        new ContainerBootstrapper()->bootstrap(
            new Config([
                'framework' => [
                    'services' => [
                        'framework.greeter' => ['class' => Greeter::class, 'arguments' => ['Hello']],
                    ],
                ],
                'services' => ['host.greeter' => ['class' => Greeter::class, 'arguments' => ['Hi']]],
            ]),
            $container,
        );

        self::assertInstanceOf(Greeter::class, $container->get('framework.greeter'));
        self::assertInstanceOf(Greeter::class, $container->get('host.greeter'));
        self::assertSame('Hello', $container->get('framework.greeter')->salutation);
        self::assertSame('Hi', $container->get('host.greeter')->salutation);
    }

    public function testANonMapFrameworkServicesValueIsRejectedUnderItsOwnName(): void
    {
        try {
            new ContainerBootstrapper()->bootstrap(
                new Config(['framework' => ['services' => 'nope']]),
                new Container(),
            );
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "The 'framework.services' configuration must be a map of definitions, string given.",
                $definitionException->getMessage(),
            );
        }
    }

    public function testAHostDefinitionWithTheFrameworkIdentifierRebindsIt(): void
    {
        $container = new Container();

        new ContainerBootstrapper()->bootstrap(
            new Config([
                'framework' => ['services' => ['shared.subject' => ['value' => 'from-framework']]],
                'services' => ['shared.subject' => ['value' => 'from-host']],
            ]),
            $container,
        );

        self::assertSame(
            'from-host',
            $container->get('shared.subject'),
            "the host's same-identifier entry wins",
        );
    }

    public function testTheHybridAutowireFormBootsAndResolves(): void
    {
        $container = new Container();

        new ContainerBootstrapper()->bootstrap(
            new Config([
                'services' => [
                    'app.greeter' => ['class' => Greeter::class, 'arguments' => ['salutation' => 'Hello']],
                    Greeter::class => ['alias' => 'app.greeter'],
                    'app.house' => ['class' => TypedHouse::class, 'arguments' => ['channel' => 'ops']],
                ],
            ]),
            $container,
        );

        $house = $container->get('app.house');
        self::assertInstanceOf(TypedHouse::class, $house);
        self::assertSame('ops', $house->channel);
        self::assertSame('Hello', $house->greeter->salutation);
        self::assertSame(1, $house->greeter->verbosity);
        self::assertSame($container->get(Greeter::class), $house->greeter);
    }

    public function testTheAutowireFormBirthsItsDependenciesAndResolves(): void
    {
        $container = new Container();

        new ContainerBootstrapper()->bootstrap(
            new Config([
                'services' => [
                    'app.house' => ['class' => DependentHouse::class, 'autowire' => true],
                ],
            ]),
            $container,
        );

        self::assertTrue($container->has(SimpleDep::class));
        $house = $container->get('app.house');
        self::assertInstanceOf(DependentHouse::class, $house);
        self::assertSame('simple', $house->dep->name);
        self::assertSame('lbl', $house->label);
        self::assertSame($container->get(SimpleDep::class), $house->dep);
    }

    public function testAMissingArgumentListOverARequiredParameterIsRefusedAtBoot(): void
    {
        $container = new Container();

        try {
            new ContainerBootstrapper()->bootstrap(
                new Config([
                    'services' => [
                        'app.greeter' => ['class' => Greeter::class],
                    ],
                ]),
                $container,
            );
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertStringContainsString(
                "the declared arguments stop before the required parameter '\$salutation'",
                $definitionException->getMessage(),
            );
        }

        self::assertFalse($container->has('app.greeter'));
    }

    public function testTheRequestLifetimeBootsAndResolvesThroughItsScope(): void
    {
        $container = new Container();

        new ContainerBootstrapper()->bootstrap(
            new Config([
                'services' => [
                    'app.greeter' => [
                        'class' => Greeter::class,
                        'arguments' => ['salutation' => 'Hello'],
                        'lifetime' => 'request',
                    ],
                ],
            ]),
            $container,
        );

        try {
            $container->get('app.greeter');
            self::fail('Expected a RequestScopeException to be thrown.');
        } catch (RequestScopeException $requestScopeException) {
            self::assertStringContainsString(
                "Service 'app.greeter' is request-scoped and cannot be resolved outside a request scope",
                $requestScopeException->getMessage(),
            );
        }

        $scope = $container->createRequestScope();
        $greeter = $scope->get('app.greeter');
        self::assertInstanceOf(Greeter::class, $greeter);
        self::assertSame('Hello', $greeter->salutation);
        self::assertSame($greeter, $scope->get('app.greeter'));
    }

    public function testTheTransientLifetimeBootsAndBuildsFresh(): void
    {
        $container = new Container();

        new ContainerBootstrapper()->bootstrap(
            new Config([
                'services' => [
                    'app.greeter' => [
                        'class' => Greeter::class,
                        'arguments' => ['salutation' => 'Hello'],
                        'lifetime' => 'transient',
                    ],
                ],
            ]),
            $container,
        );

        self::assertNotSame($container->get('app.greeter'), $container->get('app.greeter'));
    }

    public function testTheHybridAutowireFormCarriesItsLifetimeThroughThePass(): void
    {
        $container = new Container();

        new ContainerBootstrapper()->bootstrap(
            new Config([
                'services' => [
                    'app.greeter' => ['class' => Greeter::class, 'arguments' => ['salutation' => 'Hello']],
                    Greeter::class => ['alias' => 'app.greeter'],
                    'app.house' => [
                        'class' => TypedHouse::class,
                        'arguments' => ['channel' => 'ops'],
                        'lifetime' => 'request',
                    ],
                ],
            ]),
            $container,
        );

        $scope = $container->createRequestScope();
        $house = $scope->get('app.house');
        self::assertInstanceOf(TypedHouse::class, $house);
        self::assertSame($house, $scope->get('app.house'));
        self::assertNotSame($house, $container->createRequestScope()->get('app.house'));
    }

    public function testACaptiveDependencyIsRefusedAtBoot(): void
    {
        $container = new Container();

        try {
            new ContainerBootstrapper()->bootstrap(
                new Config([
                    'services' => [
                        'app.leaf' => [
                            'class' => Greeter::class,
                            'arguments' => ['salutation' => 'Hello'],
                            'lifetime' => 'request',
                        ],
                        'app.holder' => [
                            'class' => Announcer::class,
                            'arguments' => [['service' => 'app.leaf'], 'ops'],
                        ],
                    ],
                ]),
                $container,
            );
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "Service 'app.holder' (singleton) depends on the request-scoped service 'app.leaf': "
                . 'a request-scoped instance cannot be captured beyond its scope -- '
                . "give 'app.leaf' a singleton or transient lifetime, or make 'app.holder' request-scoped.",
                $definitionException->getMessage(),
            );
        }

        self::assertFalse($container->has('app.leaf'));
    }

    public function testAClassShapedDeclarationWithCallsInjectsAfterConstruction(): void
    {
        $container = new Container();

        new ContainerBootstrapper()->bootstrap(
            new Config([
                'services' => [
                    'app.tag' => ['value' => 'ops'],
                    'app.widget' => [
                        'class' => TunedWidget::class,
                        'arguments' => ['main'],
                        'calls' => [
                            ['method' => 'setVolume', 'arguments' => [7]],
                            ['method' => 'addTag', 'arguments' => [['service' => 'app.tag']]],
                            ['method' => 'warmUp'],
                        ],
                    ],
                ],
            ]),
            $container,
        );

        $widget = $container->get('app.widget');
        self::assertInstanceOf(TunedWidget::class, $widget);
        self::assertSame('main', $widget->label);
        self::assertSame(['volume:7', 'tag:ops', 'warm'], $widget->log());
        self::assertTrue($widget->warmed());
    }

    public function testTheHybridAutowireFormCarriesItsCallsThroughThePass(): void
    {
        $container = new Container();

        new ContainerBootstrapper()->bootstrap(
            new Config([
                'services' => [
                    'app.widget' => [
                        'class' => TunedWidget::class,
                        'arguments' => ['label' => 'hybrid'],
                        'calls' => [['method' => 'setVolume', 'arguments' => [3]]],
                    ],
                ],
            ]),
            $container,
        );

        $widget = $container->get('app.widget');
        self::assertInstanceOf(TunedWidget::class, $widget);
        self::assertSame('hybrid', $widget->label);
        self::assertSame(['volume:3'], $widget->log());
    }

    public function testAFactoryShapedDeclarationWithCallsInjectsIntoTheProduct(): void
    {
        $container = new Container();

        new ContainerBootstrapper()->bootstrap(
            new Config([
                'services' => [
                    'app.product' => [
                        'factory' => FactoryProduct::class . '::make',
                        'arguments' => ['gadget'],
                        'calls' => [['method' => 'setOrigin', 'arguments' => ['boot']]],
                    ],
                ],
            ]),
            $container,
        );

        $product = $container->get('app.product');
        self::assertInstanceOf(FactoryProduct::class, $product);
        self::assertSame('boot:gadget', $product->describe());
    }

    public function testCallsOnAnAliasShapedDeclarationAreRefusedAtBoot(): void
    {
        try {
            new ContainerBootstrapper()->bootstrap(
                new Config([
                    'services' => [
                        'app.greeter' => ['class' => Greeter::class, 'arguments' => ['Hello']],
                        'app.alias' => ['alias' => 'app.greeter', 'calls' => []],
                    ],
                ]),
                new Container(),
            );
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "Service 'app.alias' carries 'calls' on a 'alias' definition "
                . '(an alias is transparent: the call belongs on the definition it points to).',
                $definitionException->getMessage(),
            );
        }
    }

    public function testCallsOnAValueShapedDeclarationAreRefusedAtBoot(): void
    {
        try {
            new ContainerBootstrapper()->bootstrap(
                new Config([
                    'services' => [
                        'app.flag' => ['value' => true, 'calls' => []],
                    ],
                ]),
                new Container(),
            );
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "Service 'app.flag' carries 'calls' on a 'value' definition "
                . '(a value is a constant: there is no instance to call methods on).',
                $definitionException->getMessage(),
            );
        }
    }

    public function testAClassShapedCallToAMissingMethodIsRefusedAtBoot(): void
    {
        try {
            new ContainerBootstrapper()->bootstrap(
                new Config([
                    'services' => [
                        'app.widget' => [
                            'class' => TunedWidget::class,
                            'arguments' => ['main'],
                            'calls' => [['method' => 'detune']],
                        ],
                    ],
                ]),
                new Container(),
            );
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "Service 'app.widget' call method 'detune' does not exist on '" . TunedWidget::class . "'.",
                $definitionException->getMessage(),
            );
        }
    }

    public function testAContextualBindingGivesTheConsumerItsOwnTarget(): void
    {
        $container = new Container();

        new ContainerBootstrapper()->bootstrap(
            new Config([
                'services' => [
                    'gateway.mock' => ['class' => MockGateway::class],
                    'gateway.stripe' => ['class' => StripeGateway::class],
                    PaymentGateway::class => ['alias' => 'gateway.mock'],
                    'billing' => ['class' => BillingService::class, 'autowire' => true],
                ],
                'contextual' => [
                    'billing' => [PaymentGateway::class => 'gateway.stripe'],
                ],
            ]),
            $container,
        );

        $billing = $container->get('billing');
        self::assertInstanceOf(BillingService::class, $billing);
        self::assertSame('charged:stripe', $billing->charge());
        self::assertInstanceOf(
            MockGateway::class,
            $container->get(PaymentGateway::class),
            'the default alias still hands its own target to everyone else',
        );
    }

    public function testAContextualBindingRewritesTheReferencesACallCarries(): void
    {
        $container = new Container();

        new ContainerBootstrapper()->bootstrap(
            new Config([
                'services' => [
                    'gateway.mock' => ['class' => MockGateway::class],
                    'gateway.stripe' => ['class' => StripeGateway::class],
                    PaymentGateway::class => ['alias' => 'gateway.mock'],
                    'app.report' => [
                        'class' => ReportingService::class,
                        'autowire' => true,
                        'calls' => [
                            ['method' => 'setGateway', 'arguments' => [['service' => PaymentGateway::class]]],
                        ],
                    ],
                ],
                'contextual' => [
                    'app.report' => [PaymentGateway::class => 'gateway.stripe'],
                ],
            ]),
            $container,
        );

        $report = $container->get('app.report');
        self::assertInstanceOf(ReportingService::class, $report);
        self::assertSame('stripe', $report->gatewayName());
    }

    public function testAContextualBindingForAnUnregisteredConsumerIsRefusedAtBoot(): void
    {
        try {
            new ContainerBootstrapper()->bootstrap(
                new Config([
                    'services' => [
                        'gateway.mock' => ['class' => MockGateway::class],
                    ],
                    'contextual' => [
                        'billing' => [PaymentGateway::class => 'gateway.mock'],
                    ],
                ]),
                new Container(),
            );
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "Contextual binding: consumer 'billing' is not a registered service.",
                $definitionException->getMessage(),
            );
        }
    }

    public function testARequestScopedConsumersBindingResolvesPerScope(): void
    {
        $container = new Container();

        new ContainerBootstrapper()->bootstrap(
            new Config([
                'services' => [
                    'gateway.stripe' => [
                        'class' => StripeGateway::class,
                        'lifetime' => 'request',
                    ],
                    'billing' => [
                        'class' => BillingService::class,
                        'arguments' => [['service' => PaymentGateway::class]],
                        'lifetime' => 'request',
                    ],
                ],
                'contextual' => [
                    'billing' => [PaymentGateway::class => 'gateway.stripe'],
                ],
            ]),
            $container,
        );

        $firstScope = $container->createRequestScope();
        $firstBilling = $firstScope->get('billing');
        self::assertInstanceOf(BillingService::class, $firstBilling);
        self::assertSame('charged:stripe', $firstBilling->charge());
        self::assertSame($firstBilling, $firstScope->get('billing'), 'the scope memoises');

        $secondScope = $container->createRequestScope();
        $secondBilling = $secondScope->get('billing');
        self::assertInstanceOf(BillingService::class, $secondBilling);
        self::assertNotSame($firstBilling, $secondBilling, 'each scope builds its own');
        self::assertNotSame(
            $firstBilling->gateway,
            $secondBilling->gateway,
            'the binding flows through the per-scope synthetic alias',
        );
    }

    public function testAContextualBindingToAMissingTargetIsRefusedAtBoot(): void
    {
        try {
            new ContainerBootstrapper()->bootstrap(
                new Config([
                    'services' => [
                        'billing' => [
                            'class' => BillingService::class,
                            'arguments' => [['service' => PaymentGateway::class]],
                        ],
                    ],
                    'contextual' => [
                        'billing' => [PaymentGateway::class => 'gateway.absent'],
                    ],
                ]),
                new Container(),
            );
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "Service '@contextual:billing|" . PaymentGateway::class
                    . "' references the unknown service 'gateway.absent'.",
                $definitionException->getMessage(),
            );
        }
    }

    public function testAValueAddressBootsIntoTheConstructedService(): void
    {
        $container = new ContainerBootstrapper()->bootstrap(new Config([
            'services' => [
                'app.connection' => ['class' => ConnectionHouse::class, 'autowire' => true],
            ],
            'parameters' => [
                'db' => ['host' => 'localhost', 'port' => 3306],
            ],
        ]), new Container());

        $connection = $container->get('app.connection');

        self::assertInstanceOf(ConnectionHouse::class, $connection);
        self::assertSame('localhost', $connection->host);
        self::assertSame(3306, $connection->port);
        self::assertSame('localhost', $container->get('@value:db.host'));
        self::assertSame('localhost', $container->get('@value:db.host'));
    }

    public function testAnArrayParameterBootsIntoTheConstructedService(): void
    {
        $container = new ContainerBootstrapper()->bootstrap(new Config([
            'services' => [
                'app.pool' => ['class' => PoolHouse::class, 'autowire' => true],
            ],
            'parameters' => [
                'db' => ['replicas' => ['a.example', 'b.example']],
            ],
        ]), new Container());

        $pool = $container->get('app.pool');
        self::assertInstanceOf(PoolHouse::class, $pool);
        self::assertSame(['a.example', 'b.example'], $pool->replicas);
    }

    public function testAParameterProviderComputesTheValueAtBoot(): void
    {
        $container = new ContainerBootstrapper()->bootstrap(new Config([
            'services' => [
                'app.connection' => ['class' => ConnectionHouse::class, 'autowire' => true],
            ],
            'parameters' => [
                'providers' => [
                    'db.host' => VersionProvider::class,
                ],
                'db' => ['port' => 3306],
            ],
        ]), new Container());

        $connection = $container->get('app.connection');
        self::assertInstanceOf(ConnectionHouse::class, $connection);

        self::assertSame('2.9.0-zeflous', $connection->host);
        self::assertSame(3306, $connection->port);
    }

    public function testTheEnvironmentLayerAnswersAParameterValue(): void
    {
        $config = LayeredConfigBuilder::create()
            ->withApp([
                'services' => [
                    'app.connection' => ['class' => ConnectionHouse::class, 'autowire' => true],
                ],
                'parameters' => [
                    'db' => ['host' => 'file.example', 'port' => 3306],
                ],
            ])
            ->withEnvironment(new EnvSource('APP_', [
                'APP_PARAMETERS__DB__HOST' => 'env.example',
            ]))
            ->build()
        ;

        $container = new ContainerBootstrapper()->bootstrap($config, new Container());

        $connection = $container->get('app.connection');
        self::assertInstanceOf(ConnectionHouse::class, $connection);
        self::assertSame('env.example', $connection->host);
        self::assertSame(3306, $connection->port);
    }

    public function testTwoConsumersShareOneMaterialisedValueService(): void
    {
        $container = new ContainerBootstrapper()->bootstrap(new Config([
            'services' => [
                'app.house' => ['class' => SharedFlagHouse::class, 'autowire' => true],
                'app.audit' => ['class' => SharedFlagAudit::class, 'autowire' => true],
            ],
            'parameters' => [
                'shared' => ['flag' => true],
            ],
        ]), new Container());

        $house = $container->get('app.house');
        $audit = $container->get('app.audit');
        self::assertInstanceOf(SharedFlagHouse::class, $house);
        self::assertInstanceOf(SharedFlagAudit::class, $audit);

        self::assertTrue($house->flag);
        self::assertTrue($audit->flag);
        self::assertTrue($container->get('@value:shared.flag'));
    }

    public function testAMissingParameterValueRefusesTheBoot(): void
    {
        try {
            new ContainerBootstrapper()->bootstrap(new Config([
                'services' => [
                    'app.connection' => ['class' => ConnectionHouse::class, 'autowire' => true],
                ],
                'parameters' => [
                    'db' => ['port' => 3306],
                ],
            ]), new Container());
            self::fail('Expected an AutowiringException to be thrown.');
        } catch (AutowiringException $autowiringException) {
            self::assertStringContainsString(
                "the parameter '\$host' carries #[Value('db.host')] but the 'parameters' configuration",
                $autowiringException->getMessage(),
            );
        }
    }

    public function testADeclaredSyntheticValueIdRefusesTheBoot(): void
    {
        try {
            new ContainerBootstrapper()->bootstrap(new Config([
                'services' => [
                    'app.connection' => ['class' => ConnectionHouse::class, 'autowire' => true],
                    '@value:db.host' => ['value' => 'shadowed'],
                ],
                'parameters' => [
                    'db' => ['host' => 'localhost', 'port' => 3306],
                ],
            ]), new Container());
            self::fail('Expected an AutowiringException to be thrown.');
        } catch (AutowiringException $autowiringException) {
            self::assertStringContainsString(
                "the synthetic value service '@value:db.host' collides with a declared definition",
                $autowiringException->getMessage(),
            );
        }
    }

    public function testADoubleSourcedParameterRefusesTheBoot(): void
    {
        try {
            new ContainerBootstrapper()->bootstrap(new Config([
                'parameters' => [
                    'providers' => ['db.host' => VersionProvider::class],
                    'db' => ['host' => 'localhost'],
                ],
            ]), new Container());
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "The parameter 'db.host' is declared both as a literal value and through "
                . "'parameters' > 'providers' -- one address, one source.",
                $definitionException->getMessage(),
            );
        }
    }

    public function testANonMapParametersKnobRefusesTheBoot(): void
    {
        try {
            new ContainerBootstrapper()->bootstrap(new Config(['parameters' => 'nope']), new Container());
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "The 'parameters' configuration must be a map of addresses to values, string given.",
                $definitionException->getMessage(),
            );
        }
    }

    public function testANullParametersKnobIsTheValidEmptyBoot(): void
    {
        $container = new ContainerBootstrapper()->bootstrap(
            new Config(['parameters' => null]),
            new Container(),
        );

        self::assertFalse($container->has('anything'));
    }
}
