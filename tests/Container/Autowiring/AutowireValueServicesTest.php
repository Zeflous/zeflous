<?php

declare(strict_types=1);

namespace Zef\Test\Container\Autowiring;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Container\Autowiring\AutowireParameterSpec;
use Zef\Framework\Container\Autowiring\AutowireValueMaterialisations;
use Zef\Framework\Container\Autowiring\AutowireValueRefusals;
use Zef\Framework\Container\Autowiring\AutowireValueServices;
use Zef\Framework\Container\Autowiring\AutowiringException;
use Zef\Framework\Container\Autowiring\SyntheticValueGuard;
use Zef\Framework\Container\Autowiring\Value;
use Zef\Framework\Container\Container;
use Zef\Framework\Container\DefinitionException;
use Zef\Framework\Container\DefinitionMap;
use Zef\Framework\Container\ParameterValues;

/**
 * @internal
 */
#[CoversClass(AutowireValueServices::class)]
#[CoversClass(SyntheticValueGuard::class)]
#[CoversClass(AutowireValueMaterialisations::class)]
#[CoversClass(AutowireValueRefusals::class)]
#[CoversClass(Value::class)]
#[CoversClass(ParameterValues::class)]
final class AutowireValueServicesTest extends TestCase
{
    private Container $container;

    private AutowireValueServices $autowireValueServices;

    #[Override]
    protected function setUp(): void
    {
        $this->container = new Container();
        $this->autowireValueServices = new AutowireValueServices();
    }

    public function testTheAttributeKeySharesTheAddressGrammar(): void
    {
        try {
            new Value('a b');
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertStringContainsString(
                "#[Value] key 'a b' is invalid",
                $definitionException->getMessage(),
            );
        }
    }

    public function testTheAttributeAcceptsTheWholeAddressAlphabet(): void
    {
        $value = new Value('db.mysql-2.host_name');

        self::assertSame('db.mysql-2.host_name', $value->key);
    }

    public function testAnAddressMaterialisesTheSyntheticValueService(): void
    {
        $this->autowireValueServices->open(new ParameterValues(['db.host' => 'localhost']), [], $this->container);

        $id = $this->autowireValueServices->service('app.house', $this->spec('host', 'db.host'));

        self::assertSame('@value:db.host', $id);
        self::assertSame(['@value:db.host'], $this->autowireValueServices->materialized());

        [$definition] = $this->autowireValueServices->definitions();
        self::assertSame('@value:db.host', $definition->id);
        self::assertSame('localhost', $definition->value);
    }

    public function testTheSecondConsumerOfAnAddressReusesTheFirstMaterialisation(): void
    {
        $this->autowireValueServices->open(new ParameterValues(['shared.flag' => true]), [], $this->container);

        $first = $this->autowireValueServices->service('app.house', $this->spec('flag', 'shared.flag'));
        $second = $this->autowireValueServices->service('app.audit', $this->spec('flag', 'shared.flag'));

        self::assertSame('@value:shared.flag', $first);
        self::assertSame($first, $second);
        self::assertCount(1, $this->autowireValueServices->definitions());
        self::assertSame(['@value:shared.flag'], $this->autowireValueServices->materialized());
    }

    public function testAMissingAddressIsTheHonestRefusalWithTheWayOut(): void
    {
        $this->autowireValueServices->open(ParameterValues::empty(), [], $this->container);

        try {
            $this->autowireValueServices->service('app.house', $this->spec('host', 'db.host'));
            self::fail('Expected an AutowiringException to be thrown.');
        } catch (AutowiringException $autowiringException) {
            self::assertSame(
                "Cannot plan 'app.house': the parameter '\$host' carries #[Value('db.host')] but the "
                . "'parameters' configuration has no value for 'db.host' -- declare it under the parameters "
                . "knob, or drop the attribute so the parameter's own default or explicit spelling applies.",
                $autowiringException->getMessage(),
            );
        }
    }

    public function testADeclaredSpellingOfTheSyntheticNamespaceIsACollision(): void
    {
        $definitions = DefinitionMap::parse('services', [
            '@value:db.host' => ['value' => 'shadowed'],
        ]);

        $this->autowireValueServices->open(
            new ParameterValues(['db.host' => 'localhost']),
            $definitions,
            $this->container,
        );

        try {
            $this->autowireValueServices->service('app.house', $this->spec('host', 'db.host'));
            self::fail('Expected an AutowiringException to be thrown.');
        } catch (AutowiringException $autowiringException) {
            self::assertSame(
                "Cannot plan 'app.house': the synthetic value service '@value:db.host' collides with a "
                . "declared definition of the same identifier -- the '@value:' prefix is the parameters "
                . "lane's own; declare the parameter under another address or the service under another "
                . 'identifier.',
                $autowiringException->getMessage(),
            );
        }
    }

