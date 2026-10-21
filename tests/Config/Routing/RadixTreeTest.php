<?php

declare(strict_types=1);

namespace Zef\Test\Config\Routing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Config\ConfigException;
use Zef\Framework\Config\Routing\CompiledRoute;
use Zef\Framework\Config\Routing\LeafRoute;
use Zef\Framework\Config\Routing\MatchResult;
use Zef\Framework\Config\Routing\PrefixConsumption;
use Zef\Framework\Config\Routing\RadixNode;
use Zef\Framework\Config\Routing\RadixTree;
use Zef\Framework\Config\Routing\RouteAcceptNegotiator;
use Zef\Framework\Config\Routing\RouteHostGrammar;
use Zef\Framework\Config\Routing\RouteHostParameters;
use Zef\Framework\Config\Routing\RouteHostPrecedence;
use Zef\Framework\Config\Routing\RouteMatcher;
use Zef\Framework\Config\Routing\RouteMethodAnswer;
use Zef\Framework\Config\Routing\RouteMethodClaims;
use Zef\Framework\Config\Routing\RouteNodeInserter;
use Zef\Framework\Config\Routing\RouteParameters;
use Zef\Framework\Config\Routing\RoutePatternTokens;
use Zef\Framework\Config\Routing\RouteTableRegistry;
use Zef\Framework\Config\Routing\RouteTreeBuilder;
use Zef\Framework\Config\Routing\RouteVersion;
use Zef\Framework\Config\Routing\SegmentCapture;
use Zef\Framework\Config\Routing\SharedPrefixLength;
use Zef\Framework\Config\Routing\StaticDescent;
use Zef\Framework\Config\Routing\StaticRunInserter;
use Zef\Framework\Exception\InvariantViolationException;

/**
 * @internal
 */
#[CoversClass(RadixTree::class)]
#[CoversClass(RadixNode::class)]
#[CoversClass(RouteTreeBuilder::class)]
#[CoversClass(RouteTableRegistry::class)]
#[CoversClass(RouteMethodClaims::class)]
#[CoversClass(RouteParameters::class)]
#[CoversClass(RouteMethodAnswer::class)]
#[CoversClass(RoutePatternTokens::class)]
#[CoversClass(RouteNodeInserter::class)]
#[CoversClass(StaticRunInserter::class)]
#[CoversClass(SharedPrefixLength::class)]
#[CoversClass(RouteMatcher::class)]
#[CoversClass(PrefixConsumption::class)]
#[CoversClass(StaticDescent::class)]
#[CoversClass(SegmentCapture::class)]
#[CoversClass(LeafRoute::class)]
#[CoversClass(MatchResult::class)]
#[CoversClass(CompiledRoute::class)]
#[CoversClass(RouteHostGrammar::class)]
#[CoversClass(RouteHostParameters::class)]
#[CoversClass(RouteHostPrecedence::class)]
#[CoversClass(RouteVersion::class)]
#[CoversClass(RouteAcceptNegotiator::class)]
final class RadixTreeTest extends TestCase
{
    public function testARegistryCannotBeReadBeforeTheTreeIsBuilt(): void
    {
        try {
            new RouteTreeBuilder()->registry();
            self::fail('Reading the registry before the build must raise an invariant violation.');
        } catch (InvariantViolationException $invariantViolationException) {
            self::assertSame(
                'The route tree has not been built yet.',
                $invariantViolationException->getMessage(),
            );
        }
    }

    public function testSelectsVersionedRoutesFromApiVersionHeaderValues(): void
    {
        $radixTree = RadixTree::compile([
            ['path' => '/api', 'method' => ['GET'], 'handler' => 'ApiV1@show', 'version' => 'v1'],
            ['path' => '/api', 'method' => ['GET'], 'handler' => 'ApiV2@show', 'version' => 'v2'],
            ['path' => '/api', 'method' => ['GET'], 'handler' => 'ApiDefault@show'],
        ]);

        self::assertSame('ApiV1@show', $radixTree->match('GET', '/api', null, null, 'V1')->route()->handler());
        self::assertSame('ApiV2@show', $radixTree->match('GET', '/api', null, null, 'v2')->route()->handler());
        self::assertSame('ApiDefault@show', $radixTree->match('GET', '/api')->route()->handler());
        self::assertFalse($radixTree->match('GET', '/api', null, null, 'v3')->isFound());
    }

    public function testRejectsInvalidDeclaredApiVersionAtCompilation(): void
    {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessageIsOrContains('must start with an alphanumeric character');

        RadixTree::compile([
            ['path' => '/api', 'method' => ['GET'], 'handler' => 'Api@show', 'version' => '../v1'],
        ]);
    }

    public function testNegotiatesAcceptAgainstRouteProducedMediaTypes(): void
    {
        $radixTree = RadixTree::compile([
            [
                'path' => '/profile',
                'method' => ['GET'],
                'handler' => 'Profile@show',
                'produces' => ['application/json', 'text/html'],
            ],
        ]);

        self::assertTrue($radixTree->match('GET', '/profile', null, 'application/json')->isFound());
        self::assertTrue($radixTree->match('GET', '/profile', null, 'text/*;q=0.8')->isFound());
        self::assertTrue($radixTree->match('GET', '/profile', null, '*/*')->isFound());

        $notAcceptable = $radixTree->match('GET', '/profile', null, 'image/png');
        self::assertTrue($notAcceptable->isNotAcceptable());
        self::assertSame(['application/json', 'text/html'], $notAcceptable->acceptableMediaTypes());
    }

