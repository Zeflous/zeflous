<?php

declare(strict_types=1);

namespace Zef\Test;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Config\Config;
use Zef\Framework\Container\Container;
use Zef\Framework\Container\DefinitionException;
use Zef\Framework\Container\DefinitionMap;
use Zef\Framework\ContainerBootstrapper;
use Zef\Test\Support\Container\Announcer;
use Zef\Test\Support\Container\ExternalFactory;
use Zef\Test\Support\Container\Greeter;

/**
 * @internal
 */
#[CoversClass(ContainerBootstrapper::class)]
#[CoversClass(DefinitionMap::class)]
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
                        'app.greeter' => ['class' => Greeter::class],
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
}