    public function testAContainerServiceSpellingOfTheSyntheticNamespaceIsACollision(): void
    {
        $this->container->set('@value:db.host', static fn (): string => 'resident');

        $this->autowireValueServices->open(new ParameterValues(['db.host' => 'localhost']), [], $this->container);

        try {
            $this->autowireValueServices->service('app.house', $this->spec('host', 'db.host'));
            self::fail('Expected an AutowiringException to be thrown.');
        } catch (AutowiringException $autowiringException) {
            self::assertStringContainsString(
                "the synthetic value service '@value:db.host' collides",
                $autowiringException->getMessage(),
            );
        }
    }

    public function testANonSyntheticDeclaredIdGuardsNothing(): void
    {
        $definitions = DefinitionMap::parse('services', [
            'app.greeter' => ['value' => 'hello'],
        ]);

        $this->autowireValueServices->open(
            new ParameterValues(['db.host' => 'localhost']),
            $definitions,
            $this->container,
        );

        $id = $this->autowireValueServices->service('app.house', $this->spec('host', 'db.host'));

        self::assertSame('@value:db.host', $id);
    }

    public function testAContainerServiceOutsideThePrefixGuardsNothing(): void
    {
        $this->container->set('app.clock', static fn (): string => 'resident');

        $this->autowireValueServices->open(new ParameterValues(['db.host' => 'localhost']), [], $this->container);

        $id = $this->autowireValueServices->service('app.house', $this->spec('host', 'db.host'));

        self::assertSame('@value:db.host', $id);
    }

    public function testNullAndArrayValuesMaterialiseVerbatim(): void
    {
        $this->autowireValueServices->open(
            new ParameterValues(['unset.thing' => null, 'db.replicas' => ['a', 'b']]),
            [],
            $this->container,
        );

        $nullId = $this->autowireValueServices->service('app.house', $this->spec('thing', 'unset.thing'));
        $arrayId = $this->autowireValueServices->service('app.pool', $this->spec('replicas', 'db.replicas'));

        self::assertSame('@value:unset.thing', $nullId);
        self::assertSame('@value:db.replicas', $arrayId);

        [$nullDefinition, $arrayDefinition] = $this->autowireValueServices->definitions();
        self::assertNull($nullDefinition->value);
        self::assertSame(['a', 'b'], $arrayDefinition->value);
    }

    public function testASecondRunResetsTheBookkeeping(): void
    {
        $this->autowireValueServices->open(new ParameterValues(['db.host' => 'first']), [], $this->container);
        $this->autowireValueServices->service('app.house', $this->spec('host', 'db.host'));

        $this->autowireValueServices->open(new ParameterValues(['db.port' => 3306]), [], $this->container);

        self::assertSame([], $this->autowireValueServices->materialized());
        self::assertSame([], $this->autowireValueServices->definitions());

        $id = $this->autowireValueServices->service('app.pool', $this->spec('port', 'db.port'));

        self::assertSame('@value:db.port', $id);
        self::assertSame(['@value:db.port'], $this->autowireValueServices->materialized());
    }

    public function testAMemoisedIdIsAnsweredBeforeTheCollisionAndMissingGuards(): void
    {
        $occupied = DefinitionMap::parse('services', [
            '@value:db.host' => ['value' => 'shadowed'],
        ]);

        $this->autowireValueServices->open(new ParameterValues(['db.host' => 'localhost']), [], $this->container);
        $this->autowireValueServices->service('app.house', $this->spec('host', 'db.host'));

        // The memo answers before the occupied or missing guards could refuse:
        // a definition declared in a LATER run never retro-collides with an
        // earlier materialisation of this one.
        $this->autowireValueServices->open(
            new ParameterValues(['db.host' => 'still-here']),
            $occupied,
            $this->container,
        );
        self::assertSame([], $this->autowireValueServices->materialized());
    }

    private function spec(string $name, string $key): AutowireParameterSpec
    {
        return new AutowireParameterSpec(
            name: $name,
            isVariadic: false,
            isOptional: false,
            hasDefault: false,
            valueKey: $key,
        );
    }
}