    public function testSpecificZeroQualityOverridesWildcardAcceptRange(): void
    {
        $radixTree = RadixTree::compile([
            [
                'path' => '/profile',
                'method' => ['GET'],
                'handler' => 'Profile@show',
                'produces' => ['application/json'],
            ],
        ]);

        self::assertTrue($radixTree->match('GET', '/profile', null, 'application/json;q=0, */*;q=1')->isNotAcceptable());
    }

    public function testRejectsInvalidAndDuplicateProducedMediaTypes(): void
    {
        try {
            RadixTree::compile([
                ['path' => '/profile', 'method' => ['GET'], 'handler' => 'Profile@show', 'produces' => ['not-a-media-type']],
            ]);
            self::fail('Invalid produced media types must fail at compile time.');
        } catch (ConfigException $configException) {
            self::assertStringContainsString('invalid media type', $configException->getMessage());
        }

        $this->expectException(ConfigException::class);
        RadixTree::compile([
            ['path' => '/profile', 'method' => ['GET'], 'handler' => 'Profile@show', 'produces' => ['application/json', 'APPLICATION/JSON']],
        ]);
    }

    public function testMatchesAStaticRoute(): void
    {
        $radixTree = RadixTree::compile([
            ['path' => '/health', 'method' => ['GET'], 'handler' => 'Health@check'],
        ]);

        $matchResult = $radixTree->match('GET', '/health');

        self::assertTrue($matchResult->isFound());
        self::assertSame('Health@check', $matchResult->route()->handler());
        self::assertSame([], $matchResult->parameters());
    }

    public function testMatchesAUnicodeMethodInAnyCase(): void
    {
        $radixTree = RadixTree::compile([
            ['path' => '/umlaut', 'method' => ['gét'], 'handler' => 'Umlaut@show'],
        ]);

        $matchResult = $radixTree->match('gét', '/umlaut');

        self::assertTrue($matchResult->isFound());
        self::assertSame(['GÉT'], $matchResult->route()->methods());
    }

    public function testRejectsABranchWhoseSecondConstraintFails(): void
    {
        $radixTree = RadixTree::compile([
            [
                'path' => '/u/{a}/{b}',
                'method' => ['GET'],
                'handler' => 'Second@check',
                'constraints' => ['a' => '[0-9]+', 'b' => '[a-z]+'],
            ],
        ]);

        self::assertTrue($radixTree->match('GET', '/u/42/hello')->isFound());
        self::assertFalse($radixTree->match('GET', '/u/42/42x2')->isFound());
    }

    public function testMatchesRoutesByExactHostWithoutCrossingHosts(): void
    {
        $radixTree = RadixTree::compile([
            [
                'path' => '/dashboard',
                'host' => 'admin.example.com',
                'method' => ['GET'],
                'handler' => 'Admin@dashboard',
            ],
            [
                'path' => '/dashboard',
                'host' => 'api.example.com',
                'method' => ['GET'],
                'handler' => 'Api@dashboard',
            ],
        ]);

        self::assertSame(
            'Admin@dashboard',
            $radixTree->match('GET', '/dashboard', 'ADMIN.EXAMPLE.COM')->route()->handler(),
        );
        self::assertSame(
            'Api@dashboard',
            $radixTree->match('GET', '/dashboard', 'api.example.com')->route()->handler(),
        );
        self::assertFalse($radixTree->match('GET', '/dashboard', 'other.example.com')->isFound());
        self::assertFalse($radixTree->match('GET', '/dashboard', '*.example.com')->isFound());
        self::assertFalse($radixTree->match('GET', '/dashboard')->isFound());
    }

    public function testCapturesWholeLabelHostParametersAndMergesThemWithPathParameters(): void
    {
        $radixTree = RadixTree::compile([
            [
                'path' => '/users/{id}',
                'host' => '{tenant}.example.com',
                'method' => ['GET'],
                'handler' => 'TenantUsers@show',
            ],
        ]);

        $match = $radixTree->match('GET', '/users/42', 'ACME.example.com');

        self::assertTrue($match->isFound());
        self::assertSame(['id' => '42', 'tenant' => 'acme'], $match->parameters());
        self::assertFalse($radixTree->match('GET', '/users/42', 'example.com')->isFound());
        self::assertFalse($radixTree->match('GET', '/users/42', 'a.b.example.com')->isFound());
    }

