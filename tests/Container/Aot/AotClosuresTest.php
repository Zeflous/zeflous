<?php

declare(strict_types=1);

namespace Zef\Test\Container\Aot;

use Closure;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass;
use Zef\Framework\Container\AliasDefinition;
use Zef\Framework\Container\Aot\AotArguments;
use Zef\Framework\Container\Aot\AotClosures;
use Zef\Framework\Container\Aot\AotExportability;
use Zef\Framework\Container\Aot\AotLiterals;
use Zef\Framework\Container\Aot\AotRefusals;
use Zef\Framework\Container\Aot\AotUnrenderableException;
use Zef\Framework\Container\Container;
use Zef\Framework\Container\InstanceDefinition;
use Zef\Framework\Container\MethodCall;
use Zef\Framework\Container\MethodCallInvocations;
use Zef\Framework\Container\ServiceLifetime;
use Zef\Framework\Container\ValueDefinition;
use Zef\Test\Support\Container\Aot\CallsHouse;
use Zef\Test\Support\Container\Aot\EnumHouse;
use Zef\Test\Support\Container\Aot\House;
use Zef\Test\Support\Container\Aot\Roof;
use Zef\Test\Support\Container\Aot\Tone;
use Zef\Test\Support\Container\Aot\Window;

/**
 * The rendering half of the artifact boundary: the code the closures
 * unit answers, proven both by its spelling and by its behaviour -- the
 * rendered expression is evaluated and run against a real container, the
 * same `require`-time evaluation the artifact itself performs.
 *
 * @internal
 */
#[CoversClass(AotClosures::class)]
#[CoversClass(AotArguments::class)]
#[CoversClass(AotLiterals::class)]
#[CoversClass(AotRefusals::class)]
final class AotClosuresTest extends TestCase
{
    private Container $container;

    #[Override]
    protected function setUp(): void
    {
        $this->container = new Container();
        $this->container->set('app.window', static fn (): Window => new Window('storm'));
        $this->container->set('app.roof', static fn (): Roof => new Roof('thatch'));
    }

    public function testTheInstanceShapeRendersTheLiteralConstruction(): void
    {
        $instanceDefinition = new InstanceDefinition(
            'app.house',
            House::class,
            [
                ['service' => 'app.window'],
                4,
                ['style' => 'georgian', 'paint' => 'white'],
            ],
        );

        $code = AotClosures::instance($instanceDefinition);

        self::assertStringStartsWith(
            'static fn (Zef\Framework\Container\Container $c): object => new Zef\Test\Support\Container\Aot\House(',
            $code,
        );
        self::assertStringContainsString("\$c->get('app.window')", $code);
        self::assertStringContainsString(', 4, ', $code);
        self::assertStringContainsString("'style' => 'georgian'", $code);
        self::assertStringContainsString("'paint' => 'white'", $code);
    }

    public function testAnEnumCaseArgumentRendersAsTheConstantExpression(): void
    {
        // An enum case is the one object shape that folds as code: the
        // rendered constant expression answers the identical singleton
        // the full lane's baked plan holds.
        $instanceDefinition = new InstanceDefinition('app.tone', EnumHouse::class, [Tone::CALM]);

        $code = AotClosures::instance($instanceDefinition);

        self::assertStringContainsString(
            'new Zef\Test\Support\Container\Aot\EnumHouse(\\' . Tone::class . '::CALM)',
            $code,
        );

        $closure = eval('return ' . $code . ';');
        self::assertInstanceOf(Closure::class, $closure);

        $house = $closure($this->container);
        self::assertInstanceOf(EnumHouse::class, $house);
        self::assertSame(Tone::CALM, $house->tone, 'the rendered case is the singleton itself');
    }

    public function testAnEnumCaseInsideAnArrayArgumentStillFolds(): void
    {
        $instanceDefinition = new InstanceDefinition(
            'app.tone',
            EnumHouse::class,
            [Tone::CALM, ['first' => Tone::CALM, 'plain' => 'data']],
        );

        $code = AotClosures::instance($instanceDefinition);

        self::assertStringContainsString("'first' => \\" . Tone::class . '::CALM', $code);
        self::assertStringContainsString("'plain' => 'data'", $code);

        $closure = eval('return ' . $code . ';');
        self::assertInstanceOf(Closure::class, $closure);

        $house = $closure($this->container);
        self::assertInstanceOf(EnumHouse::class, $house);
        self::assertSame(Tone::CALM, $house->notes['first']);
    }

