<?php

declare(strict_types=1);

namespace Zef\Test\Router;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Stringable;
use Zef\Framework\Config\Config;
use Zef\Framework\Config\ConfigException;
use Zef\Framework\Config\Routing\DispatchOutcome;
use Zef\Framework\Config\Routing\MatchResult;
use Zef\Framework\Config\Routing\RouteDsl;
use Zef\Framework\Config\Routing\RouteHostRendering;
use Zef\Framework\Config\Routing\RouteTreeCache;
use Zef\Framework\Config\Routing\RouteUrlGenerator;
use Zef\Framework\Config\Routing\RoutingRuntime;
use Zef\Framework\Container\Container;
use Zef\Framework\Router\Router;

/**
 * @internal
 */
#[CoversClass(DispatchOutcome::class)]
#[CoversClass(MatchResult::class)]
#[CoversClass(RouteHostRendering::class)]
#[CoversClass(RouteUrlGenerator::class)]
#[CoversClass(Router::class)]
#[CoversClass(RoutingRuntime::class)]
final class RouterTest extends TestCase
{
    public function testDefineCompilesDslAndMatchesParameters(): void
    {
        $router = Router::define(static function (RouteDsl $routeDsl): void {
            $routeDsl->get('/health', 'Health@check')->name('health');
            $routeDsl->get('/users/{id}', 'Users@show')
                ->name('users.show')
                ->where(['id' => '[0-9]+'])
            ;
        });

        $match = $router->match('GET', '/users/42');

        self::assertTrue($match->isFound());
        self::assertSame('Users@show', $match->route()->handler());
        self::assertSame(['id' => '42'], $match->parameters());
    }

    public function testFromConfigUsesTheConfiguredRouteTable(): void
    {
        $router = Router::fromConfig(new Config([
            'routes' => [
                [
                    'path' => '/health',
                    'method' => ['GET'],
                    'handler' => 'Health@check',
                    'name' => 'health',
                ],
            ],
        ]));

        self::assertTrue($router->match('GET', '/health')->isFound());
        self::assertSame('Health@check', $router->route('health')->handler());
    }

    public function testFromRoutesSupportsNamedReverseRouting(): void
    {
        $router = Router::fromRoutes([
            [
                'path' => '/users/{id}',
                'method' => ['GET'],
                'handler' => 'Users@show',
                'name' => 'users.show',
                'constraints' => ['id' => '[0-9]+'],
            ],
        ]);

        self::assertSame('/users/42', $router->url('users.show', ['id' => 42]));
        self::assertSame($router->tree(), $router->tree());
    }

    public function testFacadePassesTheRequestHostIntoMatching(): void
    {
        $router = Router::fromRoutes([
            [
                'path' => '/dashboard',
                'host' => 'admin.example.com',
                'method' => ['GET'],
                'handler' => 'Admin@dashboard',
            ],
        ]);

        self::assertTrue($router->match('GET', '/dashboard', 'ADMIN.EXAMPLE.COM')->isFound());
        self::assertFalse($router->match('GET', '/dashboard', 'api.example.com')->isFound());
    }

    public function testFacadeMatchesWildcardHostPatterns(): void
    {
        $router = Router::fromRoutes([
            [
                'path' => '/dashboard',
                'host' => '*.example.com',
                'method' => ['GET'],
                'handler' => 'Subdomain@dashboard',
            ],
            [
                'path' => '/dashboard',
                'host' => 'api.example.com',
                'method' => ['GET'],
                'handler' => 'Api@dashboard',
            ],
        ]);

        self::assertSame(
            'Api@dashboard',
            $router->match('GET', '/dashboard', 'api.example.com')->route()->handler(),
        );
        self::assertSame(
            'Subdomain@dashboard',
            $router->match('GET', '/dashboard', 'shop.example.com')->route()->handler(),
        );
        self::assertFalse($router->match('GET', '/dashboard', 'a.b.example.com')->isFound());
        self::assertFalse($router->match('GET', '/dashboard', '*.example.com')->isFound());
    }

    public function testAbsoluteUrlRefusesAWildcardHostPattern(): void
    {
        $router = Router::fromRoutes([
            [
                'path' => '/dashboard',
                'host' => '*.example.com',
                'method' => ['GET'],
                'handler' => 'Subdomain@dashboard',
                'name' => 'subdomain.dashboard',
            ],
        ]);

        try {
            $router->absoluteUrl('subdomain.dashboard', []);
            self::fail('A wildcard host pattern must not render into an absolute URL.');
        } catch (ConfigException $configException) {
            self::assertSame(
                'The host pattern "*.example.com" carries an anonymous "*" wildcard and cannot be rendered.',
                $configException->getMessage(),
            );
        }
    }