    public function testExactHostTakesPrecedenceOverParameterizedHostAndFallback(): void
    {
        $radixTree = RadixTree::compile([
            ['path' => '/dashboard', 'method' => ['GET'], 'handler' => 'Fallback@dashboard'],
            [
                'path' => '/dashboard',
                'host' => '{tenant}.example.com',
                'method' => ['GET'],
                'handler' => 'Tenant@dashboard',
            ],
            [
                'path' => '/dashboard',
                'host' => 'admin.example.com',
                'method' => ['GET'],
                'handler' => 'Admin@dashboard',
            ],
        ]);

        self::assertSame('Admin@dashboard', $radixTree->match('GET', '/dashboard', 'admin.example.com')->route()->handler());
        self::assertSame('Tenant@dashboard', $radixTree->match('GET', '/dashboard', 'acme.example.com')->route()->handler());
        self::assertSame('Fallback@dashboard', $radixTree->match('GET', '/dashboard', 'outside.test')->route()->handler());
    }

    public function testMoreSpecificParameterizedHostWinsRegardlessOfRegistrationOrder(): void
    {
        $radixTree = RadixTree::compile([
            [
                'path' => '/dashboard',
                'host' => '{sub}.{domain}.com',
                'method' => ['GET'],
                'handler' => 'GenericSubdomain@dashboard',
            ],
            [
                'path' => '/dashboard',
                'host' => 'admin.{region}.com',
                'method' => ['GET'],
                'handler' => 'AdminRegion@dashboard',
            ],
        ]);

        $match = $radixTree->match('GET', '/dashboard', 'admin.example.com');

        self::assertSame('AdminRegion@dashboard', $match->route()->handler());
        self::assertSame(['region' => 'example'], $match->parameters());
    }

    public function testRejectsOverlappingHostPatternsAtEqualSpecificity(): void
    {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessageIsOrContains('overlaps');

        RadixTree::compile([
            [
                'path' => '/dashboard',
                'host' => '{tenant}.example.com',
                'method' => ['GET'],
                'handler' => 'Tenant@dashboard',
            ],
            [
                'path' => '/dashboard',
                'host' => 'admin.{region}.com',
                'method' => ['GET'],
                'handler' => 'Region@dashboard',
            ],
        ]);
    }

    public function testRejectsHostParameterNameCollisionsWithPathParameters(): void
    {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessageIsOrContains('must not repeat parameter name');

        RadixTree::compile([
            [
                'path' => '/users/{tenant}',
                'host' => '{tenant}.example.com',
                'method' => ['GET'],
                'handler' => 'TenantUsers@show',
            ],
        ]);
    }

    public function testExactHostRouteTakesPrecedenceOverHostAgnosticFallback(): void
    {
        $radixTree = RadixTree::compile([
            ['path' => '/dashboard', 'method' => ['GET'], 'handler' => 'Fallback@dashboard'],
            [
                'path' => '/dashboard',
                'host' => 'admin.example.com',
                'method' => ['POST'],
                'handler' => 'Admin@dashboard',
            ],
        ]);

        $exactHostMismatch = $radixTree->match('GET', '/dashboard', 'admin.example.com');
        self::assertTrue($exactHostMismatch->isMethodNotAllowed());
        self::assertSame(['POST'], $exactHostMismatch->allowedMethods());

        self::assertSame(
            'Fallback@dashboard',
            $radixTree->match('GET', '/dashboard', 'www.example.com')->route()->handler(),
        );
    }

    public function testRejectsAnInvalidExactRouteHostDuringCompilation(): void
    {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessageIsOrContains('must be an exact DNS hostname without a port or wildcard');

        RadixTree::compile([
            [
                'path' => '/dashboard',
                'host' => '*.example.com',
                'method' => ['GET'],
                'handler' => 'Admin@dashboard',
            ],
        ]);
    }

    public function testMatchesTheRootRoute(): void
    {
        $radixTree = RadixTree::compile([
            ['path' => '/', 'method' => ['GET'], 'handler' => 'Root@index'],
        ]);

        $matchResult = $radixTree->match('GET', '/');

        self::assertTrue($matchResult->isFound());
        self::assertSame('Root@index', $matchResult->route()->handler());
    }

    public function testMatchesTheRootRouteAmongOthers(): void
    {
        $radixTree = RadixTree::compile([
            ['path' => '/', 'method' => ['GET'], 'handler' => 'Root@index'],
            ['path' => '/users', 'method' => ['GET'], 'handler' => 'Users@index'],
        ]);

        self::assertSame('Root@index', $radixTree->match('GET', '/')->route()->handler());
        self::assertSame('Users@index', $radixTree->match('GET', '/users')->route()->handler());
    }

    public function testMatchesAPatternThatIsOnlyAParameter(): void
    {
        $radixTree = RadixTree::compile([
            ['path' => '/{locale}', 'method' => ['GET'], 'handler' => 'Locale@pick'],
        ]);

        $matchResult = $radixTree->match('GET', '/en');

        self::assertTrue($matchResult->isFound());
        self::assertSame(['locale' => 'en'], $matchResult->parameters());
    }

    public function testMatchesAMultiSegmentChain(): void
    {
        $radixTree = RadixTree::compile([
            ['path' => '/a/b/c/d/e', 'method' => ['GET'], 'handler' => 'Deep@end'],
        ]);

        self::assertTrue($radixTree->match('GET', '/a/b/c/d/e')->isFound());
        self::assertFalse($radixTree->match('GET', '/a/b/c/d')->isFound());
    }

