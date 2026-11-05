<?php

declare(strict_types=1);

namespace Zef\Test\Database;

use DateTimeImmutable;
use DateTimeZone;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Adapters\Database\ConnectionConfig;
use Zef\Framework\Adapters\Database\PdoConnection;
use Zef\Framework\Database\DateTimeType;
use Zef\Framework\Database\JsonType;
use Zef\Framework\Database\QueryException;
use Zef\Framework\Database\Repository;
use Zef\Framework\Database\SqlQuery;
use Zef\Framework\Database\TableGateway;
use Zef\Framework\Database\TypedTableGateway;
use Zef\Framework\Database\TypeMap;
use Zef\Framework\Database\TypeMappingException;
use Zef\Test\Support\Database\ScriptedConnection;

/**
 * Pins the typed gateway seam over the real SQLite socket: the
 * round-trips the design record promised (the DateTimeImmutable in,
 * the equal instant back; the array with unicode keys in, the equal
 * array back), the null-cell edge both directions, the default
 * spelling a column answered without the map's help, the writes that
 * encode at query build (insert rows and update pairs), and the reads
 * that decode after the narrowing (find, findOneBy, findBy) -- against
 * the honest default's own pin: the SAME table over the raw generic
 * gateway keeps the raw narrowing, the same values refuse loudly
 * there, and the encoded spellings are exactly what the raw side
 * reads. Zero adapter changes: the port's own narrowing is untouched.
 *
 * @internal
 */
#[CoversClass(TypedTableGateway::class)]
#[CoversClass(Repository::class)]
final class TypedTableGatewayTest extends TestCase
{
    private PdoConnection $pdoConnection;

    private TypedTableGateway $typedTableGateway;

    #[Override]
    protected function setUp(): void
    {
        $this->pdoConnection = new PdoConnection(
            ConnectionConfig::fromArray(['driver' => 'sqlite', 'dbname' => ':memory:']),
        );
        $this->pdoConnection->execute(SqlQuery::raw(
            'CREATE TABLE events ('
            . 'id INTEGER PRIMARY KEY AUTOINCREMENT, created_at DATETIME, payload JSON,'
            . ' note TEXT, opened_at DATETIME DEFAULT CURRENT_TIMESTAMP)',
        ));

        $this->typedTableGateway = new TypedTableGateway(
            $this->pdoConnection,
            'events',
            new TypeMap(['created_at' => new DateTimeType(), 'payload' => new JsonType()]),
        );
    }

    public function testTheDatetimeRoundTripsTheEqualInstant(): void
    {
        $jakarta = new DateTimeImmutable('2026-11-05 10:14:15', new DateTimeZone('Asia/Jakarta'));

        $this->typedTableGateway->insert(['created_at' => $jakarta, 'payload' => ['a' => 1], 'note' => 'first']);

        $row = $this->typedTableGateway->find(1);

        self::assertNotNull($row);
        $createdAt = $row['created_at'];
        self::assertInstanceOf(DateTimeImmutable::class, $createdAt);
        self::assertSame(
            $jakarta->getTimestamp(),
            $createdAt->getTimestamp(),
            'the instant the host handed in is the instant the boundary hands back',
        );
        self::assertSame('UTC', $createdAt->getTimezone()->getName());
    }

    public function testTheJsonRoundTripsTheEqualArrayWithUnicodeKeys(): void
    {
        $payload = ['kunci' => 'nilai', '日本語' => ['x' => 1], 'flag' => true, 'hole' => null];

        $this->typedTableGateway->insert([
            'created_at' => new DateTimeImmutable('2026-11-05 03:14:15'),
            'payload' => $payload,
        ]);

        $row = $this->typedTableGateway->find(1);

        self::assertNotNull($row);
        self::assertSame($payload, $row['payload']);
    }

    public function testTheNullCellAnswersNullBeforeAnythingDecodable(): void
    {
        $this->typedTableGateway->insert(['created_at' => null, 'payload' => null, 'note' => 'empty']);

        $row = $this->typedTableGateway->find(1);

        self::assertNotNull($row);
        self::assertNull($row['created_at']);
        self::assertNull($row['payload']);
    }

