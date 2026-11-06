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
 * The name's grammar corridor is the value fold's own ({@see
 * MigrationName}: lowercase words of letters and digits, single
 * spaces or hyphens, the 128-byte ceiling, the separators
 * normalised to ONE migration) and the version's is the template's
 * own boundary -- both refused as construction values ({@see
 * \InvalidArgumentException}, the CLI command name's own doctrine)
 * rather than the migration phase's typed family, so a caller
 * cannot compose a file the scan would refuse.
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
    private const string VERSION_RE = '/^\d{14}$/';

    /**
     * The file name a generated migration wears: the version stamp,
     * the name's slug, the extension -- the grammar the flat
     * alphabetical scan reads version-major, so a generated file
     * sorts where its version places it.
     */
    public static function fileName(string $name, string $version): string
    {
        $migrationName = MigrationName::fromSpelling($name);
        self::assertVersion($version);

        return $version . '-' . $migrationName->slug() . '.php';
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
        $migrationName = MigrationName::fromSpelling($name);
        self::assertVersion($version);

        $className = $migrationName->classNameWords() . $version;

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
            $migrationName->canonical(),
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
        return MigrationName::fromSpelling($name)->canonical();
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
