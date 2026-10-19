<?php

declare(strict_types=1);

namespace Zef\Test\Container;

use ArrayObject;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass;
use Zef\Framework\Container\Container;
use Zef\Framework\Container\DefinitionException;
use Zef\Framework\Container\InterceptionBuilder;
use Zef\Framework\Container\InterceptionClassBuilt;
use Zef\Framework\Container\InterceptionClassFit;
use Zef\Framework\Container\InterceptionEntry;
use Zef\Framework\Container\InterceptionFactoryBuilt;
use Zef\Framework\Container\InterceptionFires;
use Zef\Framework\Container\InterceptionMaterialisations;
use Zef\Framework\Container\InterceptionPipeline;
use Zef\Framework\Container\InterceptionRefusals;
use Zef\Framework\Container\Interceptions;
use Zef\Framework\Container\InterceptionSet;
use Zef\Framework\Container\InterceptorsMap;
use Zef\Framework\Container\ListenersMap;
use Zef\Framework\Container\NotFoundException;
use Zef\Framework\Container\ResolvedListener;
use Zef\Framework\Container\ResolvingListener;
use Zef\Framework\Container\ServiceInterceptor;
use Zef\Framework\Container\ServiceLifetime;
use Zef\Test\Support\Container\Interception\DependentInterceptor;
use Zef\Test\Support\Container\Interception\InterceptionHooks;
use Zef\Test\Support\Container\Interception\InterceptionLog;
use Zef\Test\Support\Container\Interception\ObservingResolvedListener;
use Zef\Test\Support\Container\Interception\OptionalInterceptor;
use Zef\Test\Support\Container\Interception\RecordingInterceptor;
use Zef\Test\Support\Container\Interception\RecordingResolvingListener;
use Zef\Test\Support\Container\Interception\ReplacedService;
use Zef\Test\Support\Container\Interception\ReplacingResolvedListener;
use Zef\Test\Support\Container\Interception\ShapelessService;
use Zef\Test\Support\Container\Interception\ShortCircuitInterceptor;

/**
 * The interception registry's own behaviour: the arming (the knobs'
 * entries materialised through the plain lane, the contracts proven,
 * the once-per-boot seal), and the run the resolution lanes ride --
 * the listener order around the construction, the replacement before
 * the per-lifetime memo, and the machinery bypass (ROADMAP area 9's
 * runtime half, over the store's own seam).
 *
 * Every observation rides the public surface: the entries spell the
 * fixtures' own constructors, the shared log arrives as an ordinary
 * service reference, and what the registry holds is what the
 * resolutions answer -- no reflection into the machinery.
 *
 * @internal
 */
#[CoversClass(Interceptions::class)]
#[CoversClass(InterceptionSet::class)]
#[CoversClass(InterceptionFires::class)]
#[CoversClass(InterceptionPipeline::class)]
#[CoversClass(InterceptionMaterialisations::class)]
#[CoversClass(InterceptionBuilder::class)]
#[CoversClass(InterceptionClassBuilt::class)]
#[CoversClass(InterceptionFactoryBuilt::class)]
#[CoversClass(InterceptionClassFit::class)]
#[CoversClass(InterceptionEntry::class)]
#[CoversClass(InterceptionRefusals::class)]
final class InterceptionsTest extends TestCase
{
    public function testAnUnarmedRegistryLeavesTheConstructionPlain(): void
    {
        $container = new Container();
        $container->set('plain', static fn (): stdClass => new stdClass());

        self::assertFalse($container->interceptions()->armed());

        $instance = $container->get('plain');
        self::assertInstanceOf(stdClass::class, $instance);
    }

    public function testADirectRunOnAnUnarmedRegistryIsRefused(): void
    {
        $container = new Container();

        // The caller's armed() check is the fast path; the registry's
        // own run is the armed set's run -- a registry without a set
        // has nothing to run a construction under, and the honest
        // refusal says so rather than silently answering the terminal.
        try {
            $container->interceptions()->run(
                'never.constructed',
                static fn (): stdClass => new stdClass(),
            );
            self::fail('Expected a thrown refusal.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "The container's interception registry is not armed -- the boot arms it once, "
                . 'before the first resolution.',
                $definitionException->getMessage(),
            );
        }
    }

    public function testAHandBuiltEntryCarryingNoShapeIsRefused(): void
    {
        $container = new Container();

        try {
            InterceptionBuilder::built(new InterceptionEntry(null, null, []), 'interceptors.0', $container);
            self::fail('Expected a thrown refusal.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "The interception entry 'interceptors.0' carries neither a class nor a factory spelling "
                . '-- exactly one must be declared.',
                $definitionException->getMessage(),
            );
        }
    }

