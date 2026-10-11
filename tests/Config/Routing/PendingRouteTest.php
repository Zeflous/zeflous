<?php

declare(strict_types=1);

namespace Zef\Test\Config\Routing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Config\ConfigException;
use Zef\Framework\Config\Routing\PendingRoute;
use Zef\Framework\Config\Routing\RouteDslConstraints;
use Zef\Framework\Config\Routing\RouteDslFields;
use Zef\Framework\Config\Routing\RouteDslMiddleware;
use Zef\Framework\Config\Routing\RoutePath;

/**
 * @internal
 */
#[CoversClass(PendingRoute::class)]
#[CoversClass(RouteDslConstraints::class)]
#[CoversClass(RouteDslFields::class)]
#[CoversClass(RouteDslMiddleware::class)]
#[CoversClass(RoutePath::class)]
final class PendingRouteTest extends TestCase
{
    public function testChainedDeclarationsReturnTheDraft(): void
    {
        $draft = $this->draft();

        self::assertSame($draft, $draft->name('users.show'));
        self::assertSame($draft, $draft->where(['id' => '[0-9]+']));
        self::assertSame($draft, $draft->middleware('RateLimit'));
        self::assertSame($draft, $draft->meta(['version' => 'v1']));
    }

    public function testFreezesAMinimalEntryWithoutOptionalKeys(): void
    {
        $draft = $this->draft('/health', ['GET'], 'Health@check');

        self::assertSame(
            [
                'path' => '/health',
                'method' => ['GET'],
                'handler' => 'Health@check',
            ],
            $draft->toSchemaArray(),
        );
    }

    public function testFreezesAFullyDeclaredEntryInTheSchemaFieldOrder(): void
    {
        $draft = $this->draft('/users/{id}', ['GET'], 'Users@show', ['Logging']);
        $draft->name('users.show');
        $draft->where(['id' => '[0-9]+']);
        $draft->middleware('Auth', 'RateLimit');
        $draft->meta(['version' => 'v1']);

        self::assertSame(
            [
                'path' => '/users/{id}',
                'method' => ['GET'],
                'handler' => 'Users@show',
                'name' => 'users.show',
                'middleware' => ['Logging', 'Auth', 'RateLimit'],
                'constraints' => ['id' => '[0-9]+'],
                'meta' => ['version' => 'v1'],
            ],
            $draft->toSchemaArray(),
        );
    }

    public function testMiddlewareStacksScopeBeforeRouteLevel(): void
    {
        $draft = $this->draft('/users/{id}', ['GET'], 'Users@show', ['Global', 'Group']);
        $draft->middleware('Route');

        self::assertSame(['Global', 'Group', 'Route'], $draft->toSchemaArray()['middleware']);
    }

    public function testMiddlewareWithoutScopeOrRouteDeclarationsIsOmitted(): void
    {
        $draft = $this->draft('/health', ['GET'], 'Health@check');

        self::assertArrayNotHasKey('middleware', $draft->toSchemaArray());
    }

    public function testRejectsAnEmptyName(): void
    {
        try {
            $this->draft()->name('');
            self::fail('An empty name should have been rejected.');
        } catch (ConfigException $configException) {
            self::assertSame(
                'The route "/users/{id}" must not declare an empty name.',
                $configException->getMessage(),
            );
        }
    }

    public function testRejectsASecondName(): void
    {
        try {
            $this->draft()->name('users.show')->name('users.detail');
            self::fail('A second name should have been rejected.');
        } catch (ConfigException $configException) {
            self::assertSame(
                'The route "/users/{id}" already declares the name "users.show".',
                $configException->getMessage(),
            );
        }
    }

    public function testRejectsEmptyConstraints(): void
    {
        try {
            $this->draft()->where([]);
            self::fail('Empty constraints should have been rejected.');
        } catch (ConfigException $configException) {
            self::assertSame(
                'The route "/users/{id}" must not declare empty constraints.',
                $configException->getMessage(),
            );
        }
    }

