<?php

declare(strict_types=1);

namespace Zef\Test\Config\Routing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Config\ConfigException;
use Zef\Framework\Config\Routing\RadixTree;
use Zef\Framework\Config\Routing\RouteAcceptMatch;
use Zef\Framework\Config\Routing\RouteAcceptNegotiator;
use Zef\Framework\Config\Routing\RouteAcceptRange;
use Zef\Framework\Config\Routing\RouteAcceptRanges;
use Zef\Framework\Config\Routing\RouteProduces;

/**
 * The Accept negotiation contract end to end: the header's range grammar
 * (wildcard placement, quality grammar, parameter spacing), the
 * most-specific-range-wins rule (a specific q=0 exclusion cannot be
 * overridden by a wildcard, and equally specific ranges keep the best
 * quality), the declared produces grammar (case folding included --
 * the Kelvin sign folds to ASCII `k` under mb_strtolower only), and the
 * unparsable-header boundary (issue #782): a header that parses to no
 * range expresses nothing, so the route answers as if no Accept was sent
 * -- while one valid range beside the garbage still decides, and a
 * well-formed all-media zero-quality refusal still refuses everything.
 *
 * @internal
 */
#[CoversClass(RouteAcceptMatch::class)]
#[CoversClass(RouteAcceptNegotiator::class)]
#[CoversClass(RouteAcceptRange::class)]
#[CoversClass(RouteAcceptRanges::class)]
#[CoversClass(RouteProduces::class)]
final class RouteAcceptNegotiationTest extends TestCase
{
    public function testAFullyUnparsableAcceptHeaderPermitsEveryDeclaration(): void
    {
        $radixTree = $this->treeProducing('application/json');

        self::assertTrue($radixTree->match('GET', '/profile', null, 'banana')->isFound());
    }

    public function testAGarbageEntryBesideAValidRangeLeavesTheRangeDeciding(): void
    {
        $radixTree = $this->treeProducing('application/json');

        self::assertTrue($radixTree->match('GET', '/profile', null, 'banana, text/html')->isNotAcceptable());
    }

    public function testAValidAllMediaZeroQualityRefusalStillClosesTheTable(): void
    {
        $radixTree = $this->treeProducing('application/json');

        self::assertTrue($radixTree->match('GET', '/profile', null, '*/*;q=0')->isNotAcceptable());
    }

    public function testASeparatorOnlyAcceptHeaderExpressesNothing(): void
    {
        $radixTree = $this->treeProducing('application/json');

        self::assertTrue($radixTree->match('GET', '/profile', null, ', ,')->isFound());
    }

    public function testAWhitespaceOnlyAcceptHeaderExpressesNothing(): void
    {
        $radixTree = $this->treeProducing('application/json');

        self::assertTrue($radixTree->match('GET', '/profile', null, '   ')->isFound());
    }

    public function testATypeWildcardWithAConcreteSubtypeAloneExpressesNothing(): void
    {
        $radixTree = $this->treeProducing('application/json');

        self::assertTrue($radixTree->match('GET', '/profile', null, '*/json')->isFound());
    }

    public function testASpecificZeroQualityExclusionIsNotOverriddenByTheAllMediaWildcard(): void
    {
        $radixTree = $this->treeProducing('application/json');

        self::assertTrue($radixTree->match('GET', '/profile', null, 'application/*;q=0, */*')->isNotAcceptable());
    }

    public function testParametersAreTrimmedSoASpacedQualityStillNegotiates(): void
    {
        $radixTree = $this->treeProducing('application/json');

        self::assertTrue($radixTree->match('GET', '/profile', null, 'application/json ; q=0.5')->isFound());
    }

    public function testTheKelvinSignFoldsIntoAnAsciiMediaTypeRange(): void
    {
        $radixTree = $this->treeProducing('k/type');

        self::assertTrue($radixTree->match('GET', '/profile', null, "\u{212A}/type")->isFound());
    }

    public function testTheKelvinSignFoldsIntoADeclaredProducedMediaType(): void
    {
        $radixTree = $this->treeProducing("\u{212A}/type");

        self::assertTrue($radixTree->match('GET', '/profile', null, 'k/type')->isFound());
    }

    public function testALaterMatchingRangeIsStillConsideredAfterANonMatchingOne(): void
    {
        $radixTree = $this->treeProducing('text/plain');

        self::assertTrue($radixTree->match('GET', '/profile', null, 'application/json, */*')->isFound());
    }

