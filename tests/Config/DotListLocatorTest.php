<?php

declare(strict_types=1);

namespace Zef\Test\Config;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Config\ConfigException;
use Zef\Framework\Config\DotListLocator;

/**
 * @internal
 */
#[CoversClass(DotListLocator::class)]
final class DotListLocatorTest extends TestCase
{
    public function testTargetReturnsTheAddressedListItself(): void
    {
        $list = ['auth', 'cors'];

        self::assertSame(
            $list,
            DotListLocator::target(['middleware' => $list], ['middleware'], 'append to'),
        );
    }

    public function testTargetAcceptsAnEmptyList(): void
    {
        // An existing empty list is a found, valid target: distinguishing
        // "empty list" from "not set" is exactly what the found flag is for.
        self::assertSame(
            [],
            DotListLocator::target(['middleware' => []], ['middleware'], 'append to'),
        );
    }

    public function testTargetRejectsAMissingKey(): void
    {
        try {
            DotListLocator::target(['app' => 'zef'], ['missing'], 'append to');
            self::fail('Appending to a missing key must raise a ConfigException.');
        } catch (ConfigException $configException) {
            self::assertSame(
                'Cannot append to configuration key "missing": it is not set.',
                $configException->getMessage(),
            );
        }
    }

    public function testTargetRejectsAMissingNestedKeyAndReportsTheFullPath(): void
    {
        $data = ['database' => ['mysql' => ['host' => 'localhost']]];

        try {
            DotListLocator::target($data, ['database', 'redis', 'hosts'], 'append to');
            self::fail('Appending to a missing nested key must raise a ConfigException.');
        } catch (ConfigException $configException) {
            self::assertSame(
                'Cannot append to configuration key "database.redis.hosts": it is not set.',
                $configException->getMessage(),
            );
        }
    }

    public function testTargetRejectsAScalarTarget(): void
    {
        try {
            DotListLocator::target(['app' => 'zef'], ['app'], 'append to');
            self::fail('Appending to a scalar must raise a ConfigException.');
        } catch (ConfigException $configException) {
            self::assertSame(
                'Cannot append to configuration key "app": it is not a list.',
                $configException->getMessage(),
            );
        }
    }

    public function testTargetRejectsAnAssociativeMapTarget(): void
    {
        try {
            DotListLocator::target(['driver' => ['primary' => 'mysql']], ['driver'], 'append to');
            self::fail('Appending to an associative map must raise a ConfigException.');
        } catch (ConfigException $configException) {
            self::assertSame(
                'Cannot append to configuration key "driver": it is not a list.',
                $configException->getMessage(),
            );
        }
    }

    public function testTargetRejectsAScalarIntermediate(): void
    {
        try {
            DotListLocator::target(['database' => 'mysql'], ['database', 'hosts'], 'append to');
            self::fail('Appending through a scalar intermediate must raise a ConfigException.');
        } catch (ConfigException $configException) {
            self::assertSame(
                'Cannot append to configuration key "database.hosts": it is not set.',
                $configException->getMessage(),
            );
        }
    }

    public function testTargetReportsThePrependOperationWhenAskedTo(): void
    {
        try {
            DotListLocator::target(['app' => 'zef'], ['app'], 'prepend to');
            self::fail('Prepending to a scalar must raise a ConfigException.');
        } catch (ConfigException $configException) {
            self::assertSame(
                'Cannot prepend to configuration key "app": it is not a list.',
                $configException->getMessage(),
            );
        }
    }
}
