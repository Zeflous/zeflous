<?php

declare(strict_types=1);

namespace Zef\Test\Container;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Container\Container;
use Zef\Framework\Container\DefinitionException;
use Zef\Framework\Container\MethodCall;
use Zef\Framework\Container\MethodCallInvocations;
use Zef\Framework\Container\MethodCallNames;
use Zef\Framework\Container\MethodCallRefusals;
use Zef\Framework\Container\MethodCalls;
use Zef\Framework\Container\MethodCallShape;
use Zef\Test\Support\Container\Greeter;
use Zef\Test\Support\Container\Injector\TunedWidget;

/**
 * @internal
 */
#[CoversClass(MethodCalls::class)]
#[CoversClass(MethodCall::class)]
#[CoversClass(MethodCallInvocations::class)]
#[CoversClass(MethodCallShape::class)]
#[CoversClass(MethodCallNames::class)]
#[CoversClass(MethodCallRefusals::class)]
final class MethodCallsTest extends TestCase
{
    public function testAnAbsentCallsKeyAnswersTheEmptyList(): void
    {
        self::assertSame([], MethodCalls::fromKey('app.widget', TunedWidget::class, ['class' => TunedWidget::class]));
    }

    public function testAClassShapedDeclarationParsesItsCalls(): void
    {
        $calls = MethodCalls::fromKey('app.widget', TunedWidget::class, [
            'class' => TunedWidget::class,
            'calls' => [
                ['method' => 'setVolume', 'arguments' => [7]],
                ['method' => 'warmUp'],
            ],
        ]);

        self::assertCount(2, $calls);
        self::assertSame('setVolume', $calls[0]->method);
        self::assertSame([7], $calls[0]->arguments);
        self::assertSame('warmUp', $calls[1]->method);
        self::assertSame([], $calls[1]->arguments);
    }

    public function testAFactoryShapedDeclarationParsesItsCallsWithoutTheMethodProof(): void
    {
        $calls = MethodCalls::fromKey('app.product', null, [
            'calls' => [['method' => 'setOrigin', 'arguments' => ['boot']]],
        ]);

        self::assertSame('setOrigin', $calls[0]->method);
        self::assertSame(['boot'], $calls[0]->arguments);
    }

    public function testACallEntryCarryingAServiceReferenceKeepsTheReferenceForm(): void
    {
        $calls = MethodCalls::fromKey('app.widget', TunedWidget::class, [
            'calls' => [['method' => 'addTag', 'arguments' => [['service' => 'app.tag']]]],
        ]);

        self::assertSame([['service' => 'app.tag']], $calls[0]->arguments);
    }

