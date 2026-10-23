<?php

declare(strict_types=1);

namespace Zef\Test\Export;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass;
use Zef\Framework\Config\Routing\CompiledRoute;
use Zef\Framework\Export\ExportException;
use Zef\Framework\Export\OpenApiDocument;
use Zef\Framework\Router\Router;
use Zef\Test\Export\Fixtures\EnrichedHandler;

/**
 * The OpenAPI document's own contract: the whole declared table as
 * one specification document -- the root's required shape, the
 * identity's fail-closed grammar, the paths' grouping, the
 * operation's every translated field, and the honest empties.
 *
 * @internal
 */
#[CoversClass(OpenApiDocument::class)]
final class OpenApiDocumentTest extends TestCase
{
    public function testTheDocumentCarriesTheRequiredRootShape(): void
    {
        $document = $this->document($this->table());

        self::assertSame('3.1.0', $document['openapi']);
        self::assertSame(['title' => 'Fixture API', 'version' => '2.5.0'], $document['info']);
        self::assertSame(['/users/{id}', '/users', '/health'], array_keys($this->mapAt($document['paths'])));
        self::assertArrayNotHasKey('servers', $document);
    }

    public function testTheEmptyTableAnswersTheHonestEmptyPaths(): void
    {
        $document = $this->document([]);

        self::assertSame('3.1.0', $document['openapi']);
        self::assertSame([], (array) $document['paths']);
        self::assertArrayNotHasKey('servers', $document);
    }

    public function testTheIdentityGrammarRefusesEmptyTitle(): void
    {
        $this->grammarRefused('', '2.0.0', 'info.title');
    }

    public function testTheIdentityGrammarRefusesControlBytes(): void
    {
        $this->grammarRefused("Fixture\x1fAPI", '2.0.0', 'info.title');
        $this->grammarRefused('Fixture API', "2.0\x7f", 'info.version');
    }

    public function testTheIdentityGrammarRefusesOversizeFields(): void
    {
        $this->grammarRefused(str_repeat('x', 257), '2.0.0', 'info.title');
        $this->grammarRefused('Fixture API', str_repeat('v', 257), 'info.version');
    }

    public function testTheSchemeGrammarRefusesUnknownSpellings(): void
    {
        $this->expectException(ExportException::class);
        $this->expectExceptionMessageIsOrContains(
            'The OpenAPI server scheme must be one of "http", "https", "ftp" given.',
        );

        OpenApiDocument::fromRoutes($this->routes($this->table()), 'Fixture API', '2.0.0', 'ftp')->toArray();
    }

    public function testTwoEntriesSharingAPatternMergeTheirMethods(): void
    {
        $table = [
            ['path' => '/shared', 'method' => ['GET'], 'handler' => 'A@read'],
            ['path' => '/shared', 'method' => ['POST'], 'handler' => 'A@write'],
        ];

        $document = $this->document($table);
        $pathItem = $this->mapAt($this->mapAt($document['paths'])['/shared']);

        self::assertSame(['get', 'post'], array_keys($pathItem));
        self::assertSame('A@read', $this->mapAt($pathItem['get'])['x-zef-handler']);
        self::assertSame('A@write', $this->mapAt($pathItem['post'])['x-zef-handler']);
    }

    public function testAMultiMethodRouteOmitsItsOperationId(): void
    {
        $table = [['path' => '/both', 'method' => ['GET', 'POST'], 'handler' => 'B@all', 'name' => 'both']];

        $document = $this->document($table);

        $pathItem = $this->mapAt($this->mapAt($document['paths'])['/both']);

        self::assertArrayNotHasKey('operationId', $this->mapAt($pathItem['get']));
        self::assertArrayNotHasKey('operationId', $this->mapAt($pathItem['post']));
    }

    public function testAnUnnamedRouteOmitsItsOperationId(): void
    {
        $document = $this->document([['path' => '/anon', 'method' => ['GET'], 'handler' => 'C@read']]);

        $operation = $this->mapAt($this->mapAt($this->mapAt($document['paths'])['/anon'])['get']);

        self::assertArrayNotHasKey('operationId', $operation);
    }

