<?php

declare(strict_types=1);

namespace Zef\Test\Export;

use Opis\JsonSchema\Errors\ValidationError;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
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

/**
 * The generated documents judged by the OFFICIAL interchange schemas:
 * every OpenAPI document this family can answer -- the full fixture
 * table, the empty table, the host-claimed and versioned and
 * deprecated spellings -- validates against the OpenAPI Initiative's
 * own pinned `schema/2022-10-07` artifact (mechanically rewritten as
 * {@see ExportSchemaFixture} records), on the document's JSON
 * round-trip, the artifact the export actually ships.
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
final class OpenApiMetaSchemaTest extends TestCase
{
    public function testTheFixtureTableAnswersAValidDocument(): void
    {
        $this->assertValidAgainstOfficialSchema($this->fixtureTable());
    }

    public function testTheEmptyTableAnswersAValidDocument(): void
    {
        $this->assertValidAgainstOfficialSchema([]);
    }

    public function testTheFullOptionalSurfaceAnswersAValidDocument(): void
    {
        $this->assertValidAgainstOfficialSchema([
            [
                'path' => '/users/{id}',
                'method' => ['GET'],
                'handler' => 'UserController::show',
                'name' => 'users.show',
                'constraints' => ['id' => '\d+'],
            ],
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
            [
                'path' => '/legacy',
                'method' => ['GET'],
                'handler' => 'LegacyController::show',
                'deprecation' => ['date' => '2026-01-15', 'sunset' => '2026-06-30', 'link' => '/docs/move'],
            ],
            [
                'path' => '/enriched',
                'method' => ['GET'],
                'handler' => Fixtures\EnrichedHandler::class . '@show',
            ],
            [
                'path' => '/meta-enriched',
                'method' => ['POST'],
                'handler' => 'InventoryController::store',
                'meta' => ['openapi' => ['summary' => 'Store one', 'tags' => ['Inventory']]],
            ],
        ]);
    }

    public function testTheMultiMethodRoutesAnswerAValidDocument(): void
    {
        $this->assertValidAgainstOfficialSchema([
            ['path' => '/both', 'method' => ['GET', 'POST'], 'handler' => 'Both@all'],
            ['path' => '/', 'method' => ['GET'], 'handler' => 'Root@index'],
        ]);
    }

    /**
     * @param list<array<string, mixed>> $table
     */
    private function assertValidAgainstOfficialSchema(array $table): void
    {
        $document = OpenApiDocument::fromRoutes(
            Router::fromRoutes($table)->tree()->routes(),
            'Fixture API',
            '2.5.0',
            'https',
        )->toArray();

        $result = ExportSchemaFixture::validator()->validate(
            ExportSchemaFixture::roundTrip($document),
            ExportSchemaFixture::openApiSchema(),
        );

        self::assertTrue(
            $result->isValid(),
            'The generated document must validate against the official OpenAPI 3.1 schema: '
            . ($result->error() instanceof ValidationError
                ? ExportSchemaFixture::leafSentence($result->error())
                : 'unknown refusal'),
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fixtureTable(): array
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
            ['path' => '/ping', 'method' => ['GET'], 'handler' => 'PingController::pong'],
        ];
    }
}
