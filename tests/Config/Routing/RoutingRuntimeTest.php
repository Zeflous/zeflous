<?php

declare(strict_types=1);

namespace Zef\Test\Config\Routing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Config\Config;
use Zef\Framework\Config\ConfigException;
use Zef\Framework\Config\Routing\RadixTree;
use Zef\Framework\Config\Routing\RouteDsl;
use Zef\Framework\Config\Routing\RoutingRuntime;

/**
 * @internal
 */
#[CoversClass(RoutingRuntime::class)]
#[CoversClass(RadixTree::class)]
#[CoversClass(RouteDsl::class)]
final class RoutingRuntimeTest extends TestCase
{
    public function testARepositoryWithoutTheRoutesKeyMatchesNothing(): void
    {
        $routingRuntime = RoutingRuntime::fromConfig(new Config(['app' => ['name' => 'zef']]));

        self::assertFalse($routingRuntime->match('GET', '/health')->isFound());
        self::assertFalse($routingRuntime->match('GET', '/')->isFound());
    }

    public function testARepositoryWithoutTheRoutesKeyFailsClosedOnReverseRouting(): void
    {
        $routingRuntime = RoutingRuntime::fromConfig(new Config());

        try {
            $routingRuntime->url('anything', []);
            self::fail('Reverse routing over an empty table must raise a ConfigException.');
        } catch (ConfigException $configException) {
            self::assertSame('The route name "anything" is not registered.', $configException->getMessage());
        }
    }

    public function testAnExplicitNullRoutesValueFailsClosed(): void
    {
        $this->assertRoutingIsRejected(['routes' => null]);
    }

    public function testAScalarRoutesValueFailsClosed(): void
    {
        $this->assertRoutingIsRejected(['routes' => '/health']);
    }

    public function testAnEmptyRoutesTableMatchesNothing(): void
    {
        $routingRuntime = RoutingRuntime::fromConfig(new Config(['routes' => []]));

        self::assertFalse($routingRuntime->match('GET', '/health')->isFound());
    }

    public function testAListAddressedTableMatchesItsRoutes(): void
    {
        $routingRuntime = RoutingRuntime::fromConfig(new Config(['routes' => $this->routes()]));

        $match = $routingRuntime->match('GET', '/users/42');

        self::assertTrue($match->isFound());
        self::assertSame(['id' => '42'], $match->parameters());
        self::assertSame('Users@show', $match->route()->handler());
    }

    public function testAMapAddressedTableIsNormalisedToAList(): void
    {
        $routingRuntime = RoutingRuntime::fromConfig(new Config([
            'routes' => [
                'first' => ['path' => '/health', 'method' => ['GET'], 'handler' => 'Health@check', 'name' => 'health'],
                'second' => [
                    'path' => '/users/{id}',
                    'method' => ['GET'],
                    'handler' => 'Users@show',
                    'name' => 'users.show',
                ],
            ],
        ]));

        self::assertTrue($routingRuntime->match('GET', '/health')->isFound());
        self::assertSame(['id' => '42'], $routingRuntime->match('GET', '/users/42')->parameters());
    }

    public function testMapKeysAreDiscardedAndAreNotRouteNames(): void
    {
        $routingRuntime = RoutingRuntime::fromConfig(new Config([
            'routes' => [
                'health' => ['path' => '/health', 'method' => ['GET'], 'handler' => 'Health@check'],
            ],
        ]));

        try {
            $routingRuntime->route('health');
            self::fail('A discarded map key must not resolve as a route name.');
        } catch (ConfigException $configException) {
            self::assertSame('The route name "health" is not registered.', $configException->getMessage());
        }
    }

    public function testTheEntryNameFieldIsTheSingleSourceOfTruth(): void
    {
        $routingRuntime = RoutingRuntime::fromConfig(new Config(['routes' => $this->routes()]));

        self::assertSame('Users@show', $routingRuntime->route('users.show')->handler());
    }

    public function testTheTreeIsCompiledAtMostOnce(): void
    {
        $routingRuntime = RoutingRuntime::fromConfig(new Config(['routes' => $this->routes()]));

        self::assertSame($routingRuntime->tree(), $routingRuntime->tree());
    }

    public function testTheTreeIsMemoisedAcrossMatches(): void
    {
        $routingRuntime = RoutingRuntime::fromConfig(new Config(['routes' => $this->routes()]));

        $matchResult = $routingRuntime->match('GET', '/users/42');
        $second = $routingRuntime->match('GET', '/users/42');

        self::assertTrue($matchResult->isFound());
        self::assertTrue($second->isFound());
        self::assertSame($matchResult->route(), $second->route());
    }

    public function testFromTreeAdoptsTheGivenTreeAsItsOneCompilation(): void
    {
        $radixTree = RadixTree::compile($this->routes());
        $routingRuntime = RoutingRuntime::fromTree($radixTree);

        self::assertSame($radixTree, $routingRuntime->tree());
        self::assertSame($radixTree, $routingRuntime->tree());
    }

