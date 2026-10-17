<?php

declare(strict_types=1);

namespace Zef\Test\Container\Autowiring;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Config\Config;
use Zef\Framework\Container\AliasDefinition;
use Zef\Framework\Container\Autowiring\AutoconfigClaims;
use Zef\Framework\Container\Autowiring\AutoconfigEarned;
use Zef\Framework\Container\Autowiring\AutoconfigInterfaceName;
use Zef\Framework\Container\Autowiring\AutoconfigInterfaceRule;
use Zef\Framework\Container\Autowiring\AutoconfigInterfaces;
use Zef\Framework\Container\Autowiring\AutoconfigMap;
use Zef\Framework\Container\Autowiring\AutoconfigRefusals;
use Zef\Framework\Container\Autowiring\AutoconfigTagRule;
use Zef\Framework\Container\Autowiring\Autoconfiguration;
use Zef\Framework\Container\Autowiring\AutowiringException;
use Zef\Framework\Container\Autowiring\Tag;
use Zef\Framework\Container\Container;
use Zef\Framework\Container\DefinitionException;
use Zef\Framework\Container\FactoryDefinition;
use Zef\Framework\Container\InstanceDefinition;
use Zef\Framework\Container\ServiceLifetime;
use Zef\Framework\Container\ServiceTags;
use Zef\Framework\Container\TaggedServices;
use Zef\Framework\Container\ValueDefinition;
use Zef\Framework\ContainerBootstrapper;
use Zef\Framework\FrameworkDefaults;
use Zef\Test\Support\Container\Autowiring\AttributedHouse;
use Zef\Test\Support\Container\Autowiring\AttributedMarkerHouse;
use Zef\Test\Support\Container\Autowiring\DoublyTaggedHouse;
use Zef\Test\Support\Container\Autowiring\DualMarkerHouse;
use Zef\Test\Support\Container\Autowiring\FileLogger;
use Zef\Test\Support\Container\Autowiring\LoggerInterface;
use Zef\Test\Support\Container\Autowiring\MarkerA;
use Zef\Test\Support\Container\Autowiring\MarkerB;
use Zef\Test\Support\Container\Autowiring\TwiceTaggedHouse;
use Zef\Test\Support\Container\ExternalFactory;
use Zef\Test\Support\Container\Greeter;

/**
 * @internal
 */
#[CoversClass(AutoconfigClaims::class)]
#[CoversClass(AutoconfigEarned::class)]
#[CoversClass(AutoconfigInterfaceName::class)]
#[CoversClass(AutoconfigInterfaceRule::class)]
#[CoversClass(AutoconfigInterfaces::class)]
#[CoversClass(AutoconfigMap::class)]
#[CoversClass(Autoconfiguration::class)]
#[CoversClass(AutoconfigRefusals::class)]
#[CoversClass(AutoconfigTagRule::class)]
#[CoversClass(Tag::class)]
final class AutoconfigurationTest extends TestCase
{
    public function testAnAbsentKnobIsTheValidNoRulesBoot(): void
    {
        self::assertSame([], AutoconfigMap::parse('tags', null)->interfaceTags());
    }

    public function testAnEmptyKnobIsTheValidNoRulesBoot(): void
    {
        self::assertSame([], AutoconfigMap::parse('tags', [])->interfaceTags());
        self::assertSame([], AutoconfigMap::parse('tags', ['interfaces' => []])->interfaceTags());
        self::assertSame([], AutoconfigMap::parse('tags', ['interfaces' => null])->interfaceTags());
    }

    public function testTheKnobParsesItsInterfaceRules(): void
    {
        $autoconfigMap = AutoconfigMap::parse('tags', [
            'interfaces' => [
                LoggerInterface::class => 'app.logger',
                MarkerA::class => 'app.marker',
            ],
        ]);

        self::assertSame(
            [LoggerInterface::class => 'app.logger', MarkerA::class => 'app.marker'],
            $autoconfigMap->interfaceTags(),
        );
    }

