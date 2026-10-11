<?php

declare(strict_types=1);

namespace Zef\Test\Config\Routing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Config\Routing\CompiledRoute;
use Zef\Framework\Config\Routing\MatchResult;
use Zef\Framework\Config\Routing\PendingRoute;
use Zef\Framework\Config\Routing\RadixTree;
use Zef\Framework\Config\Routing\RouteDsl;
use Zef\Framework\Config\Routing\RouteDslConstraints;
use Zef\Framework\Config\Routing\RouteDslFields;
use Zef\Framework\Config\Routing\RouteDslMethods;
use Zef\Framework\Config\Routing\RouteDslMiddleware;
use Zef\Framework\Config\Routing\RouteDslScope;
use Zef\Framework\Config\Routing\RouteDslSnapshot;
use Zef\Framework\Config\Routing\RouteParameterRenderer;
use Zef\Framework\Config\Routing\RouteParameterSet;
use Zef\Framework\Config\Routing\RouteParameterString;
use Zef\Framework\Config\Routing\RoutePath;
use Zef\Framework\Config\Routing\RoutePrefix;
use Zef\Framework\Config\Routing\RouteUrlGenerator;
use Zef\Framework\Config\Routing\RouteUrlSegments;
use Zef\Framework\Config\Schema\ConfigSchema;
use Zef\Framework\Config\Schema\RouteSchema;

/**
 * The Triad closure: the DSL's output must be exactly what the
 * configuration system validates and the radix tree engine consumes --
 * declared fluently, asserted against the Standard Route Schema, compiled
 * into the tree, matched with parameters, and reverse-routed back.
 *
 * @internal
 */
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
#[CoversClass(RouteSchema::class)]
#[CoversClass(ConfigSchema::class)]
#[CoversClass(RadixTree::class)]
#[CoversClass(CompiledRoute::class)]
#[CoversClass(MatchResult::class)]
#[CoversClass(RouteUrlGenerator::class)]
#[CoversClass(RouteUrlSegments::class)]
#[CoversClass(RouteParameterRenderer::class)]
#[CoversClass(RouteParameterString::class)]
#[CoversClass(RouteParameterSet::class)]
final class RouteDslIntegrationTest extends TestCase
{
    public function testTheDeclarationSatisfiesTheStandardRouteSchema(): void
    {
        new RouteSchema()->assertList($this->declaredRoutes());

        self::addToAssertionCount(1);
    }

    public function testTheEngineCompilesAndMatchesTheDeclaration(): void
    {
        $radixTree = RadixTree::compile($this->declaredRoutes());

        $matchResult = $radixTree->match('GET', '/api/v1/user/history/2024');

        self::assertTrue($matchResult->isFound());
        self::assertSame('History@list', $matchResult->route()->handler());
        self::assertSame(['year' => '2024'], $matchResult->parameters());
        self::assertSame(['Logging', 'Auth'], $matchResult->route()->middleware());
    }

    public function testTheEngineAnswersMethodNotAllowedFromTheDeclaration(): void
    {
        $radixTree = RadixTree::compile($this->declaredRoutes());

        $matchResult = $radixTree->match('POST', '/api/v1/user/history/2024');

        self::assertTrue($matchResult->isMethodNotAllowed());
        self::assertSame(['GET'], $matchResult->allowedMethods());
    }

    public function testTheEngineAnswersNotFoundForAnUndeclaredPath(): void
    {
        $radixTree = RadixTree::compile($this->declaredRoutes());

        $matchResult = $radixTree->match('GET', '/api/v1/missing');

        self::assertFalse($matchResult->isFound());
    }

    public function testTheReverseRoutingIndexCarriesTheDeclaredNames(): void
    {
        $radixTree = RadixTree::compile($this->declaredRoutes());

        self::assertSame('/api/v1/login', $radixTree->route('auth.login')->path()->pattern());
        self::assertSame(
            '/api/v1/user/history/{year}',
            $radixTree->route('user.history')->path()->pattern(),
        );
    }

    public function testAGeneratedUrlRoundTripsBackThroughTheDeclaration(): void
    {
        $radixTree = RadixTree::compile($this->declaredRoutes());
        $url = new RouteUrlGenerator($radixTree)->generate('user.history', ['year' => '2024']);

        $matchResult = $radixTree->match('GET', $url);

        self::assertTrue($matchResult->isFound());
        self::assertSame(
            $radixTree->route('user.history')->path()->pattern(),
            $matchResult->route()->path()->pattern(),
        );
        self::assertSame(['year' => '2024'], $matchResult->parameters());
    }

    public function testTheDeclaredConstraintReachesTheEngine(): void
    {
        $radixTree = RadixTree::compile($this->declaredRoutes());

        $matchResult = $radixTree->match('GET', '/api/v1/user/history/roman');

        self::assertFalse($matchResult->isFound());
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function declaredRoutes(): array
    {
        return RouteDsl::define(static function (RouteDsl $routeDsl): void {
            $routeDsl->middleware('Logging');

            $routeDsl->group('/api/v1', static function (RouteDsl $routeDsl): void {
                $routeDsl->get('/login', 'Auth@login')->name('auth.login');

                $routeDsl->middleware('Auth')->group('/user', static function (RouteDsl $routeDsl): void {
                    $routeDsl->get('/history/{year}', 'History@list')
                        ->where(['year' => '[0-9]{4}'])
                        ->name('user.history')
                    ;
                });
            });

            $routeDsl->get('/health', 'Health@check')->name('health.check');
        });
    }
}