    public function testDistinguishesAdjacentPrefixes(): void
    {
        $radixTree = RadixTree::compile([
            ['path' => '/u', 'method' => ['GET'], 'handler' => 'U@one'],
            ['path' => '/us', 'method' => ['GET'], 'handler' => 'Us@two'],
        ]);

        self::assertSame('U@one', $radixTree->match('GET', '/u')->route()->handler());
        self::assertSame('Us@two', $radixTree->match('GET', '/us')->route()->handler());
    }

    public function testRejectsAnEmptyRequestPath(): void
    {
        $radixTree = RadixTree::compile([
            ['path' => '/', 'method' => ['GET'], 'handler' => 'Root@index'],
        ]);

        $matchResult = $radixTree->match('GET', '');

        self::assertFalse($matchResult->isFound());
        self::assertFalse($matchResult->isMethodNotAllowed());
    }

    public function testRejectsAPathWithoutALeadingSlash(): void
    {
        $radixTree = RadixTree::compile([
            ['path' => '/users', 'method' => ['GET'], 'handler' => 'Users@index'],
        ]);

        self::assertFalse($radixTree->match('GET', 'users')->isFound());
    }

    public function testRejectsATrailingSlash(): void
    {
        $radixTree = RadixTree::compile([
            ['path' => '/users', 'method' => ['GET'], 'handler' => 'Users@index'],
        ]);

        self::assertFalse($radixTree->match('GET', '/users/')->isFound());
    }

    public function testRejectsAnEmptySegment(): void
    {
        $radixTree = RadixTree::compile([
            ['path' => '/u/{a}', 'method' => ['GET'], 'handler' => 'U@any'],
        ]);

        self::assertFalse($radixTree->match('GET', '/u//x')->isFound());
    }

    public function testRejectsALongerRequestPath(): void
    {
        $radixTree = RadixTree::compile([
            ['path' => '/users', 'method' => ['GET'], 'handler' => 'Users@index'],
        ]);

        self::assertFalse($radixTree->match('GET', '/users/all')->isFound());
    }

    public function testRejectsAShorterRequestPath(): void
    {
        $radixTree = RadixTree::compile([
            ['path' => '/users/all', 'method' => ['GET'], 'handler' => 'Users@all'],
        ]);

        self::assertFalse($radixTree->match('GET', '/users')->isFound());
    }

    public function testRejectsADifferentPath(): void
    {
        $radixTree = RadixTree::compile([
            ['path' => '/users', 'method' => ['GET'], 'handler' => 'Users@index'],
        ]);

        self::assertFalse($radixTree->match('GET', '/other')->isFound());
    }

    public function testMatchesEachDeclaredMethod(): void
    {
        $radixTree = RadixTree::compile([
            ['path' => '/users', 'method' => ['GET', 'POST'], 'handler' => 'Users@dispatch'],
        ]);

        self::assertTrue($radixTree->match('GET', '/users')->isFound());
        self::assertTrue($radixTree->match('POST', '/users')->isFound());
    }

    public function testMatchesALowerCasedRequestMethod(): void
    {
        $radixTree = RadixTree::compile([
            ['path' => '/users', 'method' => ['GET'], 'handler' => 'Users@index'],
        ]);

        self::assertTrue($radixTree->match('get', '/users')->isFound());
    }

    public function testMatchesAUnicodeUpperCasedMethod(): void
    {
        $radixTree = RadixTree::compile([
            ['path' => '/u', 'method' => ['gët'], 'handler' => 'U@any'],
        ]);

        self::assertTrue($radixTree->match('gët', '/u')->isFound());
    }

    public function testAnswersMethodNotAllowedWithSortedMethods(): void
    {
        $radixTree = RadixTree::compile([
            ['path' => '/users', 'method' => ['post', 'GET', 'delete'], 'handler' => 'Users@dispatch'],
        ]);

        $matchResult = $radixTree->match('PUT', '/users');

        self::assertFalse($matchResult->isFound());
        self::assertTrue($matchResult->isMethodNotAllowed());
        self::assertSame(['DELETE', 'GET', 'POST'], $matchResult->allowedMethods());
    }

    public function testMethodNotAllowedRejectsWrongStateAccessors(): void
    {
        $radixTree = RadixTree::compile([
            ['path' => '/users', 'method' => ['GET'], 'handler' => 'Users@index'],
        ]);

        $matchResult = $radixTree->match('PUT', '/users');

        try {
            $matchResult->route();
            self::fail('A method-not-allowed result must not expose a route.');
        } catch (InvariantViolationException $invariantViolationException) {
            self::assertSame('The match result does not hold a route.', $invariantViolationException->getMessage());
        }

        try {
            $matchResult->parameters();
            self::fail('A method-not-allowed result must not expose parameters.');
        } catch (InvariantViolationException $invariantViolationException) {
            self::assertSame('The match result does not hold parameters.', $invariantViolationException->getMessage());
        }
    }

