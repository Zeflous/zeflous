<?php

declare(strict_types=1);

namespace Zef\Test\Config\Routing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Config\Routing\CompiledRoute;
use Zef\Framework\Config\Routing\MatchResult;
use Zef\Framework\Config\Routing\RadixTree;
use Zef\Framework\Config\Routing\RouteAllowedMethods;
use Zef\Framework\Config\Routing\RouteHostShadowedMethods;
use Zef\Framework\Config\Routing\RouteHostShadowedRoute;
use Zef\Framework\Config\Routing\RouteMatchDescent;
use Zef\Framework\Config\Routing\RouteMatchExplainer;
use Zef\Framework\Config\Routing\RouteMatchExplanation;
use Zef\Framework\Config\Routing\RouteMatchNeighborhood;
use Zef\Framework\Config\Routing\RouteMatchOutcome;
use Zef\Framework\Config\Routing\RouteMatchStages;
use Zef\Framework\Config\Routing\RouteMatchSummary;
use Zef\Framework\Config\Routing\RouteSubtreePatterns;
use Zef\Framework\Config\Routing\RouteVersionMiss;

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
#[CoversClass(RouteMatchOutcome::class)]
#[CoversClass(RouteMatchStages::class)]
#[CoversClass(RouteVersionMiss::class)]
#[CoversClass(RouteAllowedMethods::class)]
#[CoversClass(RouteHostShadowedMethods::class)]
#[CoversClass(RouteHostShadowedRoute::class)]
#[CoversClass(RouteMatchDescent::class)]
#[CoversClass(RouteMatchNeighborhood::class)]
#[CoversClass(RouteMatchSummary::class)]
#[CoversClass(RouteSubtreePatterns::class)]
#[CoversClass(RadixTree::class)]
final class RouteMatchExplainerTest extends TestCase
{
    public function testExplainsAFoundMatchWithTheStageCountsAndNoFailedStage(): void
    {
        $radixTree = $this->table();

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
        $explanation = $this->table()->explain('GET', '/userss');

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
        $explanation = $this->table()->explain('GET', '/users/abc');

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

    /**
     * The #794 arm: a version-stage miss whose method judgement
     * answered the 405 -- the stage stays the version's own (the
     * narrowing that emptied is what failed) while the outcome and
     * the Allow payload carry the method verdict the miss fold
     * answered with, so the diagnostic twin keeps its equivalence with
     * the match on the new arm.
     */
    public function testExplainsAVersionMissWhoseMethodJudgementAnsweredThe405(): void
    {
        $radixTree = RadixTree::compile([
            ['path' => '/api', 'version' => 'v1', 'method' => ['GET'], 'handler' => 'V1@show'],
            ['path' => '/api', 'version' => 'v2', 'method' => ['GET'], 'handler' => 'V2@show'],
        ]);

        $explanation = $radixTree->explain('POST', '/api', null, null, 'v9');

        self::assertSame(RouteMatchExplanation::OUTCOME_METHOD_NOT_ALLOWED, $explanation->outcome());
        self::assertSame(RouteMatchExplanation::STAGE_VERSION, $explanation->failedStage());
        self::assertSame(['GET'], $explanation->allowedMethods());
        self::assertSame(2, $explanation->pathSurvivors());
        self::assertSame(2, $explanation->hostSurvivors());
        self::assertSame(0, $explanation->versionSurvivors());
        self::assertSame(
            'stage=version survivors=path:2,host:2,version:0 allowed=GET',
            $explanation->summary(),
        );
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

    #[DataProvider('provideTheOutcomeMirrorsTheMatchResultCases')]
    public function testTheOutcomeMirrorsTheMatchResult(
        string $method,
        string $path,
        ?string $host,
        ?string $accept,
        ?string $version,
    ): void {
        $radixTree = $this->table();
        $matchResult = $radixTree->match($method, $path, $host, $accept, $version);
        $explanation = $radixTree->explain($method, $path, $host, $accept, $version);

        self::assertSame(
            $this->outcomeOf($matchResult),
            $explanation->outcome(),
            "the explanation runs the matcher's own folds, so its outcome is the match's own",
        );
    }

    /**
     * The equivalence contract: the explanation's outcome is the
     * match's own, because the explainer runs the matcher's own
     * folds over the same tree.
     */
    public static function provideTheOutcomeMirrorsTheMatchResultCases(): iterable
    {
        yield 'a found static match' => ['GET', '/users', null, null, null];

        yield 'a found parameterized match' => ['GET', '/users/42', null, null, null];

        yield 'a constraint-refused match' => ['GET', '/users/abc', null, null, null];

        yield 'a path miss' => ['GET', '/nowhere', null, null, null];

        yield 'a host miss' => ['GET', '/a', 'other.example.com', null, null];

        yield 'a host-claimed match' => ['GET', '/a', 'api.example.com', null, null];

        yield 'a version miss' => ['GET', '/b', null, null, 'v3'];

        yield 'a version miss with a wrong method' => ['POST', '/b', null, null, 'v9'];

        yield 'a version-selected match' => ['GET', '/b', null, null, 'v2'];

        yield 'a method refusal' => ['GET', '/posts', null, null, null];

        yield 'an answered method' => ['POST', '/posts', null, null, null];

        yield 'an accept refusal' => ['GET', '/j', null, 'text/html', null];

        yield 'an acceptable negotiation' => ['GET', '/j', null, 'application/json', null];
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
        $explanation = $this->table()->explain('GET', "/us\x00ers\x1Fs");

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

    public function testAMultiByteRunIsTrackedAndCountedPastItsOwnCharacters(): void
    {
        $radixTree = RadixTree::compile([
            ['path' => '/bé', 'method' => ['GET'], 'handler' => 'Be@one'],
            ['path' => '/bétz', 'method' => ['GET'], 'handler' => 'Be@two'],
        ]);

        $explanation = $radixTree->explain('GET', '/bétq');

        self::assertSame(RouteMatchExplanation::STAGE_PATH, $explanation->failedStage());
        self::assertSame('/bét', $explanation->sharedPrefix(), 'the tracked prefix counts characters, never bytes');
        self::assertSame(['/bétz'], $explanation->neighborPatterns());
    }

    public function testAMultiByteChildIsEnteredByItsOwnCharacter(): void
    {
        $radixTree = RadixTree::compile([
            ['path' => '/bé', 'method' => ['GET'], 'handler' => 'Be@one'],
            ['path' => '/bét', 'method' => ['GET'], 'handler' => 'Be@two'],
        ]);

        $explanation = $radixTree->explain('GET', '/bétq');

        self::assertSame('/bét', $explanation->sharedPrefix(), 'the child lookup reads the path by characters');
        self::assertSame(['/bét'], $explanation->neighborPatterns());
    }

    public function testTheCapLandsInsideOneNode(): void
    {
        $routes = [];
        foreach (['a', 'b', 'c', 'd', 'e', 'f', 'g'] as $segment) {
            $routes[] = ['path' => '/c/' . $segment, 'method' => ['GET'], 'handler' => 'C@' . $segment];
        }

        $routes[] = ['path' => '/c/h', 'method' => ['GET'], 'handler' => 'C@get'];
        $routes[] = ['path' => '/c/h', 'method' => ['POST'], 'handler' => 'C@post'];

        $radixTree = RadixTree::compile($routes);
        $explanation = $radixTree->explain('GET', '/c/zzz');

        self::assertSame(RouteMatchExplanation::STAGE_PATH, $explanation->failedStage());
        self::assertCount(8, $explanation->neighborPatterns(), 'the cap is exactly the budget, mid-node included');
        self::assertSame('/c/g', $explanation->neighborPatterns()[6]);
        self::assertSame('/c/h', $explanation->neighborPatterns()[7]);
    }

    public function testTheBudgetCountsCharactersNotBytes(): void
    {
        $multiByteRun = '/' . str_repeat('é', 101);
        $radixTree = RadixTree::compile([
            ['path' => $multiByteRun . 'x', 'method' => ['GET'], 'handler' => 'Multi@x'],
        ]);

        $explanation = $radixTree->explain('GET', $multiByteRun . 'z');

        self::assertSame(102, mb_strlen($explanation->sharedPrefix()));
        self::assertSame(
            'stage=path survivors=path:0,host:0,version:0 prefix=' . $multiByteRun
            . ' candidates=' . $multiByteRun . 'x',
            $explanation->summary(),
            'a 102-character multi-byte rendering (202 bytes) is under the 128-character budget',
        );
    }

    public function testTheBudgetBoundaryIsInclusive(): void
    {
        $exactBudgetRun = '/' . str_repeat('é', 127);
        $radixTree = RadixTree::compile([
            ['path' => $exactBudgetRun . 'x', 'method' => ['GET'], 'handler' => 'Exact@x'],
        ]);

        $explanation = $radixTree->explain('GET', $exactBudgetRun . 'z');

        self::assertSame(128, mb_strlen($explanation->sharedPrefix()));
        self::assertSame(
            'stage=path survivors=path:0,host:0,version:0 prefix=' . $exactBudgetRun
            . ' candidates=/' . str_repeat('é', 127) . '...',
            $explanation->summary(),
            'a rendering of exactly the budget is carried whole, never clamped -- the over-budget candidate is not',
        );
    }

    public function testTheClampCutsMultiByteRenderingsByCharacters(): void
    {
        $overBudgetRun = '/' . str_repeat('é', 130);
        $radixTree = RadixTree::compile([
            ['path' => $overBudgetRun . 'x', 'method' => ['GET'], 'handler' => 'Over@x'],
        ]);

        $explanation = $radixTree->explain('GET', $overBudgetRun . 'z');

        self::assertSame(
            'stage=path survivors=path:0,host:0,version:0 prefix=/' . str_repeat('é', 127) . '...'
            . ' candidates=/' . str_repeat('é', 127) . '...',
            $explanation->summary(),
            'the clamp keeps 128 whole characters, never a byte-sliced half of one',
        );
    }

    public function testTheOutcomeFoldReadsEveryMatchResultVocabulary(): void
    {
        self::assertSame(
            RouteMatchExplanation::OUTCOME_FOUND,
            RouteMatchOutcome::of(MatchResult::found(CompiledRoute::fromArray([
                'path' => '/users',
                'method' => ['GET'],
                'handler' => 'Users@index',
            ]), [])),
        );
        self::assertSame(
            RouteMatchExplanation::OUTCOME_METHOD_NOT_ALLOWED,
            RouteMatchOutcome::of(MatchResult::methodNotAllowed(['GET'])),
        );
        self::assertSame(
            RouteMatchExplanation::OUTCOME_NOT_ACCEPTABLE,
            RouteMatchOutcome::of(MatchResult::notAcceptable(['application/json'])),
        );
        self::assertSame(
            RouteMatchExplanation::OUTCOME_NOT_FOUND,
            RouteMatchOutcome::of(MatchResult::notFound()),
        );
    }

    /**
     * The #795 sharp edge's own diagnostic: a host-exact claim that
     * wins the path shadows the host-agnostic routes for the whole
     * path at that host, so the 405's own payload names the claim's
     * methods alone -- and the explanation carries the shadowed other
     * half, the method behind the dropped claim, with the host
     * spelling that owns it (null: the host-agnostic claim).
     */
    public function testAMethodRefusalCarriesTheShadowedMethodBehindTheDroppedAgnosticClaim(): void
    {
        $radixTree = RadixTree::compile([
            ['path' => '/x', 'method' => ['GET'], 'handler' => 'Exact@show', 'host' => 'api.example.com'],
            ['path' => '/x', 'method' => ['POST'], 'handler' => 'Agnostic@create'],
        ]);

        $explanation = $radixTree->explain('POST', '/x', 'api.example.com');

        self::assertSame(RouteMatchExplanation::OUTCOME_METHOD_NOT_ALLOWED, $explanation->outcome());
        self::assertSame(RouteMatchExplanation::STAGE_METHOD, $explanation->failedStage());
        self::assertSame(['GET'], $explanation->allowedMethods());
        self::assertSame(
            [['method' => 'POST', 'host' => null]],
            $explanation->shadowedMethods(),
            "the agnostic claim's method is the shadow the exact claim cast over the path",
        );
        self::assertSame(
            'stage=method survivors=path:2,host:1,version:1 allowed=GET shadowed=POST@*',
            $explanation->summary(),
        );
    }

    /**
     * A found match never carries a shadow -- the diagnostic twin of
     * a successful match has no failure to attribute, and a shadowed
     * payload on it would be the second opinion this class refuses
     * to be.
     */
    public function testAFoundMatchCarriesNoShadowedMethods(): void
    {
        $radixTree = RadixTree::compile([
            ['path' => '/x', 'method' => ['GET'], 'handler' => 'Exact@show', 'host' => 'api.example.com'],
            ['path' => '/x', 'method' => ['POST'], 'handler' => 'Agnostic@create'],
        ]);

        $explanation = $radixTree->explain('POST', '/x', 'other.example.com');

        self::assertTrue($explanation->isFound());
        self::assertSame([], $explanation->shadowedMethods());
        self::assertSame('stage=none survivors=path:2,host:1,version:1', $explanation->summary());
    }

    /**
     * The lower-specificity co-matching claims join the shadow with
     * their own host spellings -- a claim that answers the request
     * host but lost the specificity bucket is exactly as shadowed as
     * the agnostic fallback.
     */
    public function testAMethodRefusalCarriesTheLowerSpecificityClaimsInTheShadow(): void
    {
        $radixTree = RadixTree::compile([
            ['path' => '/x', 'method' => ['GET'], 'handler' => 'Exact@show', 'host' => 'api.example.com'],
            ['path' => '/x', 'method' => ['DELETE'], 'handler' => 'Tenant@destroy', 'host' => '{tenant}.example.com'],
        ]);

        $explanation = $radixTree->explain('POST', '/x', 'api.example.com');

        self::assertSame(
            [['method' => 'DELETE', 'host' => '{tenant}.example.com']],
            $explanation->shadowedMethods(),
        );
        self::assertSame(
            'stage=method survivors=path:2,host:1,version:1 allowed=GET shadowed=DELETE@{tenant}.example.com',
            $explanation->summary(),
        );
    }

    /**
     * A route whose host claim does not answer the request host is
     * NOT in the shadow -- it is elsewhere, and naming it would
     * pretend a method one host-hop away belongs to this request's
     * answer.
     */
    public function testTheShadowExcludesRoutesThatDoNotAnswerTheRequestHost(): void
    {
        $radixTree = RadixTree::compile([
            ['path' => '/x', 'method' => ['GET'], 'handler' => 'Exact@show', 'host' => 'api.example.com'],
            ['path' => '/x', 'method' => ['PUT'], 'handler' => 'Elsewhere@put', 'host' => 'other.example.com'],
            ['path' => '/x', 'method' => ['POST'], 'handler' => 'Agnostic@create'],
        ]);

        $explanation = $radixTree->explain('POST', '/x', 'api.example.com');

        self::assertSame(
            [['method' => 'POST', 'host' => null]],
            $explanation->shadowedMethods(),
            'the elsewhere host claim never joins the shadow the request host judged',
        );
    }

    /**
     * A method refusal without host shadowing carries no shadowed
     * payload at all -- the summary line gains no token, the honest
     * empty answer.
     */
    public function testAMethodRefusalWithoutHostShadowingCarriesNoShadowedMethods(): void
    {
        $explanation = $this->table()->explain('DELETE', '/posts');

        self::assertSame(RouteMatchExplanation::OUTCOME_METHOD_NOT_ALLOWED, $explanation->outcome());
        self::assertSame(['POST'], $explanation->allowedMethods());
        self::assertSame([], $explanation->shadowedMethods());
        self::assertSame(
            'stage=method survivors=path:1,host:1,version:1 allowed=POST',
            $explanation->summary(),
        );
    }

    /**
     * The agnostic fallback's own selection shadows nothing: the
     * host-claiming routes it ignored answer a different host, and a
     * host that claimed nothing cannot have cast a shadow.
     */
    public function testTheAgnosticFallbackSelectionCarriesNoShadowedMethods(): void
    {
        $radixTree = RadixTree::compile([
            ['path' => '/x', 'method' => ['POST'], 'handler' => 'Exact@create', 'host' => 'api.example.com'],
            ['path' => '/x', 'method' => ['GET'], 'handler' => 'Agnostic@show'],
        ]);

        $explanation = $radixTree->explain('PUT', '/x', 'other.example.com');

        self::assertSame(RouteMatchExplanation::OUTCOME_METHOD_NOT_ALLOWED, $explanation->outcome());
        self::assertSame(['GET'], $explanation->allowedMethods());
        self::assertSame(
            [],
            $explanation->shadowedMethods(),
            'the exact claim ignored by the fallback answers another host -- elsewhere, not shadowed',
        );
        self::assertSame(
            'stage=method survivors=path:2,host:1,version:1 allowed=GET',
            $explanation->summary(),
        );
    }

    /**
     * The shadow dedupes: two routes behind one claim spelling (the
     * versioned and the unversioned twin of one method+host pair)
     * answer one shadowed entry, not two.
     */
    public function testTheShadowDedupesTheSameMethodBehindOneClaimSpelling(): void
    {
        $radixTree = RadixTree::compile([
            ['path' => '/x', 'method' => ['GET'], 'handler' => 'Exact@show', 'host' => 'api.example.com'],
            ['path' => '/x', 'method' => ['POST'], 'handler' => 'Tenant@create', 'host' => '{tenant}.example.com'],
            [
                'path' => '/x',
                'method' => ['POST'],
                'handler' => 'TenantV2@create',
                'host' => '{tenant}.example.com',
                'version' => 'v2',
            ],
        ]);

        $explanation = $radixTree->explain('POST', '/x', 'api.example.com');

        self::assertSame(
            [['method' => 'POST', 'host' => '{tenant}.example.com']],
            $explanation->shadowedMethods(),
            'one claim spelling, one shadowed entry, however many routes declare behind it',
        );
    }

    /**
     * The shadow's deterministic order: the method ascending, then
     * the owning host ascending with the host-agnostic claim first
     * among its own method's peers.
     */
    public function testTheShadowSortsByMethodThenHostWithTheAgnosticClaimFirst(): void
    {
        $radixTree = RadixTree::compile([
            ['path' => '/x', 'method' => ['GET'], 'handler' => 'Exact@show', 'host' => 'api.example.com'],
            ['path' => '/x', 'method' => ['POST', 'DELETE'], 'handler' => 'Agnostic@write'],
            ['path' => '/x', 'method' => ['POST'], 'handler' => 'Tenant@create', 'host' => '{tenant}.example.com'],
        ]);

        $explanation = $radixTree->explain('PUT', '/x', 'api.example.com');

        self::assertSame(
            [
                ['method' => 'DELETE', 'host' => null],
                ['method' => 'POST', 'host' => null],
                ['method' => 'POST', 'host' => '{tenant}.example.com'],
            ],
            $explanation->shadowedMethods(),
        );
        self::assertSame(
            'stage=method survivors=path:3,host:1,version:1 allowed=GET'
            . ' shadowed=DELETE@*,POST@*,POST@{tenant}.example.com',
            $explanation->summary(),
        );
    }

    /**
     * The #794 fold's own twin: a version miss whose method judgement
     * answered the 405 carries the shadow too -- its allowed methods
     * are the host-selected set's, and the shadow is the other half
     * of the same host selection.
     */
    public function testAVersionMissMethodRefusalCarriesTheShadowedMethodsToo(): void
    {
        $radixTree = RadixTree::compile([
            [
                'path' => '/x',
                'method' => ['GET'],
                'handler' => 'Exact@show',
                'host' => 'api.example.com',
                'version' => 'v1',
            ],
            ['path' => '/x', 'method' => ['POST'], 'handler' => 'Agnostic@create'],
        ]);

        $explanation = $radixTree->explain('POST', '/x', 'api.example.com', null, 'v9');

        self::assertSame(RouteMatchExplanation::OUTCOME_METHOD_NOT_ALLOWED, $explanation->outcome());
        self::assertSame(RouteMatchExplanation::STAGE_VERSION, $explanation->failedStage());
        self::assertSame(['GET'], $explanation->allowedMethods());
        self::assertSame(
            [['method' => 'POST', 'host' => null]],
            $explanation->shadowedMethods(),
        );
        self::assertSame(
            'stage=version survivors=path:2,host:1,version:0 allowed=GET shadowed=POST@*',
            $explanation->summary(),
        );
    }

    /**
     * The summary's clamp honors the shadowed entry: a host spelling
     * long enough to cross the candidate budget is clamped by
     * characters with the marker spelled, never byte-sliced.
     */
    public function testTheSummaryClampsAnOverlongShadowedEntryToTheCandidateBudget(): void
    {
        $requestHost = 'api.' . str_repeat('l', 63) . '.example.com';
        $longClaim = '{' . str_repeat('p', 64) . '}.' . str_repeat('l', 63) . '.example.com';

        $radixTree = RadixTree::compile([
            ['path' => '/x', 'method' => ['GET'], 'handler' => 'Exact@show', 'host' => $requestHost],
            ['path' => '/x', 'method' => ['POST'], 'handler' => 'Long@create', 'host' => $longClaim],
        ]);

        $explanation = $radixTree->explain('PUT', '/x', $requestHost);

        $entry = 'POST@' . $longClaim;

        self::assertSame(
            'stage=method survivors=path:2,host:1,version:1 allowed=GET'
            . ' shadowed=' . mb_substr($entry, 0, 128) . '...',
            $explanation->summary(),
            "a co-matching claim's own spelling is the entry the budget clamps, characters kept whole",
        );
    }

    /**
     * The fold's own guard: an empty selection shadows nothing -- no
     * claim won the path, so no claim cast a shadow over it.
     */
    public function testAnEmptySelectionAnswersNoShadowedMethods(): void
    {
        $agnostic = CompiledRoute::fromArray([
            'path' => '/x',
            'method' => ['POST'],
            'handler' => 'Agnostic@create',
        ]);

        self::assertSame(
            [],
            RouteHostShadowedMethods::of([$agnostic], [], 'api.example.com'),
        );
    }

    /**
     * The shared fixture: one table exercising every stage the
     * pipeline owns.
     */
    private function table(): RadixTree
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
    private function outcomeOf(MatchResult $matchResult): string
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
