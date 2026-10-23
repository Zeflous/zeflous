<?php

declare(strict_types=1);

namespace Zef\Test\Export;

use Opis\JsonSchema\Errors\ValidationError;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass;
use Zef\Framework\Config\Routing\CompiledRoute;
use Zef\Framework\Export\ExportException;
use Zef\Framework\Export\PostmanCollection;
use Zef\Framework\Export\PostmanItem;
use Zef\Framework\Router\Router;

/**
 * The Postman collection's own contract: the whole declared table as
 * one importable v2.1.0 collection -- the deterministic identity, the
 * per-route-and-method items, the URL's path-variable idiom, and the
 * headers the table's own negotiation seams read -- closed by the
 * official Postman schema's own judgement of the generated artifact.
 *
 * @internal
 */
#[CoversClass(PostmanCollection::class)]
#[CoversClass(PostmanItem::class)]
final class PostmanCollectionTest extends TestCase
{
    public function testTheCollectionCarriesItsIdentityAndItems(): void
    {
        $collection = PostmanCollection::fromRoutes($this->routes($this->table()), 'Fixture API')->toArray();

        $info = $this->mapAt($collection['info']);

        self::assertSame('Fixture API', $info['name']);
        self::assertSame('https://schema.getpostman.com/json/collection/v2.1.0/collection.json', $info['schema']);
        $identifier = $info['_postman_id'];
        self::assertIsString($identifier);
        self::assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/D',
            $identifier,
        );