    public function testTheRenderedInstanceClosureBuildsTheSameInstance(): void
    {
        $instanceDefinition = new InstanceDefinition(
            'app.house',
            House::class,
            [
                ['service' => 'app.window'],
                4,
                ['style' => 'georgian'],
            ],
        );

        $closure = eval('return ' . AotClosures::instance($instanceDefinition) . ';');
        self::assertInstanceOf(Closure::class, $closure);

        $house = $closure($this->container);
        self::assertInstanceOf(House::class, $house);
        self::assertSame('storm', $house->window->glazing);
        self::assertSame(4, $house->panes);
        self::assertSame(['style' => 'georgian'], $house->trim);
    }

    public function testAnObjectInsideACallsArgumentsRefusesWithItsPosition(): void
    {
        try {
            AotClosures::instance(new InstanceDefinition(
                'app.calls',
                CallsHouse::class,
                [['service' => 'app.window']],
                ServiceLifetime::SINGLETON,
                calls: [
                    new MethodCall('addRoof', [new stdClass()]),
                ],
            ));
            self::fail('Expected an AotException to be thrown.');
        } catch (AotUnrenderableException $aotUnrenderableException) {
            self::assertSame(
                "Service 'app.calls' carries a value the AOT artifact cannot export at 'calls.addRoof.0' (stdClass) -- "
                . 'move the computation behind the factory shape, or boot without the AOT lane.',
                $aotUnrenderableException->getMessage(),
            );
        }
    }

    public function testTheCallsCarryingShapeDelegatesToTheOneAuthority(): void
    {
        $instanceDefinition = new InstanceDefinition(
            'app.calls',
            CallsHouse::class,
            [['service' => 'app.window']],
            ServiceLifetime::SINGLETON,
            calls: [
                new MethodCall('addRoof', [['service' => 'app.roof']]),
                new MethodCall('noteWeather', ['sunny', 21]),
            ],
        );

        $code = AotClosures::instance($instanceDefinition);

        self::assertStringStartsWith(
            'static fn (Zef\Framework\Container\Container $c): object => '
            . MethodCallInvocations::class . '::apply(',
            $code,
        );
        self::assertStringContainsString(
            MethodCallInvocations::class . '::apply(new Zef\Test\Support\Container\Aot\CallsHouse(',
            $code,
        );
        self::assertStringContainsString(
            "new Zef\\Framework\\Container\\MethodCall('addRoof', [\$c->get('app.roof')])",
            $code,
        );
        self::assertStringContainsString(
            "new Zef\\Framework\\Container\\MethodCall('noteWeather', ['sunny', 21])",
            $code,
        );
    }

    public function testTheRenderedCallsClosureAppliesEveryCall(): void
    {
        $instanceDefinition = new InstanceDefinition(
            'app.calls',
            CallsHouse::class,
            [['service' => 'app.window']],
            ServiceLifetime::SINGLETON,
            calls: [
                new MethodCall('addRoof', [['service' => 'app.roof']]),
                new MethodCall('noteWeather', ['sunny', 21]),
            ],
        );

        $closure = eval('return ' . AotClosures::instance($instanceDefinition) . ';');
        self::assertInstanceOf(Closure::class, $closure);

        $house = $closure($this->container);
        self::assertInstanceOf(CallsHouse::class, $house);
        self::assertNotNull($house->roof);
        self::assertSame('thatch', $house->roof->material);
        self::assertSame('sunny', $house->weather);
        self::assertSame(21, $house->degrees);
    }

    public function testTheAliasShapeRendersTheOneDelegationHop(): void
    {
        $code = AotClosures::alias(new AliasDefinition('app.alias', 'app.house'));

        self::assertSame(
            "static fn (Zef\\Framework\\Container\\Container \$c): mixed => \$c->get('app.house')",
            $code,
        );
    }

    public function testTheValueShapeRendersTheHeldLiteral(): void
    {
        self::assertSame(
            "static fn (): mixed => 'plain answer'",
            AotClosures::value(new ValueDefinition('app.answer', 'plain answer')),
        );
    }

    public function testTheValueShapeRendersNestedData(): void
    {
        $code = AotClosures::value(new ValueDefinition('app.answer', ['numbers' => [1, 2, 3]]));

        $closure = eval('return ' . $code . ';');
        self::assertInstanceOf(Closure::class, $closure);

        self::assertSame(['numbers' => [1, 2, 3]], $closure());
    }

