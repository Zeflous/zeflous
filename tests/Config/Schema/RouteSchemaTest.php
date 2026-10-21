<?php

declare(strict_types=1);

namespace Zef\Test\Config\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Config\ConfigException;
use Zef\Framework\Config\Schema\RouteSchema;
use Zef\Framework\Config\Schema\SchemaViolation;

/**
 * @internal
 */
#[CoversClass(RouteSchema::class)]
final class RouteSchemaTest extends TestCase
{
    public function testAcceptsAFullyDeclaredRoute(): void
    {
        $violations = $this->schema()->validate([
            'path' => '/users/{id}',
            'host' => 'api.example.com',
            'version' => 'v1',
            'produces' => ['application/json'],
            'method' => ['GET'],
            'handler' => 'App\Controller\UserController@show',
            'name' => 'users.show',
            'middleware' => ['auth', 'throttle'],
            'constraints' => ['id' => '[0-9]+'],
            'meta' => ['version' => 'v1', 'rate_limit' => 100],
        ]);

        self::assertSame([], $this->render($violations));
    }

    public function testAcceptsTheRoadmapMinimalRoute(): void
    {
        $violations = $this->schema()->validate([
            'path' => '/health',
            'method' => ['GET'],
            'handler' => 'App\Controller\HealthController@check',
            'name' => 'health.check',
        ]);

        self::assertSame([], $this->render($violations));
    }

    public function testAcceptsABareRouteWithOnlyTheRequiredFields(): void
    {
        $violations = $this->schema()->validate([
            'path' => '/status',
            'method' => ['GET', 'HEAD'],
            'handler' => 'App\Controller\StatusController',
        ]);

        self::assertSame([], $this->render($violations));
    }

    public function testReportsAMissingPath(): void
    {
        $violations = $this->schema()->validate([
            'method' => ['GET'],
            'handler' => 'App\Controller\UserController@show',
        ]);

        self::assertSame(['path: expected a non-empty string, got missing'], $this->render($violations));
    }

    public function testReportsAMissingMethod(): void
    {
        $violations = $this->schema()->validate([
            'path' => '/users/{id}',
            'handler' => 'App\Controller\UserController@show',
        ]);

        self::assertSame(
            ['method: expected a non-empty list of non-empty strings, got missing'],
            $this->render($violations),
        );
    }

    public function testReportsAMissingHandler(): void
    {
        $violations = $this->schema()->validate([
            'path' => '/users/{id}',
            'method' => ['GET'],
        ]);

        self::assertSame(['handler: expected a non-empty string, got missing'], $this->render($violations));
    }

    public function testReportsEveryRequiredFieldOfAnEmptyRoute(): void
    {
        $violations = $this->schema()->validate([]);

        self::assertSame(
            [
                'path: expected a non-empty string, got missing',
                'method: expected a non-empty list of non-empty strings, got missing',
                'handler: expected a non-empty string, got missing',
            ],
            $this->render($violations),
        );
    }

    public function testReportsAPathThatIsAnInteger(): void
    {
        $violations = $this->schema()->validate([
            'path' => 404,
            'method' => ['GET'],
            'handler' => 'App\Controller\UserController@show',
        ]);

        self::assertSame(['path: expected a non-empty string, got int'], $this->render($violations));
    }

    public function testReportsAPathThatIsNull(): void
    {
        $violations = $this->schema()->validate([
            'path' => null,
            'method' => ['GET'],
            'handler' => 'App\Controller\UserController@show',
        ]);

        self::assertSame(['path: expected a non-empty string, got null'], $this->render($violations));
    }

    public function testReportsAnEmptyPath(): void
    {
        $violations = $this->schema()->validate([
            'path' => '',
            'method' => ['GET'],
            'handler' => 'App\Controller\UserController@show',
        ]);

        self::assertSame(['path: expected a non-empty string, got an empty string'], $this->render($violations));
    }

    public function testReportsAMethodThatIsAString(): void
    {
        $violations = $this->schema()->validate([
            'path' => '/users/{id}',
            'method' => 'GET',
            'handler' => 'App\Controller\UserController@show',
        ]);

        self::assertSame(
            ['method: expected a non-empty list of non-empty strings, got string'],
            $this->render($violations),
        );
    }

    public function testReportsAnEmptyMethodList(): void
    {
        $violations = $this->schema()->validate([
            'path' => '/users/{id}',
            'method' => [],
            'handler' => 'App\Controller\UserController@show',
        ]);

        self::assertSame(
            ['method: expected a non-empty list of non-empty strings, got an empty array'],
            $this->render($violations),
        );
    }

    public function testReportsAMethodThatIsAMap(): void
    {
        $violations = $this->schema()->validate([
            'path' => '/users/{id}',
            'method' => ['GET' => 'GET'],
            'handler' => 'App\Controller\UserController@show',
        ]);

        self::assertSame(
            ['method: expected a non-empty list of non-empty strings, got array'],
            $this->render($violations),
        );
    }