    public function testFromTreeAnswersLikeTheConfiguredRuntime(): void
    {
        $fromTree = RoutingRuntime::fromTree(RadixTree::compile($this->routes()));
        $fromConfig = RoutingRuntime::fromConfig(new Config(['routes' => $this->routes()]));

        self::assertTrue($fromTree->match('GET', '/users/42')->isFound());
        self::assertSame(
            $fromConfig->match('GET', '/users/42')->route()->handler(),
            $fromTree->match('GET', '/users/42')->route()->handler(),
        );
        self::assertSame($fromConfig->url('users.show', ['id' => '42']), $fromTree->url('users.show', ['id' => '42']));
        self::assertFalse($fromTree->match('GET', '/users/abc')->isFound());
        self::assertFalse($fromTree->match('GET', '/missing')->isFound());
    }

    public function testMatchDelegatesMethodNotAllowedWithSortedMethods(): void
    {
        $routingRuntime = RoutingRuntime::fromConfig(new Config(['routes' => $this->routes()]));

        $match = $routingRuntime->match('DELETE', '/health');

        self::assertTrue($match->isMethodNotAllowed());
        self::assertSame(['GET', 'POST'], $match->allowedMethods());
    }

    public function testMatchDelegatesNotFound(): void
    {
        $routingRuntime = RoutingRuntime::fromConfig(new Config(['routes' => $this->routes()]));

        self::assertFalse($routingRuntime->match('GET', '/missing')->isFound());
        self::assertFalse($routingRuntime->match('GET', '/missing')->isMethodNotAllowed());
    }

    public function testMatchAcceptsARawMethodAndUppercasesItInTheEngine(): void
    {
        $routingRuntime = RoutingRuntime::fromConfig(new Config(['routes' => $this->routes()]));

        self::assertTrue($routingRuntime->match('get', '/health')->isFound());
    }

    public function testRouteFailsClosedOnAnUnknownName(): void
    {
        $routingRuntime = RoutingRuntime::fromConfig(new Config(['routes' => $this->routes()]));

        try {
            $routingRuntime->route('missing');
            self::fail('An unknown route name must raise a ConfigException.');
        } catch (ConfigException $configException) {
            self::assertSame('The route name "missing" is not registered.', $configException->getMessage());
        }
    }

    public function testUrlGeneratesOverTheSameTree(): void
    {
        $routingRuntime = RoutingRuntime::fromConfig(new Config(['routes' => $this->routes()]));

        self::assertSame('/users/42', $routingRuntime->url('users.show', ['id' => 42]));
    }

    public function testUrlRoundTripsBackIntoTheRuntime(): void
    {
        $routingRuntime = RoutingRuntime::fromConfig(new Config(['routes' => $this->routes()]));

        $url = $routingRuntime->url('users.show', ['id' => '42']);
        $match = $routingRuntime->match('GET', $url);

        self::assertTrue($match->isFound());
        self::assertSame($routingRuntime->route('users.show'), $match->route());
        self::assertSame(['id' => '42'], $match->parameters());
    }

    public function testUrlFailsClosedOnAMissingParameter(): void
    {
        $routingRuntime = RoutingRuntime::fromConfig(new Config(['routes' => $this->routes()]));

        try {
            $routingRuntime->url('users.show', []);
            self::fail('Generating without the required parameter must raise a ConfigException.');
        } catch (ConfigException $configException) {
            self::assertSame('The route "/users/{id}" requires the parameter "id".', $configException->getMessage());
        }
    }

    public function testADslDeclaredTableRunsEndToEnd(): void
    {
        $routes = RouteDsl::define(static function (RouteDsl $routeDsl): void {
            $routeDsl->group('/api', static function (RouteDsl $routeDsl): void {
                $routeDsl->get('/health', 'Health@check')->name('api.health');
                $routeDsl->post('/users/{id}', 'Users@update')->where(['id' => '\d+'])->name('api.users.update');
            });
        });
        $routingRuntime = RoutingRuntime::fromConfig(new Config(['routes' => $routes]));

        $match = $routingRuntime->match('POST', '/api/users/7');

        self::assertTrue($match->isFound());
        self::assertSame(['id' => '7'], $match->parameters());
        self::assertSame('/api/users/9', $routingRuntime->url('api.users.update', ['id' => 9]));
        self::assertFalse($routingRuntime->match('POST', '/api/users/abc')->isFound());
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function routes(): array
    {
        return [
            ['path' => '/health', 'method' => ['GET', 'POST'], 'handler' => 'Health@check', 'name' => 'health'],
            [
                'path' => '/users/{id}',
                'method' => ['GET'],
                'handler' => 'Users@show',
                'constraints' => ['id' => '\d+'],
                'name' => 'users.show',
            ],
        ];
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private function assertRoutingIsRejected(array $data): void
    {
        try {
            RoutingRuntime::fromConfig(new Config($data));
            self::fail('A non-array routes value must raise a ConfigException.');
        } catch (ConfigException $configException) {
            self::assertSame(
                'The "routes" configuration must be a list of route entries.',
                $configException->getMessage(),
            );
        }
    }
}
