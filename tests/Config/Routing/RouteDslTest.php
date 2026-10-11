<?php

declare(strict_types=1);

namespace Zef\Test\Config\Routing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Config\ConfigException;
use Zef\Framework\Config\Routing\PendingRoute;
use Zef\Framework\Config\Routing\RouteDsl;
use Zef\Framework\Config\Routing\RouteDslFields;
use Zef\Framework\Config\Routing\RouteDslMethods;
use Zef\Framework\Config\Routing\RouteDslMiddleware;
use Zef\Framework\Config\Routing\RouteDslScope;
use Zef\Framework\Config\Routing\RouteDslSnapshot;
use Zef\Framework\Config\Routing\RoutePath;
use Zef\Framework\Config\Routing\RoutePrefix;

/**
 * @internal
 */
#[CoversClass(RouteDsl::class)]
#[CoversClass(RouteDslMethods::class)]
#[CoversClass(RouteDslScope::class)]
#[CoversClass(RouteDslSnapshot::class)]
#[CoversClass(RouteDslFields::class)]
#[CoversClass(RouteDslMiddleware::class)]
#[CoversClass(PendingRoute::class)]
#[CoversClass(RoutePrefix::class)]
#[CoversClass(RoutePath::class)]
final class RouteDslTest extends TestCase
{
    public function testDefinesAFlatRouteList(): void
    {
        $routes = RouteDsl::define(static function (RouteDsl $routeDsl): void {
            $routeDsl->get('/health', 'Health@check');
        });

        self::assertSame(
            [
                [
                    'path' => '/health',
                    'method' => ['GET'],
                    'handler' => 'Health@check',
                ],
            ],
            $routes,
        );
    }

    public function testAnEmptyDeclarationYieldsNoRoutes(): void
    {
        $routes = RouteDsl::define(static function (RouteDsl $routeDsl): void {
        });

        self::assertSame([], $routes);
    }

    public function testEveryVerbDeclaresItsMethod(): void
    {
        $routes = RouteDsl::define(static function (RouteDsl $routeDsl): void {
            $routeDsl->get('/get', 'Handler@get');
            $routeDsl->head('/head', 'Handler@head');
            $routeDsl->post('/post', 'Handler@post');
            $routeDsl->put('/put', 'Handler@put');
            $routeDsl->patch('/patch', 'Handler@patch');
            $routeDsl->delete('/delete', 'Handler@delete');
            $routeDsl->options('/options', 'Handler@options');
        });

        self::assertSame(
            [
                ['path' => '/get', 'method' => ['GET'], 'handler' => 'Handler@get'],
                ['path' => '/head', 'method' => ['HEAD'], 'handler' => 'Handler@head'],
                ['path' => '/post', 'method' => ['POST'], 'handler' => 'Handler@post'],
                ['path' => '/put', 'method' => ['PUT'], 'handler' => 'Handler@put'],
                ['path' => '/patch', 'method' => ['PATCH'], 'handler' => 'Handler@patch'],
                ['path' => '/delete', 'method' => ['DELETE'], 'handler' => 'Handler@delete'],
                ['path' => '/options', 'method' => ['OPTIONS'], 'handler' => 'Handler@options'],
            ],
            $routes,
        );
    }

    public function testMatchDeclaresAMethodSetInDeclarationOrder(): void
    {
        $routes = RouteDsl::define(static function (RouteDsl $routeDsl): void {
            $routeDsl->match(['get', 'post'], '/health', 'Health@any');
        });

        self::assertSame(['GET', 'POST'], $routes[0]['method']);
    }

    public function testMatchUpperCasesAnySpellingIncludingUnicode(): void
    {
        $routes = RouteDsl::define(static function (RouteDsl $routeDsl): void {
            $routeDsl->match(['gét'], '/health', 'Health@query');
        });

        self::assertSame(['GÉT'], $routes[0]['method']);
    }

    public function testMatchRejectsAnEmptyMethodSet(): void
    {
        try {
            RouteDsl::define(static function (RouteDsl $routeDsl): void {
                $routeDsl->match([], '/health', 'Health@any');
            });
            self::fail('An empty method set should have been rejected.');
        } catch (ConfigException $configException) {
            self::assertSame(
                'The route "/health" must declare at least one HTTP method.',
                $configException->getMessage(),
            );
        }
    }

    public function testMatchRejectsAnEmptyMethodSpelling(): void
    {
        try {
            RouteDsl::define(static function (RouteDsl $routeDsl): void {
                $routeDsl->match(['GET', ''], '/health', 'Health@any');
            });
            self::fail('An empty method spelling should have been rejected.');
        } catch (ConfigException $configException) {
            self::assertSame(
                'The route "/health" must not declare an empty HTTP method.',
                $configException->getMessage(),
            );
        }
    }

