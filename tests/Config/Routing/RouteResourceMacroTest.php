<?php

declare(strict_types=1);

namespace Zef\Test\Config\Routing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Config\ConfigException;
use Zef\Framework\Config\Routing\PendingRoute;
use Zef\Framework\Config\Routing\RadixTree;
use Zef\Framework\Config\Routing\RouteDsl;
use Zef\Framework\Config\Routing\RouteDslConstraints;
use Zef\Framework\Config\Routing\RouteDslFields;
use Zef\Framework\Config\Routing\RouteDslMethods;
use Zef\Framework\Config\Routing\RouteDslMiddleware;
use Zef\Framework\Config\Routing\RouteDslScope;
use Zef\Framework\Config\Routing\RouteDslSnapshot;
use Zef\Framework\Config\Routing\RoutePath;
use Zef\Framework\Config\Routing\RoutePrefix;
use Zef\Framework\Config\Routing\RouteResourceMacro;
use Zef\Framework\Config\Routing\RouteResourceName;
use Zef\Framework\Config\Schema\RouteSchema;

/**
 * @internal
 */
#[CoversClass(RouteResourceMacro::class)]
#[CoversClass(RouteResourceName::class)]
#[CoversClass(RouteDsl::class)]
#[CoversClass(RouteDslMethods::class)]
#[CoversClass(RouteDslScope::class)]
#[CoversClass(RouteDslSnapshot::class)]
#[CoversClass(RouteDslConstraints::class)]
#[CoversClass(RouteDslFields::class)]
#[CoversClass(RouteDslMiddleware::class)]
#[CoversClass(PendingRoute::class)]
#[CoversClass(RoutePrefix::class)]
#[CoversClass(RoutePath::class)]
final class RouteResourceMacroTest extends TestCase
{
    public function testExpandsAPlainResourceIntoSevenRoutes(): void
    {
        $routes = RouteDsl::define(static function (RouteDsl $routeDsl): void {
            $routeDsl->resource('/photos', 'PhotoController');
        });

        self::assertSame(
            [
                [
                    'path' => '/photos',
                    'method' => ['GET'],
                    'handler' => 'PhotoController@index',
                    'name' => 'photos.index',
                ],
                [
                    'path' => '/photos/create',
                    'method' => ['GET'],
                    'handler' => 'PhotoController@create',
                    'name' => 'photos.create',
                ],
                [
                    'path' => '/photos',
                    'method' => ['POST'],
                    'handler' => 'PhotoController@store',
                    'name' => 'photos.store',
                ],
                [
                    'path' => '/photos/{id}',
                    'method' => ['GET'],
                    'handler' => 'PhotoController@show',
                    'name' => 'photos.show',
                ],
                [
                    'path' => '/photos/{id}/edit',
                    'method' => ['GET'],
                    'handler' => 'PhotoController@edit',
                    'name' => 'photos.edit',
                ],
                [
                    'path' => '/photos/{id}',
                    'method' => ['PUT', 'PATCH'],
                    'handler' => 'PhotoController@update',
                    'name' => 'photos.update',
                ],
                [
                    'path' => '/photos/{id}',
                    'method' => ['DELETE'],
                    'handler' => 'PhotoController@destroy',
                    'name' => 'photos.destroy',
                ],
            ],
            $routes,
        );
    }

    public function testTheIdConstraintReachesOnlyTheParameterCarryingRoutes(): void
    {
        $routes = RouteDsl::define(static function (RouteDsl $routeDsl): void {
            $routeDsl->resource('/photos', 'PhotoController', ['id' => '[0-9]+']);
        });

        self::assertArrayNotHasKey('constraints', $routes[0]);
        self::assertArrayNotHasKey('constraints', $routes[1]);
        self::assertArrayNotHasKey('constraints', $routes[2]);
        self::assertSame(['id' => '[0-9]+'], $routes[3]['constraints']);
        self::assertSame(['id' => '[0-9]+'], $routes[4]['constraints']);
        self::assertSame(['id' => '[0-9]+'], $routes[5]['constraints']);
        self::assertSame(['id' => '[0-9]+'], $routes[6]['constraints']);
    }

    public function testNamesTheResourceFromItsStaticSegmentsOnly(): void
    {
        $routes = RouteDsl::define(static function (RouteDsl $routeDsl): void {
            $routeDsl->resource('/users/{user}/posts', 'PostController');
        });

        self::assertSame('users.posts.show', $routes[3]['name']);
        self::assertSame('/users/{user}/posts/{id}', $routes[3]['path']);
    }