    public function testTheDefaultSpellingAColumnAnsweredWithoutTheMapStillDecodes(): void
    {
        $typedTableGateway = new TypedTableGateway(
            $this->pdoConnection,
            'events',
            new TypeMap(['opened_at' => new DateTimeType(), 'payload' => new JsonType()]),
        );

        $typedTableGateway->insert(['payload' => ['a' => 1], 'note' => 'defaulted']);

        $row = $typedTableGateway->find(1);

        self::assertNotNull($row);
        self::assertInstanceOf(
            DateTimeImmutable::class,
            $row['opened_at'],
            'the column DEFAULT the map did not spell on the write still answers the canonical'
            . ' spelling, and the map decodes it honestly',
        );
    }

    public function testTheUpdatePairsEncodeAtQueryBuild(): void
    {
        $this->typedTableGateway->insert([
            'created_at' => new DateTimeImmutable('2026-11-05 03:14:15'),
            'payload' => ['a' => 1],
        ]);

        self::assertSame(1, $this->typedTableGateway->update(
            ['payload' => ['b' => 2], 'created_at' => new DateTimeImmutable('2026-11-06 04:15:16')],
            ['id' => 1],
        ));

        $row = $this->typedTableGateway->find(1);

        self::assertNotNull($row);
        self::assertSame(['b' => 2], $row['payload']);
        $updatedAt = $row['created_at'];
        self::assertInstanceOf(DateTimeImmutable::class, $updatedAt);
        self::assertSame(
            new DateTimeImmutable('2026-11-06 04:15:16 UTC')->getTimestamp(),
            $updatedAt->getTimestamp(),
        );
    }

    public function testTheFindAllReadsDecodeAfterTheNarrowing(): void
    {
        $this->typedTableGateway->insert([
            'created_at' => new DateTimeImmutable('2026-11-05 03:14:15'),
            'payload' => ['i' => 1],
            'note' => 'a',
        ]);
        $this->typedTableGateway->insert([
            'created_at' => new DateTimeImmutable('2026-11-06 04:15:16'),
            'payload' => ['i' => 2],
            'note' => 'b',
        ]);

        $rows = $this->typedTableGateway->findBy([], ['id' => 'ASC']);

        self::assertCount(2, $rows);
        $firstCreatedAt = $rows[0]['created_at'];
        $secondCreatedAt = $rows[1]['created_at'];
        self::assertInstanceOf(DateTimeImmutable::class, $firstCreatedAt);
        self::assertInstanceOf(DateTimeImmutable::class, $secondCreatedAt);
        self::assertSame(
            new DateTimeImmutable('2026-11-05 03:14:15 UTC')->getTimestamp(),
            $firstCreatedAt->getTimestamp(),
        );
        self::assertSame(['i' => 1], $rows[0]['payload']);
        self::assertSame(
            new DateTimeImmutable('2026-11-06 04:15:16 UTC')->getTimestamp(),
            $secondCreatedAt->getTimestamp(),
        );
        self::assertSame(['i' => 2], $rows[1]['payload']);
    }

    public function testTheOneRowFinderDecodesAndTheCriteriaTravelsRaw(): void
    {
        $this->typedTableGateway->insert([
            'created_at' => new DateTimeImmutable('2026-11-05 03:14:15'),
            'payload' => ['a' => 1],
            'note' => 'x',
        ]);

        $row = $this->typedTableGateway->findOneBy(['created_at' => '2026-11-05 03:14:15']);

        self::assertNotNull($row);
        $foundAt = $row['created_at'];
        self::assertInstanceOf(DateTimeImmutable::class, $foundAt);
        self::assertSame(
            new DateTimeImmutable('2026-11-05 03:14:15 UTC')->getTimestamp(),
            $foundAt->getTimestamp(),
        );
        self::assertSame(['a' => 1], $row['payload']);
    }

    public function testTheAbsentRowStillAnswersNull(): void
    {
        self::assertNull($this->typedTableGateway->find(999));
    }

