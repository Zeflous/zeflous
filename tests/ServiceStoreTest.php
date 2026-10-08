<?php

declare(strict_types=1);

namespace Zef\Test;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass;
use Zef\Framework\Container\Container;
use Zef\Framework\Container\NotFoundException;
use Zef\Framework\Container\ServiceStore;

/**
 * @internal
 */
#[CoversClass(ServiceStore::class)]
final class ServiceStoreTest extends TestCase
{
    public function testHasIsFalseForAnUnknownIdentifier(): void
    {
        self::assertFalse(new ServiceStore()->has('unknown'));
    }

    public function testHasIsTrueAfterSet(): void
    {
        $serviceStore = new ServiceStore();
        $serviceStore->set('service', static fn (): string => 'value');

        self::assertTrue($serviceStore->has('service'));
    }

    public function testResolveBuildsTheFactoryResult(): void
    {
        $serviceStore = new ServiceStore();
        $serviceStore->set('service', static fn (): string => 'value');

        self::assertSame('value', $serviceStore->resolve('service', new Container()));
    }

    public function testResolveMemoisesTheInstance(): void
    {
        $serviceStore = new ServiceStore();
        $serviceStore->set('service', static fn (): object => new stdClass());

        $container = new Container();

        self::assertSame($serviceStore->resolve('service', $container), $serviceStore->resolve('service', $container));
    }

    public function testResolveReturnsAMemoisedNullWithoutRebuilding(): void
    {
        $serviceStore = new ServiceStore();
        $calls = 0;
        $serviceStore->set('service', static function () use (&$calls): mixed {
            ++$calls;

            return null;
        });
        $container = new Container();

        self::assertNull($serviceStore->resolve('service', $container));
        self::assertNull($serviceStore->resolve('service', $container));
        self::assertSame(1, $calls);
    }

    public function testSetInvalidatesAPreviouslyResolvedInstance(): void
    {
        $serviceStore = new ServiceStore();
        $serviceStore->set('service', static fn (): string => 'first');
        $serviceStore->resolve('service', new Container());
        $serviceStore->set('service', static fn (): string => 'second');

        self::assertSame('second', $serviceStore->resolve('service', new Container()));
    }

    public function testResolveThrowsForAnUnknownIdentifier(): void
    {
        $this->expectException(NotFoundException::class);

        new Container()->get('unknown');
    }
}
