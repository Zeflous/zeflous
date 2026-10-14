<?php

declare(strict_types=1);

namespace Zef\Test\Container;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Container\ArgumentReference;
use Zef\Framework\Container\ArgumentResolution;
use Zef\Framework\Container\Container;
use Zef\Framework\Container\DefinitionArguments;
use Zef\Framework\Container\DefinitionException;
use Zef\Test\Support\Container\Announcer;
use Zef\Test\Support\Container\Greeter;

/**
 * @internal
 */
#[CoversClass(DefinitionArguments::class)]
#[CoversClass(ArgumentReference::class)]
#[CoversClass(ArgumentResolution::class)]
final class DefinitionArgumentsTest extends TestCase
{
    public function testResolutionPassesPlainValuesThroughVerbatim(): void
    {
        $container = new Container();

        $resolved = DefinitionArguments::resolve(['Hello', 2, true, null, ['a' => 1]], $container);

        self::assertSame(['Hello', 2, true, null, ['a' => 1]], $resolved);
    }

    public function testResolutionAnswersTheReferencedServiceInstance(): void
    {
        $container = new Container();
        $container->set('app.greeter', static fn (): Greeter => new Greeter('Hello'));

        $resolved = DefinitionArguments::resolve([['service' => 'app.greeter'], 'plain'], $container);

        self::assertInstanceOf(Greeter::class, $resolved[0]);
        self::assertSame('plain', $resolved[1]);
    }

    public function testResolutionAnswersReferencedServicesInPlace(): void
    {
        $container = new Container();
        $container->set('app.greeter', static fn (): Greeter => new Greeter('Hello'));
        $container->set('app.channel', static fn (): string => 'ops');

        $resolved = DefinitionArguments::resolve(
            [['service' => 'app.greeter'], ['service' => 'app.channel']],
            $container,
        );
        [$greeter, $channel] = $resolved;

        self::assertInstanceOf(Greeter::class, $greeter);
        self::assertSame('ops', $channel);
    }

    public function testResolutionServesTheWholeConstructorThroughReferences(): void
    {
        $container = new Container();
        $container->set('app.greeter', static fn (): Greeter => new Greeter('Hello'));
        $container->set('app.channel', static fn (): string => 'ops');

        $resolved = DefinitionArguments::resolve(
            [['service' => 'app.greeter'], ['service' => 'app.channel']],
            $container,
        );
        [$greeter, $channel] = $resolved;

        self::assertInstanceOf(Greeter::class, $greeter);
        self::assertIsString($channel);

        $announcer = new Announcer($greeter, $channel);

        self::assertSame('[ops] Hello Zef!', $announcer->announce('Zef'));
    }

    public function testResolutionResolvesAReferenceOnlyWhenCalled(): void
    {
        $container = new Container();
        $container->set('app.greeter', static fn (): Greeter => new Greeter('Hello'));

        $resolved = DefinitionArguments::resolve([['service' => 'app.greeter']], $container);
        $first = $resolved[0];
        $second = DefinitionArguments::resolve([['service' => 'app.greeter']], $container)[0];

        self::assertSame($first, $second, 'the container memoises the referenced singleton');
    }

    public function testAssertionKeepsAValidListVerbatim(): void
    {
        $validated = DefinitionArguments::assertValid(
            'app.x',
            ['Hello', ['service' => 'app.y']],
        );

        self::assertSame(['Hello', ['service' => 'app.y']], $validated);
    }

    public function testAssertionRejectsANonListArgumentsValue(): void
    {
        try {
            DefinitionArguments::assertValid('app.x', 'nope');
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "Service 'app.x' arguments must be a list, string given.",
                $definitionException->getMessage(),
            );
        }
    }

    public function testAssertionRejectsStringKeyedLists(): void
    {
        try {
            DefinitionArguments::assertValid('app.x', ['named' => 1]);
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertStringContainsString(
                'arguments must be a positional list (string keys are rejected)',
                $definitionException->getMessage(),
            );
        }
    }

    public function testAssertionRejectsAMultiKeyReference(): void
    {
        try {
            DefinitionArguments::assertValid('app.x', [['service' => 'app.y', 'extra' => 1]]);
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "Service 'app.x' argument reference must be the single key 'service' "
                . '(extra keys alongside it are rejected).',
                $definitionException->getMessage(),
            );
        }
    }

    public function testAssertionRejectsANonStringReference(): void
    {
        try {
            DefinitionArguments::assertValid('app.x', [['service' => 7]]);
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "Service 'app.x' argument reference must name a non-empty service identifier, int given.",
                $definitionException->getMessage(),
            );
        }
    }

    public function testAssertionRejectsAnEmptyStringReference(): void
    {
        try {
            DefinitionArguments::assertValid('app.x', [['service' => '']]);
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "Service 'app.x' argument reference must name a non-empty service identifier, string given.",
                $definitionException->getMessage(),
            );
        }
    }

    public function testAMapWithoutTheServiceKeyIsAPlainValue(): void
    {
        $container = new Container();

        $resolved = DefinitionArguments::resolve([['not-a-service' => 'map']], $container);

        self::assertSame([['not-a-service' => 'map']], $resolved);
    }
}