        $items = $collection['item'];
        self::assertIsArray($items);
        self::assertCount(4, $items);
        self::assertSame('users.show', $this->mapAt($items[0])['name']);
        self::assertSame('users.index', $this->mapAt($items[1])['name']);
        self::assertSame('users.index', $this->mapAt($items[2])['name']);
        self::assertSame('/ping', $this->mapAt($items[3])['name']);
    }

    public function testTheIdentifierIsDeterministicOverTheTable(): void
    {
        $first = PostmanCollection::fromRoutes($this->routes($this->table()), 'Fixture API')->toArray();
        $again = PostmanCollection::fromRoutes($this->routes($this->table()), 'Another name')->toArray();
        $changed = PostmanCollection::fromRoutes(
            $this->routes([['path' => '/other', 'method' => ['GET'], 'handler' => 'Other@show']]),
            'Fixture API',
        )->toArray();

        self::assertSame(
            $this->mapAt($first['info'])['_postman_id'],
            $this->mapAt($again['info'])['_postman_id'],
        );
        self::assertNotSame(
            $this->mapAt($first['info'])['_postman_id'],
            $this->mapAt($changed['info'])['_postman_id'],
        );
    }

    public function testTheRequestSpellsThePathVariableIdiom(): void
    {
        $collection = PostmanCollection::fromRoutes(
            $this->routes([['path' => '/users/{id}/posts/{post}', 'method' => ['GET'], 'handler' => 'Posts@show']]),
            'Fixture API',
        )->toArray();

        $request = $this->mapAt($this->mapAt($this->mapAt($collection['item'])[0])['request']);
        $url = $this->mapAt($request['url']);

        self::assertSame('GET', $request['method']);
        self::assertSame('/users/:id/posts/:post', $url['raw']);
        self::assertSame(['users', ':id', 'posts', ':post'], $url['path']);
        self::assertSame([['key' => 'id', 'value' => ''], ['key' => 'post', 'value' => '']], $url['variable']);
        self::assertSame([], $request['header']);
    }

    public function testTheRootRouteSpellsTheRootUrl(): void
    {
        $collection = PostmanCollection::fromRoutes(
            $this->routes([['path' => '/', 'method' => ['GET'], 'handler' => 'Root@index']]),
            'Fixture API',
        )->toArray();

        $url = $this->mapAt($this->mapAt($this->mapAt($this->mapAt($collection['item'])[0])['request'])['url']);

        self::assertSame('/', $url['raw']);
        self::assertSame([], $url['path']);
        self::assertSame([], $url['variable']);
    }

    public function testTheHeadersCarryTheNegotiationSeamsDeclarations(): void
    {
        $collection = PostmanCollection::fromRoutes(
            $this->routes([
                [
                    'path' => '/admin/reports',
                    'method' => ['GET'],
                    'handler' => 'ReportController::show',
                    'version' => '2',
                    'produces' => ['application/json', 'text/csv'],
                ],
            ]),
            'Fixture API',
        )->toArray();

        $request = $this->mapAt($this->mapAt($this->mapAt($collection['item'])[0])['request']);

        self::assertSame(
            [
                ['key' => 'Accept', 'value' => 'application/json, text/csv'],
                ['key' => 'Accept-Version', 'value' => '2'],
            ],
            $request['header'],
        );
    }

    public function testTheIdentifierIsPinnedForAFixedTable(): void
    {
        // The golden id: the exact rendering a fixed table answers,
        // pinned so every formatting mutant (an offset shifted, a
        // segment widened, the hash spliced differently) dies on the
        // literal it no longer spells.
        $collection = PostmanCollection::fromRoutes($this->routes($this->table()), 'Fixture API')->toArray();

        self::assertSame(
            'fe905764-a7b5-d845-f342-1887e9f8e510',
            $this->mapAt($collection['info'])['_postman_id'],
        );
    }

    public function testTheIdentifierDistinguishesThePathAlone(): void
    {
        // Two tables that differ in nothing but the path pattern must
        // answer different ids: the pattern is a field of the table's
        // own canonical encoding, not decoration.
        $first = PostmanCollection::fromRoutes(
            $this->routes([['path' => '/a', 'method' => ['GET'], 'handler' => 'H@show']]),
            'Fixture API',
        )->toArray();
        $second = PostmanCollection::fromRoutes(
            $this->routes([['path' => '/b', 'method' => ['GET'], 'handler' => 'H@show']]),
            'Fixture API',
        )->toArray();

        self::assertNotSame(
            $this->mapAt($first['info'])['_postman_id'],
            $this->mapAt($second['info'])['_postman_id'],
        );
    }

    public function testTheCollectionNameGrammarRefuses(): void
    {
        $this->expectException(ExportException::class);
        $this->expectExceptionMessageIsOrContains('The Postman collection name must be non-empty');

        PostmanCollection::fromRoutes([], '');
    }

    public function testTheCollectionNameCeilingIsPinnedAtItsBoundary(): void
    {
        // The ceiling is a character budget, and the boundary is part
        // of the budget: a 256-character name is a name, a 257-character
        // one is a refusal -- and the mutant that shifts the ceiling
        // either way dies on the boundary it no longer spells.
        $accepted = PostmanCollection::fromRoutes([], str_repeat('x', 256))->toArray();

        self::assertSame(str_repeat('x', 256), $this->mapAt($accepted['info'])['name']);

        $this->expectException(ExportException::class);
        $this->expectExceptionMessageIsOrContains('The Postman collection name must be non-empty');

        PostmanCollection::fromRoutes([], str_repeat('x', 257));
    }

    public function testTheCollectionNameCeilingCountsCharactersNotBytes(): void
    {
        // A 130-character two-byte-per-character name is 260 bytes but
        // 130 characters: the character fold accepts it, the byte fold
        // refuses it, and the pin is the character semantics the
        // grammar spells.
        $multibyte = str_repeat('é', 130);

        $collection = PostmanCollection::fromRoutes([], $multibyte)->toArray();

        self::assertSame($multibyte, $this->mapAt($collection['info'])['name']);
    }

    public function testTheGeneratedCollectionValidatesAgainstTheOfficialSchema(): void
    {
        $collection = PostmanCollection::fromRoutes($this->routes($this->table()), 'Fixture API')->toArray();

        $result = ExportSchemaFixture::validator()->validate(
            ExportSchemaFixture::roundTrip($collection),
            ExportSchemaFixture::postmanSchema(),
        );

        self::assertTrue(
            $result->isValid(),
            'The generated collection must validate against the official Postman v2.1.0 schema: '
            . ($result->error() instanceof ValidationError
                ? ExportSchemaFixture::leafSentence($result->error())
                : 'unknown refusal'),
        );
    }

    public function testTheEmptyTableAnswersAValidCollection(): void
    {
        $collection = PostmanCollection::fromRoutes([], 'Empty API')->toArray();

        $result = ExportSchemaFixture::validator()->validate(
            ExportSchemaFixture::roundTrip($collection),
            ExportSchemaFixture::postmanSchema(),
        );

        self::assertTrue($result->isValid());
        self::assertSame([], $collection['item']);
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
            ['path' => '/ping', 'method' => ['GET'], 'handler' => 'PingController::pong'],
        ];
    }

    /**
     * One collection node the assertions read containers through,
     * shaped to the map it must be before any offset touches it.
     *
     * @return array<array-key, mixed>
     */
    private function mapAt(mixed $node): array
    {
        if (!\is_array($node) && !$node instanceof stdClass) {
            self::fail('The collection node the assertion read is not an object.');
        }

        return (array) $node;
    }
}
