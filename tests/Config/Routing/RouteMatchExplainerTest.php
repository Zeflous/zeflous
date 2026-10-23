<?php

declare(strict_types=1);

namespace Zef\Test\Config\Routing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Config\Routing\MatchResult;
use Zef\Framework\Config\Routing\RadixTree;
use Zef\Framework\Config\Routing\RouteMatchExplanation;
use Zef\Framework\Config\Routing\RouteMatchExplainer;
use Zef\Framework\Config\Routing\RouteMatchNeighborhood;

/**
 * The route match explanation, on its own terms: the staged
 * attribution of every outcome the matcher can answer (which fold
 * emptied the set, and what each stage kept), the path-stage
 * neighborhood (the deepest static prefix the request tracked, the
 * capped candidate patterns under it, host claims included), the
 * bounded summary rendering destined for a development header, and
 * the equivalence contract -- the explanation's outcome is the
 * match's own, because the explainer runs the matcher's own folds.
 *
 * @internal
 */
#[CoversClass(RouteMatchExplanation::class)]
#[CoversClass(RouteMatchExplainer::class)]
#[CoversClass(RouteMatchNeighborhood::class)]
#[CoversClass(RadixTree::class)]
final class RouteMatchExplainerTest extends TestCase
{
    public function testExplainsAFoundMatchWithTheStageCountsAndNoFailedStage(): void
    {
        $radixTree = self::table();

        $explanation = $radixTree->explain('GET', '/users/42');

        self::assertTrue($explanation->isFound());
        self::assertSame(RouteMatchExplanation::OUTCOME_FOUND, $explanation->outcome());
        self::assertNull($explanation->failedStage());
        self::assertSame(1, $explanation->pathSurvivors());
        self::assertSame(1, $explanation->hostSurvivors());
        self::assertSame(1, $explanation->versionSurvivors());
        self::assertSame('', $explanation->sharedPrefix());
        self::assertSame([], $explanation->neighborPatterns());
        self::assertSame([], $explanation->allowedMethods());
        self::assertSame([], $explanation->producibleMediaTypes());
        self::assertSame('stage=none survivors=path:1,host:1,version:1', $explanation->summary());
    }

    public function testExplainsAPathStageMissWithTheNeighborhood(): void
    {
        $explanation = self::table()->explain('GET', '/userss');

        self::assertSame(RouteMatchExplanation::OUTCOME_NOT_FOUND, $explanation->outcome());
        self::assertSame(RouteMatchExplanation::STAGE_PATH, $explanation->failedStage());
        self::assertSame(0, $explanation->pathSurvivors());
        self::assertSame(0, $explanation->hostSurvivors());
        self::assertSame(0, $explanation->versionSurvivors());
        self::assertSame('/users', $explanation->sharedPrefix());
        self::assertSame(['/users', '/users/{id}'], $explanation->neighborPatterns());
        self::assertSame(
            'stage=path survivors=path:0,host:0,version:0 prefix=/users candidates=/users,/users/{id}',
            $explanation->summary(),
        );
    }

    public function testTheNeighborhoodCountsAPartiallyContinuedRun(): void
    {
        $radixTree = RadixTree::compile([
            ['path' => '/users/{id}', 'method' => ['GET'], 'handler' => 'Users@show'],
        ]);

        $explanation = $radixTree->explain('GET', '/users');

        self::assertSame(RouteMatchExplanation::STAGE_PATH, $explanation->failedStage());
        self::assertSame('/users', $explanation->sharedPrefix());
        self::assertSame(['/users/{id}'], $explanation->neighborPatterns());
    }

    public function testTheNeighborhoodIncludesParameterEdgePatternsAndTracksTheRun(): void
    {
        $explanation = self::table()->explain('GET', '/users/abc');

        self::assertSame(RouteMatchExplanation::STAGE_PATH, $explanation->failedStage());
        self::assertSame('/users/', $explanation->sharedPrefix());
        self::assertSame(['/users/{id}'], $explanation->neighborPatterns());
    }

    public function testTheNeighborhoodCarriesHostClaims(): void
    {
        $radixTree = RadixTree::compile([
            ['path' => '/a', 'method' => ['GET'], 'handler' => 'A@x'],
            ['path' => '/b', 'method' => ['GET'], 'handler' => 'B@x', 'host' => 'api.example.com'],
        ]);

        $explanation = $radixTree->explain('GET', '/c');

        self::assertSame(RouteMatchExplanation::STAGE_PATH, $explanation->failedStage());
        self::assertSame('/', $explanation->sharedPrefix());
        self::assertSame(['/a', '/b @api.example.com'], $explanation->neighborPatterns());
    }

    public function testTheNeighborhoodIsCappedAtEightPatterns(): void
    {
        $routes = [];
        foreach (range(1, 12) as $index) {
            $routes[] = ['path' => '/c/p' . $index, 'method' => ['GET'], 'handler' => 'C@x' . $index];
        }

        $radixTree = RadixTree::compile($routes);
        $explanation = $radixTree->explain('GET', '/c/zzz');

        self::assertSame(RouteMatchExplanation::STAGE_PATH, $explanation->failedStage());
        self::assertCount(8, $explanation->neighborPatterns());
        self::assertSame('/c/p1', $explanation->neighborPatterns()[0]);
    }

