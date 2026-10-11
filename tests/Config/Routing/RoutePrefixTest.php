<?php

declare(strict_types=1);

namespace Zef\Test\Config\Routing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Config\ConfigException;
use Zef\Framework\Config\Routing\RoutePrefix;

/**
 * @internal
 */
#[CoversClass(RoutePrefix::class)]
final class RoutePrefixTest extends TestCase
{
    public function testRejectsAnEmptyPrefix(): void
    {
        try {
            RoutePrefix::fromString('');
            self::fail('An empty prefix should have been rejected.');
        } catch (ConfigException $configException) {
            self::assertSame('The route prefix "" is empty.', $configException->getMessage());
        }
    }

    public function testRejectsAPrefixWithoutALeadingSlash(): void
    {
        try {
            RoutePrefix::fromString('api');
            self::fail('A prefix without a leading slash should have been rejected.');
        } catch (ConfigException $configException) {
            self::assertSame('The route prefix "api" must start with a slash.', $configException->getMessage());
        }
    }

    public function testRejectsAPrefixWithATrailingSlash(): void
    {
        try {
            RoutePrefix::fromString('/api/');
            self::fail('A prefix with a trailing slash should have been rejected.');
        } catch (ConfigException $configException) {
            self::assertSame('The route prefix "/api/" must not end with a slash.', $configException->getMessage());
        }
    }

    public function testRootPrefixLeavesThePathAlone(): void
    {
        $routePrefix = RoutePrefix::fromString('/');

        self::assertSame('/users/{id}', $routePrefix->concat('/users/{id}'));
    }

    public function testRootPathYieldsThePrefixItself(): void
    {
        $routePrefix = RoutePrefix::fromString('/api');

        self::assertSame('/api', $routePrefix->concat('/'));
    }

    public function testJoinsPrefixAndPath(): void
    {
        $routePrefix = RoutePrefix::fromString('/api/v1');

        self::assertSame('/api/v1/users/{id}', $routePrefix->concat('/users/{id}'));
    }

    public function testJoinsTwoRootsIntoTheRoot(): void
    {
        $routePrefix = RoutePrefix::fromString('/');

        self::assertSame('/', $routePrefix->concat('/'));
    }

    public function testConcatRejectsAnEmptyPath(): void
    {
        $routePrefix = RoutePrefix::fromString('/api');

        try {
            $routePrefix->concat('');
            self::fail('An empty path should have been rejected.');
        } catch (ConfigException $configException) {
            self::assertSame('The route path "" is empty.', $configException->getMessage());
        }
    }

    public function testConcatRejectsAPathWithoutALeadingSlash(): void
    {
        $routePrefix = RoutePrefix::fromString('/api');

        try {
            $routePrefix->concat('users');
            self::fail('A path without a leading slash should have been rejected.');
        } catch (ConfigException $configException) {
            self::assertSame('The route path "users" must start with a slash.', $configException->getMessage());
        }
    }
}