    public function testTheArmingMaterialisesThroughThePlainLaneEagerly(): void
    {
        $container = new Container();
        $arrayObject = new ArrayObject([], ArrayObject::STD_PROP_LIST);
        $container->set('dep.service', static function () use ($arrayObject): stdClass {
            $arrayObject[] = 'built';

            return new stdClass();
        });

        $container->interceptions()->arm(
            InterceptorsMap::parse('interceptors', [
                ['class' => DependentInterceptor::class, 'arguments' => [['service' => 'dep.service']]],
            ]),
            ListenersMap::parse('listeners', null),
            $container,
        );

        self::assertTrue($container->interceptions()->armed());
        self::assertSame(
            ['built'],
            $arrayObject->getArrayCopy(),
            'the reference answered at the materialisation, before a single get()',
        );
    }

    public function testASecondArmingIsRefused(): void
    {
        $container = new Container();
        $container->interceptions()->arm(
            InterceptorsMap::parse('interceptors', null),
            ListenersMap::parse('listeners', null),
            $container,
        );

        try {
            $container->interceptions()->arm(
                InterceptorsMap::parse('interceptors', null),
                ListenersMap::parse('listeners', null),
                $container,
            );
            self::fail('Expected a thrown refusal.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "The container's interception registry is already armed -- the boot arms it once.",
                $definitionException->getMessage(),
            );
        }
    }

    public function testAnEmptyArmingIsTheUnarmedState(): void
    {
        $container = new Container();
        $container->interceptions()->arm(
            InterceptorsMap::parse('interceptors', null),
            ListenersMap::parse('listeners', []),
            $container,
        );

        self::assertFalse(
            $container->interceptions()->armed(),
            'a registry armed with nothing is unarmed in every observable way but the second arming',
        );
    }

    public function testAMaterialisationRefusingAnUnregisteredReferenceStaysUnarmed(): void
    {
        $container = new Container();

        try {
            $container->interceptions()->arm(
                InterceptorsMap::parse('interceptors', [
                    ['class' => DependentInterceptor::class, 'arguments' => [['service' => 'not.registered']]],
                ]),
                ListenersMap::parse('listeners', null),
                $container,
            );
        } catch (NotFoundException) {
            // The plain lane's own refusal, raw.
        }

        self::assertFalse($container->interceptions()->armed());
    }

    public function testTheSeamOrderIsResolvingThenTheOnionThenTheReplacement(): void
    {
        $interceptionLog = new InterceptionLog();
        $container = new Container();
        $container->set('test.log', static fn (): InterceptionLog => $interceptionLog);
        $container->set('wrapped', static function () use ($interceptionLog): stdClass {
            $interceptionLog->record('constructed');

            return new stdClass();
        });

        $this->armed($container, [
            'interceptors' => [
                [
                    'class' => RecordingInterceptor::class,
                    'arguments' => ['outer', ['service' => 'test.log']],
                    'priority' => 10,
                ],
                ['class' => RecordingInterceptor::class, 'arguments' => ['inner', ['service' => 'test.log']]],
            ],
            'resolving' => [['class' => RecordingResolvingListener::class, 'arguments' => [['service' => 'test.log']]]],
            'resolved' => [['class' => ReplacingResolvedListener::class, 'arguments' => [['service' => 'test.log']]]],
        ]);

        $answer = $container->get('wrapped');

        self::assertInstanceOf(ReplacedService::class, $answer);
        self::assertSame(
            [
                'resolving: wrapped',
                'outer > wrapped',
                'inner > wrapped',
                'constructed',
                'inner < wrapped',
                'outer < wrapped',
                'replaced: wrapped',
            ],
            $interceptionLog->lines(),
        );
    }

    public function testTheResolvedListenerReplacesBeforeTheSingletonMemo(): void
    {
        $container = new Container();
        $container->set('replaced', static fn (): stdClass => new stdClass());

        $this->armed($container, [
            'resolved' => [['class' => ReplacingResolvedListener::class, 'arguments' => [['service' => 'test.log']]]],
        ]);

        $first = $container->get('replaced');
        self::assertInstanceOf(ReplacedService::class, $first);
        self::assertInstanceOf(stdClass::class, $first->inner);

        $second = $container->get('replaced');
        self::assertSame($first, $second, 'the memo holds the replacement, not the construction');
    }

    public function testANullResolvedAnswerKeepsTheInstance(): void
    {
        $container = new Container();
        $instance = new stdClass();
        $container->set('kept', static fn (): stdClass => $instance);

        $this->armed($container, [
            'resolved' => [['class' => ObservingResolvedListener::class, 'arguments' => [['service' => 'test.log']]]],
        ]);

        self::assertSame($instance, $container->get('kept'));
    }