    public function testNotFoundRejectsWrongStateAccessors(): void
    {
        $radixTree = RadixTree::compile([
            ['path' => '/users', 'method' => ['GET'], 'handler' => 'Users@index'],
        ]);

        $matchResult = $radixTree->match('GET', '/nope');

        try {
            $matchResult->route();
            self::fail('A not-found result must not expose a route.');
        } catch (InvariantViolationException $invariantViolationException) {
            self::assertSame('The match result does not hold a route.', $invariantViolationException->getMessage());
        }

        try {
            $matchResult->allowedMethods();
            self::fail('A not-found result must not expose allowed methods.');
        } catch (InvariantViolationException $invariantViolationException) {
            self::assertSame(
                'The match result does not hold allowed methods.',
                $invariantViolationException->getMessage(),
            );
        }
    }

    public function testCapturesATrailingParameter(): void
    {
        $radixTree = RadixTree::compile([
            ['path' => '/users/{id}', 'method' => ['GET'], 'handler' => 'Users@show'],
        ]);

        $matchResult = $radixTree->match('GET', '/users/42');

        self::assertTrue($matchResult->isFound());
        self::assertSame(['id' => '42'], $matchResult->parameters());
    }

    public function testCapturesAMiddleParameter(): void
    {
        $radixTree = RadixTree::compile([
            ['path' => '/users/{id}/posts', 'method' => ['GET'], 'handler' => 'Posts@index'],
        ]);

        $matchResult = $radixTree->match('GET', '/users/42/posts');

        self::assertSame(['id' => '42'], $matchResult->parameters());
    }

    public function testCapturesAdjacentParameters(): void
    {
        $radixTree = RadixTree::compile([
            ['path' => '/{a}/{b}', 'method' => ['GET'], 'handler' => 'Pair@two'],
        ]);

        self::assertSame(['a' => 'x', 'b' => 'y'], $radixTree->match('GET', '/x/y')->parameters());
    }

    public function testCapturesAParameterContainingPunctuation(): void
    {
        $radixTree = RadixTree::compile([
            ['path' => '/files/{file}', 'method' => ['GET'], 'handler' => 'Files@show'],
        ]);

        self::assertSame(['file' => 'a.b-c.txt'], $radixTree->match('GET', '/files/a.b-c.txt')->parameters());
    }

    public function testCapturesUnicodeSegments(): void
    {
        $radixTree = RadixTree::compile([
            ['path' => '/ä/{x}', 'method' => ['GET'], 'handler' => 'U@any'],
        ]);

        self::assertSame(['x' => 'ü'], $radixTree->match('GET', '/ä/ü')->parameters());
    }

    public function testMatchesAStaticUnicodeRoute(): void
    {
        $radixTree = RadixTree::compile([
            ['path' => '/ä', 'method' => ['GET'], 'handler' => 'Static@unicode'],
        ]);

        self::assertTrue($radixTree->match('GET', '/ä')->isFound());
        self::assertFalse($radixTree->match('GET', '/b')->isFound());
    }

    public function testSplitsMultiByteSiblings(): void
    {
        $radixTree = RadixTree::compile([
            ['path' => '/ab', 'method' => ['GET'], 'handler' => 'Ascii@two'],
            ['path' => '/äb', 'method' => ['GET'], 'handler' => 'Unicode@two'],
            ['path' => '/äb/end', 'method' => ['GET'], 'handler' => 'Unicode@deep'],
            ['path' => '/äc', 'method' => ['GET'], 'handler' => 'Unicode@three'],
        ]);

        self::assertSame('Ascii@two', $radixTree->match('GET', '/ab')->route()->handler());
        self::assertSame('Unicode@two', $radixTree->match('GET', '/äb')->route()->handler());
        self::assertSame('Unicode@deep', $radixTree->match('GET', '/äb/end')->route()->handler());
        self::assertSame('Unicode@three', $radixTree->match('GET', '/äc')->route()->handler());
    }

    public function testSplitsSharedPrefixesInBothInsertOrders(): void
    {
        $entries = [
            ['path' => '/alpha', 'method' => ['GET'], 'handler' => 'Alpha@one'],
            ['path' => '/alphabet', 'method' => ['GET'], 'handler' => 'Alphabet@two'],
        ];

        foreach ([$entries, array_reverse($entries)] as $ordered) {
            $radixTree = RadixTree::compile($ordered);

            self::assertSame('Alpha@one', $radixTree->match('GET', '/alpha')->route()->handler());
            self::assertSame('Alphabet@two', $radixTree->match('GET', '/alphabet')->route()->handler());
        }
    }

    public function testSplitsMultiByteSiblingsSharingALeadByte(): void
    {
        $radixTree = RadixTree::compile([
            ['path' => '/äb', 'method' => ['GET'], 'handler' => 'A_Umlaut@two'],
            ['path' => '/ãb', 'method' => ['GET'], 'handler' => 'A_Tilde@two'],
        ]);

        self::assertSame('A_Umlaut@two', $radixTree->match('GET', '/äb')->route()->handler());
        self::assertSame('A_Tilde@two', $radixTree->match('GET', '/ãb')->route()->handler());
    }

    public function testSplitsMultiByteSiblingsWithACommonTrailingCharacter(): void
    {
        $radixTree = RadixTree::compile([
            ['path' => '/äPQ', 'method' => ['GET'], 'handler' => 'PQ@one'],
            ['path' => '/äXQ', 'method' => ['GET'], 'handler' => 'XQ@two'],
        ]);

        self::assertSame('PQ@one', $radixTree->match('GET', '/äPQ')->route()->handler());
        self::assertSame('XQ@two', $radixTree->match('GET', '/äXQ')->route()->handler());
    }