    public function testAnObjectValueRefusesTheBoundaryWithTheWayOut(): void
    {
        try {
            AotClosures::value(new ValueDefinition('app.object', new stdClass()));
            self::fail('Expected an AotException to be thrown.');
        } catch (AotUnrenderableException $aotUnrenderableException) {
            self::assertSame(
                "Service 'app.object' carries a value the AOT artifact cannot export at 'value' (stdClass) -- "
                . 'move the computation behind the factory shape, or boot without the AOT lane.',
                $aotUnrenderableException->getMessage(),
            );
        }
    }

    public function testAnObjectArgumentRefusesWithItsPosition(): void
    {
        try {
            AotClosures::instance(new InstanceDefinition(
                'app.house',
                House::class,
                [['service' => 'app.window'], new stdClass()],
            ));
            self::fail('Expected an AotException to be thrown.');
        } catch (AotUnrenderableException $aotUnrenderableException) {
            self::assertSame(
                "Service 'app.house' carries a value the AOT artifact cannot export at 'arguments.1' (stdClass) -- "
                . 'move the computation behind the factory shape, or boot without the AOT lane.',
                $aotUnrenderableException->getMessage(),
            );
        }
    }

    public function testANestedServiceShapedMapStaysVerbatimData(): void
    {
        // The resolution lane recognises the reference form at the top
        // level of an argument entry only -- a service-shaped map nested
        // inside a plain array value is data the resolution passes
        // through, so the rendering exports it untouched.
        $instanceDefinition = new InstanceDefinition(
            'app.house',
            House::class,
            [
                ['service' => 'app.window'],
                1,
                ['nested' => ['service' => 'app.roof']],
            ],
        );

        $closure = eval('return ' . AotClosures::instance($instanceDefinition) . ';');
        self::assertInstanceOf(Closure::class, $closure);

        $house = $closure($this->container);
        self::assertInstanceOf(House::class, $house);
        self::assertSame(['nested' => ['service' => 'app.roof']], $house->trim);
    }

    public function testAHandBuiltMultiKeyServiceMapResolvesLikeTheRuntimeWalk(): void
    {
        // The recognition mirrors ArgumentResolution exactly: a map whose
        // `service` key holds a string answers the container's call, the
        // extra keys ignored -- both lanes agree by construction.
        $instanceDefinition = new InstanceDefinition(
            'app.house',
            House::class,
            [
                ['service' => 'app.window', 'ignored' => 1],
                2,
                [],
            ],
        );

        $closure = eval('return ' . AotClosures::instance($instanceDefinition) . ';');
        self::assertInstanceOf(Closure::class, $closure);

        $house = $closure($this->container);
        self::assertInstanceOf(House::class, $house);
        self::assertSame('storm', $house->window->glazing);
    }

    public function testLiteralsExportScalarsAndRefuseNothingData(): void
    {
        self::assertSame('NULL', AotLiterals::export(null, 's', 'p'));
        self::assertSame('true', AotLiterals::export(true, 's', 'p'));
        self::assertSame('4', AotLiterals::export(4, 's', 'p'));
        self::assertSame('1.5', AotLiterals::export(1.5, 's', 'p'));
        self::assertSame("'word'", AotLiterals::export('word', 's', 'p'));
    }

    public function testAssertExportableRefusesAnObjectAtItsDottedPath(): void
    {
        try {
            AotExportability::assertExportable(['a' => [new stdClass()]], 's', 'p');
            self::fail('Expected an AotException to be thrown.');
        } catch (AotUnrenderableException $aotUnrenderableException) {
            self::assertSame(
                "Service 's' carries a value the AOT artifact cannot export at 'p.a.0' (stdClass) -- "
                . 'move the computation behind the factory shape, or boot without the AOT lane.',
                $aotUnrenderableException->getMessage(),
            );
        }
    }

    public function testTheArgumentParameterListJoinsEntriesWithCommas(): void
    {
        self::assertSame(
            "\$c->get('a'), 1, 'two'",
            AotArguments::parameterList([['service' => 'a'], 1, 'two'], 's', 'p'),
        );
    }

    public function testTheArgumentArrayLiteralBracketsEntries(): void
    {
        self::assertSame(
            "[\$c->get('a'), 1]",
            AotArguments::arrayLiteral([['service' => 'a'], 1], 's', 'p'),
        );
    }
}
