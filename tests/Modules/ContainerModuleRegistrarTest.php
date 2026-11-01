<?php

declare(strict_types=1);

namespace Zef\Test\Modules;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Container\Container;
use Zef\Framework\ContainerModuleRegistrar;
use Zef\Framework\FrameworkDefaults;
use Zef\Framework\ModuleClaims;
use Zef\Framework\Modules\ModuleException;
use Zef\Test\Modules\Fixtures\SharedIdModule;

/**
 * The container-backed registrar's claim policing: a module's registration
 * rides the real container, the framework's reserved identifiers are not a
 * module's to claim, a second claimant is refused with the first named,
 * and an identifier the boot already carries is refused the same way --
 * while the read side answers straight through the container.
 *
 * @internal
 */
#[CoversClass(ContainerModuleRegistrar::class)]
#[CoversClass(ModuleClaims::class)]
#[CoversClass(ModuleException::class)]
final class ContainerModuleRegistrarTest extends TestCase
{
    private Container $container;

    private ModuleClaims $moduleClaims;

    #[Override]
    protected function setUp(): void
    {
        $this->container = new Container();
        $this->moduleClaims = new ModuleClaims();
    }

    public function testARegistrationRidesTheRealContainer(): void
    {
        $containerModuleRegistrar = new ContainerModuleRegistrar($this->container, 'test/alpha', $this->moduleClaims);
        $containerModuleRegistrar->set('test.alpha.greeting', static fn (): string => 'alpha');

        self::assertTrue($this->container->has('test.alpha.greeting'));
        self::assertSame('alpha', $this->container->get('test.alpha.greeting'));
    }

    public function testTheReadSideAnswersThroughTheContainer(): void
    {
        $this->container->set(
            FrameworkDefaults::CONFIG_SERVICE,
            static fn (): array => ['framework' => true],
        );

        $containerModuleRegistrar = new ContainerModuleRegistrar($this->container, 'test/alpha', $this->moduleClaims);

        self::assertTrue($containerModuleRegistrar->has(FrameworkDefaults::CONFIG_SERVICE));
        self::assertSame(['framework' => true], $containerModuleRegistrar->get(FrameworkDefaults::CONFIG_SERVICE));
        self::assertFalse($containerModuleRegistrar->has('test.nobody'));
    }

    public function testAReservedIdentifierIsRefused(): void
    {
        $containerModuleRegistrar = new ContainerModuleRegistrar($this->container, 'test/greedy', $this->moduleClaims);

        $this->expectException(ModuleException::class);
        $this->expectExceptionMessageIs(
            "The module 'test/greedy' registers '" . FrameworkDefaults::TAGS_SERVICE
            . "', which the framework reserves; a module never claims the framework's "
            . 'own namespace.',
        );

        $containerModuleRegistrar->set(FrameworkDefaults::TAGS_SERVICE, static fn (): array => []);
    }

    public function testACarriedIdentifierIsRefused(): void
    {
        $this->container->set('host.thing', static fn (): string => 'host');

        $containerModuleRegistrar = new ContainerModuleRegistrar($this->container, 'test/greedy', $this->moduleClaims);

        $this->expectException(ModuleException::class);
        $this->expectExceptionMessageIsOrContains(
            "The module 'test/greedy' registers 'host.thing', which the boot already carries",
        );

        $containerModuleRegistrar->set('host.thing', static fn (): string => 'module');
    }

    public function testASecondClaimantIsRefusedWithTheFirstNamed(): void
    {
        $first = new ContainerModuleRegistrar($this->container, 'test/first', $this->moduleClaims);
        $first->set('shared.id', static fn (): string => 'first');

        $second = new ContainerModuleRegistrar($this->container, 'test/second', $this->moduleClaims);

        $this->expectException(ModuleException::class);
        $this->expectExceptionMessageIsOrContains(
            "The module 'test/second' registers 'shared.id', which the module "
            . "'test/first' already claimed; one identifier is one registration.",
        );

        $second->set('shared.id', static fn (): string => 'second');
    }

    public function testTheSameClaimantIsRefusedOnItsOwnRepeat(): void
    {
        $containerModuleRegistrar = new ContainerModuleRegistrar($this->container, 'test/first', $this->moduleClaims);
        $containerModuleRegistrar->set('shared.id', static fn (): string => 'first');

        $this->expectException(ModuleException::class);
        $this->expectExceptionMessageIsOrContains(
            "which the module 'test/first' already claimed",
        );

        $containerModuleRegistrar->set('shared.id', static fn (): string => 'again');
    }

    public function testTheFixtureModuleClaimsThroughTheSamePath(): void
    {
        $containerModuleRegistrar = new ContainerModuleRegistrar($this->container, 'test/shared', $this->moduleClaims);
        new SharedIdModule()->register($containerModuleRegistrar);

        self::assertSame('first', $this->container->get('shared.id'));
    }
}
