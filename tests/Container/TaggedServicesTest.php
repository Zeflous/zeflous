<?php

declare(strict_types=1);

namespace Zef\Test\Container;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Zef\Framework\Config\Config;
use Zef\Framework\Container\Container;
use Zef\Framework\Container\DefinitionException;
use Zef\Framework\Container\FactoryDefinition;
use Zef\Framework\Container\InstanceDefinition;
use Zef\Framework\Container\ServiceLifetime;
use Zef\Framework\Container\ServiceTagIndex;
use Zef\Framework\Container\ServiceTags;
use Zef\Framework\Container\TaggedServices;
use Zef\Framework\ContainerBootstrapper;
use Zef\Framework\FrameworkDefaults;
use Zef\Test\Support\Container\ExternalFactory;
use Zef\Test\Support\Container\Greeter;

/**
 * @internal
 */
#[CoversClass(ContainerBootstrapper::class)]
#[CoversClass(ServiceTagIndex::class)]
#[CoversClass(TaggedServices::class)]
#[CoversClass(ServiceTags::class)]
final class TaggedServicesTest extends TestCase
{
    public function testTheIndexCarriesNoTagWhenNoDeclarationHasOne(): void
    {
        $serviceTagIndex = new ServiceTagIndex([
            new InstanceDefinition('app.greeter', Greeter::class),
        ]);

        self::assertFalse($this->indexed($serviceTagIndex)->has('app.greeting'));
        self::assertSame([], $this->indexed($serviceTagIndex)->ids('app.greeting'));
    }

    public function testTheIndexAnswersTheIdentifiersCarryingATag(): void
    {
        $greeter = new InstanceDefinition(
            'app.greeter',
            Greeter::class,
            [],
            ServiceLifetime::SINGLETON,
            new ServiceTags(['app.greeting']),
        );
        $announcer = new InstanceDefinition(
            'app.announcer',
            Greeter::class,
            [],
            ServiceLifetime::SINGLETON,
            new ServiceTags(['app.greeting', 'shared.label']),
        );
        $factoryDefinition = new FactoryDefinition(
            'app.label',
            ExternalFactory::class . '::make',
            [],
            ServiceLifetime::SINGLETON,
            new ServiceTags(['shared.label']),
        );

        $serviceTagIndex = new ServiceTagIndex([$greeter, $announcer, $factoryDefinition]);

        $locator = $this->indexed($serviceTagIndex);

        self::assertSame(['app.greeter', 'app.announcer'], $locator->ids('app.greeting'));
        self::assertSame(['app.announcer', 'app.label'], $locator->ids('shared.label'));
        self::assertTrue($locator->has('app.greeting'));
        self::assertTrue($locator->has('shared.label'));
        self::assertFalse($locator->has('app.unknown'));
    }

    public function testTheIndexAnswersInFirstDeclarationOrder(): void
    {
        $serviceTagIndex = new ServiceTagIndex([
            $this->laneCarrier('app.third'),
            $this->laneCarrier('app.first'),
            $this->laneCarrier('app.second'),
        ]);

        self::assertSame(['app.third', 'app.first', 'app.second'], $this->indexed($serviceTagIndex)->ids('lane'));
    }

    public function testARedeclaredIdentifierContributesItsLastTagsAtItsFirstPosition(): void
    {
        $serviceTagIndex = new ServiceTagIndex([
            $this->carrier('app.greeter', ['framework.old']),
            $this->carrier('app.other', ['lane']),
            $this->carrier('app.greeter', ['host.new']),
        ]);

        $locator = $this->indexed($serviceTagIndex);

        self::assertFalse($locator->has('framework.old'));
        self::assertSame(['app.greeter'], $locator->ids('host.new'));
        self::assertSame(['app.other'], $locator->ids('lane'));
    }

    public function testARedeclaredIdentifierKeepsFirstPositionUnderItsNewTags(): void
    {
        $serviceTagIndex = new ServiceTagIndex([
            $this->carrier('app.early', ['lane']),
            $this->carrier('app.greeter', ['dropped']),
            $this->carrier('app.late', ['lane']),
            $this->carrier('app.greeter', ['lane']),
        ]);

        $locator = $this->indexed($serviceTagIndex);

        self::assertFalse($locator->has('dropped'));
        self::assertSame(['app.early', 'app.greeter', 'app.late'], $locator->ids('lane'));
    }

    public function testABootedContainerAnswersTheLocatorUnderTheReservedIdentifier(): void
    {
        $container = $this->booted([
            'app.greeter' => ['class' => Greeter::class, 'arguments' => ['Hello'], 'tags' => ['app.greeting']],
            'app.cheerful' => ['class' => Greeter::class, 'arguments' => ['Hi'], 'tags' => ['app.greeting']],
        ]);

        $locator = $this->locator($container);
        self::assertSame(['app.greeter', 'app.cheerful'], $locator->ids('app.greeting'));
    }

    public function testTheLocatorAnswersEvenWhenNoDeclarationCarriesATag(): void
    {
        $container = $this->booted(['app.greeter' => ['class' => Greeter::class, 'arguments' => ['Hello']]]);

        $locator = $this->locator($container);
        self::assertFalse($locator->has('app.greeting'));
        self::assertSame([], $locator->ids('app.greeting'));
    }

    public function testServicesResolveThroughTheContainerInDeclarationOrder(): void
    {
        $container = $this->booted([
            'app.greeter' => ['class' => Greeter::class, 'arguments' => ['Hello'], 'tags' => ['app.greeting']],
            'app.cheerful' => ['class' => Greeter::class, 'arguments' => ['Hi'], 'tags' => ['app.greeting']],
        ]);

        $services = $this->locator($container)->services('app.greeting');

        self::assertCount(2, $services);
        self::assertInstanceOf(Greeter::class, $services[0]);
        self::assertInstanceOf(Greeter::class, $services[1]);
        self::assertSame('Hello', $services[0]->salutation);
        self::assertSame('Hi', $services[1]->salutation);
    }