    public function testRejectsAnEmptyHandler(): void
    {
        try {
            RouteDsl::define(static function (RouteDsl $routeDsl): void {
                $routeDsl->get('/health', '');
            });
            self::fail('An empty handler should have been rejected.');
        } catch (ConfigException $configException) {
            self::assertSame('The route handler must not be empty.', $configException->getMessage());
        }
    }

    public function testRejectsAnEmptyPath(): void
    {
        try {
            RouteDsl::define(static function (RouteDsl $routeDsl): void {
                $routeDsl->get('', 'Health@check');
            });
            self::fail('An empty path should have been rejected.');
        } catch (ConfigException $configException) {
            self::assertSame('The route path "" is empty.', $configException->getMessage());
        }
    }

    public function testRejectsAPathWithoutALeadingSlashAtTheRoot(): void
    {
        try {
            RouteDsl::define(static function (RouteDsl $routeDsl): void {
                $routeDsl->get('health', 'Health@check');
            });
            self::fail('A path without a leading slash should have been rejected.');
        } catch (ConfigException $configException) {
            self::assertSame(
                'The route path "health" must start with a slash.',
                $configException->getMessage(),
            );
        }
    }

    public function testRejectsAPathWithoutALeadingSlashInsideAGroupWithTheSameMessage(): void
    {
        try {
            RouteDsl::define(static function (RouteDsl $routeDsl): void {
                $routeDsl->group('/api', static function (RouteDsl $routeDsl): void {
                    $routeDsl->get('health', 'Health@check');
                });
            });
            self::fail('A path without a leading slash should have been rejected.');
        } catch (ConfigException $configException) {
            self::assertSame(
                'The route path "health" must start with a slash.',
                $configException->getMessage(),
            );
        }
    }

    public function testRejectsAGrammarViolationOnTheComposedPath(): void
    {
        try {
            RouteDsl::define(static function (RouteDsl $routeDsl): void {
                $routeDsl->group('/api', static function (RouteDsl $routeDsl): void {
                    $routeDsl->get('/users/', 'Users@index');
                });
            });
            self::fail('A trailing slash should have been rejected by the grammar.');
        } catch (ConfigException $configException) {
            self::assertSame(
                'The route path "/api/users/" must not end with a slash.',
                $configException->getMessage(),
            );
        }
    }

    public function testComposesNestedGroupPrefixes(): void
    {
        $routes = RouteDsl::define(static function (RouteDsl $routeDsl): void {
            $routeDsl->group('/api', static function (RouteDsl $routeDsl): void {
                $routeDsl->group('/v1', static function (RouteDsl $routeDsl): void {
                    $routeDsl->get('/users', 'Users@index');
                });
            });
        });

        self::assertSame('/api/v1/users', $routes[0]['path']);
    }

    public function testAGroupWithTheRootPrefixContributesNothing(): void
    {
        $routes = RouteDsl::define(static function (RouteDsl $routeDsl): void {
            $routeDsl->group('/', static function (RouteDsl $routeDsl): void {
                $routeDsl->get('/health', 'Health@check');
            });
        });

        self::assertSame('/health', $routes[0]['path']);
    }

    public function testTheRootPathInsideAGroupYieldsThePrefixItself(): void
    {
        $routes = RouteDsl::define(static function (RouteDsl $routeDsl): void {
            $routeDsl->group('/api', static function (RouteDsl $routeDsl): void {
                $routeDsl->get('/', 'Api@root');
            });
        });

        self::assertSame('/api', $routes[0]['path']);
    }

    public function testRejectsAGroupPrefixWithoutALeadingSlash(): void
    {
        try {
            RouteDsl::define(static function (RouteDsl $routeDsl): void {
                $routeDsl->group('api', static function (RouteDsl $routeDsl): void {
                });
            });
            self::fail('A group prefix without a leading slash should have been rejected.');
        } catch (ConfigException $configException) {
            self::assertSame('The route prefix "api" must start with a slash.', $configException->getMessage());
        }
    }

    public function testRejectsAGroupPrefixWithATrailingSlash(): void
    {
        try {
            RouteDsl::define(static function (RouteDsl $routeDsl): void {
                $routeDsl->group('/api/', static function (RouteDsl $routeDsl): void {
                });
            });
            self::fail('A group prefix with a trailing slash should have been rejected.');
        } catch (ConfigException $configException) {
            self::assertSame(
                'The route prefix "/api/" must not end with a slash.',
                $configException->getMessage(),
            );
        }
    }

