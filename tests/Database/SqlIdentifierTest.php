<?php

declare(strict_types=1);

namespace Zef\Test\Database;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Database\QueryException;
use Zef\Framework\Database\SqlColumn;
use Zef\Framework\Database\SqlExpression;
use Zef\Framework\Database\SqlIdentifier;

/**
 * The identifier grammar's own matrix: every accepted spelling answers
 * double-quoted, the byte ceiling is enforced on both sides, the dot
 * path is capped at two segments, the `AS alias` spelling is anchored
 * (case-insensitive keyword, strict sides), and the raw-SqlExpression
 * passthrough stays verbatim -- the one hole user input can never
 * reach, because every other branch validates before it quotes.
 *
 * @internal
 */
#[CoversClass(SqlIdentifier::class)]
#[CoversClass(SqlColumn::class)]
#[CoversClass(QueryException::class)]
final class SqlIdentifierTest extends TestCase
{
    private SqlIdentifier $sqlIdentifier;

    #[Override]
    protected function setUp(): void
    {
        $this->sqlIdentifier = new SqlIdentifier();
    }

    #[DataProvider('provideAcceptedIdentifiersAnswerQuotedCases')]
    public function testAcceptedIdentifiersAnswerQuoted(string $identifier, string $quoted): void
    {
        self::assertSame($quoted, $this->sqlIdentifier->quote($identifier));
    }

    /**
     * @return iterable<non-empty-string, array{non-empty-string, non-empty-string}>
     */
    public static function provideAcceptedIdentifiersAnswerQuotedCases(): iterable
    {
        yield 'single letter' => ['a', '"a"'];

        yield 'underscore first' => ['_private', '"_private"'];

        yield 'mixed case' => ['UserId', '"UserId"'];

        yield 'sixty-four bytes' => [str_repeat('a', 64), '"' . str_repeat('a', 64) . '"'];

        yield 'digits after first' => ['col_2', '"col_2"'];
    }

    public function testTheEmptyIdentifierIsRefused(): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionMessageIsOrContains('column must not be empty.');

