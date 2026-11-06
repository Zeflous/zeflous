<?php

declare(strict_types=1);

namespace Zef\Framework\Database;

use InvalidArgumentException;

/**
 * The house migration template: the exact file shape the scaffolding
 * verb prints for a new migration -- the interface's own contract
 * ({@see MigrationInterface}) spelled with the migration's name and
 * its 14-digit version stamp, nothing else. No scaffolding magic
 * beyond the names: a final class carrying the interface's five
 * verbs, the version and the canonical name as literal answers, the
 * two DDL verbs left empty for the operator to own, the lock TTL
 * answer the ordinary-migration doctrine spells (`null` -- the
 * runner's default), and the file's own return composing the class,
 * the declaration grammar {@see MigrationFile} includes.
 *
 * The name grammar is the template's own corridor, refused as a
 * construction value ({@see InvalidArgumentException}, the CLI
 * command name's own doctrine) rather than the migration phase's
 * typed family: lowercase words of letters and digits (a word may
 * start with a digit, the name itself must start with a letter)
 * separated by single spaces or hyphens, at most the registration
 * grammar's own 128-byte ceiling ({@see MigrationRegistration}), and a version
 * that is exactly the 14-digit stamp the runner's own grammar
 * demands. The separator is normalised, never invented: a name
 * spelled with hyphens and the same name spelled with spaces are
 * ONE migration, the canonical name carrying spaces (the
 * human-readable spelling {@see MigrationInterface::name()} answers)
 * and the file slug carrying hyphens (the file grammar's own
 * spelling, `20261106143000-create-users.php`).
 *
 * The class name carries the version stamp beside the name's own
 * words (`CreateUsers20261106143000`), the reference's own generate
 * lineage (Doctrine's `Version` spelling): two migrations sharing a
 * name at different times are two DIFFERENT classes, so the
 * discovery walk's flat includes ({@see MigrationFiles}) never meet
 * a duplicate class name -- the version stamp is the uniqueness the
 * file grammar already sorts by, made structural in the class name
 * instead of a fatal the second include would raise.
 *
 * @internal
 */
final class MigrationTemplate
{
    private const string NAME_RE = '/^[a-z][a-z0-9]*(?:[- ][a-z0-9]+)*$/';

    private const string VERSION_RE = '/^\d{14}$/';

    private const int MAX_NAME_BYTES = 128;

    /**
     * The file name a generated migration wears: the version stamp,
     * the name's slug, the extension -- the grammar the flat
     * alphabetical scan reads version-major, so a generated file
     * sorts where its version places it.
     */
    public static function fileName(string $name, string $version): string
    {
        self::assertName($name);
        self::assertVersion($version);

        return $version . '-' . self::slug($name) . '.php';
    }

    /**
     * The whole generated file, byte for byte: the interface's five
     * verbs with the version, the canonical name and the null lock
     * TTL as literal answers, the two DDL verbs left empty, and the
     * file's own return composing the class -- the declaration
     * {@see MigrationFile::declaration} includes. A template that
     * drifts from this shape is a footgun printed on every generate,
     * so the byte spelling is the pin the tests assert against.
     */
    public static function content(string $name, string $version): string
    {
        self::assertName($name);
        self::assertVersion($version);

        $className = self::className($name, $version);
        $canonicalName = self::canonicalWords($name);

        return \sprintf(
            '<?php

declare(strict_types=1);

use Zef\Framework\Database\ConnectionInterface;
use Zef\Framework\Database\MigrationInterface;

final class %s implements MigrationInterface
{
    public function version(): string
    {
        return \'%s\';
    }

    public function name(): string
    {
        return \'%s\';
    }

    public function up(ConnectionInterface $connection): void
    {
    }

    public function down(ConnectionInterface $connection): void
    {
    }

    public function getLockTtl(): ?float
    {
        return null;
    }
}

return new %s();
',
            $className,
            $version,
            $canonicalName,
            $className,
        );
    }

    /**
     * The canonical name a generated migration answers: the words
     * the operator spelled, joined by single spaces -- the
     * human-readable spelling {@see MigrationInterface::name()}
     * carries, whichever separator the argument brought. One
     * canonical name per migration, the same answer the generated
     * file's own name verb returns.
     *
     * @return non-empty-string the words joined by single spaces
     */
    public static function canonicalName(string $name): string
    {
        self::assertName($name);

        return self::canonicalWords($name);
    }

    /**
     * The words joined by single spaces, without the corridor: the
     * private fold every public entry composes AFTER its own
     * refusal, so each public spelling validates exactly once and
     * the validation's removal is visible in that entry's own
     * answer -- never masked by a nested re-validation.
     *
     * @return non-empty-string the words joined by single spaces
     */
    private static function canonicalWords(string $name): string
    {
        $words = preg_split('/[- ]/', $name);
        \assert(\is_array($words));

        $canonicalName = implode(' ', $words);
        // The grammar the public entries refused above left at
        // least one word, so the joined spelling is never empty.
        \assert($canonicalName !== '');

        return $canonicalName;
    }

    /**
     * The class name a generated migration declares: the name's own
     * words, capitalised, carrying the version stamp beside them --
     * two migrations sharing a name are two classes, the duplicate
     * the discovery walk would otherwise fatal on.
     */
    private static function className(string $name, string $version): string
    {
        $words = preg_split('/[- ]/', $name);
        \assert(\is_array($words));

        $className = '';

        foreach ($words as $word) {
            $className .= ucfirst($word);
        }

        return $className . $version;
    }

    /**
     * The file slug the generated migration's name wears: the words
     * joined by single hyphens -- the file grammar's own spelling.
     */
    private static function slug(string $name): string
    {
        $words = preg_split('/[- ]/', $name);
        \assert(\is_array($words));

        return implode('-', $words);
    }

    /**
     * The name grammar's own refusal: lowercase words of letters and
     * digits separated by single spaces or hyphens, within the
     * registration grammar's 128-byte ceiling.
     */
    private static function assertName(string $name): void
    {
        if (
            preg_match(self::NAME_RE, $name) !== 1
            || mb_strlen($name, '8bit') > self::MAX_NAME_BYTES
        ) {
            throw new InvalidArgumentException(
                'Migration name must be lowercase words of letters and digits'
                . ' separated by single spaces or hyphens, 1..' . self::MAX_NAME_BYTES . ' bytes'
                . " (spelled '{$name}').",
            );
        }
    }

    /**
     * The version grammar's own refusal: the 14-digit stamp, the
     * runner's own corridor spelled at the template's boundary so a
     * caller cannot compose a file the scan would refuse.
     */
    private static function assertVersion(string $version): void
    {
        if (preg_match(self::VERSION_RE, $version) !== 1) {
            throw new InvalidArgumentException(
                "Migration version '{$version}' must be a 14-digit timestamp (YYYYmmddHHMMSS).",
            );
        }
    }
}
