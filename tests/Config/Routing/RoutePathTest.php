<?php

declare(strict_types=1);

namespace Zef\Test\Config\Routing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Config\ConfigException;
use Zef\Framework\Config\Routing\PathBraceScanner;
use Zef\Framework\Config\Routing\PathSegment;
use Zef\Framework\Config\Routing\RoutePath;
use Zef\Framework\Config\Routing\RoutePathGrammar;

/**
 * @internal
 */
#[CoversClass(RoutePath::class)]
#[CoversClass(RoutePathGrammar::class)]
#[CoversClass(PathBraceScanner::class)]
#[CoversClass(PathSegment::class)]
final class RoutePathTest extends TestCase
{
    public function testParsesTheRootPath(): void
    {
        $routePath = RoutePath::parse('/');

        self::assertSame([], $routePath->segments());
        self::assertSame([], $routePath->parameterNames());
        self::assertSame('/', $routePath->canonical());
        self::assertSame('/', $routePath->pattern());
    }

    public function testKeepsThePatternVerbatim(): void
    {
        $routePath = RoutePath::parse('/users/{id}/posts');

        self::assertSame('/users/{id}/posts', $routePath->pattern());
        self::assertSame('/users/{}/posts', $routePath->canonical());
    }

    public function testParsesAStaticPath(): void
    {
        self::assertSame(['users'], $this->render(RoutePath::parse('/users')));
    }

    public function testParsesAMultiSegmentStaticPath(): void
    {
        self::assertSame(['api', 'v1', 'users'], $this->render(RoutePath::parse('/api/v1/users')));
    }

    public function testParsesATrailingParameter(): void
    {
        $routePath = RoutePath::parse('/users/{id}');

        self::assertSame(['users', '{id}'], $this->render($routePath));
        self::assertSame(['id'], $routePath->parameterNames());
    }

    public function testParsesAMiddleParameter(): void
    {
        $routePath = RoutePath::parse('/users/{id}/posts');

        self::assertSame(['users', '{id}', 'posts'], $this->render($routePath));
        self::assertSame(['id'], $routePath->parameterNames());
    }

    public function testParsesAdjacentParameters(): void
    {
        $routePath = RoutePath::parse('/{a}/{b}');

        self::assertSame(['{a}', '{b}'], $this->render($routePath));
        self::assertSame(['a', 'b'], $routePath->parameterNames());
    }

    public function testParsesUnderscoreAndDigitParameterNames(): void
    {
        $routePath = RoutePath::parse('/{_x}/{x9}/{x_1}');

        self::assertSame(['{_x}', '{x9}', '{x_1}'], $this->render($routePath));
        self::assertSame(['_x', 'x9', 'x_1'], $routePath->parameterNames());
    }

    public function testParsesStaticTextWithUnusualCharacters(): void
    {
        self::assertSame(
            ['my-page.v2', 'UPPER', 'ähm', 'a b'],
            $this->render(RoutePath::parse('/my-page.v2/UPPER/ähm/a b')),
        );
    }

    public function testParameterNamesFollowPathOrder(): void
    {
        self::assertSame(
            ['a', 'b'],
            RoutePath::parse('/{a}/between/{b}/end')->parameterNames(),
        );
    }

    public function testCanonicalRendersParametersBlind(): void
    {
        self::assertSame('/users/{}', RoutePath::parse('/users/{id}')->canonical());
        self::assertSame(
            '/users/{}/posts/{}',
            RoutePath::parse('/users/{id}/posts/{postId}')->canonical(),
        );
    }

    public function testCanonicalIsStableForStaticPaths(): void
    {
        self::assertSame('/api/v1/users', RoutePath::parse('/api/v1/users')->canonical());
    }

    public function testCanonicalIgnoresParameterNames(): void
    {
        self::assertSame(
            RoutePath::parse('/x/{a}')->canonical(),
            RoutePath::parse('/x/{b}')->canonical(),
        );
    }

    public function testRejectsAnEmptyPath(): void
    {
        $this->assertMalformed('', 'The route path "" is empty.');
    }

    public function testRejectsAPathWithoutALeadingSlash(): void
    {
        $this->assertMalformed(
            'users',
            'The route path "users" must start with a slash.',
        );
    }

    public function testRejectsATrailingSlash(): void
    {
        $this->assertMalformed(
            '/users/',
            'The route path "/users/" must not end with a slash.',
        );
    }

    public function testRejectsTheLoneDoubleSlash(): void
    {
        $this->assertMalformed(
            '//',
            'The route path "//" must not end with a slash.',
        );
    }

    public function testRejectsAnEmptySegment(): void
    {
        $this->assertMalformed(
            '/a//b',
            'The route path "/a//b" must not contain an empty segment.',
        );
    }