    public function testTheHonestDefaultKeepsTheRawNarrowingOnTheSameTable(): void
    {
        $tableGateway = new TableGateway($this->pdoConnection, 'events');
        $jakarta = new DateTimeImmutable('2026-11-05 10:14:15', new DateTimeZone('Asia/Jakarta'));

        $this->typedTableGateway->insert(['created_at' => $jakarta, 'payload' => ['kunci' => 'nilai']]);

        $row = $tableGateway->find(1);

        self::assertNotNull($row);
        self::assertSame(
            '2026-11-05 03:14:15',
            $row['created_at'],
            'the raw narrowing reads the UTC wall spelling the map stored',
        );
        self::assertSame(
            '{"kunci":"nilai"}',
            $row['payload'],
            'the raw narrowing reads the encoded string the map stored',
        );
    }

    public function testTheRawSideRefusesThePhpValueLoudly(): void
    {
        $tableGateway = new TableGateway($this->pdoConnection, 'events');

        $this->expectException(QueryException::class);
        $this->expectExceptionMessageIsOrContains(
            'must be null, bool, int, float, string or SqlExpression (got DateTimeImmutable)',
        );

        $tableGateway->insert(['created_at' => new DateTimeImmutable('2026-11-05 03:14:15')]);
    }

    public function testTheTypedSideRefusesTheMalformedCellLoudly(): void
    {
        $tableGateway = new TableGateway($this->pdoConnection, 'events');
        $tableGateway->insert(['payload' => '{oops']);

        $this->expectException(TypeMappingException::class);
        $this->expectExceptionMessageIsOrContains("The JSON column's stored cell '{oops' is malformed JSON");

        $this->typedTableGateway->find(1);
    }

    public function testTheTypedSideRefusesTheNonDatetimeCellLoudly(): void
    {
        $tableGateway = new TableGateway($this->pdoConnection, 'events');
        $tableGateway->insert(['created_at' => 'not a datetime']);

        $this->expectException(TypeMappingException::class);
        $this->expectExceptionMessageIsOrContains(
            "The datetime column's stored cell 'not a datetime' is not a spelling the immutable clock reads",
        );

        $this->typedTableGateway->find(1);
    }

    public function testTheUnmappedColumnTravelsAsTheHostSpelledIt(): void
    {
        $this->typedTableGateway->insert(['note' => 'plain', 'payload' => ['a' => 1]]);

        $row = $this->typedTableGateway->find(1);

        self::assertNotNull($row);
        self::assertSame('plain', $row['note']);
    }

    public function testFindSpellsTheSingleRowSelectExactly(): void
    {
        $scriptedConnection = new ScriptedConnection();
        $scriptedConnection->fetchOneScript = [null];

        $typedTableGateway = new TypedTableGateway(
            $scriptedConnection,
            'events',
            new TypeMap(['payload' => new JsonType()]),
        );

        self::assertNull($typedTableGateway->find(1));
        self::assertSame(
            ['SELECT * FROM "events" WHERE "id" = ? LIMIT 1'],
            $scriptedConnection->executed,
            'the typed primary-key lookup pins its single-row limit',
        );
    }

    public function testFindOneBySpellsTheSingleRowSelectExactly(): void
    {
        $scriptedConnection = new ScriptedConnection();
        $scriptedConnection->fetchOneScript = [null];

        $typedTableGateway = new TypedTableGateway(
            $scriptedConnection,
            'events',
            new TypeMap(['payload' => new JsonType()]),
        );

        self::assertNull($typedTableGateway->findOneBy(['note' => 'x']));
        self::assertSame(
            ['SELECT * FROM "events" WHERE "note" = ? LIMIT 1'],
            $scriptedConnection->executed,
            'the typed criteria lookup pins its single-row limit',
        );
    }

    public function testTheEagerTableGrammarRunsForTheTypedToo(): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionMessageIsOrContains("Invalid table 'events; DROP'.");

        new TypedTableGateway(
            $this->pdoConnection,
            'events; DROP',
            new TypeMap(['payload' => new JsonType()]),
        );
    }
}
