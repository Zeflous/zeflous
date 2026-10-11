<?php

declare(strict_types=1);

namespace Zef\Test\Config\Routing;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Stringable;
use Zef\Framework\Config\ConfigException;
use Zef\Framework\Config\Routing\CompiledRoute;
use Zef\Framework\Config\Routing\RadixTree;
use Zef\Framework\Config\Routing\RouteParameterRenderer;
use Zef\Framework\Config\Routing\RouteParameterSet;
use Zef\Framework\Config\Routing\RouteParameterString;
use Zef\Framework\Config\Routing\RouteUrlGenerator;
use Zef\Framework\Config\Routing\RouteUrlSegments;

/**
 * @internal
 */
#[CoversClass(RouteUrlGenerator::class)]
#[CoversClass(RouteUrlSegments::class)]
#[CoversClass(RouteParameterRenderer::class)]
#[CoversClass(RouteParameterString::class)]
#[CoversClass(RouteParameterSet::class)]
#[CoversClass(RadixTree::class)]
#[CoversClass(CompiledRoute::class)]
final class RouteUrlGeneratorTest extends TestCase
{
    public function testResolvesARegisteredName(): void
    {
        $radixTree = RadixTree::compile([
            ['path' => '/health', 'method' => ['GET'], 'handler' => 'Health@check', 'name' => 'health'],
        ]);

        self::assertSame('Health@check', $radixTree->route('health')->handler());
    }

    public function testRejectsAnUnknownName(): void
    {
        $radixTree = RadixTree::compile([
            ['path' => '/health', 'method' => ['GET'], 'handler' => 'Health@check', 'name' => 'health'],
        ]);

        $this->assertGeneratorRejects(
            $radixTree,
            'missing',
            [],
            'The route name "missing" is not registered.',
        );
    }

    public function testGeneratesAStaticRoute(): void
    {
        $radixTree = RadixTree::compile([
            ['path' => '/health', 'method' => ['GET'], 'handler' => 'Health@check', 'name' => 'health'],
        ]);

        self::assertSame('/health', new RouteUrlGenerator($radixTree)->generate('health', []));
    }

    public function testGeneratesTheRootRoute(): void
    {
        $radixTree = RadixTree::compile([
            ['path' => '/', 'method' => ['GET'], 'handler' => 'Root@index', 'name' => 'root'],
        ]);

        self::assertSame('/', new RouteUrlGenerator($radixTree)->generate('root', []));
    }

    public function testGeneratesAParameterisedRoute(): void
    {
        $radixTree = RadixTree::compile([
            ['path' => '/users/{id}', 'method' => ['GET'], 'handler' => 'Users@show', 'name' => 'users.show'],
        ]);

        self::assertSame(
            '/users/42',
            new RouteUrlGenerator($radixTree)->generate('users.show', ['id' => 42]),
        );
    }

    public function testGeneratesAMultiParameterRoute(): void
    {
        $radixTree = RadixTree::compile([
            [
                'path' => '/users/{id}/posts/{slug}',
                'method' => ['GET'],
                'handler' => 'Posts@show',
                'name' => 'posts.show',
            ],
        ]);

        self::assertSame(
            '/users/42/posts/hello-world',
            new RouteUrlGenerator($radixTree)->generate('posts.show', ['id' => '42', 'slug' => 'hello-world']),
        );
    }

    public function testRejectsAMissingParameter(): void
    {
        $radixTree = RadixTree::compile([
            ['path' => '/users/{id}', 'method' => ['GET'], 'handler' => 'Users@show', 'name' => 'users.show'],
        ]);

        $this->assertGeneratorRejects(
            $radixTree,
            'users.show',
            [],
            'The route "/users/{id}" requires the parameter "id".',
        );
    }

    public function testRejectsAnExcessParameter(): void
    {
        $radixTree = RadixTree::compile([
            ['path' => '/users/{id}', 'method' => ['GET'], 'handler' => 'Users@show', 'name' => 'users.show'],
        ]);

        $this->assertGeneratorRejects(
            $radixTree,
            'users.show',
            ['id' => '42', 'extra' => 'x'],
            'The route "/users/{id}" does not accept the parameter "extra".',
        );
    }

    public function testRejectsANonScalarValue(): void
    {
        $radixTree = RadixTree::compile([
            ['path' => '/users/{id}', 'method' => ['GET'], 'handler' => 'Users@show', 'name' => 'users.show'],
        ]);

        $this->assertGeneratorRejects(
            $radixTree,
            'users.show',
            ['id' => [42]],
            'The parameter "id" of the route "/users/{id}" must be scalar or Stringable, got array.',
        );
    }

    public function testAcceptsAStringableValue(): void
    {
        $radixTree = RadixTree::compile([
            ['path' => '/users/{id}', 'method' => ['GET'], 'handler' => 'Users@show', 'name' => 'users.show'],
        ]);

        $identifier = new class implements Stringable {
            #[Override]
            public function __toString(): string
            {
                return '42';
            }
        };

        self::assertSame(
            '/users/42',
            new RouteUrlGenerator($radixTree)->generate('users.show', ['id' => $identifier]),
        );
    }

    public function testRejectsAnEmptyValue(): void
    {
        $radixTree = RadixTree::compile([
            ['path' => '/users/{id}', 'method' => ['GET'], 'handler' => 'Users@show', 'name' => 'users.show'],
        ]);

        $this->assertGeneratorRejects(
            $radixTree,
            'users.show',
            ['id' => ''],
            'The parameter "id" of the route "/users/{id}" must not be empty.',
        );
    }

