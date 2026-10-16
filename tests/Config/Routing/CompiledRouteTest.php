<?php

declare(strict_types=1);

namespace Zef\Test\Config\Routing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Config\ConfigException;
use Zef\Framework\Config\Routing\CompiledRoute;
use Zef\Framework\Config\Routing\RouteConstraints;
use Zef\Framework\Config\Routing\RouteEntryCollections;
use Zef\Framework\Config\Routing\RouteEntryMeta;
use Zef\Framework\Config\Routing\RouteEntryShape;

/**
 * @internal
 */
#[CoversClass(CompiledRoute::class)]
#[CoversClass(RouteEntryShape::class)]
#[CoversClass(RouteEntryCollections::class)]
#[CoversClass(RouteEntryMeta::class)]
#[CoversClass(RouteConstraints::class)]
final class CompiledRouteTest extends TestCase
{
    public function testCompilesAMinimalEntry(): void
    {
        $compiledRoute = CompiledRoute::fromArray([
            'path' => '/health',
            'method' => ['get'],
            'handler' => 'HealthController@check',
        ]);

        self::assertSame('/health', $compiledRoute->path()->pattern());
        self::assertSame(['GET'], $compiledRoute->methods());
        self::assertSame('HealthController@check', $compiledRoute->handler());
        self::assertNull($compiledRoute->name());
        self::assertSame([], $compiledRoute->middleware());
        self::assertSame([], $compiledRoute->meta());
        self::assertTrue($compiledRoute->constraints()->check('id', 'anything'));
    }

    public function testCompilesAFullEntry(): void
    {
        $compiledRoute = CompiledRoute::fromArray([
            'path' => '/users/{id}/posts',
            'method' => ['post', 'GET'],
            'handler' => 'UserController@store',
            'name' => 'users.posts.store',
            'middleware' => ['auth', 'throttle'],
            'constraints' => ['id' => '[0-9]+'],
            'meta' => ['api' => true, 'version' => 2],
        ]);

        self::assertSame('/users/{id}/posts', $compiledRoute->path()->pattern());
        self::assertSame(['id'], $compiledRoute->path()->parameterNames());
        self::assertSame(['GET', 'POST'], $compiledRoute->methods());
        self::assertSame('UserController@store', $compiledRoute->handler());
        self::assertSame('users.posts.store', $compiledRoute->name());
        self::assertSame(['auth', 'throttle'], $compiledRoute->middleware());
        self::assertSame(['api' => true, 'version' => 2], $compiledRoute->meta());
        self::assertTrue($compiledRoute->constraints()->check('id', '42'));
        self::assertFalse($compiledRoute->constraints()->check('id', '42a'));
    }

    public function testAConstraintRefusesATrailingNewline(): void
    {
        // A `$` anchor matches before a trailing newline -- the one
        // value a fully-anchored constraint must never wave past
        // itself: the segment "42\n" is not a value `[0-9]+` matched
        // whole, so the constraint's own contract refuses it. The
        // anchor is `\z`, the absolute end.
        $compiledRoute = CompiledRoute::fromArray([
            'path' => '/users/{id}',
            'method' => ['GET'],
            'handler' => 'Users@show',
            'constraints' => ['id' => '[0-9]+'],
        ]);

        self::assertFalse($compiledRoute->constraints()->check('id', "42\n"));
        self::assertFalse($compiledRoute->constraints()->check('id', "42\n\n"));
        self::assertTrue($compiledRoute->constraints()->check('id', '42'));
    }

    public function testNormalizesMethodsUpperCasedAndSorted(): void
    {
        $compiledRoute = CompiledRoute::fromArray([
            'path' => '/a',
            'method' => ['post', 'GET', 'Delete'],
            'handler' => 'H@a',
        ]);

        self::assertSame(['DELETE', 'GET', 'POST'], $compiledRoute->methods());
    }

    public function testUpperCasesMethodsWithUnicode(): void
    {
        $compiledRoute = CompiledRoute::fromArray([
            'path' => '/a',
            'method' => ['gët'],
            'handler' => 'H@a',
        ]);

        self::assertSame(['GËT'], $compiledRoute->methods());
    }

    public function testRejectsARepeatedMethodAfterUpperCasing(): void
    {
        $this->assertRejected(
            ['path' => '/a', 'method' => ['get', 'GET'], 'handler' => 'H@a'],
            'The route entry field "method" must not repeat the method "GET".',
        );
    }

    public function testRejectsAnEntryThatIsNotAnArray(): void
    {
        $this->assertRejected(
            '/users',
            'The route entry must be an array, got string.',
        );
    }

    public function testRejectsANullEntry(): void
    {
        $this->assertRejected(
            null,
            'The route entry must be an array, got null.',
        );
    }

