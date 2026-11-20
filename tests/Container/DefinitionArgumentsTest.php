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
                . '(extra keys alongside it are rejected; a literal map that must keep its own '
                . "'service' key escapes through the single-key form ['literal' => value]).",
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

    public function testTheLiteralEscapeAnswersItsOwnValueVerbatim(): void
    {
        // The grammar's own escape (issue #800's finding 1): a single-key
        // `['literal' => $value]` entry answers `$value` itself, so the
        // reference-shaped map the declaration actually wanted as data
        // reaches the constructor as data -- never the container's answer
        // for the identifier it spells.
        $container = new Container();
        $container->set('app.greeter', static fn (): Greeter => new Greeter('Hello'));

        $resolved = DefinitionArguments::resolve(
            [
                ['literal' => ['service' => 'app.greeter']],
                ['literal' => 'plain'],
                ['literal' => ['nested' => ['literal' => true]]],
            ],
            $container,
        );

        self::assertSame(['service' => 'app.greeter'], $resolved[0]);
        self::assertSame('plain', $resolved[1]);
        self::assertSame(['nested' => ['literal' => true]], $resolved[2]);
    }

    public function testAResolutionRefusesAMultiKeyServiceCarrierLikeTheValidator(): void
    {
        // The two lanes answer one shape with one verdict (issue #800's
        // finding 1): the first runtime spelling resolved ANY map that
        // merely carried a `service` key, so a hand-built definition --
        // the path that bypasses the parse-time validation -- resolved a
        // multi-key map the declarative lane refuses. The aligned
        // recognition surfaces the same ambiguity at the instantiation
        // seam instead.
        $container = new Container();
        $container->set('app.greeter', static fn (): Greeter => new Greeter('Hello'));

        try {
            DefinitionArguments::resolve([['service' => 'app.greeter', 'extra' => 1]], $container);
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "An argument entry must not carry 'service' beside other keys: the reference "
                . "form owns the whole map (['service' => identifier]; a literal map that must "
                . "keep its own 'service' key escapes through ['literal' => value]).",
                $definitionException->getMessage(),
            );
        }
    }

    public function testAResolutionRefusesAMalformedServiceCarrierLikeTheValidator(): void
    {
        // The malformed single-key carriers refuse at the instantiation
        // seam too: the aligned grammar recognises the reference by the
        // single-key non-empty string form alone.
        $container = new Container();

        try {
            DefinitionArguments::resolve([['service' => 7]], $container);
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                'An argument entry reference must name a non-empty service identifier, int given.',
                $definitionException->getMessage(),
            );
        }

        try {
            DefinitionArguments::resolve([['service' => '']], $container);
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                'An argument entry reference must name a non-empty service identifier, string given.',
                $definitionException->getMessage(),
            );
        }
    }

    public function testAResolutionRefusesAMultiKeyLiteralCarrier(): void
    {
        // The escape owns the whole map the same way the reference form
        // does: a `literal` key beside siblings is neither the escape nor
        // data, and the ambiguity is surfaced rather than guessed.
        $container = new Container();

        try {
            DefinitionArguments::resolve([['literal' => 'value', 'extra' => 1]], $container);
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "An argument entry must not carry 'literal' beside other keys: the literal "
                . 'escape owns the whole map (single key only).',
                $definitionException->getMessage(),
            );
        }
    }
}