    public function testSplitsSiblingsWithAQuestionMarkAgainstABrokenByteRead(): void
    {
        $entries = [
            ['path' => '/ääX', 'method' => ['GET'], 'handler' => 'Broken@byte'],
            ['path' => '/ä?X', 'method' => ['GET'], 'handler' => 'Question@mark'],
        ];

        foreach ([$entries, array_reverse($entries)] as $ordered) {
            $radixTree = RadixTree::compile($ordered);

            self::assertSame('Broken@byte', $radixTree->match('GET', '/ääX')->route()->handler());
            self::assertSame('Question@mark', $radixTree->match('GET', '/ä?X')->route()->handler());
        }
    }

    public function testRejectsAMissAfterASiblingSplit(): void
    {
        $radixTree = RadixTree::compile([
            ['path' => '/ab', 'method' => ['GET'], 'handler' => 'Ab@one'],
            ['path' => '/ac', 'method' => ['GET'], 'handler' => 'Ac@two'],
        ]);

        self::assertFalse($radixTree->match('GET', '/ax')->isFound());
    }

    public function testRejectsARequestShapedLikeAParameterName(): void
    {
        $radixTree = RadixTree::compile([
            ['path' => '/users/{id}', 'method' => ['GET'], 'handler' => 'Users@show'],
        ]);

        self::assertFalse($radixTree->match('GET', 'id')->isFound());
        self::assertFalse($radixTree->match('GET', 'users')->isFound());
        self::assertSame(['id' => 'id'], $radixTree->match('GET', '/users/id')->parameters());
    }

    public function testRejectsAnEmptySegmentInsideAParameterRun(): void
    {
        $radixTree = RadixTree::compile([
            ['path' => '/u/{a}/z', 'method' => ['GET'], 'handler' => 'U@z'],
        ]);

        self::assertFalse($radixTree->match('GET', '/u//z')->isFound());
    }

    public function testBacktracksThroughAFailingParameterCapture(): void
    {
        $radixTree = RadixTree::compile([
            ['path' => '/u/s/{x}/end', 'method' => ['GET'], 'handler' => 'Inner@end'],
            ['path' => '/u/{a}/v', 'method' => ['GET'], 'handler' => 'Outer@v'],
        ]);

        $matchResult = $radixTree->match('GET', '/u/s/v');

        self::assertTrue($matchResult->isFound());
        self::assertSame('Outer@v', $matchResult->route()->handler());
        self::assertSame(['a' => 's'], $matchResult->parameters());
    }

    public function testRejectsAConstraintViolation(): void
    {
        $radixTree = RadixTree::compile([
            [
                'path' => '/users/{id}',
                'method' => ['GET'],
                'handler' => 'Users@show',
                'constraints' => ['id' => '[0-9]+'],
            ],
        ]);

        self::assertFalse($radixTree->match('GET', '/users/abc')->isFound());
        self::assertTrue($radixTree->match('GET', '/users/42')->isFound());
    }

    public function testRejectsATrailingNewlineAConstraintMustNotWavePast(): void
    {
        // The audit probe itself: "/users/12\n" reaching the leaf with
        // the captured segment "12\n" -- a `$`-anchored constraint
        // would accept it (`$` matches before a trailing newline),
        // handing the handler a parameter no whole-value grammar ever
        // admitted. The `\z` anchor refuses it; the walk backtracks
        // and answers not-found.
        $radixTree = RadixTree::compile([
            [
                'path' => '/users/{id}',
                'method' => ['GET'],
                'handler' => 'Users@show',
                'constraints' => ['id' => '[0-9]+'],
            ],
        ]);

        self::assertFalse($radixTree->match('GET', "/users/12\n")->isFound());
        self::assertTrue($radixTree->match('GET', '/users/12')->isFound());
    }

    public function testRejectsAMiddleParameterConstraintViolation(): void
    {
        $radixTree = RadixTree::compile([
            [
                'path' => '/users/{id}/posts',
                'method' => ['GET'],
                'handler' => 'Posts@index',
                'constraints' => ['id' => '[0-9]+'],
            ],
        ]);

        self::assertFalse($radixTree->match('GET', '/users/abc/posts')->isFound());
    }

    public function testBacktracksFromTheStaticSubtreeToTheParameterEdge(): void
    {
        $radixTree = RadixTree::compile([
            ['path' => '/u/static', 'method' => ['GET'], 'handler' => 'Static@end'],
            ['path' => '/u/{a}/deep', 'method' => ['GET'], 'handler' => 'Deep@end'],
        ]);

        $matchResult = $radixTree->match('GET', '/u/static/deep');

        self::assertTrue($matchResult->isFound());
        self::assertSame('Deep@end', $matchResult->route()->handler());
        self::assertSame(['a' => 'static'], $matchResult->parameters());
    }