    public function testChecksEveryMethodElement(): void
    {
        $violations = $this->schema()->validate([
            'path' => '/users/{id}',
            'method' => ['GET', 42],
            'handler' => 'App\Controller\UserController@show',
        ]);

        self::assertSame(
            ['method.1: expected a non-empty string, got int'],
            $this->render($violations),
        );
    }

    public function testReportsAnEmptyMethodElement(): void
    {
        $violations = $this->schema()->validate([
            'path' => '/users/{id}',
            'method' => [''],
            'handler' => 'App\Controller\UserController@show',
        ]);

        self::assertSame(
            ['method.0: expected a non-empty string, got an empty string'],
            $this->render($violations),
        );
    }

    public function testReportsAHandlerThatIsAFloat(): void
    {
        $violations = $this->schema()->validate([
            'path' => '/users/{id}',
            'method' => ['GET'],
            'handler' => 3.5,
        ]);

        self::assertSame(['handler: expected a non-empty string, got float'], $this->render($violations));
    }

    public function testReportsAnEmptyName(): void
    {
        $violations = $this->schema()->validate([
            'path' => '/users/{id}',
            'method' => ['GET'],
            'handler' => 'App\Controller\UserController@show',
            'name' => '',
        ]);

        self::assertSame(['name: expected a non-empty string, got an empty string'], $this->render($violations));
    }

    public function testReportsMiddlewareThatIsAString(): void
    {
        $violations = $this->schema()->validate([
            'path' => '/users/{id}',
            'method' => ['GET'],
            'handler' => 'App\Controller\UserController@show',
            'middleware' => 'auth',
        ]);

        self::assertSame(
            ['middleware: expected a non-empty list of non-empty strings, got string'],
            $this->render($violations),
        );
    }

    public function testReportsAnEmptyMiddlewareList(): void
    {
        $violations = $this->schema()->validate([
            'path' => '/users/{id}',
            'method' => ['GET'],
            'handler' => 'App\Controller\UserController@show',
            'middleware' => [],
        ]);

        self::assertSame(
            ['middleware: expected a non-empty list of non-empty strings, got an empty array'],
            $this->render($violations),
        );
    }

    public function testChecksEveryMiddlewareElement(): void
    {
        $violations = $this->schema()->validate([
            'path' => '/users/{id}',
            'method' => ['GET'],
            'handler' => 'App\Controller\UserController@show',
            'middleware' => ['auth', ''],
        ]);

        self::assertSame(
            ['middleware.1: expected a non-empty string, got an empty string'],
            $this->render($violations),
        );
    }

    public function testReportsConstraintsThatIsAString(): void
    {
        $violations = $this->schema()->validate([
            'path' => '/users/{id}',
            'method' => ['GET'],
            'handler' => 'App\Controller\UserController@show',
            'constraints' => '/[0-9]+/',
        ]);

        self::assertSame(
            ['constraints: expected a non-empty map of non-empty strings, got string'],
            $this->render($violations),
        );
    }

    public function testReportsAnEmptyConstraintsMap(): void
    {
        $violations = $this->schema()->validate([
            'path' => '/users/{id}',
            'method' => ['GET'],
            'handler' => 'App\Controller\UserController@show',
            'constraints' => [],
        ]);

        self::assertSame(
            ['constraints: expected a non-empty map of non-empty strings, got an empty array'],
            $this->render($violations),
        );
    }

    public function testReportsConstraintsThatIsAList(): void
    {
        $violations = $this->schema()->validate([
            'path' => '/users/{id}',
            'method' => ['GET'],
            'handler' => 'App\Controller\UserController@show',
            'constraints' => ['[0-9]+'],
        ]);

        self::assertSame(
            ['constraints: expected a non-empty map of non-empty strings, got array'],
            $this->render($violations),
        );
    }

    public function testChecksEveryConstraintValue(): void
    {
        $violations = $this->schema()->validate([
            'path' => '/history/{year}',
            'method' => ['GET'],
            'handler' => 'App\Controller\HistoryController@show',
            'constraints' => ['year' => '[0-9]{4}', 'page' => 2],
        ]);

        self::assertSame(
            ['constraints.page: expected a non-empty string, got int'],
            $this->render($violations),
        );
    }

    public function testReportsAnEmptyConstraintValue(): void
    {
        $violations = $this->schema()->validate([
            'path' => '/users/{id}',
            'method' => ['GET'],
            'handler' => 'App\Controller\UserController@show',
            'constraints' => ['id' => ''],
        ]);

        self::assertSame(
            ['constraints.id: expected a non-empty string, got an empty string'],
            $this->render($violations),
        );
    }

    public function testReportsMetaThatIsAString(): void
    {
        $violations = $this->schema()->validate([
            'path' => '/users/{id}',
            'method' => ['GET'],
            'handler' => 'App\Controller\UserController@show',
            'meta' => 'v1',
        ]);

        self::assertSame(['meta: expected a non-empty map, got string'], $this->render($violations));
    }