        $this->sqlIdentifier->quote('', 'column');
    }

    public function testTheByteCeilingIsRefusedWithTheExactCount(): void
    {
        $identifier = str_repeat('a', 65);

        $this->expectException(QueryException::class);
        $this->expectExceptionMessageIsOrContains('identifier exceeds the 64-byte limit (got 65).');

        $this->sqlIdentifier->quote($identifier);
    }

    #[DataProvider('provideGrammarRefusalsNameTheIdentifierCases')]
    public function testGrammarRefusalsNameTheIdentifier(string $identifier): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionMessageIsOrContains("Invalid identifier '{$identifier}'.");

        $this->sqlIdentifier->quote($identifier);
    }

    /**
     * @return iterable<non-empty-string, array{non-empty-string}>
     */
    public static function provideGrammarRefusalsNameTheIdentifierCases(): iterable
    {
        yield 'leading digit' => ['1a'];

        yield 'hyphen' => ['user-id'];

        yield 'space inside' => ['user id'];

        yield 'quoted injection' => ['users"; DROP TABLE t; --'];

        yield 'backtick' => ['`id`'];

        yield 'semicolon' => ['id;'];
    }

    public function testTheRawFragmentRefusalNamesTheEscapeHatch(): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionMessageIsOrContains('for raw SQL pass an explicit SqlExpression instead');

        $this->sqlIdentifier->quote('COUNT(*)');
    }

    public function testTheTablePathAcceptsOneDotAndTrims(): void
    {
        self::assertSame('"public"."users"', $this->sqlIdentifier->table('public.users'));
        self::assertSame('"users"', $this->sqlIdentifier->table(' users '));
    }

    public function testTheTablePathRefusesTwoDots(): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionMessageIsOrContains("Invalid table 'a.b.c' (at most one dot is allowed).");

        $this->sqlIdentifier->table('a.b.c');
    }

    public function testTheTableExpressionPassesThroughVerbatim(): void
    {
        self::assertSame(
            '(SELECT 1) AS derived',
            $this->sqlIdentifier->table(new SqlExpression('(SELECT 1) AS derived')),
        );
    }

    public function testTheColumnPathAcceptsOneDot(): void
    {
        self::assertSame('"u"."age"', $this->sqlIdentifier->columnPath('u.age'));
    }

    public function testTheColumnPathRefusesTwoDots(): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionMessageIsOrContains("Invalid column 'a.b.c' (at most one dot is allowed).");

        $this->sqlIdentifier->columnPath('a.b.c');
    }

    #[DataProvider('provideRenderedColumnsSpellTheQuotedShapeCases')]
    public function testRenderedColumnsSpellTheQuotedShape(string $column, string $rendered): void
    {
        self::assertSame($rendered, SqlColumn::render($this->sqlIdentifier, $column));
    }

    /**
     * @return iterable<non-empty-string, array{non-empty-string, non-empty-string}>
     */
    public static function provideRenderedColumnsSpellTheQuotedShapeCases(): iterable
    {
        yield 'bare column' => ['email', '"email"'];

        yield 'dotted column' => ['u.age', '"u"."age"'];

        yield 'alias keyword lowercase' => ['name as n', '"name" AS "n"'];

        yield 'alias keyword uppercase' => ['name AS n', '"name" AS "n"'];

        yield 'dotted path with alias' => ['u.age AS years', '"u"."age" AS "years"'];
    }

    public function testTheAliasGrammarIsAnchoredOnBothSides(): void
    {
        // The regex must not accept a two-token tail: `AS n extra` fails
        // the anchored match and falls through to the plain path refusal.
        $this->expectException(QueryException::class);
        $this->expectExceptionMessageIsOrContains('Invalid column');

        SqlColumn::render($this->sqlIdentifier, 'name AS n extra');
    }

    public function testTheBlankColumnIsRefused(): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionMessageIsOrContains('Column must not be empty.');

        SqlColumn::render($this->sqlIdentifier, '   ');
    }

    public function testTheExpressionColumnPassesThroughVerbatim(): void
    {
        self::assertSame('NOW()', SqlColumn::render($this->sqlIdentifier, new SqlExpression('NOW()')));
    }

    public function testTheByteCeilingCountsBytesNotCharacters(): void
    {
        // 33 two-byte characters: 66 bytes over the 64-byte ceiling the
        // byte spelling refuses, while the character spelling would let
        // it through to the grammar's own refusal -- the ceiling's own
        // message names which one fired.
        $this->expectException(QueryException::class);
        $this->expectExceptionMessageIsOrContains('identifier exceeds the 64-byte limit (got 66).');

        $this->sqlIdentifier->quote(str_repeat("\u{00e9}", 33));
    }

    public function testTheAliasGrammarIsAnchoredOnItsHead(): void
    {
        // Leading garbage: without the caret the pattern would harvest
        // the trailing `name AS n` and render it as an alias spelling.
        $this->expectException(QueryException::class);
        $this->expectExceptionMessageIsOrContains('Invalid column');

        SqlColumn::render($this->sqlIdentifier, '!!! name AS n');
    }

    public function testTheRawFragmentRefusalSpellsTheWholeHint(): void
    {
        $caught = null;
        try {
            $this->sqlIdentifier->quote('COUNT(*)');
        } catch (QueryException $queryException) {
            $caught = $queryException;
        }

        self::assertNotNull($caught);
        self::assertSame(
            "Invalid identifier 'COUNT(*)'. Identifiers must match [A-Za-z_][A-Za-z0-9_]{0,63}"
            . ' (optionally table.column or column AS alias); for raw SQL pass an'
            . ' explicit SqlExpression instead — e.g. new SqlExpression(...),'
            . ' selectRaw(...), or SqlQuery::raw(...).',
            $caught->getMessage(),
        );
    }
}