    public function testTheOperationCarriesTheTablesWithinItsExtensions(): void
    {
        $table = [
            [
                'path' => '/admin/reports',
                'method' => ['GET'],
                'handler' => 'ReportController::show',
                'name' => 'reports.show',
                'host' => 'admin.example.com',
                'middleware' => ['auth.session'],
                'version' => '2',
                'produces' => ['application/json'],
            ],
        ];

        $operation = $this->mapAt(
            $this->mapAt($this->mapAt($this->document($table)['paths'])['/admin/reports'])['get'],
        );

        self::assertSame('reports.show', $operation['operationId']);
        self::assertSame('ReportController::show', $operation['x-zef-handler']);
        self::assertSame('admin.example.com', $operation['x-zef-host']);
        self::assertSame('2', $operation['x-zef-version']);
        self::assertSame(['auth.session'], $operation['x-zef-middleware']);
        self::assertArrayNotHasKey('deprecated', $operation);

        $response = $this->mapAt($this->mapAt($operation['responses'])['200']);
        self::assertSame(['application/json'], array_keys($this->mapAt($response['content'])));
        self::assertSame([], (array) $this->mapAt($response['content'])['application/json']);
    }

    public function testAProduceslessRouteAnswersNoContentMap(): void
    {
        $operation = $this->mapAt(
            $this->mapAt($this->mapAt($this->document($this->table())['paths'])['/health'])['get'],
        );

        $response = $this->mapAt($this->mapAt($operation['responses'])['200']);
        self::assertArrayNotHasKey('content', $response);
        self::assertIsString($response['description']);
        self::assertStringContainsString(
            'response schemas are not declared by the table',
            $response['description'],
        );
    }

    public function testTheDeprecationDeclarationRendersItsInstants(): void
    {
        $table = [
            [
                'path' => '/legacy',
                'method' => ['GET'],
                'handler' => 'LegacyController@show',
                'name' => 'legacy',
                'deprecation' => ['date' => '2026-01-15', 'sunset' => '2026-06-30', 'link' => '/docs/move'],
            ],
        ];

        $operation = $this->mapAt($this->mapAt($this->mapAt($this->document($table)['paths'])['/legacy'])['get']);

        self::assertTrue($operation['deprecated']);
        self::assertSame(
            ['date' => '2026-01-15T00:00:00Z', 'sunset' => '2026-06-30T00:00:00Z', 'link' => '/docs/move'],
            $this->mapAt($operation['x-zef-deprecation']),
        );
    }

    public function testTheParametersCarryTheDeclaredConstraints(): void
    {
        $operation = $this->mapAt(
            $this->mapAt($this->mapAt($this->document($this->table())['paths'])['/users/{id}'])['get'],
        );

        $parameter = $this->mapAt($this->mapAt($operation['parameters'])[0]);

        self::assertSame('id', $parameter['name']);
        self::assertSame('path', $parameter['in']);
        self::assertTrue($parameter['required']);
        self::assertSame('^(?:\d+)$', $this->mapAt($parameter['schema'])['pattern']);
        self::assertSame('string', $this->mapAt($parameter['schema'])['type']);
    }

    public function testAnUnconstrainedParameterAnswersThePlainStringSchema(): void
    {
        $table = [['path' => '/files/{name}', 'method' => ['GET'], 'handler' => 'Files@show']];

        $operation = $this->mapAt($this->mapAt($this->mapAt($this->document($table)['paths'])['/files/{name}'])['get']);
        $parameter = $this->mapAt($this->mapAt($operation['parameters'])[0]);

        self::assertSame(['type' => 'string'], $this->mapAt($parameter['schema']));
    }

    public function testTheServersSpellTheExactHostsInTheScheme(): void
    {
        $table = [
            ['path' => '/a', 'method' => ['GET'], 'handler' => 'A@x', 'host' => 'api.example.com'],
            ['path' => '/b', 'method' => ['GET'], 'handler' => 'B@x', 'host' => '{tenant}.example.com'],
            ['path' => '/c', 'method' => ['GET'], 'handler' => 'C@x', 'host' => 'api.example.com'],
            ['path' => '/d', 'method' => ['GET'], 'handler' => 'D@x', 'host' => '*.example.com'],
        ];

        $document = $this->document($table);

        self::assertSame(
            [['url' => 'https://api.example.com']],
            $document['servers'],
        );

        $tenantOperation = $this->mapAt($this->mapAt($this->mapAt($document['paths'])['/b'])['get']);
        self::assertSame('{tenant}.example.com', $tenantOperation['x-zef-host']);
        $wildcardOperation = $this->mapAt($this->mapAt($this->mapAt($document['paths'])['/d'])['get']);

        self::assertSame('*.example.com', $wildcardOperation['x-zef-host']);
    }

    public function testTheHandlerAttributeEnrichesTheOperation(): void
    {
        $table = [
            [
                'path' => '/enriched',
                'method' => ['GET'],
                'handler' => EnrichedHandler::class . '@show',
            ],
        ];

        $operation = $this->mapAt($this->mapAt($this->mapAt($this->document($table)['paths'])['/enriched'])['get']);

        self::assertSame('One enriched operation', $operation['summary']);
        self::assertSame(
            'The handler method this attribute decorates is the route the document enriches.',
            $operation['description'],
        );
        self::assertSame(['Fixture', 'Enrichment'], $operation['tags']);
    }

