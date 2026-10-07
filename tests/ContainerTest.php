<?php

declare(strict_types=1);

namespace Zef\Test;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Container\NotFoundExceptionInterface;
use RuntimeException;
use stdClass;
use Zef\Framework\Container\Container;
use Zef\Framework\Container\NotFoundException;
use Zef\Framework\Contracts\ContainerException;

/**
 * @internal
 */
#[CoversClass(Container::class)]
#[CoversClass(ContainerException::class)]
#[CoversClass(NotFoundException::class)]
final class ContainerTest extends TestCase
{
    public function testHasReturnsFalseForAnUnknownIdentifier(): void
    {
        self::assertFalse(new Container()->has('unknown'));
    }

    public function testHasReturnsTrueAfterSet(): void
    {
        $container = new Container();
        $container->set('service', static fn (): string => 'value');

        self::assertTrue($container->has('service'));
    }

    public function testGetResolvesTheFactoryResult(): void
    {
        $container = new Container();
        $container->set('service', static fn (): string => 'value');

        self::assertSame('value', $container->get('service'));
    }

    public function testFactoryReceivesTheContainer(): void
    {
        $container = new Container();
        $container->set('service', static fn (Container $container): Container => $container);

        self::assertSame($container, $container->get('service'));
    }

    public function testGetMemoisesTheResolvedInstance(): void
    {
        $container = new Container();
        $container->set('service', static fn (): stdClass => new stdClass());

        self::assertSame($container->get('service'), $container->get('service'));
    }

    public function testSetInvalidatesAPreviouslyResolvedInstance(): void
    {
        $container = new Container();
        $container->set('service', static fn (): string => 'first');
        $container->get('service');
        $container->set('service', static fn (): string => 'second');

        self::assertSame('second', $container->get('service'));
    }

    public function testGetThrowsForAnUnknownIdentifier(): void
    {
        $container = new Container();

        $this->expectException(NotFoundExceptionInterface::class);

        $container->get('unknown');
    }

    public function testNotFoundMessageMentionsTheIdentifier(): void
    {
        $container = new Container();

        try {
            $container->get('zef.missing');
            self::fail('Expected a NotFoundException to be thrown.');
        } catch (NotFoundException $notFoundException) {
            self::assertStringContainsString('zef.missing', $notFoundException->getMessage());
        }
    }

    public function testNotFoundIsCatchableAsAContainerException(): void
    {
        $container = new Container();

        try {
            $container->get('missing');
            self::fail('Expected a container exception to be thrown.');
        } catch (ContainerException $containerException) {
            self::assertStringContainsString('missing', $containerException->getMessage());
        }
    }

    public function testNotFoundExceptionIsAlsoARuntimeException(): void
    {
        self::assertArrayHasKey(RuntimeException::class, class_parents(new NotFoundException('boom')));
    }
}