    public function testRejectsAnEmptyScopeMiddlewareIdentifier(): void
    {
        try {
            RouteDsl::define(static function (RouteDsl $routeDsl): void {
                $routeDsl->middleware('Auth', '');
            });
            self::fail('An empty scope middleware identifier should have been rejected.');
        } catch (ConfigException $configException) {
            self::assertSame(
                'The middleware identifier must not be empty.',
                $configException->getMessage(),
            );
        }
    }

    public function testScopeMiddlewareFlowsForwardOnly(): void
    {
        $routes = RouteDsl::define(static function (RouteDsl $routeDsl): void {
            $routeDsl->get('/before', 'Handler@before');
            $routeDsl->middleware('Logging');
            $routeDsl->get('/after', 'Handler@after');
        });

        self::assertArrayNotHasKey('middleware', $routes[0]);
        self::assertSame(['Logging'], $routes[1]['middleware']);
    }

    public function testMiddlewareStacksOuterToInner(): void
    {
        $routes = RouteDsl::define(static function (RouteDsl $routeDsl): void {
            $routeDsl->middleware('Global');

            $routeDsl->middleware('Auth')->group('/user', static function (RouteDsl $routeDsl): void {
                $routeDsl->get('/profile', 'Profile@show')->middleware('Route');
            });
        });

        self::assertSame(['Global', 'Auth', 'Route'], $routes[0]['middleware']);
        self::assertSame('/user/profile', $routes[0]['path']);
    }

    public function testGroupMiddlewareDoesNotLeakPastTheGroup(): void
    {
        $routes = RouteDsl::define(static function (RouteDsl $routeDsl): void {
            $routeDsl->group('/api', static function (RouteDsl $routeDsl): void {
                $routeDsl->middleware('Api');

                $routeDsl->get('/inside', 'Handler@inside');
            });

            $routeDsl->get('/outside', 'Handler@outside');
        });

        self::assertSame(['Api'], $routes[0]['middleware']);
        self::assertArrayNotHasKey('middleware', $routes[1]);
    }

    public function testAGroupThatThrowsRestoresPrefixAndTruncatesInnerMiddleware(): void
    {
        $routeDsl = new RouteDsl();
        $routeDsl->middleware('Global');

        try {
            $routeDsl->group('/api', static function (RouteDsl $routeDsl): never {
                $routeDsl->middleware('Api');
                $routeDsl->get('/inside', 'Handler@inside');

                throw new ConfigException('boom');
            });
            self::fail('The group closure exception should have propagated.');
        } catch (ConfigException $configException) {
            self::assertSame('boom', $configException->getMessage());
        }

        $routeDsl->get('/after', 'Handler@after');

        $routes = $routeDsl->toArray();

        self::assertSame(['Global', 'Api'], $routes[0]['middleware']);
        self::assertSame('/after', $routes[1]['path']);
        self::assertSame(['Global'], $routes[1]['middleware']);
    }

    public function testAChainedDeclarationFreezesEveryOptionalField(): void
    {
        $routes = RouteDsl::define(static function (RouteDsl $routeDsl): void {
            $routeDsl->get('/users/{id}', 'Users@show')
                ->name('users.show')
                ->where(['id' => '[0-9]+'])
                ->middleware('Auth')
                ->meta(['version' => 'v1'])
            ;
        });

        self::assertSame(
            [
                'path' => '/users/{id}',
                'method' => ['GET'],
                'handler' => 'Users@show',
                'name' => 'users.show',
                'middleware' => ['Auth'],
                'constraints' => ['id' => '[0-9]+'],
                'meta' => ['version' => 'v1'],
            ],
            $routes[0],
        );
    }

    public function testToArrayIsIdempotent(): void
    {
        $routeDsl = new RouteDsl();
        $routeDsl->get('/health', 'Health@check');

        self::assertSame($routeDsl->toArray(), $routeDsl->toArray());
    }

    public function testTheSessionStaysUsableAfterToArray(): void
    {
        $routeDsl = new RouteDsl();
        $routeDsl->get('/first', 'Handler@first');

        $first = $routeDsl->toArray();

        $routeDsl->get('/second', 'Handler@second');

        self::assertSame('/first', $first[0]['path']);
        self::assertCount(2, $routeDsl->toArray());
    }

    public function testTheVerbDraftIsTheEntryTheSessionFreezes(): void
    {
        $routeDsl = new RouteDsl();
        $pendingRoute = $routeDsl->get('/health', 'Health@check');
        $pendingRoute->name('health.check');

        self::assertSame('health.check', $routeDsl->toArray()[0]['name']);
    }
}
