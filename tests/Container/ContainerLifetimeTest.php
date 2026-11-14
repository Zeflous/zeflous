<?php

declare(strict_types=1);

namespace Zef\Test\Container;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass;
use Zef\Framework\Container\Container;
use Zef\Framework\Container\ContainerAnswers;
use Zef\Framework\Container\ServiceLifetime;
use Zef\Framework\Container\ServiceStore;

/**
 * The facade's lifetime probe (#769's read half): the sharing policy an
 * identifier's own registration carries -- the registered lane's
 * policy, the covering fallback's prefix, and null for exactly the
 * identifiers no lane answers, the same totality has() answers with.
 *
 * @internal
 */
#[CoversClass(Container::class)]
#[CoversClass(ContainerAnswers::class)]
#[CoversClass(ServiceStore::class)]
final class ContainerLifetimeTest extends TestCase
{
    public function testTheRegisteredLaneAnswersItsOwnPolicy(): void
    {
        $container = new Container();
        $container->set('app.shared', static fn (): stdClass => new stdClass());
        $container->set('app.fresh', static fn (): stdClass => new stdClass(), ServiceLifetime::TRANSIENT);
        $container->set('app.scoped', static fn (): stdClass => new stdClass(), ServiceLifetime::REQUEST);

        self::assertSame(ServiceLifetime::SINGLETON, $container->lifetime('app.shared'));
        self::assertSame(ServiceLifetime::TRANSIENT, $container->lifetime('app.fresh'));
        self::assertSame(ServiceLifetime::REQUEST, $container->lifetime('app.scoped'));
    }

    public function testAResolvedSingletonStillAnswersItsRegistrationPolicy(): void
    {
        $container = new Container();
        $container->set('app.shared', static fn (): stdClass => new stdClass());

        $container->get('app.shared');

        self::assertSame(ServiceLifetime::SINGLETON, $container->lifetime('app.shared'));
    }

    public function testTheCoveringFallbackAnswersThePrefixPolicy(): void
    {
        $container = new Container();
        $container->namespaceFallbacks()->fallback(
            'repo.',
            static fn (): stdClass => new stdClass(),
            ServiceLifetime::TRANSIENT,
        );

        self::assertSame(ServiceLifetime::TRANSIENT, $container->lifetime('repo.widget'));
        self::assertNull($container->lifetime('other.widget'), 'a prefix nobody registered covers nothing');
    }

    public function testAnUnknownIdentifierAnswersNull(): void
    {
        self::assertNull(new Container()->lifetime('app.ghost'));
    }

    public function testARegisteredIdentifierWinsOverItsCoveringFallback(): void
    {
        // The probe's dispatch mirrors the answer lane's own: a
        // registered identifier answers through the store even when a
        // fallback prefix also covers it -- the miss path is the
        // fallback's alone, and the two lanes' policies must never
        // swap precedence between what get() reads and what the probe
        // reads.
        $container = new Container();
        $container->set('repo.thing', static fn (): stdClass => new stdClass());
        $container->namespaceFallbacks()->fallback(
            'repo.',
            static fn (): stdClass => new stdClass(),
            ServiceLifetime::TRANSIENT,
        );

        self::assertSame(ServiceLifetime::SINGLETON, $container->lifetime('repo.thing'));
        self::assertSame($container->get('repo.thing'), $container->get('repo.thing'));
    }

    public function testTheProbeAnswersForExactlyWhatHasAnswers(): void
    {
        // The same totality has() answers with: an identifier reads a
        // policy if and only if the container answers for it.
        $container = new Container();
        $container->set('app.shared', static fn (): stdClass => new stdClass());
        $container->set('app.fresh', static fn (): stdClass => new stdClass(), ServiceLifetime::TRANSIENT);
        $container->namespaceFallbacks()->fallback(
            'repo.',
            static fn (): stdClass => new stdClass(),
            ServiceLifetime::TRANSIENT,
        );

        foreach (['app.shared', 'app.fresh', 'repo.widget', 'app.ghost'] as $id) {
            self::assertSame(
                $container->has($id),
                $container->lifetime($id) instanceof ServiceLifetime,
                "the probe and has() must agree on '{$id}'",
            );
        }
    }
}