    public function testComposesGroupPrefixAndScopeMiddleware(): void
    {
        $routes = RouteDsl::define(static function (RouteDsl $routeDsl): void {
            $routeDsl->middleware('Logging');

            $routeDsl->middleware('Auth')->group('/api', static function (RouteDsl $routeDsl): void {
                $routeDsl->resource('/photos', 'PhotoController');
            });
        });

        self::assertSame('/api/photos/{id}', $routes[3]['path']);
        self::assertSame(['Logging', 'Auth'], $routes[3]['middleware']);
        self::assertSame('photos.show', $routes[3]['name']);
    }

    public function testRejectsAnEmptyController(): void
    {
        try {
            RouteDsl::define(static function (RouteDsl $routeDsl): void {
                $routeDsl->resource('/photos', '');
            });
            self::fail('An empty resource controller should have been rejected.');
        } catch (ConfigException $configException) {
            self::assertSame('The resource controller must not be empty.', $configException->getMessage());
        }
    }

    public function testRejectsAResourcePathWithoutAStaticSegment(): void
    {
        try {
            RouteDsl::define(static function (RouteDsl $routeDsl): void {
                $routeDsl->resource('/{category}', 'CategoryController');
            });
            self::fail('A resource path without a static segment should have been rejected.');
        } catch (ConfigException $configException) {
            self::assertSame(
                'The resource path "/{category}" must contain at least one static segment to name the resource.',
                $configException->getMessage(),
            );
        }
    }

    public function testRejectsTheRootResourcePath(): void
    {
        try {
            RouteDsl::define(static function (RouteDsl $routeDsl): void {
                $routeDsl->resource('/', 'RootController');
            });
            self::fail('The root resource path should have been rejected.');
        } catch (ConfigException $configException) {
            self::assertSame(
                'The resource path "/" must contain at least one static segment to name the resource.',
                $configException->getMessage(),
            );
        }
    }

    public function testRejectsAConstraintForAParameterTheMacroNeverCreates(): void
    {
        try {
            RouteDsl::define(static function (RouteDsl $routeDsl): void {
                $routeDsl->resource('/photos', 'PhotoController', ['slug' => '[a-z]+']);
            });
            self::fail('A constraint for a foreign parameter should have been rejected.');
        } catch (ConfigException $configException) {
            self::assertSame(
                'The resource constraints must only carry the "id" parameter.',
                $configException->getMessage(),
            );
        }
    }

    public function testRejectsAnInvalidIdPatternThroughTheDeclarationRules(): void
    {
        try {
            RouteDsl::define(static function (RouteDsl $routeDsl): void {
                $routeDsl->resource('/photos', 'PhotoController', ['id' => '']);
            });
            self::fail('An empty id pattern should have been rejected.');
        } catch (ConfigException $configException) {
            self::assertSame(
                'The constraint "id" on route "/photos/{id}" must be a non-empty pattern.',
                $configException->getMessage(),
            );
        }
    }

    public function testAMalformedResourcePathFailsThroughTheGrammar(): void
    {
        try {
            RouteDsl::define(static function (RouteDsl $routeDsl): void {
                $routeDsl->resource('photos', 'PhotoController');
            });
            self::fail('A malformed resource path should have been rejected.');
        } catch (ConfigException $configException) {
            self::assertSame('The route path "photos" must start with a slash.', $configException->getMessage());
        }
    }

    public function testTheEngineCompilesTheExpandedResource(): void
    {
        $routes = RouteDsl::define(static function (RouteDsl $routeDsl): void {
            $routeDsl->resource('/photos', 'PhotoController', ['id' => '[0-9]+']);
        });

        new RouteSchema()->assertList($routes);

        $matchResult = RadixTree::compile($routes)->match('GET', '/photos/42');

        self::assertTrue($matchResult->isFound());
        self::assertSame('PhotoController@show', $matchResult->route()->handler());
        self::assertSame(['id' => '42'], $matchResult->parameters());
    }

    public function testANameCollisionWithAnExplicitRouteIsRejectedByTheEngine(): void
    {
        $routes = RouteDsl::define(static function (RouteDsl $routeDsl): void {
            $routeDsl->get('/galleries', 'GalleryController@index')->name('photos.index');
            $routeDsl->resource('/photos', 'PhotoController');
        });

        try {
            RadixTree::compile($routes);
            self::fail('A duplicate route name should have been rejected by the engine.');
        } catch (ConfigException $configException) {
            self::assertStringContainsString('photos.index', $configException->getMessage());
        }
    }
}
