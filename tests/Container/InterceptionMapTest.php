<?php

declare(strict_types=1);

namespace Zef\Test\Container;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Container\DefinitionException;
use Zef\Framework\Container\InterceptionClassShape;
use Zef\Framework\Container\InterceptionEntry;
use Zef\Framework\Container\InterceptionEntryArguments;
use Zef\Framework\Container\InterceptionEntryComposer;
use Zef\Framework\Container\InterceptionEntryKeys;
use Zef\Framework\Container\InterceptionEntryParser;
use Zef\Framework\Container\InterceptionEntryShapes;
use Zef\Framework\Container\InterceptionPriorities;
use Zef\Framework\Container\InterceptionRefusals;
use Zef\Framework\Container\InterceptorsMap;
use Zef\Framework\Container\ListenerList;
use Zef\Framework\Container\ListenersMap;
use Zef\Framework\Container\ListenersShapes;
use Zef\Test\Support\Container\Interception\InterceptionHooks;
use Zef\Test\Support\Container\Interception\ObservingResolvedListener;
use Zef\Test\Support\Container\Interception\RecordingInterceptor;
use Zef\Test\Support\Container\Interception\RecordingResolvingListener;
use Zef\Test\Support\Container\Interception\ShapelessService;

/**
 * The interception knobs' own grammar: the `interceptors` and
 * `listeners` configuration entries parsed fail-closed -- the list and
 * map shapes, the entry mini-shapes, the priority's strict integer
 * gate, the listener kinds' two-key vocabulary, and both lanes'
 * budgets (ROADMAP area 9's declaration surface).
 *
 * @internal
 */
#[CoversClass(InterceptorsMap::class)]
#[CoversClass(ListenersMap::class)]
#[CoversClass(ListenersShapes::class)]
#[CoversClass(ListenerList::class)]
#[CoversClass(InterceptionRefusals::class)]
#[CoversClass(InterceptionEntry::class)]
#[CoversClass(InterceptionEntryParser::class)]
#[CoversClass(InterceptionEntryComposer::class)]
#[CoversClass(InterceptionEntryShapes::class)]
#[CoversClass(InterceptionEntryKeys::class)]
#[CoversClass(InterceptionEntryArguments::class)]
#[CoversClass(InterceptionPriorities::class)]
#[CoversClass(InterceptionClassShape::class)]
final class InterceptionMapTest extends TestCase
{
    private const string COUNTING_FACTORY = InterceptionHooks::class . '::counting';

    public function testAnAbsentInterceptorsKnobAnswersNoEntries(): void
    {
        self::assertSame([], InterceptorsMap::parse('interceptors', null)->interceptors());
    }

    public function testAValidInterceptorsKnobAnswersTheParsedEntries(): void
    {
        $interceptorsMap = InterceptorsMap::parse('interceptors', [
            ['class' => RecordingInterceptor::class, 'arguments' => []],
            ['factory' => self::COUNTING_FACTORY, 'arguments' => [], 'priority' => 500],
        ]);

        $entries = $interceptorsMap->interceptors();
        self::assertCount(2, $entries);

        self::assertSame(RecordingInterceptor::class, $entries[0]->class);
        self::assertNull($entries[0]->factory);
        self::assertSame([], $entries[0]->arguments);
        self::assertSame(0, $entries[0]->priority, 'an absent priority is the declaration-order band');

        self::assertNull($entries[1]->class);
        self::assertSame(self::COUNTING_FACTORY, $entries[1]->factory);
        self::assertSame(500, $entries[1]->priority);
    }

    public function testTheInterceptorsKnobMustBeAPositionalList(): void
    {
        try {
            InterceptorsMap::parse('interceptors', ['first' => ['class' => RecordingInterceptor::class]]);
            self::fail('Expected a thrown refusal.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "The 'interceptors' configuration must be a positional list of interceptor entries, array given.",
                $definitionException->getMessage(),
            );
        }
    }

    public function testTheInterceptorsBudgetIsRefused(): void
    {
        $entries = array_fill(0, InterceptorsMap::BUDGET + 1, ['class' => RecordingInterceptor::class]);

        try {
            InterceptorsMap::parse('interceptors', $entries);
            self::fail('Expected a thrown refusal.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "The 'interceptors' configuration declares more than 128 entries -- the lane's hard bound.",
                $definitionException->getMessage(),
            );
        }
    }

    public function testTheBoundaryInterceptorsBudgetIsAccepted(): void
    {
        $entries = array_fill(0, InterceptorsMap::BUDGET, ['class' => RecordingInterceptor::class]);

        self::assertCount(InterceptorsMap::BUDGET, InterceptorsMap::parse('interceptors', $entries)->interceptors());
    }

    public function testAnInterceptorEntryMustBeAMap(): void
    {
        try {
            InterceptorsMap::parse('interceptors', [RecordingInterceptor::class]);
            self::fail('Expected a thrown refusal.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "The interception entry 'interceptors.0' must be a map, string given.",
                $definitionException->getMessage(),
            );
        }
    }