    public function testAbsoluteUrlRequiresEveryHostParameter(): void
    {
        $router = Router::fromRoutes([
            [
                'path' => '/dashboard',
                'host' => '{tenant}.example.com',
                'method' => ['GET'],
                'handler' => 'Tenant@dashboard',
                'name' => 'tenant.dashboard',
            ],
        ]);

        try {
            $router->absoluteUrl('tenant.dashboard', []);
            self::fail('A missing host parameter must fail closed.');
        } catch (ConfigException $configException) {
            self::assertSame(
                'The host parameter "tenant" is required.',
                $configException->getMessage(),
            );
        }
    }

    public function testAbsoluteUrlGenerationRendersTenantHostParameters(): void
    {
        $router = Router::fromRoutes([
            [
                'path' => '/users/{id}',
                'host' => '{tenant}.example.com',
                'method' => ['GET'],
                'handler' => 'TenantUsers@show',
                'name' => 'tenant.users.show',
            ],
        ]);

        self::assertSame(
            'https://acme.example.com/users/42',
            $router->absoluteUrl('tenant.users.show', ['tenant' => 'Acme', 'id' => 42]),
        );
        self::assertSame(
            '/users/42',
            $router->url('tenant.users.show', ['id' => 42]),
        );
    }

    public function testAbsoluteUrlRejectsUnsafeHostParameterAndScheme(): void
    {
        $router = Router::fromRoutes([
            [
                'path' => '/dashboard',
                'host' => '{tenant}.example.com',
                'method' => ['GET'],
                'handler' => 'Tenant@dashboard',
                'name' => 'tenant.dashboard',
            ],
        ]);

        try {
            $router->absoluteUrl('tenant.dashboard', ['tenant' => 'acme.evil']);
            self::fail('A host parameter must not inject another hostname.');
        } catch (ConfigException $configException) {
            self::assertStringContainsString('valid DNS label', $configException->getMessage());
        }

        $this->expectException(ConfigException::class);
        $router->absoluteUrl('tenant.dashboard', ['tenant' => 'acme'], 'javascript');
    }

    public function testAbsoluteUrlFoldsAnUpperCasedSchemeToLowerCase(): void
    {
        $router = Router::fromRoutes([
            [
                'path' => '/users/{id}',
                'host' => '{tenant}.example.com',
                'method' => ['GET'],
                'handler' => 'TenantUsers@show',
                'name' => 'tenant.users.show',
            ],
        ]);

        self::assertSame(
            'https://acme.example.com/users/42',
            $router->absoluteUrl('tenant.users.show', ['tenant' => 'acme', 'id' => 42], 'HTTPS'),
        );
    }

    public function testAbsoluteUrlRendersAStringableHostParameter(): void
    {
        $router = Router::fromRoutes([
            [
                'path' => '/users/{id}',
                'host' => '{tenant}.example.com',
                'method' => ['GET'],
                'handler' => 'TenantUsers@show',
                'name' => 'tenant.users.show',
            ],
        ]);

        $tenant = new class implements Stringable {
            #[Override]
            public function __toString(): string
            {
                return 'acme';
            }
        };

        self::assertSame(
            'https://acme.example.com/users/42',
            $router->absoluteUrl('tenant.users.show', ['tenant' => $tenant, 'id' => 42]),
        );
    }

    public function testAbsoluteUrlRendersAnIntegerHostParameter(): void
    {
        $router = Router::fromRoutes([
            [
                'path' => '/users/{id}',
                'host' => '{tenant}.example.com',
                'method' => ['GET'],
                'handler' => 'TenantUsers@show',
                'name' => 'tenant.users.show',
            ],
        ]);

        self::assertSame(
            'https://42.example.com/users/7',
            $router->absoluteUrl('tenant.users.show', ['tenant' => 42, 'id' => 7]),
        );
    }

    public function testAbsoluteUrlRefusesAnArrayHostParameter(): void
    {
        $router = Router::fromRoutes([
            [
                'path' => '/users/{id}',
                'host' => '{tenant}.example.com',
                'method' => ['GET'],
                'handler' => 'TenantUsers@show',
                'name' => 'tenant.users.show',
            ],
        ]);

        $this->expectException(ConfigException::class);
        $this->expectExceptionMessageIsOrContains('must be a DNS label value');

        $router->absoluteUrl('tenant.users.show', ['tenant' => ['nested'], 'id' => 42]);
    }

    public function testUrlGenerationExcludesHostParametersFromThePath(): void
    {
        $router = Router::fromRoutes([
            [
                'path' => '/users/{id}',
                'host' => '{tenant}.example.com',
                'method' => ['GET'],
                'handler' => 'TenantUsers@show',
                'name' => 'tenant.users.show',
            ],
        ]);

        self::assertSame(
            '/users/42',
            $router->url('tenant.users.show', ['tenant' => 'acme', 'id' => 42]),
        );
    }