    public function testPrefersTheStaticRouteWhenBothCanMatch(): void
    {
        $radixTree = RadixTree::compile([
            ['path' => '/u/{a}', 'method' => ['GET'], 'handler' => 'Param@any'],
            ['path' => '/u/static', 'method' => ['GET'], 'handler' => 'Static@end'],
        ]);

        $matchResult = $radixTree->match('GET', '/u/static');

        self::assertSame('Static@end', $matchResult->route()->handler());
        self::assertSame([], $matchResult->parameters());
    }

    public function testSharesTheParameterEdgeBetweenRoutes(): void
    {
        $radixTree = RadixTree::compile([
            [
                'path' => '/n/{a}',
                'method' => ['GET'],
                'handler' => 'One@end',
                'constraints' => ['a' => '[0-9]+'],
            ],
            [
                'path' => '/n/{a}/x',
                'method' => ['GET'],
                'handler' => 'Two@end',
                'constraints' => ['a' => '[0-9]+'],
            ],
        ]);

        self::assertSame('One@end', $radixTree->match('GET', '/n/5')->route()->handler());
        self::assertSame('Two@end', $radixTree->match('GET', '/n/5/x')->route()->handler());
        self::assertFalse($radixTree->match('GET', '/n/zz')->isFound());
        self::assertFalse($radixTree->match('GET', '/n/zz/x')->isFound());
    }

    public function testSplitsSharedPrefixes(): void
    {
        $radixTree = RadixTree::compile([
            ['path' => '/alpha', 'method' => ['GET'], 'handler' => 'Alpha@one'],
            ['path' => '/alphabet', 'method' => ['GET'], 'handler' => 'Alphabet@two'],
        ]);

        self::assertSame('Alpha@one', $radixTree->match('GET', '/alpha')->route()->handler());
        self::assertSame('Alphabet@two', $radixTree->match('GET', '/alphabet')->route()->handler());
    }

    public function testSplitsSiblingPrefixes(): void
    {
        $radixTree = RadixTree::compile([
            ['path' => '/ab', 'method' => ['GET'], 'handler' => 'Ab@one'],
            ['path' => '/ac', 'method' => ['GET'], 'handler' => 'Ac@two'],
            ['path' => '/cd', 'method' => ['GET'], 'handler' => 'Cd@three'],
        ]);

        self::assertSame('Ab@one', $radixTree->match('GET', '/ab')->route()->handler());
        self::assertSame('Ac@two', $radixTree->match('GET', '/ac')->route()->handler());
        self::assertSame('Cd@three', $radixTree->match('GET', '/cd')->route()->handler());
    }

    public function testInsertOrderDoesNotChangeTheOutcomes(): void
    {
        $entries = [
            ['path' => '/u/static', 'method' => ['GET'], 'handler' => 'Static@end'],
            ['path' => '/u/{a}/deep', 'method' => ['GET'], 'handler' => 'Deep@end'],
        ];

        $radixTree = RadixTree::compile($entries);
        $reversed = RadixTree::compile(array_reverse($entries));

        foreach (['/u/static', '/u/static/deep', '/u/x/deep', '/u/x'] as $path) {
            self::assertSame(
                $radixTree->match('GET', $path)->isFound(),
                $reversed->match('GET', $path)->isFound(),
                \sprintf('The outcomes for "%s" must not depend on insert order.', $path),
            );
        }
    }

    public function testMatchingTwiceYieldsTheSameResult(): void
    {
        $radixTree = RadixTree::compile([
            ['path' => '/u/{a}', 'method' => ['GET'], 'handler' => 'U@any'],
        ]);

        $matchResult = $radixTree->match('GET', '/u/x');
        $second = $radixTree->match('GET', '/u/x');

        self::assertSame($matchResult->route()->handler(), $second->route()->handler());
        self::assertSame($matchResult->parameters(), $second->parameters());
    }

    public function testCompilesAnEmptyRouteTable(): void
    {
        $radixTree = RadixTree::compile([]);

        self::assertFalse($radixTree->match('GET', '/anything')->isFound());
    }

    public function testRejectsAMapInsteadOfAList(): void
    {
        try {
            RadixTree::compile(['first' => ['path' => '/a', 'method' => ['GET'], 'handler' => 'A@one']]);
            self::fail('A map-shaped route table must raise a ConfigException.');
        } catch (ConfigException $configException) {
            self::assertSame('The route table must be a list of route entries.', $configException->getMessage());
        }
    }

    public function testRejectsADuplicateMethodOnOnePattern(): void
    {
        try {
            RadixTree::compile([
                ['path' => '/x', 'method' => ['GET'], 'handler' => 'X@one'],
                ['path' => '/x', 'method' => ['GET'], 'handler' => 'X@two'],
            ]);
            self::fail('A duplicated method on one pattern must raise a ConfigException.');
        } catch (ConfigException $configException) {
            self::assertSame(
                'The route pattern "/x" duplicates the method "GET" of the registered pattern "/x".',
                $configException->getMessage(),
            );
        }
    }