    public function testAnInterceptorEntryRefusesAnUnknownKey(): void
    {
        try {
            InterceptorsMap::parse('interceptors', [['class' => RecordingInterceptor::class, 'tag' => 'x']]);
            self::fail('Expected a thrown refusal.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "The interceptor entry 'interceptors.0' carries the unknown key 'tag' "
                . '(known keys: class, factory, arguments, priority).',
                $definitionException->getMessage(),
            );
        }
    }

    public function testAnInterceptorEntryRefusesBothShapeKeys(): void
    {
        try {
            InterceptorsMap::parse('interceptors', [[
                'class' => RecordingInterceptor::class,
                'factory' => self::COUNTING_FACTORY,
            ]]);
            self::fail('Expected a thrown refusal.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "The interception entry 'interceptors.0' carries both shape keys (class, factory) "
                . '-- exactly one must be declared.',
                $definitionException->getMessage(),
            );
        }
    }

    public function testAnInterceptorEntryRefusesNoShapeKey(): void
    {
        try {
            InterceptorsMap::parse('interceptors', [['arguments' => []]]);
            self::fail('Expected a thrown refusal.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "The interception entry 'interceptors.0' carries no shape key "
                . '-- exactly one of (class, factory) must be declared.',
                $definitionException->getMessage(),
            );
        }
    }

    public function testAnInterceptorClassMustExist(): void
    {
        try {
            InterceptorsMap::parse('interceptors', [['class' => 'Vendor\MissingInterceptor']]);
            self::fail('Expected a thrown refusal.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "The interception entry 'interceptors.0' class 'Vendor\\MissingInterceptor' does not exist.",
                $definitionException->getMessage(),
            );
        }
    }

    public function testAnInterceptorClassMustBeANonEmptyString(): void
    {
        try {
            InterceptorsMap::parse('interceptors', [['class' => 0]]);
            self::fail('Expected a thrown refusal.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "The interception entry 'interceptors.0' class must be a non-empty class name, int given.",
                $definitionException->getMessage(),
            );
        }
    }

    public function testAnInterceptorPriorityMustBeAnInteger(): void
    {
        try {
            InterceptorsMap::parse('interceptors', [['class' => RecordingInterceptor::class, 'priority' => '500']]);
            self::fail('Expected a thrown refusal.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "The interceptor entry 'interceptors.0' priority must be an integer, string given.",
                $definitionException->getMessage(),
            );
        }
    }

    public function testAnInterceptorPriorityRefusesABoolean(): void
    {
        try {
            InterceptorsMap::parse('interceptors', [['class' => RecordingInterceptor::class, 'priority' => true]]);
            self::fail('Expected a thrown refusal.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "The interceptor entry 'interceptors.0' priority must be an integer, bool given.",
                $definitionException->getMessage(),
            );
        }
    }

    public function testAnInterceptorFactorySpellingIsProven(): void
    {
        try {
            InterceptorsMap::parse('interceptors', [['factory' => 'Hooks']]);
            self::fail('Expected a thrown refusal.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "Service 'interceptors.0' factory 'Hooks' must carry exactly one '::' separator.",
                $definitionException->getMessage(),
            );
        }
    }

    public function testInterceptorArgumentsFollowTheReferenceGrammar(): void
    {
        try {
            InterceptorsMap::parse('interceptors', [[
                'class' => RecordingInterceptor::class,
                'arguments' => ['named' => 'rejected'],
            ]]);
            self::fail('Expected a thrown refusal.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "Service 'interceptors.0' arguments must be a positional list (string keys are rejected).",
                $definitionException->getMessage(),
            );
        }
    }

    public function testAnAbsentListenersKnobAnswersNoLists(): void
    {
        $listenersMap = ListenersMap::parse('listeners', null);

        self::assertSame([], $listenersMap->resolving());
        self::assertSame([], $listenersMap->resolved());
    }

    public function testAValidListenersKnobAnswersBothKinds(): void
    {
        $listenersMap = ListenersMap::parse('listeners', [
            'resolving' => [['class' => RecordingResolvingListener::class]],
            'resolved' => [['factory' => InterceptionHooks::class . '::replacing', 'arguments' => []]],
        ]);

        self::assertCount(1, $listenersMap->resolving());
        self::assertSame(RecordingResolvingListener::class, $listenersMap->resolving()[0]->class);
        self::assertSame(0, $listenersMap->resolving()[0]->priority, 'listener entries never carry a priority');

        self::assertCount(1, $listenersMap->resolved());
        self::assertSame(InterceptionHooks::class . '::replacing', $listenersMap->resolved()[0]->factory);
    }

    public function testEachListenerKindIsIndependentlyOptional(): void
    {
        $listenersMap = ListenersMap::parse('listeners', ['resolved' => []]);

        self::assertSame([], $listenersMap->resolving());
        self::assertSame([], $listenersMap->resolved());
    }

    public function testTheListenersKnobMustBeAMap(): void
    {
        try {
            ListenersMap::parse('listeners', [[]]);
            self::fail('Expected a thrown refusal.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "The 'listeners' configuration must be a map of listener kinds (resolving, resolved), array given.",
                $definitionException->getMessage(),
            );
        }
    }