    public function testTheMemoHitFiresNothing(): void
    {
        $interceptionLog = new InterceptionLog();
        $container = new Container();
        $container->set('test.log', static fn (): InterceptionLog => $interceptionLog);
        $container->set('memoised', static fn (): stdClass => new stdClass());

        $this->armed($container, [
            'resolving' => [['class' => RecordingResolvingListener::class, 'arguments' => [['service' => 'test.log']]]],
            'resolved' => [['class' => ObservingResolvedListener::class, 'arguments' => [['service' => 'test.log']]]],
        ]);

        $container->get('memoised');
        $container->get('memoised');
        $container->get('memoised');

        self::assertSame(
            ['resolving: memoised', 'observed: memoised'],
            $interceptionLog->lines(),
        );
    }

    public function testTheTransientLaneReFiresEveryResolution(): void
    {
        $interceptionLog = new InterceptionLog();
        $container = new Container();
        $container->set('test.log', static fn (): InterceptionLog => $interceptionLog);
        $container->set('fresh', static fn (): stdClass => new stdClass(), ServiceLifetime::TRANSIENT);

        $this->armed($container, [
            'resolving' => [['class' => RecordingResolvingListener::class, 'arguments' => [['service' => 'test.log']]]],
            'resolved' => [['class' => ReplacingResolvedListener::class, 'arguments' => [['service' => 'test.log']]]],
        ]);

        $first = $container->get('fresh');
        $second = $container->get('fresh');

        self::assertNotSame($first, $second);
        self::assertSame(
            ['resolving: fresh', 'replaced: fresh', 'resolving: fresh', 'replaced: fresh'],
            $interceptionLog->lines(),
        );
    }

    public function testTheRequestLaneRecordsTheReplacementInTheScope(): void
    {
        $container = new Container();
        $container->set('scoped', static fn (): stdClass => new stdClass(), ServiceLifetime::REQUEST);

        $this->armed($container, [
            'resolved' => [['class' => ReplacingResolvedListener::class, 'arguments' => [['service' => 'test.log']]]],
        ]);

        $scope = $container->createRequestScope();
        $first = $scope->get('scoped');
        $second = $scope->get('scoped');

        self::assertInstanceOf(ReplacedService::class, $first);
        self::assertSame($first, $second, 'the scope memo holds the replacement');
    }

    public function testTheOnionBypassesTheMachineryIdentifiers(): void
    {
        $interceptionLog = new InterceptionLog();
        $container = new Container();
        $container->set('test.log', static fn (): InterceptionLog => $interceptionLog);
        $container->set('@decor:demo:base', static fn (): stdClass => new stdClass());

        $this->armed($container, [
            'interceptors' => [
                ['class' => RecordingInterceptor::class, 'arguments' => ['link', ['service' => 'test.log']]],
            ],
            'resolving' => [['class' => RecordingResolvingListener::class, 'arguments' => [['service' => 'test.log']]]],
        ]);

        $container->get('@decor:demo:base');

        self::assertSame(
            ['resolving: @decor:demo:base'],
            $interceptionLog->lines(),
            "the onion wraps the host's services, not the container's plumbing; "
            . 'the listeners observe every construction',
        );
    }

    public function testAShortCircuitBecomesTheMemoisedInstance(): void
    {
        $sentinel = new stdClass();
        $container = new Container();
        $container->set('test.sentinel', static fn (): stdClass => $sentinel);
        $container->set('short circuited', static fn (): stdClass => new stdClass());

        $this->armed($container, [
            'interceptors' => [
                ['class' => ShortCircuitInterceptor::class, 'arguments' => [['service' => 'test.sentinel']]],
            ],
        ]);

        self::assertSame($sentinel, $container->get('short circuited'));
        self::assertSame($sentinel, $container->get('short circuited'));
    }

    public function testAShapelessClassIsRefusedAtTheMaterialisation(): void
    {
        $container = new Container();

        try {
            $this->armed($container, ['interceptors' => [['class' => ShapelessService::class]]]);
            self::fail('Expected a thrown refusal.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "The interception entry 'interceptors.0' built " . ShapelessService::class
                . ', which does not implement the ' . ServiceInterceptor::class . ' contract the knob demands.',
                $definitionException->getMessage(),
            );
        }
    }

    public function testAShapelessResolvedListenerIsRefused(): void
    {
        $container = new Container();

        try {
            $this->armed($container, ['resolved' => [['class' => ShapelessService::class]]]);
            self::fail('Expected a thrown refusal.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "The interception entry 'listeners.resolved.0' built " . ShapelessService::class
                . ', which does not implement the ' . ResolvedListener::class . ' contract the knob demands.',
                $definitionException->getMessage(),
            );
        }
    }

