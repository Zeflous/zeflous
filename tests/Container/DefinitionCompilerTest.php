<?php

declare(strict_types=1);

namespace Zef\Test\Container;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Container\AliasDefinition;
use Zef\Framework\Container\ArgumentResolution;
use Zef\Framework\Container\Container;
use Zef\Framework\Container\DefinitionCompiler;
use Zef\Framework\Container\DefinitionException;
use Zef\Framework\Container\FactoryClosures;
use Zef\Framework\Container\FactoryDefinition;
use Zef\Framework\Container\FactoryInvocation;
use Zef\Framework\Container\InstanceDefinition;
use Zef\Framework\Container\ServiceDefinition;
use Zef\Framework\Container\UnplannedInstanceDefinition;
use Zef\Framework\Container\ValueDefinition;
use Zef\Test\Support\Container\Announcer;
use Zef\Test\Support\Container\ExternalFactory;
use Zef\Test\Support\Container\Greeter;
use Zef\Test\Support\Container\UnsupportedDefinition;

/**
 * @internal
 */
#[CoversClass(DefinitionCompiler::class)]
#[CoversClass(FactoryClosures::class)]
#[CoversClass(FactoryInvocation::class)]
#[CoversClass(ArgumentResolution::class)]
#[CoversClass(ServiceDefinition::class)]
#[CoversClass(DefinitionException::class)]
final class DefinitionCompilerTest extends TestCase
{
    private DefinitionCompiler $definitionCompiler;

    #[Override]
    protected function setUp(): void
    {
        $this->definitionCompiler = new DefinitionCompiler();
    }

    public function testAnInstanceDefinitionInstantiatesTheClassWithItsArguments(): void
    {
        $factory = $this->definitionCompiler->compile(
            new InstanceDefinition('app.greeter', Greeter::class, ['Hello', 2]),
        );

        $greeter = $factory(new Container());

        self::assertInstanceOf(Greeter::class, $greeter);
        self::assertSame('Hello', $greeter->salutation);
        self::assertSame(2, $greeter->verbosity);
    }

    public function testAnInstanceDefinitionResolvesItsServiceReferences(): void
    {
        $container = new Container();
        $container->set('app.greeter', static fn (): Greeter => new Greeter('Hello'));
        $container->set('app.channel', static fn (): string => 'ops');

        $factory = $this->definitionCompiler->compile(
            new InstanceDefinition('app.announcer', Announcer::class, [
                ['service' => 'app.greeter'],
                ['service' => 'app.channel'],
            ]),
        );

        $announcer = $factory($container);

        self::assertInstanceOf(Announcer::class, $announcer);
        self::assertSame('[ops] Hello Zef!', $announcer->announce('Zef'));
    }

    public function testAFactoryDefinitionCallsTheStaticMethodWhenTheTargetIsAClass(): void
    {
        $factory = $this->definitionCompiler->compile(
            new FactoryDefinition('app.label', ExternalFactory::class . '::make', ['widget']),
        );

        self::assertSame('made:widget', $factory(new Container()));
    }

    public function testAFactoryDefinitionCallsTheInstanceMethodWhenTheTargetIsRegistered(): void
    {
        $container = new Container();
        $container->set('app.builder', static fn (): ExternalFactory => new ExternalFactory('built'));

        $factory = $this->definitionCompiler->compile(
            new FactoryDefinition('app.label', 'app.builder::build', ['widget']),
        );

        self::assertSame('built:widget', $factory($container));
    }

    public function testARegisteredServiceShadowsAnEquallyNamedClass(): void
    {
        $container = new Container();
        $container->set(
            ExternalFactory::class,
            static fn (): ExternalFactory => new ExternalFactory('shadowed'),
        );

        $factory = $this->definitionCompiler->compile(
            new FactoryDefinition('app.label', ExternalFactory::class . '::build', ['widget']),
        );

        self::assertSame('shadowed:widget', $factory($container));
    }