    public function testRejectsConstraintsDeclaredAsAList(): void
    {
        try {
            $this->draft()->where(['[0-9]+']);
            self::fail('Constraints declared as a list should have been rejected.');
        } catch (ConfigException $configException) {
            self::assertSame(
                'The route "/users/{id}" must declare constraints as a parameter-name map.',
                $configException->getMessage(),
            );
        }
    }

    public function testRejectsASecondConstraintsDeclaration(): void
    {
        try {
            $this->draft()->where(['id' => '[0-9]+'])->where(['id' => '[a-z]+']);
            self::fail('A second constraints declaration should have been rejected.');
        } catch (ConfigException $configException) {
            self::assertSame(
                'The route "/users/{id}" already declares its constraints.',
                $configException->getMessage(),
            );
        }
    }

    public function testRejectsAConstraintForAnUnknownParameter(): void
    {
        try {
            $this->draft()->where(['slug' => '[a-z]+']);
            self::fail('A constraint for an unknown parameter should have been rejected.');
        } catch (ConfigException $configException) {
            self::assertSame(
                'The route "/users/{id}" has no parameter "slug" to constrain.',
                $configException->getMessage(),
            );
        }
    }

    public function testRejectsANonStringConstraintPattern(): void
    {
        try {
            $this->draft()->where(['id' => 42]);
            self::fail('A non-string constraint pattern should have been rejected.');
        } catch (ConfigException $configException) {
            self::assertSame(
                'The constraint "id" on route "/users/{id}" must be a non-empty pattern.',
                $configException->getMessage(),
            );
        }
    }

    public function testRejectsAnEmptyConstraintPattern(): void
    {
        try {
            $this->draft()->where(['id' => '']);
            self::fail('An empty constraint pattern should have been rejected.');
        } catch (ConfigException $configException) {
            self::assertSame(
                'The constraint "id" on route "/users/{id}" must be a non-empty pattern.',
                $configException->getMessage(),
            );
        }
    }

    public function testRejectsAnEmptyMiddlewareIdentifier(): void
    {
        try {
            $this->draft()->middleware('Auth', '');
            self::fail('An empty middleware identifier should have been rejected.');
        } catch (ConfigException $configException) {
            self::assertSame(
                'The route "/users/{id}" must not declare an empty middleware identifier.',
                $configException->getMessage(),
            );
        }
    }

    public function testRejectsEmptyMetadata(): void
    {
        try {
            $this->draft()->meta([]);
            self::fail('Empty metadata should have been rejected.');
        } catch (ConfigException $configException) {
            self::assertSame(
                'The route "/users/{id}" must declare metadata as a non-empty map.',
                $configException->getMessage(),
            );
        }
    }

    public function testRejectsMetadataDeclaredAsAList(): void
    {
        try {
            $this->draft()->meta(['v1', 'public']);
            self::fail('Metadata declared as a list should have been rejected.');
        } catch (ConfigException $configException) {
            self::assertSame(
                'The route "/users/{id}" must declare metadata as a non-empty map.',
                $configException->getMessage(),
            );
        }
    }

    public function testRejectsASecondMetadataDeclaration(): void
    {
        try {
            $this->draft()->meta(['version' => 'v1'])->meta(['tenant' => 'acme']);
            self::fail('A second metadata declaration should have been rejected.');
        } catch (ConfigException $configException) {
            self::assertSame(
                'The route "/users/{id}" already declares its metadata.',
                $configException->getMessage(),
            );
        }
    }

    public function testFreezesEveryConstraintOfAMultiParameterRoute(): void
    {
        $draft = self::draft('/docs/{lang}/{page}', ['GET'], 'Docs@show');
        $draft->where(['lang' => '[a-z]{2}', 'page' => '[a-z0-9-]+']);

        self::assertSame(
            ['lang' => '[a-z]{2}', 'page' => '[a-z0-9-]+'],
            $draft->toSchemaArray()['constraints'],
        );
    }

    /**
     * @param list<string> $methods
     * @param list<string> $scopeMiddleware
     */
    private function draft(
        string $path = '/users/{id}',
        array $methods = ['GET'],
        string $handler = 'Users@show',
        array $scopeMiddleware = [],
    ): PendingRoute {
        return new PendingRoute(RoutePath::parse($path), $methods, $handler, $scopeMiddleware);
    }
}
