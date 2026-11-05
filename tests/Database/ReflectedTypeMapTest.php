<?php

declare(strict_types=1);

namespace Zef\Test\Database;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Adapters\Database\ConnectionConfig;
use Zef\Framework\Adapters\Database\PdoConnection;
use Zef\Framework\Adapters\Database\SchemaReflection;
use Zef\Framework\Database\ColumnSchema;
use Zef\Framework\Database\ReflectedTypeMap;
use Zef\Framework\Database\SqlQuery;
use Zef\Framework\Database\TableName;
use Zef\Framework\Database\TableSchema;
use Zef\Framework\Database\TypedTableGateway;
use Zef\Framework\Database\TypeMapDecode;

/**
 * Pins the reflected map entry: schema reflection's second consumer.
 * The closed matching vocabulary over the reflected declared types
 * (the whole-second datetime spellings and the JSON word, matched
 * case-insensitively as the driver spells them), the spellings that
 * deliberately map to NO entry (decimals stay strings -- a float
 * would lie about precision; the precision spellings, the multiword
 * PostgreSQL spellings, JSONB and DATE are each their own follow-up),
 * and the live-socket composition: the reflected table's own declared
 * types answering the map, then carrying a typed round-trip over the
 * real SQLite socket end to end.
 *
 * @internal
 */
#[CoversClass(ReflectedTypeMap::class)]
final class ReflectedTypeMapTest extends TestCase
{
    public function testTheDatetimeSpellingsAnswerTheDatetimeConverter(): void
    {
        $map = ReflectedTypeMap::fromSchema(new TableSchema(
            new TableName('events'),
            [
                new ColumnSchema('created_at', 'DATETIME', true, null, null),
                new ColumnSchema('changed_at', 'timestamp', false, null, null),
            ],
            [],
            [],
        ));

        $decodedCreated = TypeMapDecode::row($map, ['created_at' => '2026-11-05 03:14:15'])['created_at'];
        self::assertInstanceOf(DateTimeImmutable::class, $decodedCreated);
        self::assertSame(
            new DateTimeImmutable('2026-11-05 03:14:15 UTC')->getTimestamp(),
            $decodedCreated->getTimestamp(),
        );
        $decodedChanged = TypeMapDecode::row($map, ['changed_at' => '2026-11-05 03:14:15'])['changed_at'];
        self::assertInstanceOf(DateTimeImmutable::class, $decodedChanged);
        self::assertSame(
            new DateTimeImmutable('2026-11-05 03:14:15 UTC')->getTimestamp(),
            $decodedChanged->getTimestamp(),
        );
    }

    public function testTheJsonWordAnswersTheJsonConverterCaseInsensitively(): void
    {
        $map = ReflectedTypeMap::fromSchema(new TableSchema(
            new TableName('events'),
            [new ColumnSchema('payload', 'Json', true, null, null)],
            [],
            [],
        ));

        self::assertSame(['a' => 1], TypeMapDecode::row($map, ['payload' => '{"a":1}'])['payload']);
    }

    public function testTheSpellingsOutsideTheVocabularyMapToNoEntry(): void
    {
        $map = ReflectedTypeMap::fromSchema(new TableSchema(
            new TableName('events'),
            [
                new ColumnSchema('id', 'INTEGER', false, null, 1),
                new ColumnSchema('note', 'TEXT', true, null, null),
                new ColumnSchema('label', 'VARCHAR(10)', true, null, null),
                new ColumnSchema('price', 'DECIMAL(10,2)', true, null, null),
                new ColumnSchema('day', 'DATE', true, null, null),
                new ColumnSchema('precise', 'datetime(6)', true, null, null),
                new ColumnSchema('zoneful', 'timestamp without time zone', true, null, null),
                new ColumnSchema('bin', 'JSONB', true, null, null),
            ],
            [],
            [],
        ));

        self::assertSame(
            [
                'id' => 1,
                'note' => 'x',
                'label' => 'y',
                'price' => '1.50',
                'day' => '2026-11-05',
                'precise' => '2026-11-05 03:14:15.987654',
                'zoneful' => 'anything',
                'bin' => '{"a":1}',
            ],
            TypeMapDecode::row($map, [
                'id' => 1,
                'note' => 'x',
                'label' => 'y',
                'price' => '1.50',
                'day' => '2026-11-05',
                'precise' => '2026-11-05 03:14:15.987654',
                'zoneful' => 'anything',
                'bin' => '{"a":1}',
            ]),
            'decimals stay strings, and every out-of-vocabulary spelling keeps the raw narrowing',
        );
    }