    public function testExplainsAHostStageMissWithTheSurvivorCounts(): void
    {
        $radixTree = RadixTree::compile([
            ['path' => '/a', 'method' => ['GET'], 'handler' => 'A@x', 'host' => 'api.example.com'],
        ]);

        $explanation = $radixTree->explain('GET', '/a', 'other.example.com');

        self::assertSame(RouteMatchExplanation::OUTCOME_NOT_FOUND, $explanation->outcome());
        self::assertSame(RouteMatchExplanation::STAGE_HOST, $explanation->failedStage());
        self::assertSame(1, $explanation->pathSurvivors());
        self::assertSame(0, $explanation->hostSurvivors());
        self::assertSame(0, $explanation->versionSurvivors());
        self::assertSame('', $explanation->sharedPrefix());
        self::assertSame([], $explanation->neighborPatterns());
        self::assertSame('stage=host survivors=path:1,host:0,version:0', $explanation->summary());
    }

    public function testExplainsAVersionStageMissWithTheSurvivorCounts(): void
    {
        $radixTree = RadixTree::compile([
            ['path' => '/b', 'method' => ['GET'], 'handler' => 'B@x', 'version' => 'v2'],
        ]);

        $explanation = $radixTree->explain('GET', '/b', null, null, 'v3');

        self::assertSame(RouteMatchExplanation::OUTCOME_NOT_FOUND, $explanation->outcome());
        self::assertSame(RouteMatchExplanation::STAGE_VERSION, $explanation->failedStage());
        self::assertSame(1, $explanation->pathSurvivors());
        self::assertSame(1, $explanation->hostSurvivors());
        self::assertSame(0, $explanation->versionSurvivors());
        self::assertSame('stage=version survivors=path:1,host:1,version:0', $explanation->summary());
    }

    public function testExplainsAMethodRefusalWithTheAllowedMethods(): void
    {
        $radixTree = RadixTree::compile([
            ['path' => '/posts', 'method' => ['POST', 'PUT'], 'handler' => 'Posts@store'],
        ]);

        $explanation = $radixTree->explain('GET', '/posts');

        self::assertSame(RouteMatchExplanation::OUTCOME_METHOD_NOT_ALLOWED, $explanation->outcome());
        self::assertSame(RouteMatchExplanation::STAGE_METHOD, $explanation->failedStage());
        self::assertSame(['POST', 'PUT'], $explanation->allowedMethods());
        self::assertSame('stage=method survivors=path:1,host:1,version:1 allowed=POST,PUT', $explanation->summary());
    }

    public function testExplainsAnAcceptRefusalWithTheProducibleMediaTypes(): void
    {
        $radixTree = RadixTree::compile([
            [
                'path' => '/j',
                'method' => ['GET'],
                'handler' => 'J@x',
                'produces' => ['application/json', 'text/plain'],
            ],
        ]);

        $explanation = $radixTree->explain('GET', '/j', null, 'text/html');

        self::assertSame(RouteMatchExplanation::OUTCOME_NOT_ACCEPTABLE, $explanation->outcome());
        self::assertSame(RouteMatchExplanation::STAGE_ACCEPT, $explanation->failedStage());
        self::assertSame(['application/json', 'text/plain'], $explanation->producibleMediaTypes());
        self::assertSame(
            'stage=accept survivors=path:1,host:1,version:1 producible=application/json,text/plain',
            $explanation->summary(),
        );
    }

    /**
     * The equivalence contract: the explanation's outcome is the
     * match's own, because the explainer runs the matcher's own
     * folds over the same tree.
     *
     * @return iterable<string, array{string, string, null|string, null|string, null|string}>
     */
    public static function provideMatchEquivalenceCases(): iterable
    {
        return [
            'a found static match' => ['GET', '/users', null, null, null],
            'a found parameterized match' => ['GET', '/users/42', null, null, null],
            'a constraint-refused match' => ['GET', '/users/abc', null, null, null],
            'a path miss' => ['GET', '/nowhere', null, null, null],
            'a host miss' => ['GET', '/a', 'other.example.com', null, null],
            'a host-claimed match' => ['GET', '/a', 'api.example.com', null, null],
            'a version miss' => ['GET', '/b', null, null, 'v3'],
            'a version-selected match' => ['GET', '/b', null, null, 'v2'],
            'a method refusal' => ['GET', '/posts', null, null, null],
            'an answered method' => ['POST', '/posts', null, null, null],
            'an accept refusal' => ['GET', '/j', null, 'text/html', null],
            'an acceptable negotiation' => ['GET', '/j', null, 'application/json', null],
        ];
    }

    #[DataProvider('provideMatchEquivalenceCases')]
    public function testTheOutcomeMirrorsTheMatchResult(
        string $method,
        string $path,
        ?string $host,
        ?string $accept,
        ?string $version,
    ): void {
        $radixTree = self::table();
        $matchResult = $radixTree->match($method, $path, $host, $accept, $version);
        $explanation = $radixTree->explain($method, $path, $host, $accept, $version);

        self::assertSame(
            self::outcomeOf($matchResult),
            $explanation->outcome(),
            'the explanation runs the matcher\'s own folds, so its outcome is the match\'s own',
        );
    }