    public function testTheMetaMapEnrichesTheOperation(): void
    {
        $table = [
            [
                'path' => '/meta-enriched',
                'method' => ['GET'],
                'handler' => 'InventoryController::list',
                'meta' => ['openapi' => ['summary' => 'The inventory', 'tags' => ['Inventory']]],
            ],
        ];

        $operation = $this->mapAt(
            $this->mapAt($this->mapAt($this->document($table)['paths'])['/meta-enriched'])['get'],
        );

        self::assertSame('The inventory', $operation['summary']);
        self::assertSame(['Inventory'], $operation['tags']);
        self::assertArrayNotHasKey('description', $operation);
    }

    public function testTheTwoEnrichmentSpellingsAreRefusedTogether(): void
    {
        $table = [
            [
                'path' => '/twice',
                'method' => ['GET'],
                'handler' => EnrichedHandler::class . '@show',
                'meta' => ['openapi' => ['summary' => 'The other spelling']],
            ],
        ];

        $this->expectException(ExportException::class);
        $this->expectExceptionMessageIsOrContains('declares its OpenAPI enrichment twice');

        $this->document($table);
    }

    public function testAWrongMetaShapeIsRefusedWithTheRouteNamed(): void
    {
        $table = [
            [
                'path' => '/wrong',
                'method' => ['GET'],
                'handler' => 'WrongController::list',
                'meta' => ['openapi' => ['summary' => '']],
            ],
        ];

        $this->expectException(ExportException::class);
        $this->expectExceptionMessageIsOrContains('The route "/wrong" OpenAPI summary must be non-empty');

        $this->document($table);
    }

    public function testAnUnknownMetaKeyIsRefused(): void
    {
        $table = [
            [
                'path' => '/unknown',
                'method' => ['GET'],
                'handler' => 'UnknownController::list',
                'meta' => ['openapi' => ['summary' => 'Fine', 'operationId' => 'invented']],
            ],
        ];

        $this->expectException(ExportException::class);
        $this->expectExceptionMessageIsOrContains('unknown key "operationId"');

        $this->document($table);
    }

    public function testANonReflectableHandlerCarriesNoAttributeEnrichment(): void
    {
        $table = [
            ['path' => '/service', 'method' => ['GET'], 'handler' => 'users.controller'],
            ['path' => '/missing', 'method' => ['GET'], 'handler' => 'NoSuchClass@method'],
        ];

        $document = $this->document($table);

        $serviceOperation = $this->mapAt($this->mapAt($this->mapAt($document['paths'])['/service'])['get']);
        $missingOperation = $this->mapAt($this->mapAt($this->mapAt($document['paths'])['/missing'])['get']);

        self::assertArrayNotHasKey('summary', $serviceOperation);
        self::assertArrayNotHasKey('summary', $missingOperation);
    }

    /**
     * @param list<array<string, mixed>> $table
     *
     * @return array<string, mixed>
     */
    private function document(array $table): array
    {
        return OpenApiDocument::fromRoutes($this->routes($table), 'Fixture API', '2.5.0', 'https')->toArray();
    }

    /**
     * @param list<array<string, mixed>> $table
     *
     * @return list<CompiledRoute>
     */
    private function routes(array $table): array
    {
        return Router::fromRoutes($table)->tree()->routes();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function table(): array
    {
        return [
            [
                'path' => '/users/{id}',
                'method' => ['GET'],
                'handler' => 'UserController::show',
                'name' => 'users.show',
                'constraints' => ['id' => '\d+'],
            ],
            [
                'path' => '/users',
                'method' => ['GET', 'POST'],
                'handler' => 'UserController::index',
                'name' => 'users.index',
            ],
            ['path' => '/health', 'method' => ['GET'], 'handler' => 'HealthController::check'],
        ];
    }

    private function grammarRefused(string $title, string $version, string $field): void
    {
        $this->expectException(ExportException::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('The OpenAPI %s must be non-empty', $field));

        OpenApiDocument::fromRoutes([], $title, $version, 'https')->toArray();
    }

    /**
     * One document node the assertions read containers through,
     * shaped to the map it must be before any offset touches it.
     *
     * @return array<array-key, mixed>
     */
    private function mapAt(mixed $node): array
    {
        if (!\is_array($node) && !$node instanceof stdClass) {
            self::fail('The document node the assertion read is not an object.');
        }

        return (array) $node;
    }
}