    public function testReportsAnEmptyMetaMap(): void
    {
        $violations = $this->schema()->validate([
            'path' => '/users/{id}',
            'method' => ['GET'],
            'handler' => 'App\Controller\UserController@show',
            'meta' => [],
        ]);

        self::assertSame(['meta: expected a non-empty map, got an empty array'], $this->render($violations));
    }

    public function testAcceptsFreeFormMetaValues(): void
    {
        $violations = $this->schema()->validate([
            'path' => '/users/{id}',
            'method' => ['GET'],
            'handler' => 'App\Controller\UserController@show',
            'meta' => ['version' => 'v1', 'tenant' => 'acme', 'rate_limit' => 100, 'stable' => false],
        ]);

        self::assertSame([], $this->render($violations));
    }

    public function testReportsAnUnexpectedKeySoATypoCannotVanish(): void
    {
        $violations = $this->schema()->validate([
            'path' => '/users/{id}',
            'method' => ['GET'],
            'handler' => 'App\Controller\UserController@show',
            'metod' => ['GET'],
        ]);

        self::assertSame(
            [
                'metod: expected one of path, host, version, produces, method, handler, name, middleware, '
                    . 'constraints, meta, '
                    . 'got an unexpected key',
            ],
            $this->render($violations),
        );
    }

    public function testAssertThrowsWithTheFullPathContext(): void
    {
        try {
            $this->schema()->assert([
                'path' => '/users/{id}',
                'handler' => 'App\Controller\UserController@show',
                'metod' => ['GET'],
            ]);
            self::fail('A route that violates the schema must raise a ConfigException.');
        } catch (ConfigException $configException) {
            self::assertSame(
                'Configuration failed schema validation: '
                    . 'method: expected a non-empty list of non-empty strings, got missing; '
                    . 'metod: expected one of path, host, version, produces, method, handler, name, middleware, '
                    . 'constraints, meta, '
                    . 'got an unexpected key.',
                $configException->getMessage(),
            );
        }
    }

    public function testAssertListAcceptsAValidRoutesList(): void
    {
        $routeSchema = $this->schema();

        $routeSchema->assertList([
            ['path' => '/health', 'method' => ['GET'], 'handler' => 'HealthController@check'],
            [
                'path' => '/users/{id}',
                'method' => ['GET'],
                'handler' => 'UserController@show',
                'constraints' => ['id' => '[0-9]+'],
            ],
        ]);

        self::assertSame([], $routeSchema->validate(['path' => '/health', 'method' => ['GET'], 'handler' => 'h']));
    }

    public function testAssertListAcceptsAMapOfNamedRoutes(): void
    {
        $routeSchema = $this->schema();

        $routeSchema->assertList([
            'health' => ['path' => '/health', 'method' => ['GET'], 'handler' => 'HealthController@check'],
        ]);

        self::assertSame([], $routeSchema->validate([
            'path' => '/health',
            'method' => ['GET'],
            'handler' => 'HealthController@check',
        ]));
    }

    public function testAssertListRejectsAValueThatIsNotAnArray(): void
    {
        try {
            $this->schema()->assertList('/health');
            self::fail('A scalar routes value must raise a ConfigException.');
        } catch (ConfigException $configException) {
            self::assertSame(
                'The "routes" configuration must be a list of route entries.',
                $configException->getMessage(),
            );
        }
    }

    public function testAssertListRejectsAnExplicitlyNullRoutesValue(): void
    {
        try {
            $this->schema()->assertList(null);
            self::fail('A null routes value must raise a ConfigException.');
        } catch (ConfigException $configException) {
            self::assertSame(
                'The "routes" configuration must be a list of route entries.',
                $configException->getMessage(),
            );
        }
    }

    public function testAssertListHaltsOnTheFirstViolatingEntry(): void
    {
        try {
            $this->schema()->assertList([
                ['path' => '/health', 'method' => ['GET'], 'handler' => 'HealthController@check'],
                ['path' => '/users', 'method' => ['GET']],
            ]);
            self::fail('A routes list with a violating entry must raise a ConfigException.');
        } catch (ConfigException $configException) {
            self::assertSame(
                'Configuration failed schema validation: handler: expected a non-empty string, got missing.',
                $configException->getMessage(),
            );
        }
    }

    public function testAssertAcceptsAValidRoute(): void
    {
        $route = [
            'path' => '/health',
            'method' => ['GET'],
            'handler' => 'App\Controller\HealthController@check',
        ];

        $this->schema()->assert($route);

        self::assertSame([], $this->render($this->schema()->validate($route)));
    }

    private function schema(): RouteSchema
    {
        return new RouteSchema();
    }

    /**
     * @param list<SchemaViolation> $violations
     *
     * @return list<string>
     */
    private function render(array $violations): array
    {
        return array_map(
            static fn (SchemaViolation $schemaViolation): string => (string) $schemaViolation,
            $violations,
        );
    }
}