    public function testAFactoryServiceThatDoesNotResolveToAnObjectFailsClosed(): void
    {
        $container = new Container();
        $container->set('app.builder', static fn (): string => 'a string, not a service');

        $factory = $this->definitionCompiler->compile(
            new FactoryDefinition('app.label', 'app.builder::build', []),
        );

        try {
            $factory($container);
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "Factory service 'app.builder' did not resolve to an object, string given.",
                $definitionException->getMessage(),
            );
        }
    }

    public function testAMissingInstanceMethodFailsClosed(): void
    {
        $container = new Container();
        $container->set('app.builder', static fn (): ExternalFactory => new ExternalFactory());

        $factory = $this->definitionCompiler->compile(
            new FactoryDefinition('app.label', 'app.builder::nope', []),
        );

        try {
            $factory($container);
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "Factory target 'app.builder' has no callable method 'nope'.",
                $definitionException->getMessage(),
            );
        }
    }

    public function testAMissingStaticMethodFailsClosed(): void
    {
        $factory = $this->definitionCompiler->compile(
            new FactoryDefinition('app.label', ExternalFactory::class . '::nope', []),
        );

        try {
            $factory(new Container());
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "Factory target '" . ExternalFactory::class . "' has no callable method 'nope'.",
                $definitionException->getMessage(),
            );
        }
    }

    public function testAnAliasDefinitionAnswersTheTargetService(): void
    {
        $container = new Container();
        $container->set('app.greeter', static fn (): Greeter => new Greeter('Hello'));

        $factory = $this->definitionCompiler->compile(new AliasDefinition(Greeter::class, 'app.greeter'));

        $greeter = $factory($container);

        self::assertInstanceOf(Greeter::class, $greeter);
        self::assertSame($container->get('app.greeter'), $greeter);
    }

    public function testAValueDefinitionAnswersItsRawValue(): void
    {
        $factory = $this->definitionCompiler->compile(new ValueDefinition('app.debug', ['flag' => true]));

        self::assertSame(['flag' => true], $factory(new Container()));
    }

    public function testAValueDefinitionAnswersNullVerbatim(): void
    {
        $factory = $this->definitionCompiler->compile(new ValueDefinition('app.absent', null));

        self::assertNull($factory(new Container()));
    }

    public function testAnUnsupportedDefinitionShapeFailsClosed(): void
    {
        $unsupportedDefinition = new UnsupportedDefinition('app.custom');

        try {
            $this->definitionCompiler->compile($unsupportedDefinition);
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "The service 'app.custom' carries an unsupported definition shape.",
                $definitionException->getMessage(),
            );
        }
    }

    public function testCompiledClosuresComposeWithTheContainersOwnSurface(): void
    {
        $container = new Container();
        $container->set(
            'app.greeter',
            $this->definitionCompiler->compile(new InstanceDefinition('app.greeter', Greeter::class, ['Hello'])),
        );
        $container->set(
            'app.announcer',
            $this->definitionCompiler->compile(
                new InstanceDefinition('app.announcer', Announcer::class, [
                    ['service' => 'app.greeter'],
                    'ops',
                ]),
            ),
        );

        $announcer = $container->get('app.announcer');

        self::assertInstanceOf(Announcer::class, $announcer);
        self::assertSame('[ops] Hello Zef!', $announcer->announce('Zef'));
        self::assertSame(
            $container->get('app.greeter'),
            $announcer->greeter,
            'the shared dependency is the memoised singleton',
        );
    }

    public function testAnUnplannedDefinitionIsRefusedUntilTheAutowirePassRuns(): void
    {
        try {
            new DefinitionCompiler()->compile(
                new UnplannedInstanceDefinition('app.x', Greeter::class, ['salutation' => 'Hello']),
            );
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertStringContainsString(
                "The service 'app.x' carries an unplanned constructor plan -- run the autowire compiler pass",
                $definitionException->getMessage(),
            );
        }
    }
}
