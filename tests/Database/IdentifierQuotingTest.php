<?php

declare(strict_types=1);

namespace Zef\Test\Database;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Database\IdentifierQuoting;
use Zef\Framework\Database\QueryException;
use Zef\Framework\Database\SqlExpression;
use Zef\Framework\Database\SqlIdentifier;

/**
 * The issue #778 dialect vocabulary's own matrix: which driver word
 * answers which quoting, both quote spellings, the LIKE escape's two
 * dialects end to end (pattern transform AND clause), and the
 * {@see SqlIdentifier} collaboration -- the fold quotes in the
 * vocabulary it was constructed with, its default staying the
 * standard's Ansi so every pre-vocabulary deployment's SQL is
 * byte-identical.
 *
 * @internal
 */
#[CoversClass(IdentifierQuoting::class)]
#[CoversClass(SqlIdentifier::class)]
final class IdentifierQuotingTest extends TestCase
{
    #[DataProvider('provideDriverWordsAnswerTheirVocabularyCases')]
    public function testDriverWordsAnswerTheirVocabulary(string $driver, IdentifierQuoting $identifierQuoting): void
    {
        self::assertSame($identifierQuoting, IdentifierQuoting::forDriver($driver));
    }

    /**
     * @return iterable<non-empty-string, array{string, IdentifierQuoting}>
     */
    public static function provideDriverWordsAnswerTheirVocabularyCases(): iterable
    {
        yield 'mysql deviates from the standard' => ['mysql', IdentifierQuoting::MySql];

        yield 'postgresql answers the standard' => ['pgsql', IdentifierQuoting::Ansi];

        yield 'sqlite answers the standard' => ['sqlite', IdentifierQuoting::Ansi];

        yield 'an unknown custom word answers the standard' => ['sqlsrv', IdentifierQuoting::Ansi];

        yield 'the empty word answers the standard' => ['', IdentifierQuoting::Ansi];

        yield 'the word is matched exactly, not folded' => ['MySQL', IdentifierQuoting::Ansi];
    }

    #[DataProvider('provideBothVocabulariesQuoteTheirIdentifierCases')]
    public function testBothVocabulariesQuoteTheirIdentifier(IdentifierQuoting $identifierQuoting, string $quoted): void
    {
        self::assertSame($quoted, $identifierQuoting->quote('users'));
    }

    /**
     * @return iterable<non-empty-string, array{IdentifierQuoting, non-empty-string}>
     */
    public static function provideBothVocabulariesQuoteTheirIdentifierCases(): iterable
    {
        yield 'ansi double quotes' => [IdentifierQuoting::Ansi, '"users"'];

        yield 'mysql backticks' => [IdentifierQuoting::MySql, '`users`'];
    }

    public function testTheSqlIdentifierQuotesInTheVocabularyItWasConstructedWith(): void
    {
        $sqlIdentifier = new SqlIdentifier(IdentifierQuoting::MySql);

        self::assertSame('`users`', $sqlIdentifier->quote('users'));
        self::assertSame('`app_db`.`users`', $sqlIdentifier->table('app_db.users'));
        self::assertSame('`u`.`id`', $sqlIdentifier->columnPath('u.id'));
    }

    public function testTheSqlIdentifierDefaultStaysTheStandardsSpelling(): void
    {
        // The pre-vocabulary contract, unchanged: every deployment that
        // declares no MySQL word sees byte-identical SQL.
        self::assertSame(IdentifierQuoting::Ansi, new SqlIdentifier()->quoting());
        self::assertSame('"users"', new SqlIdentifier()->quote('users'));
    }

    public function testTheSqlIdentifierExposesTheVocabularyThePredicateFoldsRead(): void
    {
        self::assertSame(IdentifierQuoting::MySql, new SqlIdentifier(IdentifierQuoting::MySql)->quoting());
    }

    public function testTheGrammarRefusesTheSameVocabularyOverBothQuotings(): void
    {
        // The grammar is the dialect-neutral half: a backticked or
        // double-quoted input identifier refuses identically whichever
        // vocabulary would have quoted it -- the grammar refused the
        // quote characters before the vocabulary ever reached them.
        $sqlIdentifier = new SqlIdentifier(IdentifierQuoting::MySql);

        self::assertSame('`id`', $sqlIdentifier->quote('id'));

        $this->expectException(QueryException::class);
        $this->expectExceptionMessageIsOrContains("Invalid column '`id`'.");

        $sqlIdentifier->quote('`id`', 'column');
    }

    public function testTheRawExpressionPassesThroughBothQuotingsVerbatim(): void
    {
        $sqlIdentifier = new SqlIdentifier(IdentifierQuoting::MySql);

        self::assertSame(
            '(SELECT 1) AS derived',
            $sqlIdentifier->table(new SqlExpression('(SELECT 1) AS derived')),
        );
    }

    #[DataProvider('provideEscapeLikePatternDialectsCases')]
    public function testEscapeLikePatternDialects(
        IdentifierQuoting $identifierQuoting,
        string $pattern,
        string $escaped,
    ): void {
        self::assertSame($escaped, $identifierQuoting->escapeLikePattern($pattern));
    }

    /**
     * @return iterable<non-empty-string, array{IdentifierQuoting, non-empty-string, non-empty-string}>
     */
    public static function provideEscapeLikePatternDialectsCases(): iterable
    {
        yield 'ansi escapes the backslash first' => [
            IdentifierQuoting::Ansi,
            'a\b%c_d',
            'a\\\b\%c\_d',
        ];

        yield 'mysql escapes its own escape character first' => [
            IdentifierQuoting::MySql,
            'a|b\c%d_e',
            'a||b|\c|%d|_e',
        ];

        yield 'mysql pattern of the escape character alone doubles it' => [
            IdentifierQuoting::MySql,
            '|',
            '||',
        ];

        yield 'ansi pattern of the backslash alone doubles it' => [
            IdentifierQuoting::Ansi,
            '\\',
            '\\\\',
        ];

        yield 'mysql leaves an unladen pipe after its own doubling' => [
            IdentifierQuoting::MySql,
            'a|',
            'a||',
        ];
    }

    public function testTheLikeEscapeClauseSpellsEachDialect(): void
    {
        // The clause's own reason lives in the vocabulary's docblock:
        // the backslash spelling cannot ride MySQL -- its literal
        // would need one spelling under the default sql_mode and
        // another under NO_BACKSLASH_ESCAPES.
        self::assertSame(" ESCAPE '\\'", IdentifierQuoting::Ansi->likeEscapeClause());
        self::assertSame(" ESCAPE '|'", IdentifierQuoting::MySql->likeEscapeClause());
    }
}