    public function testTheSummaryOmitsTheTokensACarriedOutcomeDoesNotHold(): void
    {
        $emptyTable = RadixTree::compile([]);

        $explanation = $emptyTable->explain('GET', '/anything');

        self::assertSame(RouteMatchExplanation::STAGE_PATH, $explanation->failedStage());
        self::assertSame([], $explanation->neighborPatterns());
        self::assertSame('stage=path survivors=path:0,host:0,version:0', $explanation->summary());
    }

    public function testTheSummaryStripsControlBytesFromItsFreeFormRenderings(): void
    {
        $explanation = RouteMatchExplanation::acceptRefusal(["app\x00lication/json\x1F"], 1, 1, 1);

        self::assertSame(
            'stage=accept survivors=path:1,host:1,version:1 producible=application/json',
            $explanation->summary(),
        );
    }

    public function testAControlByteInTheRequestPathEndsTheTrackedPrefixWhereTheTableDiverges(): void
    {
        $explanation = self::table()->explain("GET", "/us\x00ers\x1Fs");

        self::assertSame('/us', $explanation->sharedPrefix());
        self::assertSame(
            'stage=path survivors=path:0,host:0,version:0 prefix=/us candidates=/users,/users/{id}',
            $explanation->summary(),
        );
    }

    public function testTheSummaryClampsTheTrackedPrefixToItsBudget(): void
    {
        $longRun = '/' . str_repeat('a', 200);
        $radixTree = RadixTree::compile([
            ['path' => $longRun . 'x', 'method' => ['GET'], 'handler' => 'Long@x'],
        ]);

        $explanation = $radixTree->explain('GET', $longRun . 'z');

        self::assertSame($longRun, $explanation->sharedPrefix());
        self::assertSame(
            'stage=path survivors=path:0,host:0,version:0 prefix=/' . str_repeat('a', 127) . '...'
            . ' candidates=/' . str_repeat('a', 127) . '...',
            $explanation->summary(),
        );
    }

    public function testTheSummaryClampsLongCandidates(): void
    {
        $longSegment = str_repeat('b', 200);
        $radixTree = RadixTree::compile([
            ['path' => '/' . $longSegment . 'x', 'method' => ['GET'], 'handler' => 'Long@x'],
        ]);

        $explanation = $radixTree->explain('GET', '/' . $longSegment . 'z');

        self::assertSame(
            'stage=path survivors=path:0,host:0,version:0 prefix=/' . str_repeat('b', 127) . '...'
            . ' candidates=/' . str_repeat('b', 127) . '...',
            $explanation->summary(),
        );
    }

    public function testAMultiBytePathTracksItsNeighborhoodByCharacters(): void
    {
        $radixTree = RadixTree::compile([
            [
                'path' => '/gebruiker/{id}',
                'method' => ['GET'],
                'handler' => 'Gebruiker@show',
                'constraints' => ['id' => '\d+'],
            ],
        ]);

        $explanation = $radixTree->explain('GET', '/gebruiker/ä');

        self::assertSame(RouteMatchExplanation::STAGE_PATH, $explanation->failedStage());
        self::assertSame('/gebruiker/', $explanation->sharedPrefix());
        self::assertSame(['/gebruiker/{id}'], $explanation->neighborPatterns());
    }

    /**
     * The shared fixture: one table exercising every stage the
     * pipeline owns.
     *
     * @return RadixTree
     */
    private static function table(): RadixTree
    {
        return RadixTree::compile([
            ['path' => '/users', 'method' => ['GET'], 'handler' => 'Users@index'],
            [
                'path' => '/users/{id}',
                'method' => ['GET'],
                'handler' => 'Users@show',
                'constraints' => ['id' => '\d+'],
            ],
            ['path' => '/posts', 'method' => ['POST'], 'handler' => 'Posts@store'],
            ['path' => '/a', 'method' => ['GET'], 'handler' => 'A@x', 'host' => 'api.example.com'],
            ['path' => '/b', 'method' => ['GET'], 'handler' => 'B@x', 'version' => 'v2'],
            [
                'path' => '/j',
                'method' => ['GET'],
                'handler' => 'J@x',
                'produces' => ['application/json'],
            ],
        ]);
    }

    /**
     * The outcome name a match result answers, in the explanation's
     * own vocabulary.
     */
    private static function outcomeOf(MatchResult $matchResult): string
    {
        if ($matchResult->isFound()) {
            return RouteMatchExplanation::OUTCOME_FOUND;
        }

        if ($matchResult->isMethodNotAllowed()) {
            return RouteMatchExplanation::OUTCOME_METHOD_NOT_ALLOWED;
        }

        if ($matchResult->isNotAcceptable()) {
            return RouteMatchExplanation::OUTCOME_NOT_ACCEPTABLE;
        }

        return RouteMatchExplanation::OUTCOME_NOT_FOUND;
    }
}
