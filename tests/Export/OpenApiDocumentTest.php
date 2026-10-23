<?php

declare(strict_types=1);

namespace Zef\Test\Export;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass;
use Zef\Framework\Config\Routing\CompiledRoute;
use Zef\Framework\Export\ExportException;
use Zef\Framework\Export\ExportJson;
use Zef\Framework\Export\OpenApiDeprecation;
use Zef\Framework\Export\OpenApiDocument;
use Zef\Framework\Export\OpenApiInfo;
use Zef\Framework\Export\OpenApiOperation;
use Zef\Framework\Export\OpenApiOperationAttribute;
use Zef\Framework\Export\OpenApiOperationAttributeReader;
use Zef\Framework\Export\OpenApiOperationEnrichment;
use Zef\Framework\Export\OpenApiOperationExtensions;
use Zef\Framework\Export\OpenApiOperationMetaReader;
use Zef\Framework\Export\OpenApiOperationProse;
use Zef\Framework\Export\OpenApiParameters;
use Zef\Framework\Export\OpenApiPaths;
use Zef\Framework\Export\OpenApiResponses;
use Zef\Framework\Export\OpenApiServers;
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
#[CoversClass(OpenApiInfo::class)]
#[CoversClass(OpenApiServers::class)]
#[CoversClass(OpenApiPaths::class)]
#[CoversClass(OpenApiOperation::class)]
#[CoversClass(OpenApiParameters::class)]
#[CoversClass(OpenApiResponses::class)]
#[CoversClass(OpenApiDeprecation::class)]
#[CoversClass(OpenApiOperationExtensions::class)]
#[CoversClass(OpenApiOperationEnrichment::class)]
#[CoversClass(OpenApiOperationAttribute::class)]
#[CoversClass(OpenApiOperationAttributeReader::class)]
#[CoversClass(OpenApiOperationMetaReader::class)]
#[CoversClass(OpenApiOperationProse::class)]
#[CoversClass(ExportJson::class)]
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

    public function testAMultiParameterRoutePinsEveryParameter(): void
    {
        $table = [
            [
                'path' => '/users/{id}/posts/{post}',
                'method' => ['GET'],
                'handler' => 'Posts@show',
                'constraints' => ['id' => '\d+'],
            ],
        ];

        $operation = $this->mapAt(
            $this->mapAt($this->mapAt($this->document($table)['paths'])['/users/{id}/posts/{post}'])['get'],
        );

        $first = $this->mapAt($this->mapAt($operation['parameters'])[0]);
        $second = $this->mapAt($this->mapAt($operation['parameters'])[1]);

        self::assertSame('id', $first['name']);
        self::assertSame('^(?:\d+)$', $this->mapAt($first['schema'])['pattern']);
        self::assertSame('post', $second['name']);
        self::assertSame(['type' => 'string'], $this->mapAt($second['schema']));
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

    public function testTheServersWalkSurvivesEverySkipInTheMiddle(): void
    {
        // Every skip the exact-hosts walk can take -- a duplicate
        // claim, a host-agnostic route, a wildcard -- lands in the
        // MIDDLE, before another exact host the walk must still
        // reach: a walk that stops at any skip loses the tail.
        $table = [
            ['path' => '/a', 'method' => ['GET'], 'handler' => 'A@x', 'host' => 'api.example.com'],
            ['path' => '/b', 'method' => ['GET'], 'handler' => 'B@x', 'host' => 'api.example.com'],
            ['path' => '/c', 'method' => ['GET'], 'handler' => 'C@x'],
            ['path' => '/d', 'method' => ['GET'], 'handler' => 'D@x', 'host' => '{tenant}.example.com'],
            ['path' => '/e', 'method' => ['GET'], 'handler' => 'E@x', 'host' => 'other.example.com'],
        ];

        $document = $this->document($table);

        self::assertSame(
            [['url' => 'https://api.example.com'], ['url' => 'https://other.example.com']],
            $document['servers'],
        );
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
        $this->expectExceptionMessageIsOrContains(
            'The route "/twice" declares its OpenAPI enrichment twice: the handler method carries'
            . ' the attribute and the route meta carries the openapi map -- declare one.',
        );

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

    public function testAReflectableHandlerWithoutTheAttributeCarriesNoEnrichment(): void
    {
        $table = [
            ['path' => '/plain', 'method' => ['GET'], 'handler' => EnrichedHandler::class . '@plain'],
        ];

        $operation = $this->mapAt($this->mapAt($this->mapAt($this->document($table)['paths'])['/plain'])['get']);

        self::assertArrayNotHasKey('summary', $operation);
    }

    public function testAMetaMapThatIsNotAMapIsRefused(): void
    {
        $table = [
            [
                'path' => '/notamap',
                'method' => ['GET'],
                'handler' => 'InventoryController::list',
                'meta' => ['openapi' => 'just a string'],
            ],
        ];

        $this->expectException(ExportException::class);
        $this->expectExceptionMessageIsOrContains('meta openapi must be a map, string given');

        $this->document($table);
    }

    public function testTagsThatAreNotAListAreRefused(): void
    {
        $table = [
            [
                'path' => '/maptags',
                'method' => ['GET'],
                'handler' => 'InventoryController::list',
                'meta' => ['openapi' => ['tags' => ['first' => 'Inventory']]],
            ],
        ];

        $this->expectException(ExportException::class);
        $this->expectExceptionMessageIsOrContains('OpenAPI tags must be a list of strings');

        $this->document($table);
    }

    public function testATagThatIsNotAStringIsRefused(): void
    {
        $table = [
            [
                'path' => '/inttags',
                'method' => ['GET'],
                'handler' => 'InventoryController::list',
                'meta' => ['openapi' => ['tags' => [42]]],
            ],
        ];

        $this->expectException(ExportException::class);
        $this->expectExceptionMessageIsOrContains('OpenAPI tags must be non-empty printable strings');

        $this->document($table);
    }

    public function testADoublyAnnotatedHandlerIsRefused(): void
    {
        $table = [
            ['path' => '/twice', 'method' => ['GET'], 'handler' => EnrichedHandler::class . '@twice'],
        ];

        $this->expectException(ExportException::class);
        $this->expectExceptionMessageIsOrContains('carries the OpenAPI operation attribute more than once');

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

    public function testTheIdentityCeilingAcceptsItsBoundaryAndCountsCharacters(): void
    {
        // The boundary is part of the budget (a 256-character title is a
        // title) and the fold counts characters, not bytes (a
        // 130-character two-byte-per-character title is 260 bytes but a
        // 130-character title): both spellings kill the ceiling and
        // byte-fold mutants respectively.
        $multibyte = str_repeat('é', 130);

        $boundary = OpenApiDocument::fromRoutes([], str_repeat('x', 256), '2.0.0', 'https')->toArray();
        self::assertSame(str_repeat('x', 256), $this->mapAt($boundary['info'])['title']);

        $characters = OpenApiDocument::fromRoutes([], $multibyte, '2.0.0', 'https')->toArray();
        self::assertSame($multibyte, $this->mapAt($characters['info'])['title']);

        $this->expectException(ExportException::class);
        $this->expectExceptionMessageIsOrContains('The OpenAPI info.title must be non-empty');

        OpenApiDocument::fromRoutes([], str_repeat('x', 257), '2.0.0', 'https')->toArray();
    }

    public function testTheProseCeilingAcceptsItsBoundaryAndCountsCharacters(): void
    {
        // The prose grammar's own boundary and character semantics: a
        // 2048-character summary is prose, a 2049-character one is a
        // refusal, and a two-byte-per-character summary of 1200
        // characters (2400 bytes) is prose.
        $table = [
            [
                'path' => '/long',
                'method' => ['GET'],
                'handler' => 'LongController::show',
                'meta' => ['openapi' => ['summary' => str_repeat('x', 2048)]],
            ],
        ];

        $operation = $this->mapAt($this->mapAt($this->mapAt($this->document($table)['paths'])['/long'])['get']);
        self::assertSame(str_repeat('x', 2048), $operation['summary']);

        $multibyte = str_repeat('é', 1200);
        $table[0]['meta']['openapi']['summary'] = $multibyte;
        $operation = $this->mapAt($this->mapAt($this->mapAt($this->document($table)['paths'])['/long'])['get']);
        self::assertSame($multibyte, $operation['summary']);

        $table[0]['meta']['openapi']['summary'] = str_repeat('x', 2049);
        $this->expectException(ExportException::class);
        $this->expectExceptionMessageIsOrContains('The route "/long" OpenAPI summary must be non-empty');

        $this->document($table);
    }

    public function testAnEmptyTagAndAnOversizeTagAreEachRefusedAlone(): void
    {
        // Each operand of the tag grammar's guard is observable alone:
        // the empty string (no other operand fires) and the oversize
        // tag without a control byte (only the ceiling fires).
        $table = [
            [
                'path' => '/tags',
                'method' => ['GET'],
                'handler' => 'TagController::list',
                'meta' => ['openapi' => ['tags' => ['']]],
            ],
        ];

        try {
            $this->document($table);
            self::fail('An empty tag must be refused.');
        } catch (ExportException $exportException) {
            self::assertStringContainsString(
                'The route "/tags" OpenAPI tags must be non-empty printable strings',
                $exportException->getMessage(),
            );
        }

        $table[0]['meta']['openapi']['tags'] = [str_repeat('x', 2048)];

        $accepted = $this->mapAt($this->mapAt($this->mapAt($this->document($table)['paths'])['/tags'])['get']);
        self::assertSame([str_repeat('x', 2048)], $accepted['tags']);

        $multibyteTag = str_repeat('é', 1200);
        $table[0]['meta']['openapi']['tags'] = [$multibyteTag];

        $characters = $this->mapAt($this->mapAt($this->mapAt($this->document($table)['paths'])['/tags'])['get']);
        self::assertSame([$multibyteTag], $characters['tags']);

        $table[0]['meta']['openapi']['tags'] = [str_repeat('x', 2049)];
        $this->expectException(ExportException::class);
        $this->expectExceptionMessageIsOrContains('The route "/tags" OpenAPI tags must be non-empty printable strings');

        $this->document($table);
    }

    public function testAHandlerNamingAMissingMethodCarriesNoEnrichment(): void
    {
        // A loadable class whose method does not exist: the reflection
        // guard refuses it before any ReflectionMethod is constructed,
        // and the operation carries no attribute enrichment.
        $table = [
            ['path' => '/missingmethod', 'method' => ['GET'], 'handler' => EnrichedHandler::class . '@nonexistent'],
        ];

        $operation = $this->mapAt(
            $this->mapAt($this->mapAt($this->document($table)['paths'])['/missingmethod'])['get'],
        );

        self::assertArrayNotHasKey('summary', $operation);
    }

    public function testAHandlerNamingABareClassCarriesNoEnrichment(): void
    {
        // A handler with no separator at all: the count judgement
        // refuses it before any class lookup, and the operation carries
        // no attribute enrichment.
        $table = [
            ['path' => '/bare', 'method' => ['GET'], 'handler' => EnrichedHandler::class],
        ];

        $operation = $this->mapAt($this->mapAt($this->mapAt($this->document($table)['paths'])['/bare'])['get']);

        self::assertArrayNotHasKey('summary', $operation);
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