    public function testASingletonTaggedServiceResolvesOnceForTheWholeCollection(): void
    {
        $container = $this->booted([
            'app.greeter' => ['class' => Greeter::class, 'arguments' => ['Hello'], 'tags' => ['app.greeting']],
        ]);

        $locator = $this->locator($container);
        $first = $locator->services('app.greeting');
        $second = $locator->services('app.greeting');

        self::assertSame($first[0], $second[0]);
        self::assertSame($first[0], $container->get('app.greeter'));
    }

    public function testOneAnswersTheExactlyOneCarrier(): void
    {
        $container = $this->booted([
            'app.greeter' => ['class' => Greeter::class, 'arguments' => ['Hello'], 'tags' => ['app.solo']],
        ]);

        $resolved = $this->locator($container)->one('app.solo');

        self::assertInstanceOf(Greeter::class, $resolved);
        self::assertSame('Hello', $resolved->salutation);
    }

    public function testOneRefusesTheTagNobodyCarries(): void
    {
        $container = $this->booted(['app.greeter' => ['class' => Greeter::class, 'arguments' => ['Hello']]]);

        try {
            $this->locator($container)->one('app.greeting');
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "The tag 'app.greeting' is carried by no booted service "
                . '(declare one, or spell a tag the definitions carry).',
                $definitionException->getMessage(),
            );
        }
    }

    public function testOneRefusesTheTagSeveralServicesCarry(): void
    {
        $container = $this->booted([
            'app.greeter' => ['class' => Greeter::class, 'arguments' => ['Hello'], 'tags' => ['app.greeting']],
            'app.cheerful' => ['class' => Greeter::class, 'arguments' => ['Hi'], 'tags' => ['app.greeting']],
        ]);

        try {
            $this->locator($container)->one('app.greeting');
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "The tag 'app.greeting' is carried by 2 services (app.greeter, app.cheerful); "
                . 'use services() when multiplicity is expected.',
                $definitionException->getMessage(),
            );
        }
    }

    public function testALookupSpelledOutsideTheGrammarIsRefusedWithTheGrammar(): void
    {
        $container = $this->booted(['app.greeter' => ['class' => Greeter::class, 'arguments' => ['Hello']]]);

        $locator = $this->locator($container);

        foreach (['', ' app.greeting', 'app greeting', 'APP_GREETING!'] as $tag) {
            try {
                $locator->ids($tag);
                self::fail("Expected a DefinitionException for the lookup tag '{$tag}'.");
            } catch (DefinitionException $definitionException) {
                self::assertSame(
                    'A service tag must match [A-Za-z0-9._-]{1,128}, ' . "'{$tag}'" . ' given.',
                    $definitionException->getMessage(),
                );
            }
        }
    }

    public function testEveryLookupSurfaceRefusesTheSameSpellings(): void
    {
        $container = $this->booted(['app.greeter' => ['class' => Greeter::class, 'arguments' => ['Hello']]]);

        $locator = $this->locator($container);

        try {
            $locator->has('bad tag');
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "A service tag must match [A-Za-z0-9._-]{1,128}, 'bad tag' given.",
                $definitionException->getMessage(),
            );
        }

        try {
            $locator->services('bad tag');
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "A service tag must match [A-Za-z0-9._-]{1,128}, 'bad tag' given.",
                $definitionException->getMessage(),
            );
        }

        try {
            $locator->one('bad tag');
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "A service tag must match [A-Za-z0-9._-]{1,128}, 'bad tag' given.",
                $definitionException->getMessage(),
            );
        }
    }

    public function testAResolutionFailurePropagatesUnchanged(): void
    {
        $container = new Container();
        $container->set(
            'app.trouble',
            static fn (): Greeter => throw new RuntimeException('the factory refuses'),
        );

        $taggedServices = new TaggedServices(
            new ServiceTagIndex([$this->carrier('app.trouble', ['lane'])]),
            $container,
        );

        try {
            $taggedServices->services('lane');
            self::fail('Expected the resolution failure to propagate.');
        } catch (RuntimeException $runtimeException) {
            self::assertSame('the factory refuses', $runtimeException->getMessage());
        }
    }

    public function testAHostRebindOfTheReservedIdentifierWins(): void
    {
        $container = $this->booted([
            FrameworkDefaults::TAGS_SERVICE => ['value' => 'the host took the name'],
        ]);

        self::assertSame('the host took the name', $container->get(FrameworkDefaults::TAGS_SERVICE));
    }

    private function locator(Container $container): TaggedServices
    {
        $locator = $container->get(FrameworkDefaults::TAGS_SERVICE);
        self::assertInstanceOf(TaggedServices::class, $locator);

        return $locator;
    }

    private function indexed(ServiceTagIndex $serviceTagIndex): TaggedServices
    {
        return new TaggedServices($serviceTagIndex, new Container());
    }

    private function laneCarrier(string $id): InstanceDefinition
    {
        return $this->carrier($id, ['lane']);
    }

    /** @param list<string> $tags */
    private function carrier(string $id, array $tags): InstanceDefinition
    {
        return new InstanceDefinition(
            $id,
            Greeter::class,
            [],
            ServiceLifetime::SINGLETON,
            new ServiceTags($tags),
        );
    }

    /**
     * @param array<string, array<string, mixed>> $services
     */
    private function booted(array $services): Container
    {
        return new ContainerBootstrapper()->bootstrap(
            new Config(['services' => $services]),
            new Container(),
        );
    }
}