    public function testAllowsMethodDisjointRoutesOnOnePattern(): void
    {
        $radixTree = RadixTree::compile([
            ['path' => '/photos', 'method' => ['GET'], 'handler' => 'Photos@index'],
            ['path' => '/photos', 'method' => ['POST'], 'handler' => 'Photos@store'],
            ['path' => '/photos', 'method' => ['PUT', 'PATCH'], 'handler' => 'Photos@update'],
        ]);

        self::assertSame('Photos@index', $radixTree->match('GET', '/photos')->route()->handler());
        self::assertSame('Photos@store', $radixTree->match('POST', '/photos')->route()->handler());
        self::assertSame('Photos@update', $radixTree->match('PUT', '/photos')->route()->handler());
        self::assertSame('Photos@update', $radixTree->match('PATCH', '/photos')->route()->handler());
    }

    public function testMethodNotAllowedUnionsEveryMethodOnThePattern(): void
    {
        $radixTree = RadixTree::compile([
            ['path' => '/photos', 'method' => ['GET'], 'handler' => 'Photos@index'],
            ['path' => '/photos', 'method' => ['POST'], 'handler' => 'Photos@store'],
            ['path' => '/photos', 'method' => ['PUT', 'PATCH'], 'handler' => 'Photos@update'],
        ]);

        $matchResult = $radixTree->match('DELETE', '/photos');

        self::assertTrue($matchResult->isMethodNotAllowed());
        self::assertSame(['GET', 'PATCH', 'POST', 'PUT'], $matchResult->allowedMethods());
    }

    public function testRejectsAMethodOverlapBetweenMethodSets(): void
    {
        try {
            RadixTree::compile([
                ['path' => '/x', 'method' => ['GET', 'POST'], 'handler' => 'X@any'],
                ['path' => '/x', 'method' => ['POST'], 'handler' => 'X@store'],
            ]);
            self::fail('An overlapping method set on one pattern must raise a ConfigException.');
        } catch (ConfigException $configException) {
            self::assertSame(
                'The route pattern "/x" duplicates the method "POST" of the registered pattern "/x".',
                $configException->getMessage(),
            );
        }
    }

    public function testDisjointRoutesKeepTheirOwnReverseRoutingNames(): void
    {
        $radixTree = RadixTree::compile([
            ['path' => '/x', 'method' => ['GET'], 'handler' => 'X@show', 'name' => 'x.show'],
            ['path' => '/x', 'method' => ['POST'], 'handler' => 'X@store', 'name' => 'x.store'],
        ]);

        self::assertSame('X@show', $radixTree->route('x.show')->handler());
        self::assertSame('X@store', $radixTree->route('x.store')->handler());
    }

    public function testConstraintFailuresNarrowCandidacyRouteByRoute(): void
    {
        $radixTree = RadixTree::compile([
            ['path' => '/n/{id}', 'method' => ['GET'], 'handler' => 'N@show', 'constraints' => ['id' => '[0-9]+']],
            ['path' => '/n/{id}', 'method' => ['POST'], 'handler' => 'N@store'],
        ]);

        self::assertSame('N@store', $radixTree->match('POST', '/n/abc')->route()->handler());
        self::assertSame('N@show', $radixTree->match('GET', '/n/42')->route()->handler());
        self::assertSame('N@store', $radixTree->match('POST', '/n/42')->route()->handler());

        $matchResult = $radixTree->match('GET', '/n/abc');

        self::assertTrue($matchResult->isMethodNotAllowed());
        self::assertSame(['POST'], $matchResult->allowedMethods());
    }

    public function testAMiddleConstraintFailureKeepsLaterCandidates(): void
    {
        $radixTree = RadixTree::compile([
            ['path' => '/m/{id}', 'method' => ['GET'], 'handler' => 'M@show', 'constraints' => ['id' => '[0-9]+']],
            ['path' => '/m/{id}', 'method' => ['POST'], 'handler' => 'M@store', 'constraints' => ['id' => '[a-z]+']],
            ['path' => '/m/{id}', 'method' => ['PUT'], 'handler' => 'M@update'],
        ]);

        $matchResult = $radixTree->match('PUT', '/m/42');

        self::assertTrue($matchResult->isFound());
        self::assertSame('M@update', $matchResult->route()->handler());
        self::assertSame(['id' => '42'], $matchResult->parameters());
    }

    public function testRejectsADuplicateCanonicalPattern(): void
    {
        try {
            RadixTree::compile([
                ['path' => '/x/{a}', 'method' => ['GET'], 'handler' => 'X@one'],
                ['path' => '/x/{b}', 'method' => ['GET'], 'handler' => 'X@two'],
            ]);
            self::fail('A parameter-blind duplicated pattern must raise a ConfigException.');
        } catch (ConfigException $configException) {
            self::assertSame(
                'The route pattern "/x/{b}" duplicates the method "GET" of the registered pattern "/x/{a}".',
                $configException->getMessage(),
            );
        }
    }

    public function testRejectsADuplicateRouteName(): void
    {
        try {
            RadixTree::compile([
                ['path' => '/one', 'method' => ['GET'], 'handler' => 'One@a', 'name' => 'same'],
                ['path' => '/two', 'method' => ['GET'], 'handler' => 'Two@b', 'name' => 'same'],
            ]);
            self::fail('A duplicated route name must raise a ConfigException.');
        } catch (ConfigException $configException) {
            self::assertSame(
                'The route name "same" duplicates the name of the pattern "/one".',
                $configException->getMessage(),
            );
        }
    }
}