    public function testTheReflectedTableAnswersTheMapOverTheRealSocket(): void
    {
        $pdoConnection = $this->socket();
        $schemaReflection = new SchemaReflection($pdoConnection, 'sqlite');

        $map = ReflectedTypeMap::fromSchema($schemaReflection->table(new TableName('events')));

        $decoded = TypeMapDecode::row($map, [
            'id' => 1,
            'created_at' => '2026-11-05 03:14:15',
            'payload' => '{"kunci":"nilai"}',
            'note' => 'x',
            'price' => '1.50',
        ]);

        $decodedCreatedAt = $decoded['created_at'];
        self::assertInstanceOf(DateTimeImmutable::class, $decodedCreatedAt);
        self::assertSame(
            new DateTimeImmutable('2026-11-05 03:14:15 UTC')->getTimestamp(),
            $decodedCreatedAt->getTimestamp(),
        );
        self::assertSame(['kunci' => 'nilai'], $decoded['payload']);
        self::assertSame('x', $decoded['note']);
        self::assertSame(
            '1.50',
            $decoded['price'],
            'a decimal cell the socket answered as text travels untouched -- no float converter ever',
        );
    }

    public function testTheReflectedMapCarriesATypedRoundTripOverTheRealSocket(): void
    {
        $pdoConnection = $this->socket();
        $schemaReflection = new SchemaReflection($pdoConnection, 'sqlite');

        $typedTableGateway = new TypedTableGateway(
            $pdoConnection,
            'events',
            ReflectedTypeMap::fromSchema($schemaReflection->table(new TableName('events'))),
        );

        $jakarta = new DateTimeImmutable('2026-11-05 10:14:15', new DateTimeZone('Asia/Jakarta'));
        $payload = ['kunci' => 'nilai', '日本語' => ['x' => 1]];

        $typedTableGateway->insert(['created_at' => $jakarta, 'payload' => $payload, 'note' => 'x', 'price' => '1.50']);

        $row = $typedTableGateway->find(1);

        self::assertNotNull($row);
        $createdAt = $row['created_at'];
        self::assertInstanceOf(DateTimeImmutable::class, $createdAt);
        self::assertSame(
            $jakarta->getTimestamp(),
            $createdAt->getTimestamp(),
            "the reflected map's datetime entry round-trips the equal instant",
        );
        self::assertSame($payload, $row['payload'], "the reflected map's JSON entry round-trips the equal array");
        self::assertSame(
            1.5,
            $row['price'],
            "the reflected decimal column maps to no converter, and SQLite's own numeric affinity answers it a float"
            . " -- the map owns no decimal opinion, the driver's own answer travels untouched",
        );
    }

    /**
     * The one real-socket fixture the family's tests share: the
     * declared spellings the reflected fold reads.
     */
    private function socket(): PdoConnection
    {
        $pdoConnection = new PdoConnection(
            ConnectionConfig::fromArray(['driver' => 'sqlite', 'dbname' => ':memory:']),
        );
        $pdoConnection->execute(SqlQuery::raw(
            'CREATE TABLE events ('
            . 'id INTEGER PRIMARY KEY AUTOINCREMENT, created_at DATETIME, payload JSON,'
            . ' note TEXT, price DECIMAL(10,2))',
        ));

        return $pdoConnection;
    }
}