    public function testRejectsAnUnknownField(): void
    {
        $this->assertRejected(
            ['path' => '/a', 'method' => ['GET'], 'handler' => 'H@a', 'controlls' => []],
            'The route entry field "controlls" is unknown.',
        );
    }

    public function testRejectsAMissingPath(): void
    {
        $this->assertRejected(
            ['method' => ['GET'], 'handler' => 'H@a'],
            'The route entry field "path" is required.',
        );
    }

    public function testRejectsAPathThatIsNotAString(): void
    {
        $this->assertRejected(
            ['path' => 42, 'method' => ['GET'], 'handler' => 'H@a'],
            'The route entry field "path" must be a string, got int.',
        );
    }

    public function testRejectsAnEmptyPath(): void
    {
        $this->assertRejected(
            ['path' => '', 'method' => ['GET'], 'handler' => 'H@a'],
            'The route entry field "path" must not be empty.',
        );
    }

    public function testRejectsAMalformedPath(): void
    {
        $this->assertRejected(
            ['path' => 'users', 'method' => ['GET'], 'handler' => 'H@a'],
            'The route path "users" must start with a slash.',
        );
    }

    public function testRejectsAMissingHandler(): void
    {
        $this->assertRejected(
            ['path' => '/a', 'method' => ['GET']],
            'The route entry field "handler" is required.',
        );
    }

    public function testRejectsAnEmptyHandler(): void
    {
        $this->assertRejected(
            ['path' => '/a', 'method' => ['GET'], 'handler' => ''],
            'The route entry field "handler" must not be empty.',
        );
    }

    public function testRejectsAMissingMethod(): void
    {
        $this->assertRejected(
            ['path' => '/a', 'handler' => 'H@a'],
            'The route entry field "method" is required.',
        );
    }

    public function testRejectsAMethodThatIsNotAnArray(): void
    {
        $this->assertRejected(
            ['path' => '/a', 'method' => 'GET', 'handler' => 'H@a'],
            'The route entry field "method" must be a list of non-empty strings.',
        );
    }

    public function testRejectsAMethodMap(): void
    {
        $this->assertRejected(
            ['path' => '/a', 'method' => ['GET' => 'yes'], 'handler' => 'H@a'],
            'The route entry field "method" must be a list of non-empty strings.',
        );
    }

    public function testRejectsAnEmptyMethodList(): void
    {
        $this->assertRejected(
            ['path' => '/a', 'method' => [], 'handler' => 'H@a'],
            'The route entry field "method" must not be empty.',
        );
    }

    public function testRejectsAMethodItemThatIsNotAString(): void
    {
        $this->assertRejected(
            ['path' => '/a', 'method' => [42], 'handler' => 'H@a'],
            'The route entry field "method" must be a string, got int.',
        );
    }

    public function testRejectsAnEmptyMethodItem(): void
    {
        $this->assertRejected(
            ['path' => '/a', 'method' => [''], 'handler' => 'H@a'],
            'The route entry field "method" must not be empty.',
        );
    }

    public function testRejectsANameThatIsNotAString(): void
    {
        $this->assertRejected(
            ['path' => '/a', 'method' => ['GET'], 'handler' => 'H@a', 'name' => 7],
            'The route entry field "name" must be a string, got int.',
        );
    }

    public function testRejectsAnEmptyName(): void
    {
        $this->assertRejected(
            ['path' => '/a', 'method' => ['GET'], 'handler' => 'H@a', 'name' => ''],
            'The route entry field "name" must not be empty.',
        );
    }

    public function testRejectsAMiddlewareThatIsNotAList(): void
    {
        $this->assertRejected(
            ['path' => '/a', 'method' => ['GET'], 'handler' => 'H@a', 'middleware' => 'auth'],
            'The route entry field "middleware" must be a list of non-empty strings.',
        );
    }

    public function testRejectsAnEmptyMiddlewareList(): void
    {
        $this->assertRejected(
            ['path' => '/a', 'method' => ['GET'], 'handler' => 'H@a', 'middleware' => []],
            'The route entry field "middleware" must not be empty.',
        );
    }

    public function testRejectsAnEmptyMiddlewareItem(): void
    {
        $this->assertRejected(
            ['path' => '/a', 'method' => ['GET'], 'handler' => 'H@a', 'middleware' => ['']],
            'The route entry field "middleware" must not be empty.',
        );
    }

    public function testRejectsConstraintsThatAreNotAnArray(): void
    {
        $this->assertRejected(
            ['path' => '/a/{id}', 'method' => ['GET'], 'handler' => 'H@a', 'constraints' => '[0-9]+'],
            'The route entry field "constraints" must be a map of parameter names to patterns.',
        );
    }