    public function testANonListCallsValueIsRejected(): void
    {
        try {
            MethodCalls::fromKey('app.widget', TunedWidget::class, ['calls' => 'nope']);
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "Service 'app.widget' calls must be a list, string given.",
                $definitionException->getMessage(),
            );
        }
    }

    public function testAStringKeyedCallsMapIsRejected(): void
    {
        try {
            MethodCalls::fromKey('app.widget', TunedWidget::class, [
                'calls' => ['warmUp' => []],
            ]);
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "Service 'app.widget' calls must be a positional list (string keys are rejected).",
                $definitionException->getMessage(),
            );
        }
    }

    public function testANonMapCallEntryIsRejected(): void
    {
        try {
            MethodCalls::fromKey('app.widget', TunedWidget::class, ['calls' => ['warmUp']]);
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "Service 'app.widget' call entry must be a map, string given.",
                $definitionException->getMessage(),
            );
        }
    }

    public function testAnUnknownCallEntryKeyIsRejectedWithTheKnownList(): void
    {
        try {
            MethodCalls::fromKey('app.widget', TunedWidget::class, [
                'calls' => [['method' => 'warmUp', 'arguments' => [], 'times' => 2]],
            ]);
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "Service 'app.widget' call entry carries the unknown key 'times' (known keys: method, arguments).",
                $definitionException->getMessage(),
            );
        }
    }

    public function testACallEntryWithoutAMethodKeyIsRejected(): void
    {
        try {
            MethodCalls::fromKey('app.widget', TunedWidget::class, [
                'calls' => [['arguments' => [7]]],
            ]);
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "Service 'app.widget' call entry method must be a non-empty method name, null given.",
                $definitionException->getMessage(),
            );
        }
    }

    public function testANonStringMethodNameIsRejected(): void
    {
        try {
            MethodCalls::fromKey('app.widget', TunedWidget::class, [
                'calls' => [['method' => 7]],
            ]);
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "Service 'app.widget' call entry method must be a non-empty method name, int given.",
                $definitionException->getMessage(),
            );
        }
    }

    public function testAnEmptyMethodNameIsRejected(): void
    {
        try {
            MethodCalls::fromKey('app.widget', TunedWidget::class, [
                'calls' => [['method' => '']],
            ]);
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "Service 'app.widget' call entry method must be a non-empty method name, string given.",
                $definitionException->getMessage(),
            );
        }
    }

    public function testAClassShapedCallToAMissingMethodIsRejectedAtParseTime(): void
    {
        try {
            MethodCalls::fromKey('app.widget', TunedWidget::class, [
                'calls' => [['method' => 'detune']],
            ]);
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "Service 'app.widget' call method 'detune' does not exist on '" . TunedWidget::class . "'.",
                $definitionException->getMessage(),
            );
        }
    }

    public function testCallArgumentsCarryTheArgumentsGrammarRefusals(): void
    {
        try {
            MethodCalls::fromKey('app.widget', TunedWidget::class, [
                'calls' => [['method' => 'setVolume', 'arguments' => ['level' => 7]]],
            ]);
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "Service 'app.widget' arguments must be a positional list (string keys are rejected).",
                $definitionException->getMessage(),
            );
        }
    }

    public function testCallArgumentsRefuseAMalformedServiceReference(): void
    {
        try {
            MethodCalls::fromKey('app.widget', TunedWidget::class, [
                'calls' => [['method' => 'addTag', 'arguments' => [['service' => 'app.tag', 'extra' => 1]]]],
            ]);
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "Service 'app.widget' argument reference must be the single key 'service' "
                . '(extra keys alongside it are rejected).',
                $definitionException->getMessage(),
            );
        }
    }

    public function testAMethodCallRequiresANonEmptyMethodName(): void
    {
        try {
            new MethodCall('');
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                'A method call requires a non-empty method name.',
                $definitionException->getMessage(),
            );
        }
    }

    public function testApplyInvokesEveryCallInDeclarationOrder(): void
    {
        $tunedWidget = new TunedWidget('main');

        $result = MethodCallInvocations::apply($tunedWidget, [
            new MethodCall('setVolume', [7]),
            new MethodCall('addTag', ['ops']),
            new MethodCall('warmUp'),
        ], new Container());

        self::assertSame($tunedWidget, $result, 'the mutated instance is the answer');
        self::assertSame(['volume:7', 'tag:ops', 'warm'], $tunedWidget->log());
        self::assertTrue($tunedWidget->warmed());
    }

    public function testApplyResolvesServiceReferencesThroughTheContainer(): void
    {
        $container = new Container();
        $container->set('app.peer', static fn (): TunedWidget => new TunedWidget('peer'));

        $tunedWidget = new TunedWidget('main');

        MethodCallInvocations::apply($tunedWidget, [
            new MethodCall('adoptPeer', [['service' => 'app.peer']]),
        ], $container);

        self::assertSame(['peer:peer'], $tunedWidget->log());
    }

    public function testApplyWithNoCallsAnswersTheInstanceUntouched(): void
    {
        $tunedWidget = new TunedWidget('main');

        $result = MethodCallInvocations::apply($tunedWidget, [], new Container());

        self::assertSame($tunedWidget, $result);
        self::assertSame([], $tunedWidget->log());
    }

    public function testApplyOnAMissingMethodFailsClosedWithTheInstanceClassNamed(): void
    {
        $greeter = new Greeter('Hello');

        try {
            MethodCallInvocations::apply($greeter, [new MethodCall('detune')], new Container());
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "Service call 'detune' is not callable on " . Greeter::class . '.',
                $definitionException->getMessage(),
            );
        }
    }
}