    public function testAShapelessResolvingListenerIsRefused(): void
    {
        $container = new Container();

        try {
            $this->armed($container, ['resolving' => [['class' => ShapelessService::class]]]);
            self::fail('Expected a thrown refusal.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "The interception entry 'listeners.resolving.0' built " . ShapelessService::class
                . ', which does not implement the ' . ResolvingListener::class . ' contract the knob demands.',
                $definitionException->getMessage(),
            );
        }
    }

    public function testAFactoryEntryMaterialisesItsBuilder(): void
    {
        $container = new Container();

        $this->armed($container, [
            'interceptors' => [
                ['factory' => InterceptionHooks::class . '::counting', 'arguments' => [['service' => 'test.log']]],
            ],
        ]);

        self::assertTrue($container->interceptions()->armed());
    }

    public function testARegisteredFactoryTargetRunsAsTheInstanceMethod(): void
    {
        $container = new Container();
        $container->set('hooks.instance', static fn (): InterceptionHooks => new InterceptionHooks());

        $this->armed($container, [
            'interceptors' => [
                ['factory' => 'hooks.instance::countingInstance', 'arguments' => [['service' => 'test.log']]],
            ],
        ]);

        // The non-static method only answers through the resolved
        // instance: reaching this point is the precedence's own proof.
        self::assertTrue($container->interceptions()->armed());
    }

    public function testAFactoryAnsweringANonObjectIsRefused(): void
    {
        $container = new Container();

        try {
            $this->armed($container, [
                'interceptors' => [
                    ['factory' => InterceptionHooks::class . '::notAnObject'],
                ],
            ]);
            self::fail('Expected a thrown refusal.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "The interception entry 'interceptors.0' factory built string, "
                . 'an interception builder must answer an object.',
                $definitionException->getMessage(),
            );
        }
    }

    public function testAFactoryTargetResolvingToANonObjectIsRefused(): void
    {
        $container = new Container();
        $container->set('plain.value', static fn (): string => 'a string');

        try {
            $this->armed($container, [
                'interceptors' => [
                    ['factory' => 'plain.value::method'],
                ],
            ]);
            self::fail('Expected a thrown refusal.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "The interception entry 'interceptors.0' factory service 'plain.value' "
                . 'did not resolve to an object, string given.',
                $definitionException->getMessage(),
            );
        }
    }

    public function testTooManyArgumentsAreRefusedAtTheFit(): void
    {
        $container = new Container();

        try {
            $this->armed($container, [
                'interceptors' => [
                    ['class' => ShapelessService::class, 'arguments' => ['one', 'two']],
                ],
            ]);
            self::fail('Expected a thrown refusal.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "The interception entry 'interceptors.0' class "
                . '\'Zef\Test\Support\Container\Interception\ShapelessService\' '
                . 'declares more arguments than the constructor carries (1).',
                $definitionException->getMessage(),
            );
        }
    }

    public function testStoppingBeforeARequiredParameterIsRefusedAtTheFit(): void
    {
        $container = new Container();

        try {
            $this->armed($container, [
                'interceptors' => [['class' => DependentInterceptor::class]],
            ]);
            self::fail('Expected a thrown refusal.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "The interception entry 'interceptors.0' class "
                . '\'Zef\Test\Support\Container\Interception\DependentInterceptor\' '
                . "stops before the required parameter 'dependency' -- declare its argument, "
                . 'or give the constructor a default.',
                $definitionException->getMessage(),
            );
        }
    }

    public function testOptionalParametersBeyondThePlanAreTheConstructorSAnswer(): void
    {
        $container = new Container();

        $this->armed($container, [
            'interceptors' => [
                ['class' => OptionalInterceptor::class, 'arguments' => []],
            ],
        ]);

        self::assertTrue(
            $container->interceptions()->armed(),
            "an empty plan against an optional-only constructor is the constructor's own answer, not a fit refusal",
        );

        $answer = new stdClass();
        $container->set('proof', static fn (): stdClass => $answer);
        self::assertSame($answer, $container->get('proof'), 'the chain runs, the default-built link calling $next()');
    }

    /**
     * Arms the container's registry from the raw knob arrays, the log
     * service installed first so the entries' references answer.
     *
     * @param array<string, mixed> $knobs the interception knobs (interceptors, resolving, resolved)
     */
    private function armed(Container $container, array $knobs): void
    {
        if (!$container->has('test.log')) {
            $container->set('test.log', static fn (): InterceptionLog => new InterceptionLog());
        }

        $container->interceptions()->arm(
            InterceptorsMap::parse('interceptors', $knobs['interceptors'] ?? null),
            ListenersMap::parse('listeners', [
                'resolving' => $knobs['resolving'] ?? [],
                'resolved' => $knobs['resolved'] ?? [],
            ]),
            $container,
        );
    }
}