    public function testEquallySpecificRangesKeepTheBestQuality(): void
    {
        $radixTree = $this->treeProducing('text/plain');

        self::assertTrue($radixTree->match('GET', '/profile', null, 'text/plain;q=0.9, text/plain;q=0.1')->isFound());
    }

    public function testARangeCoversAMediaTypeOnlyWhenBothSidesAgree(): void
    {
        $radixTree = $this->treeProducing('text/json');

        self::assertTrue($radixTree->match('GET', '/profile', null, 'application/json')->isNotAcceptable());
    }

    public function testAnAbsentAcceptHeaderPermitsEveryDeclaration(): void
    {
        $radixTree = $this->treeProducing('application/json');

        self::assertTrue($radixTree->match('GET', '/profile')->isFound());
    }

    public function testAnAcceptHeaderPermitsARouteWithoutDeclarations(): void
    {
        $radixTree = RadixTree::compile([
            ['path' => '/profile', 'method' => ['GET'], 'handler' => 'Profile@show'],
        ]);

        self::assertTrue($radixTree->match('GET', '/profile', null, 'text/html')->isFound());
    }

    public function testARejectedDeclarationStillNamesItsMediaTypes(): void
    {
        $radixTree = $this->treeProducing('application/json');

        $notAcceptable = $radixTree->match('GET', '/profile', null, 'text/html');

        self::assertSame(['application/json'], $notAcceptable->acceptableMediaTypes());
    }

    public function testAProducedMediaTypeSpellingIsFoldedBeforeValidation(): void
    {
        $radixTree = $this->treeProducing('APPLICATION/JSON');

        self::assertTrue($radixTree->match('GET', '/profile', null, 'application/json')->isFound());
    }

    public function testAProductWithTwoSlashesIsRefused(): void
    {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessageIsOrContains('contains an invalid media type');

        RadixTree::compile([
            ['path' => '/profile', 'method' => ['GET'], 'handler' => 'Profile@show', 'produces' => ['a/b/c']],
        ]);
    }

    public function testASpecificRangeOutranksAWildcardSubtypeOfTheSameType(): void
    {
        $radixTree = $this->treeProducing('application/json');

        self::assertTrue(
            $radixTree->match('GET', '/profile', null, 'application/json;q=0, application/*')->isNotAcceptable(),
        );
    }

    public function testASkippedTypeWildcardRangeDoesNotHideLaterRanges(): void
    {
        $radixTree = $this->treeProducing('application/json');

        self::assertTrue($radixTree->match('GET', '/profile', null, '*/json, application/json')->isFound());
    }

    public function testAMultibyteQualityValueSkipsItsRangeSoAValidExclusionDecides(): void
    {
        $radixTree = $this->treeProducing('k/type');

        self::assertTrue(
            $radixTree->match('GET', '/profile', null, "\u{212A}/type; q=\u{0130}, */*;q=0")->isNotAcceptable(),
        );
    }

    public function testAMultibyteQualityValueAloneExpressesNothing(): void
    {
        $radixTree = $this->treeProducing('k/type');

        self::assertTrue($radixTree->match('GET', '/profile', null, "\u{212A}/type; Q=\u{0130}")->isFound());
    }

    public function testEquallySpecificRangesKeepTheLastExcludedQualityOut(): void
    {
        $radixTree = $this->treeProducing('text/plain');

        self::assertTrue($radixTree->match('GET', '/profile', null, 'text/plain;q=0.5, text/plain;q=0')->isFound());
    }

    public function testARangeCoversAMediaTypeOnlyWhenTheSubtypeAlsoAgrees(): void
    {
        $radixTree = $this->treeProducing('text/json');

        self::assertTrue($radixTree->match('GET', '/profile', null, 'text/html')->isNotAcceptable());
    }

    public function testAnInvalidQualitySkipsTheRangeWithoutHidingLaterOnes(): void
    {
        $radixTree = $this->treeProducing('application/json');

        self::assertTrue($radixTree->match('GET', '/profile', null, 'application/json;q=bad, */*')->isFound());
    }

    private function treeProducing(string ...$produces): RadixTree
    {
        return RadixTree::compile([
            [
                'path' => '/profile',
                'method' => ['GET'],
                'handler' => 'Profile@show',
                'produces' => array_values($produces),
            ],
        ]);
    }
}
