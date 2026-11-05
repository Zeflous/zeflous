<?php

declare(strict_types=1);

namespace Zef\Test\Database;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Database\QueryException;
use Zef\Framework\Database\TableName;

/**
 * Pins the reflected table name's grammar fold: the builder's own
 * identifier vocabulary, single-segment, validated at construction
 * so a misspelled ask surfaces before the first question travels to
 * the socket.
 *
 * @internal
 */
#[CoversClass(TableName::class)]
final class TableNameTest extends TestCase
{
    public function testTheGrammarAcceptsTheBuildersOwnVocabulary(): void
    {
        self::assertSame('users', new TableName('users')->name);
        self::assertSame('a', new TableName('a')->name);
        self::assertSame('_ledger', new TableName('_ledger')->name);
        self::assertSame('t48', new TableName('t48')->name);
    }

    public function testTheEmptyNameRefuses(): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionMessageIsOrContains('table name must not be empty.');

        new TableName('');
    }

    public function testTheNameOutsideTheGrammarRefuses(): void
    {
        try {
            new TableName('1abc');
            self::fail('a digit head is outside the grammar');
        } catch (QueryException $queryException) {
            self::assertSame("Invalid table name '1abc'.", $queryException->getMessage());
        }

        try {
            new TableName('bad_name!');
            self::fail('a punctuation tail is outside the grammar');
        } catch (QueryException $queryException) {
            self::assertSame("Invalid table name 'bad_name!'.", $queryException->getMessage());
        }
    }

    public function testTheDottedPathRefusesBecauseReflectionOwnsOneSegment(): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionMessageIsOrContains("Invalid table name 'schema.users'.");

        new TableName('schema.users');
    }

    public function testTheOverlongNameRefusesAtTheGrammarCeiling(): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionMessageIsOrContains('table name exceeds the 64-byte limit (got 65).');

        new TableName(str_repeat('n', 65));
    }
}