    public function testAScalarListenersKnobIsRefusedAsAMap(): void
    {
        try {
            ListenersMap::parse('listeners', 'nonsense');
            self::fail('Expected a thrown refusal.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "The 'listeners' configuration must be a map of listener kinds (resolving, resolved), string given.",
                $definitionException->getMessage(),
            );
        }
    }

    public function testAnEmptyListenersListIsTheValidEmptyKnob(): void
    {
        $listenersMap = ListenersMap::parse('listeners', []);

        self::assertSame([], $listenersMap->resolving());
        self::assertSame([], $listenersMap->resolved());
    }

    public function testTheListenersKnobRefusesAnUnknownKind(): void
    {
        try {
            ListenersMap::parse('listeners', ['resoved' => []]);
            self::fail('Expected a thrown refusal.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "The 'listeners' configuration carries the unknown listener kind 'resoved' "
                . '(known kinds: resolving, resolved).',
                $definitionException->getMessage(),
            );
        }
    }

    public function testAListenerKindMustBeAPositionalList(): void
    {
        try {
            ListenersMap::parse('listeners', ['resolving' => 7]);
            self::fail('Expected a thrown refusal.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "The 'listeners.resolving' configuration must be a positional list of listener entries, int given.",
                $definitionException->getMessage(),
            );
        }
    }

    public function testAListenerKindRefusesItsBudget(): void
    {
        $entries = array_fill(0, ListenerList::BUDGET + 1, ['class' => RecordingResolvingListener::class]);

        try {
            ListenersMap::parse('listeners', ['resolved' => $entries]);
            self::fail('Expected a thrown refusal.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "The 'listeners.resolved' configuration declares more than 32 entries -- the lane's hard bound.",
                $definitionException->getMessage(),
            );
        }
    }

    public function testTheListenerBudgetBoundaryIsAccepted(): void
    {
        $entries = array_fill(0, ListenerList::BUDGET, ['class' => ObservingResolvedListener::class]);

        self::assertCount(ListenerList::BUDGET, ListenersMap::parse('listeners', ['resolved' => $entries])->resolved());
    }

    public function testAListenerEntryRefusesThePriorityKey(): void
    {
        try {
            ListenersMap::parse('listeners', [
                'resolving' => [['class' => RecordingResolvingListener::class, 'priority' => 10]],
            ]);
            self::fail('Expected a thrown refusal.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "The listener entry 'listeners.resolving.0' carries the 'priority' key -- listeners fire "
                . 'in declaration order, the honest spelling a priority would blur.',
                $definitionException->getMessage(),
            );
        }
    }

    public function testAListenerEntryRefusesAnUnknownKey(): void
    {
        try {
            ListenersMap::parse('listeners', [
                'resolved' => [['class' => ObservingResolvedListener::class, 'tags' => ['x']]],
            ]);
            self::fail('Expected a thrown refusal.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "The listener entry 'listeners.resolved.0' carries the unknown key 'tags' "
                . '(known keys: class, factory, arguments).',
                $definitionException->getMessage(),
            );
        }
    }

    public function testAListenerClassMustExist(): void
    {
        try {
            ListenersMap::parse('listeners', [
                'resolving' => [['class' => 'Vendor\MissingListener']],
            ]);
            self::fail('Expected a thrown refusal.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "The interception entry 'listeners.resolving.0' class 'Vendor\\MissingListener' does not exist.",
                $definitionException->getMessage(),
            );
        }
    }

    public function testAListenerEntryMustBeAMap(): void
    {
        try {
            ListenersMap::parse('listeners', ['resolving' => [7]]);
            self::fail('Expected a thrown refusal.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "The interception entry 'listeners.resolving.0' must be a map, int given.",
                $definitionException->getMessage(),
            );
        }
    }

    public function testAListenerEntryCannotCarryBothShapeKeys(): void
    {
        try {
            ListenersMap::parse('listeners', [
                'resolving' => [[
                    'class' => RecordingResolvingListener::class,
                    'factory' => InterceptionHooks::class . '::replacing',
                ]],
            ]);
            self::fail('Expected a thrown refusal.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "The interception entry 'listeners.resolving.0' carries both shape keys (class, factory) "
                . '-- exactly one must be declared.',
                $definitionException->getMessage(),
            );
        }
    }

    public function testTheEntryLabelCarriesTheKnobPathAndPosition(): void
    {
        try {
            ListenersMap::parse('listeners', [
                'resolved' => [
                    ['class' => ObservingResolvedListener::class],
                    'not-a-map',
                ],
            ]);
            self::fail('Expected a thrown refusal.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "The interception entry 'listeners.resolved.1' must be a map, string given.",
                $definitionException->getMessage(),
            );
        }
    }

    public function testAShapelessClassSurvivesTheParse(): void
    {
        // The interface proof is the materialisation's own: a class that
        // implements nothing parses cleanly (the grammar owns the shape,
        // the arming owns the contract).
        $interceptorsMap = InterceptorsMap::parse('interceptors', [
            ['class' => ShapelessService::class],
        ]);

        self::assertSame(ShapelessService::class, $interceptorsMap->interceptors()[0]->class);
    }
}