    public function testANonMapKnobIsRejected(): void
    {
        try {
            AutoconfigMap::parse('tags', 'nope');
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "The 'tags' configuration must be a map of autoconfiguration keys, string given.",
                $definitionException->getMessage(),
            );
        }
    }

    public function testAnUnknownKnobKeyIsRejectedWithTheKnownList(): void
    {
        try {
            AutoconfigMap::parse('tags', ['attributes' => []]);
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "The 'tags' configuration carries the unknown key 'attributes' (known keys: interfaces).",
                $definitionException->getMessage(),
            );
        }
    }

    public function testANonArrayInterfacesValueIsRejected(): void
    {
        try {
            AutoconfigMap::parse('tags', ['interfaces' => 'nope']);
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "The 'tags.interfaces' configuration must be a map of interface names to tags, string given.",
                $definitionException->getMessage(),
            );
        }
    }

    public function testAListInterfacesValueIsRejected(): void
    {
        try {
            AutoconfigMap::parse('tags', ['interfaces' => ['app.logger']]);
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "The 'tags.interfaces' configuration must be a map of interface names to tags, list given.",
                $definitionException->getMessage(),
            );
        }
    }

    public function testANonStringInterfaceKeyIsRejected(): void
    {
        try {
            AutoconfigMap::parse('tags', ['interfaces' => [7 => 'app.logger']]);
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "The 'tags.interfaces' keys must be non-empty interface names, int given.",
                $definitionException->getMessage(),
            );
        }
    }

    public function testAMissingInterfaceIsRejectedByName(): void
    {
        try {
            AutoconfigMap::parse('tags', ['interfaces' => ['App\NoSuchMarker' => 'app.marker']]);
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "The 'tags.interfaces' key 'App\\NoSuchMarker' does not name an existing interface.",
                $definitionException->getMessage(),
            );
        }
    }

    public function testANonStringTagIsRejectedWithItsInterface(): void
    {
        try {
            AutoconfigMap::parse('tags', ['interfaces' => [MarkerA::class => 7]]);
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "The 'tags.interfaces' rule for '" . MarkerA::class . "' must spell a tag string, int given.",
                $definitionException->getMessage(),
            );
        }
    }

    public function testATagOutsideTheGrammarIsRejectedWithItsInterface(): void
    {
        try {
            AutoconfigMap::parse('tags', ['interfaces' => [MarkerA::class => 'bad tag']]);
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "The 'tags.interfaces' rule for '" . MarkerA::class . "' spells a tag the grammar refuses: "
                . "A service tag must match [A-Za-z0-9._-]{1,128}, 'bad tag' given.",
                $definitionException->getMessage(),
            );
        }
    }

    public function testTheTagAttributeRequiresTheTagsGrammar(): void
    {
        try {
            new Tag('bad tag');
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "A service tag must match [A-Za-z0-9._-]{1,128}, 'bad tag' given.",
                $definitionException->getMessage(),
            );
        }

        self::assertSame('made.tag', new Tag('made.tag')->tag);
    }

    public function testANonMatchingRuleBeforeAMatchingOneStillLetsTheMatchClaim(): void
    {
        $planned = Autoconfiguration::apply(
            [new InstanceDefinition('app.file', FileLogger::class)],
            AutoconfigMap::parse('tags', [
                'interfaces' => [
                    MarkerA::class => 'unrelated.marker',
                    LoggerInterface::class => 'app.logger',
                ],
            ]),
        );

        self::assertSame(['app.logger'], $planned[0]->tags->all());
    }

    public function testTwoRulesBothMatchingEarnBothTheirTags(): void
    {
        $planned = Autoconfiguration::apply(
            [new InstanceDefinition('app.house', DualMarkerHouse::class)],
            AutoconfigMap::parse('tags', [
                'interfaces' => [
                    MarkerA::class => 'app.marker.a',
                    MarkerB::class => 'app.marker.b',
                ],
            ]),
        );

        self::assertSame(['app.marker.a', 'app.marker.b'], $planned[0]->tags->all());
    }

    public function testADeclarationThatEarnsNothingKeepsItsOwnInstance(): void
    {
        $definition = new InstanceDefinition('app.file', FileLogger::class);

        $planned = Autoconfiguration::apply(
            [$definition],
            AutoconfigMap::parse('tags', ['interfaces' => [MarkerA::class => 'unrelated.marker']]),
        );

        self::assertSame($definition, $planned[0]);
    }

    public function testAMarkerRuleEarnsItsTagOnTheImplementingDeclaration(): void
    {
        $planned = Autoconfiguration::apply(
            [new InstanceDefinition('app.file', FileLogger::class)],
            AutoconfigMap::parse('tags', ['interfaces' => [LoggerInterface::class => 'app.logger']]),
        );

        self::assertSame(['app.logger'], $planned[0]->tags->all());
    }

    public function testTheAttributeEarnsItsTagOnTheClassShapedDeclaration(): void
    {
        $planned = Autoconfiguration::apply(
            [new InstanceDefinition('app.house', AttributedHouse::class)],
            AutoconfigMap::parse('tags', null),
        );

        self::assertSame(['made.tag'], $planned[0]->tags->all());
    }

    public function testEarnedTagsMergeWithExplicitTags(): void
    {
        $instanceDefinition = new InstanceDefinition(
            'app.file',
            FileLogger::class,
            [],
            ServiceLifetime::SINGLETON,
            new ServiceTags(['app.explicit']),
        );

        $planned = Autoconfiguration::apply(
            [$instanceDefinition],
            AutoconfigMap::parse('tags', ['interfaces' => [LoggerInterface::class => 'app.logger']]),
        );

        self::assertSame(['app.explicit', 'app.logger'], $planned[0]->tags->all());
    }

    public function testTheNonClassShapesPassThroughUntouched(): void
    {
        $factoryDefinition = new FactoryDefinition('app.label', ExternalFactory::class . '::make');
        $valueDefinition = new ValueDefinition('app.debug', true);
        $aliasDefinition = new AliasDefinition(Greeter::class, 'app.greeter');

        $planned = Autoconfiguration::apply(
            [$factoryDefinition, $valueDefinition, $aliasDefinition],
            AutoconfigMap::parse('tags', ['interfaces' => [LoggerInterface::class => 'app.logger']]),
        );

        self::assertSame($factoryDefinition, $planned[0]);
        self::assertSame($valueDefinition, $planned[1]);
        self::assertSame($aliasDefinition, $planned[2]);
    }

    public function testAnExplicitTagAMarkerRuleWouldAlsoEarnIsTheCollisionItIs(): void
    {
        $instanceDefinition = new InstanceDefinition(
            'app.file',
            FileLogger::class,
            [],
            ServiceLifetime::SINGLETON,
            new ServiceTags(['app.logger']),
        );

        try {
            Autoconfiguration::apply(
                [$instanceDefinition],
                AutoconfigMap::parse('tags', ['interfaces' => [LoggerInterface::class => 'app.logger']]),
            );
            self::fail('Expected an AutowiringException to be thrown.');
        } catch (AutowiringException $autowiringException) {
            self::assertSame(
                "Service 'app.file' would carry the tag 'app.logger' from several sources "
                . "(the tags.interfaces rule for '" . LoggerInterface::class . "'; the definition's own tags key) "
                . '-- declare the tag once, from one source.',
                $autowiringException->getMessage(),
            );
        }
    }

    public function testAnExplicitTagTheAttributeWouldAlsoEarnIsTheCollisionItIs(): void
    {
        $instanceDefinition = new InstanceDefinition(
            'app.house',
            AttributedHouse::class,
            [],
            ServiceLifetime::SINGLETON,
            new ServiceTags(['made.tag']),
        );

        try {
            Autoconfiguration::apply([$instanceDefinition], AutoconfigMap::parse('tags', null));
            self::fail('Expected an AutowiringException to be thrown.');
        } catch (AutowiringException $autowiringException) {
            self::assertSame(
                "Service 'app.house' would carry the tag 'made.tag' from several sources "
                . "(the #[Tag] attribute; the definition's own tags key) "
                . '-- declare the tag once, from one source.',
                $autowiringException->getMessage(),
            );
        }
    }

    public function testTwoRulesClaimingOneTagIsTheCollisionItIs(): void
    {
        try {
            Autoconfiguration::apply(
                [new InstanceDefinition('app.house', DualMarkerHouse::class)],
                AutoconfigMap::parse('tags', [
                    'interfaces' => [
                        MarkerA::class => 'dual.tag',
                        MarkerB::class => 'dual.tag',
                    ],
                ]),
            );
            self::fail('Expected an AutowiringException to be thrown.');
        } catch (AutowiringException $autowiringException) {
            self::assertSame(
                "Service 'app.house' would carry the tag 'dual.tag' from several sources "
                . "(the tags.interfaces rule for '" . MarkerA::class . "'; "
                . "the tags.interfaces rule for '" . MarkerB::class . "') "
                . '-- declare the tag once, from one source.',
                $autowiringException->getMessage(),
            );
        }
    }

    public function testAnAttributeAndARuleClaimingOneTagIsTheCollisionItIs(): void
    {
        try {
            Autoconfiguration::apply(
                [new InstanceDefinition('app.house', AttributedMarkerHouse::class)],
                AutoconfigMap::parse('tags', ['interfaces' => [MarkerA::class => 'marked.tag']]),
            );
            self::fail('Expected an AutowiringException to be thrown.');
        } catch (AutowiringException $autowiringException) {
            self::assertSame(
                "Service 'app.house' would carry the tag 'marked.tag' from several sources "
                . "(the tags.interfaces rule for '" . MarkerA::class . "'; the #[Tag] attribute) "
                . '-- declare the tag once, from one source.',
                $autowiringException->getMessage(),
            );
        }
    }

    public function testSeveralDistinctAttributesAllEarnTheirTags(): void
    {
        $planned = Autoconfiguration::apply(
            [new InstanceDefinition('app.house', TwiceTaggedHouse::class)],
            AutoconfigMap::parse('tags', null),
        );

        self::assertSame(['first.tag', 'second.tag'], $planned[0]->tags->all());
    }

    public function testTheSameAttributeTwiceIsTheCollisionItIs(): void
    {
        try {
            Autoconfiguration::apply(
                [new InstanceDefinition('app.house', DoublyTaggedHouse::class)],
                AutoconfigMap::parse('tags', null),
            );
            self::fail('Expected an AutowiringException to be thrown.');
        } catch (AutowiringException $autowiringException) {
            self::assertSame(
                "Service 'app.house' would carry the tag 'dup.tag' from several sources "
                . '(the #[Tag] attribute; the #[Tag] attribute) '
                . '-- declare the tag once, from one source.',
                $autowiringException->getMessage(),
            );
        }
    }

    public function testABootedContainerAnswersEarnedTagsThroughTheLocator(): void
    {
        $container = new ContainerBootstrapper()->bootstrap(
            new Config([
                'tags' => ['interfaces' => [LoggerInterface::class => 'app.logger']],
                'services' => [
                    'app.file' => ['class' => FileLogger::class],
                    'app.house' => ['class' => AttributedHouse::class],
                ],
            ]),
            new Container(),
        );

        $locator = $this->locator($container);

        self::assertSame(['app.file'], $locator->ids('app.logger'));
        self::assertSame(['app.house'], $locator->ids('made.tag'));
    }

    public function testAClassTheDeclaredSetNeverNamesCarriesItsAttributeUnheard(): void
    {
        $container = new ContainerBootstrapper()->bootstrap(
            new Config([
                'services' => [
                    'app.file' => ['class' => FileLogger::class],
                ],
            ]),
            new Container(),
        );

        $locator = $this->locator($container);

        self::assertFalse($locator->has('made.tag'));
    }

    public function testTheDefaultProfileShipsTheEmptyAutoconfigKnob(): void
    {
        self::assertSame(['interfaces' => []], FrameworkDefaults::profile()['tags']);
    }

    private function locator(Container $container): TaggedServices
    {
        $locator = $container->get(FrameworkDefaults::TAGS_SERVICE);
        self::assertInstanceOf(TaggedServices::class, $locator);

        return $locator;
    }
}