    public function testRejectsAnEmptySegmentAfterAPrecedingSlashOnlySegment(): void
    {
        $this->assertMalformed(
            '/{a}//{b}',
            'The route path "/{a}//{b}" must not contain an empty segment.',
        );
    }

    public function testRejectsAStrayClosingBraceAfterAParameter(): void
    {
        $this->assertMalformed(
            '/{a}}',
            'The route path "/{a}}" has an unmatched closing brace.',
        );
    }

    public function testRejectsAStrayClosingBraceInsideTheLastParameterPart(): void
    {
        $this->assertMalformed(
            '/{a}/{b}}',
            'The route path "/{a}/{b}}" has an unmatched closing brace.',
        );
    }

    public function testRejectsAnUnmatchedClosingBrace(): void
    {
        $this->assertMalformed(
            '/a/}b',
            'The route path "/a/}b" has an unmatched closing brace.',
        );
    }

    public function testRejectsAClosingBraceInAStaticSegment(): void
    {
        $this->assertMalformed(
            '/a/b}',
            'The route path "/a/b}" has an unmatched closing brace.',
        );
    }

    public function testRejectsAClosingBraceBeforeAnOpeningBrace(): void
    {
        $this->assertMalformed(
            '/x/}a{b}',
            'The route path "/x/}a{b}" has an unmatched closing brace.',
        );
    }

    public function testRejectsAnUnclosedParameterBrace(): void
    {
        $this->assertMalformed(
            '/a/{x',
            'The route path "/a/{x" has an unclosed parameter brace.',
        );
    }

    public function testRejectsAnOpeningBraceInsideAStaticSegment(): void
    {
        $this->assertMalformed(
            '/a/x{y',
            'The route path "/a/x{y" has an unclosed parameter brace.',
        );
    }

    public function testRejectsABraceThatDoesNotStartAWholeSegmentParameter(): void
    {
        $this->assertMalformed(
            '/users{id}',
            'The route path "/users{id}" has a segment "users{id}" whose braces do not form a whole parameter.',
        );
    }

    public function testRejectsABraceThatDoesNotEndAWholeSegmentParameter(): void
    {
        $this->assertMalformed(
            '/{a}b',
            'The route path "/{a}b" has a segment "{a}b" whose braces do not form a whole parameter.',
        );
    }

    public function testRejectsTwoParametersSharingOneSegment(): void
    {
        $this->assertMalformed(
            '/{a}{b}',
            'The route path "/{a}{b}" has a segment "{a}{b}" whose braces do not form a whole parameter.',
        );
    }

    public function testRejectsNestedOpeningBraces(): void
    {
        $this->assertMalformed(
            '/{a{b}',
            'The route path "/{a{b}" has a nested parameter brace.',
        );
    }

    public function testRejectsNestedBracesWithABalancedPairInside(): void
    {
        $this->assertMalformed(
            '/{a{b}c}',
            'The route path "/{a{b}c}" has a nested parameter brace.',
        );
    }

    public function testRejectsAnEmptyParameterName(): void
    {
        $this->assertMalformed(
            '/{}',
            'The route path "/{}" has an empty parameter name.',
        );
    }

    public function testRejectsAnInvalidParameterName(): void
    {
        $this->assertMalformed(
            '/{a-b}',
            'The route path "/{a-b}" has an invalid parameter name "a-b".',
        );
    }

    public function testRejectsAParameterNameContainingASlash(): void
    {
        $this->assertMalformed(
            '/{a/b}',
            'The route path "/{a/b}" has a parameter brace that spans more than one segment.',
        );
    }

    public function testRejectsAParameterBraceSpanningTheTrailingSlash(): void
    {
        $this->assertMalformed(
            '/{a/}',
            'The route path "/{a/}" has a parameter brace that spans more than one segment.',
        );
    }

    public function testRejectsARepeatedParameterName(): void
    {
        $this->assertMalformed(
            '/{x}/{x}',
            'The route path "/{x}/{x}" repeats the parameter name "x".',
        );
    }

    /**
     * @return list<string>
     */
    private function render(RoutePath $routePath): array
    {
        $rendered = [];

        foreach ($routePath->segments() as $pathSegment) {
            $rendered[] = $pathSegment->isParameter()
                ? '{' . $pathSegment->value() . '}'
                : $pathSegment->value();
        }

        return $rendered;
    }

    private function assertMalformed(string $path, string $message): void
    {
        try {
            RoutePath::parse($path);
            self::fail(\sprintf('The route path "%s" must raise a ConfigException.', $path));
        } catch (ConfigException $configException) {
            self::assertSame($message, $configException->getMessage());
        }
    }
}