    public function testRejectsConstraintsThatAreAList(): void
    {
        $this->assertRejected(
            ['path' => '/a/{id}', 'method' => ['GET'], 'handler' => 'H@a', 'constraints' => ['[0-9]+']],
            'The route entry field "constraints" must be a map of parameter names to patterns.',
        );
    }

    public function testRejectsAnEmptyConstraintsMap(): void
    {
        $this->assertRejected(
            ['path' => '/a/{id}', 'method' => ['GET'], 'handler' => 'H@a', 'constraints' => []],
            'The route entry field "constraints" must not be empty.',
        );
    }

    public function testRejectsANonSequentialIntegerConstraintKey(): void
    {
        $this->assertRejected(
            ['path' => '/a/{id}', 'method' => ['GET'], 'handler' => 'H@a', 'constraints' => [1 => '[0-9]+']],
            'The route entry field "constraints" must use parameter names as keys.',
        );
    }

    public function testRejectsAConstraintValueThatIsNotAString(): void
    {
        $this->assertRejected(
            ['path' => '/a/{id}', 'method' => ['GET'], 'handler' => 'H@a', 'constraints' => ['id' => 0]],
            'The route entry field "constraints" must be a string, got int.',
        );
    }

    public function testRejectsAnEmptyConstraintValue(): void
    {
        $this->assertRejected(
            ['path' => '/a/{id}', 'method' => ['GET'], 'handler' => 'H@a', 'constraints' => ['id' => '']],
            'The route entry field "constraints" must not be empty.',
        );
    }

    public function testRejectsAMetaThatIsNotAnArray(): void
    {
        $this->assertRejected(
            ['path' => '/a', 'method' => ['GET'], 'handler' => 'H@a', 'meta' => 'v1'],
            'The route entry field "meta" must be a map, got string.',
        );
    }

    public function testRejectsAnEmptyMetaMap(): void
    {
        $this->assertRejected(
            ['path' => '/a', 'method' => ['GET'], 'handler' => 'H@a', 'meta' => []],
            'The route entry field "meta" must not be empty.',
        );
    }

    public function testRejectsAConstraintForAnUnknownParameter(): void
    {
        $this->assertRejected(
            [
                'path' => '/users/{id}',
                'method' => ['GET'],
                'handler' => 'H@a',
                'constraints' => ['other' => '[0-9]+'],
            ],
            'The route pattern "/users/{id}" constrains the unknown parameter "other".',
        );
    }

    public function testRejectsAnInvalidConstraintPattern(): void
    {
        $this->assertRejected(
            [
                'path' => '/users/{id}',
                'method' => ['GET'],
                'handler' => 'H@a',
                'constraints' => ['id' => '[0-9+'],
            ],
            'The route pattern "/users/{id}" has an invalid constraint pattern for the parameter "id".',
        );
    }

    public function testChecksConstraintsWithUnicodeValues(): void
    {
        $compiledRoute = CompiledRoute::fromArray([
            'path' => '/users/{name}',
            'method' => ['GET'],
            'handler' => 'H@a',
            'constraints' => ['name' => '[a-z]+'],
        ]);

        self::assertFalse($compiledRoute->constraints()->check('name', 'Änna'));
        self::assertTrue($compiledRoute->constraints()->check('name', 'anna'));
    }

    public function testChecksConstraintsThatBringTheirOwnAnchors(): void
    {
        $compiledRoute = CompiledRoute::fromArray([
            'path' => '/a/{x}',
            'method' => ['GET'],
            'handler' => 'H@a',
            'constraints' => ['x' => '^a+$'],
        ]);

        self::assertTrue($compiledRoute->constraints()->check('x', 'aaa'));
        self::assertFalse($compiledRoute->constraints()->check('x', 'aab'));
    }

    public function testCompilesMultipleConstraintsOnOneRoute(): void
    {
        $compiledRoute = CompiledRoute::fromArray([
            'path' => '/users/{id}/posts/{slug}',
            'method' => ['GET'],
            'handler' => 'H@show',
            'constraints' => ['id' => '[0-9]+', 'slug' => '[a-z-]+'],
        ]);

        self::assertTrue($compiledRoute->constraints()->check('id', '42'));
        self::assertTrue($compiledRoute->constraints()->check('slug', 'hello-world'));
        self::assertFalse($compiledRoute->constraints()->check('id', '42a'));
        self::assertFalse($compiledRoute->constraints()->check('slug', 'Hello'));
    }

    private function assertRejected(mixed $route, string $message): void
    {
        try {
            CompiledRoute::fromArray($route);
            self::fail('The route entry must raise a ConfigException.');
        } catch (ConfigException $configException) {
            self::assertSame($message, $configException->getMessage());
        }
    }
}