    public function testRejectsAValueTheConstraintRefuses(): void
    {
        $radixTree = RadixTree::compile([
            [
                'path' => '/users/{id}',
                'method' => ['GET'],
                'handler' => 'Users@show',
                'name' => 'users.show',
                'constraints' => ['id' => '[0-9]+'],
            ],
        ]);

        $this->assertGeneratorRejects(
            $radixTree,
            'users.show',
            ['id' => '42a'],
            'The value "42a" of the parameter "id" violates the constraint of the route "/users/{id}".',
        );
    }

    public function testGeneratesAValueTheConstraintAccepts(): void
    {
        $radixTree = RadixTree::compile([
            [
                'path' => '/users/{id}',
                'method' => ['GET'],
                'handler' => 'Users@show',
                'name' => 'users.show',
                'constraints' => ['id' => '[0-9]+'],
            ],
        ]);

        self::assertSame(
            '/users/42',
            new RouteUrlGenerator($radixTree)->generate('users.show', ['id' => '42']),
        );
    }

    public function testEncodesValuesForTheUrl(): void
    {
        $radixTree = RadixTree::compile([
            ['path' => '/files/{name}', 'method' => ['GET'], 'handler' => 'Files@show', 'name' => 'files.show'],
        ]);

        self::assertSame(
            '/files/hello%20world%21',
            new RouteUrlGenerator($radixTree)->generate('files.show', ['name' => 'hello world!']),
        );
    }

    public function testEncodesUnicodeValues(): void
    {
        $radixTree = RadixTree::compile([
            ['path' => '/u/{label}', 'method' => ['GET'], 'handler' => 'U@show', 'name' => 'u.show'],
        ]);

        self::assertSame(
            '/u/%C3%A4nna',
            new RouteUrlGenerator($radixTree)->generate('u.show', ['label' => 'änna']),
        );
    }

    public function testRejectsAnyNameOnAnEmptyTree(): void
    {
        $radixTree = RadixTree::compile([]);

        $this->assertGeneratorRejects(
            $radixTree,
            'anything',
            [],
            'The route name "anything" is not registered.',
        );
    }

    public function testGeneratedUrlsResolveBackToTheirRoute(): void
    {
        $radixTree = RadixTree::compile([
            [
                'path' => '/users/{id}/posts/{slug}',
                'method' => ['GET', 'POST'],
                'handler' => 'Posts@show',
                'name' => 'posts.show',
                'constraints' => ['id' => '[0-9]+', 'slug' => '[a-z-]+'],
            ],
            ['path' => '/health', 'method' => ['GET'], 'handler' => 'Health@check', 'name' => 'health'],
        ]);
        $routeUrlGenerator = new RouteUrlGenerator($radixTree);

        $url = $routeUrlGenerator->generate('posts.show', ['id' => '42', 'slug' => 'hello-world']);

        $matchResult = $radixTree->match('GET', $url);

        self::assertTrue($matchResult->isFound());
        self::assertSame('Posts@show', $matchResult->route()->handler());
        self::assertSame(['id' => '42', 'slug' => 'hello-world'], $matchResult->parameters());
    }

    public function testGeneratedStaticUrlsResolveBackToTheirRoute(): void
    {
        $radixTree = RadixTree::compile([
            ['path' => '/health', 'method' => ['GET'], 'handler' => 'Health@check', 'name' => 'health'],
        ]);
        $routeUrlGenerator = new RouteUrlGenerator($radixTree);

        $matchResult = $radixTree->match('GET', $routeUrlGenerator->generate('health', []));

        self::assertTrue($matchResult->isFound());
        self::assertSame('Health@check', $matchResult->route()->handler());
        self::assertSame([], $matchResult->parameters());
    }

    public function testGeneratedUrlsOnABacktrackingTreeResolveBack(): void
    {
        $radixTree = RadixTree::compile([
            ['path' => '/u/static', 'method' => ['GET'], 'handler' => 'Static@show', 'name' => 'static'],
            [
                'path' => '/u/{a}/deep',
                'method' => ['GET'],
                'handler' => 'Deep@show',
                'name' => 'deep',
                'constraints' => ['a' => '[a-z]+'],
            ],
        ]);
        $routeUrlGenerator = new RouteUrlGenerator($radixTree);

        $matchResult = $radixTree->match('GET', $routeUrlGenerator->generate('deep', ['a' => 'zeta']));

        self::assertTrue($matchResult->isFound());
        self::assertSame('Deep@show', $matchResult->route()->handler());
        self::assertSame(['a' => 'zeta'], $matchResult->parameters());
    }

    public function testAGeneratedEncodedUrlResolvesBackToItsRoute(): void
    {
        $radixTree = RadixTree::compile([
            ['path' => '/files/{name}', 'method' => ['GET'], 'handler' => 'Files@show', 'name' => 'files.show'],
        ]);
        $routeUrlGenerator = new RouteUrlGenerator($radixTree);

        $matchResult = $radixTree->match('GET', $routeUrlGenerator->generate('files.show', ['name' => 'hello world!']));

        self::assertTrue($matchResult->isFound());
        self::assertSame('Files@show', $matchResult->route()->handler());
        self::assertSame(['name' => 'hello%20world%21'], $matchResult->parameters());
    }

    /**
     * @param array<string, mixed> $params intentionally invalid shapes included
     */
    private function assertGeneratorRejects(
        RadixTree $radixTree,
        string $name,
        array $params,
        string $message,
    ): void {
        try {
            new RouteUrlGenerator($radixTree)->generate($name, $params);
            self::fail(\sprintf('Generating "%s" must raise a ConfigException.', $name));
        } catch (ConfigException $configException) {
            self::assertSame($message, $configException->getMessage());
        }
    }
}