    public function testDispatcherReturnsNotAcceptableWithoutExecutingHandler(): void
    {
        $container = new Container();
        $container->set(
            'Profile@show',
            static fn (): callable => static fn (array $parameters): string => 'should-not-run',
        );

        $router = Router::fromRoutes([
            [
                'path' => '/profile',
                'method' => ['GET'],
                'handler' => 'Profile@show',
                'produces' => ['application/json'],
            ],
        ]);

        $outcome = $router->dispatcher($container)->dispatch('GET', '/profile', null, 'text/html');

        self::assertTrue($outcome->isNotAcceptable());
        self::assertSame(['application/json'], $outcome->acceptableMediaTypes());
    }

    public function testDispatcherUsesTheFacadeRuntimeAndContainerHandler(): void
    {
        $container = new Container();
        $container->set(
            'Health@check',
            static fn (): callable => static fn (array $parameters): string => 'healthy',
        );

        $router = $this->routerWithHealthRoute();
        $dispatcher = $router->dispatcher($container);
        $outcome = $dispatcher->dispatch('GET', '/health');

        self::assertTrue($outcome->isExecuted());
        self::assertSame('healthy', $outcome->result());
        self::assertSame('Health@check', $outcome->route()->handler());
    }

    public function testMatchPreservesMethodNotAllowedAndNotFoundOutcomes(): void
    {
        $router = $this->routerWithHealthRoute();

        $methodMismatch = $router->match('POST', '/health');
        self::assertTrue($methodMismatch->isMethodNotAllowed());
        self::assertSame(['GET'], $methodMismatch->allowedMethods());

        $missing = $router->match('GET', '/missing');
        self::assertFalse($missing->isFound());
        self::assertFalse($missing->isMethodNotAllowed());
    }

    public function testInvalidRouteTableFailsClosedWhenCompiled(): void
    {
        $router = Router::fromRoutes([
            ['path' => 'health', 'method' => ['GET'], 'handler' => 'Health@check'],
        ]);

        $this->expectException(ConfigException::class);
        $router->match('GET', '/health');
    }

    public function testUnknownRouteNamesFailClosed(): void
    {
        $router = $this->routerWithHealthRoute();

        try {
            $router->route('missing');
            self::fail('An unknown route name must be rejected.');
        } catch (ConfigException $configException) {
            self::assertSame('The route name "missing" is not registered.', $configException->getMessage());
        }

        $this->expectException(ConfigException::class);
        $router->url('missing');
    }

    public function testReverseRoutingRejectsValuesThatViolateTheRouteConstraint(): void
    {
        $router = Router::fromRoutes([
            [
                'path' => '/users/{id}',
                'method' => ['GET'],
                'handler' => 'Users@show',
                'name' => 'users.show',
                'constraints' => ['id' => '[0-9]+'],
            ],
        ]);

        $this->expectException(ConfigException::class);
        $router->url('users.show', ['id' => 'not-a-number']);
    }

    public function testFromCacheAnswersFromTheLoadedTree(): void
    {
        $routes = [
            [
                'path' => '/users/{id}',
                'method' => ['GET'],
                'handler' => 'Users@show',
                'name' => 'users.show',
                'constraints' => ['id' => '[0-9]+'],
            ],
        ];

        $file = sys_get_temp_dir() . '/zef-router-cache-' . uniqid('', false) . '.php';
        $routeTreeCache = new RouteTreeCache($file);
        $routeTreeCache->dump($routes);

        $router = Router::fromCache($routeTreeCache);
        $reference = Router::fromRoutes($routes);

        $match = $router->match('GET', '/users/42');

        self::assertTrue($match->isFound());
        self::assertSame('Users@show', $match->route()->handler());
        self::assertSame(['id' => '42'], $match->parameters());
        self::assertSame('/users/42', $router->url('users.show', ['id' => 42]));
        self::assertFalse($router->match('GET', '/users/abc')->isFound());
        self::assertSame($reference->match('GET', '/users/42')->route()->handler(), $match->route()->handler());
        self::assertSame($router->tree(), $router->tree());

        unlink($file);
    }

    public function testFromCacheFailsClosedOnAMissingArtifact(): void
    {
        $missing = sys_get_temp_dir() . '/zef-router-cache-missing-' . uniqid('', false) . '.php';
        $routeTreeCache = new RouteTreeCache($missing);

        try {
            Router::fromCache($routeTreeCache);
            self::fail('A router over a missing cache artifact must raise a ConfigException.');
        } catch (ConfigException $configException) {
            self::assertStringContainsString('does not exist.', $configException->getMessage());
        }
    }

    private function routerWithHealthRoute(): Router
    {
        return Router::fromRoutes([
            [
                'path' => '/health',
                'method' => ['GET'],
                'handler' => 'Health@check',
                'name' => 'health',
            ],
        ]);
    }
}
