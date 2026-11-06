<?php

declare(strict_types=1);

namespace Zef\Test\Database;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Database\MigrationInterface;
use Zef\Framework\Database\MigrationTemplate;

/**
 * The house migration template ({@see MigrationTemplate}): the exact
 * file shape the generate verb prints, pinned byte for byte against
 * the interface's own contract ({@see MigrationInterface}) -- the
 * class shape, the name and version grammar corridors, the
 * canonical-name normalisation, and the file name's sort position
 * in the flat alphabetical scan the discovery walk reads. A
 * template that drifts from the contract is a footgun printed on
 * every generate, so the generated file is INCLUDED and its own
 * answers asserted: the contract pin, not just the byte pin.
 *
 * @internal
 */
#[CoversClass(MigrationTemplate::class)]
final class MigrationTemplateTest extends TestCase
{
    private const string NAME = 'create users';

    private const string VERSION = '20261106143000';

    /**
     * The whole generated file, byte for byte: the interface's five
     * verbs, the version and the canonical name as literal answers,
     * the two DDL verbs left empty, the null lock TTL, and the
     * file's own return composing the class.
     */
    public function testTheContentIsTheHouseTemplateByteForByte(): void
    {
        self::assertSame(
            "<?php

declare(strict_types=1);

use Zef\\Framework\\Database\\ConnectionInterface;
use Zef\\Framework\\Database\\MigrationInterface;

final class CreateUsers20261106143000 implements MigrationInterface
{
    public function version(): string
    {
        return '20261106143000';
    }

    public function name(): string
    {
        return 'create users';
    }

    public function up(ConnectionInterface \$connection): void
    {
    }

    public function down(ConnectionInterface \$connection): void
    {
    }

    public function getLockTtl(): ?float
    {
        return null;
    }
}

return new CreateUsers20261106143000();
",
            MigrationTemplate::content(self::NAME, self::VERSION),
        );
    }

    /**
     * The contract pin: the generated file, included, answers the
     * interface itself -- a template that drifts from the contract
     * is a footgun printed on every generate, so the file's own
     * return is asserted, not just its bytes. The version stamp is
     * this test's own: the suite's one PHP process keeps every
     * declared class, so no other test includes it.
     */
    public function testTheGeneratedFileSatisfiesTheInterfaceContract(): void
    {
        $file = sys_get_temp_dir() . '/zef-migration-template-' . uniqid('', false) . '.php';

        try {
            file_put_contents(
                $file,
                MigrationTemplate::content(self::NAME, self::VERSION),
            );

            $migration = include $file;

            self::assertInstanceOf(MigrationInterface::class, $migration);
            self::assertSame(self::VERSION, $migration->version());
            self::assertSame(self::NAME, $migration->name());
            self::assertNull($migration->getLockTtl());
        } finally {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }

    /**
     * The class name carries the version stamp beside the name's
     * own words: two migrations sharing a name at different times
     * are two DIFFERENT classes, so the discovery walk's flat
     * includes never meet a duplicate class name. The two stamps
     * are this test's own (no other test includes them), because
     * the suite's one PHP process keeps every declared class.
     */
    public function testTwoSameNamedMigrationsIncludeAsTwoClasses(): void
    {
        $first = sys_get_temp_dir() . '/zef-migration-template-' . uniqid('', false) . '-a.php';
        $second = sys_get_temp_dir() . '/zef-migration-template-' . uniqid('', false) . '-b.php';

        try {
            file_put_contents($first, MigrationTemplate::content(self::NAME, '20261106143010'));
            file_put_contents($second, MigrationTemplate::content(self::NAME, '20261106143011'));

            $firstMigration = include $first;
            $secondMigration = include $second;

            self::assertInstanceOf(MigrationInterface::class, $firstMigration);
            self::assertInstanceOf(MigrationInterface::class, $secondMigration);
            self::assertNotSame($firstMigration::class, $secondMigration::class);
        } finally {
            if (is_file($first)) {
                unlink($first);
            }

            if (is_file($second)) {
                unlink($second);
            }
        }
    }

    /**
     * The file name: the version stamp, the name's slug, the
     * extension -- the grammar the flat alphabetical scan reads.
     */
    public function testTheFileNameWearsTheVersionAndTheSlug(): void
    {
        self::assertSame(
            '20261106143000-create-users.php',
            MigrationTemplate::fileName(self::NAME, self::VERSION),
        );
    }

    /**
     * The sort position: the version stamp leads the file name, so
     * a generated file sorts where its version places it in the
     * flat alphabetical scan -- the older version first, always.
     */
    public function testTheFileNameSortsWhereItsVersionPlacesIt(): void
    {
        $older = MigrationTemplate::fileName(self::NAME, '20261105143000');
        $newer = MigrationTemplate::fileName(self::NAME, '20261106143000');

        self::assertSame(-1, strcmp($older, $newer));
        self::assertStringStartsWith('20261105143000-', $older);
    }

    /**
     * The separator is normalised, never invented: a name spelled
     * with hyphens and the same name spelled with spaces are ONE
     * migration -- the same file name, the same content.
     */
    public function testTheHyphenSpelledNameAnswersTheSameMigration(): void
    {
        self::assertSame(
            MigrationTemplate::fileName(self::NAME, self::VERSION),
            MigrationTemplate::fileName('create-users', self::VERSION),
        );
        self::assertSame(
            MigrationTemplate::content(self::NAME, self::VERSION),
            MigrationTemplate::content('create-users', self::VERSION),
        );
    }

    /**
     * The canonical name: the words the operator spelled, joined by
     * single spaces -- whichever separator the argument carried.
     */
    public function testTheCanonicalNameJoinsTheWordsWithSpaces(): void
    {
        self::assertSame('create users', MigrationTemplate::canonicalName(self::NAME));
        self::assertSame('create users', MigrationTemplate::canonicalName('create-users'));
        self::assertSame('enable 2fa login', MigrationTemplate::canonicalName('enable-2fa login'));
    }

    /**
     * The name grammar's own corridor, refused as a construction
     * value naming the spelling: uppercase, a leading digit, an
     * empty spelling, doubled separators, a leading or trailing
     * separator, an underscore, and a spell past the 128-byte
     * ceiling all refuse -- through every public entry.
     */
    public function testANameOutsideTheGrammarRefusesNamingIt(): void
    {
        $refusedNames = [
            'Create Users',
            'create Users',
            '2fa login',
            '',
            'create  users',
            'create--users',
            ' create users',
            'create users ',
            '-create-users',
            'create-users-',
            'create_users',
            'créate users',
            str_repeat('a ', 63) . 'aaa',
        ];

        foreach ($refusedNames as $refusedName) {
            $message = 'Migration name must be lowercase words of letters and digits'
                . ' separated by single spaces or hyphens, 1..128 bytes'
                . " (spelled '{$refusedName}').";

            try {
                MigrationTemplate::fileName($refusedName, self::VERSION);
                self::fail("The name '{$refusedName}' was not refused by fileName().");
            } catch (InvalidArgumentException $invalidArgumentException) {
                self::assertSame($message, $invalidArgumentException->getMessage());
            }

            try {
                MigrationTemplate::content($refusedName, self::VERSION);
                self::fail("The name '{$refusedName}' was not refused by content().");
            } catch (InvalidArgumentException $invalidArgumentException) {
                self::assertSame($message, $invalidArgumentException->getMessage());
            }

            try {
                MigrationTemplate::canonicalName($refusedName);
                self::fail("The name '{$refusedName}' was not refused by canonicalName().");
            } catch (InvalidArgumentException $invalidArgumentException) {
                self::assertSame($message, $invalidArgumentException->getMessage());
            }
        }
    }

    /**
     * The version grammar's own corridor: the 14-digit stamp the
     * runner demands, refused at the template's boundary so a
     * caller cannot compose a file the scan would refuse.
     */
    public function testAVersionOutsideTheGrammarRefusesNamingIt(): void
    {
        $refusedVersions = [
            '2026110614300',
            '202611061430001',
            '2026110614300a',
            '2026110614300 ',
            '',
        ];

        foreach ($refusedVersions as $refusedVersion) {
            try {
                MigrationTemplate::fileName(self::NAME, $refusedVersion);
                self::fail("The version '{$refusedVersion}' was not refused by fileName().");
            } catch (InvalidArgumentException $invalidArgumentException) {
                self::assertSame(
                    "Migration version '{$refusedVersion}' must be a 14-digit"
                    . ' timestamp (YYYYmmddHHMMSS).',
                    $invalidArgumentException->getMessage(),
                );
            }

            try {
                MigrationTemplate::content(self::NAME, $refusedVersion);
                self::fail("The version '{$refusedVersion}' was not refused by content().");
            } catch (InvalidArgumentException $invalidArgumentException) {
                self::assertSame(
                    "Migration version '{$refusedVersion}' must be a 14-digit"
                    . ' timestamp (YYYYmmddHHMMSS).',
                    $invalidArgumentException->getMessage(),
                );
            }
        }
    }

    /**
     * The grammar's own acceptance edges: digits ride inside a word,
     * a single word is a whole name, the 128-byte ceiling holds at
     * its own boundary spelling (a name of exactly 128 bytes
     * passes; the 129-byte spell refuses in the corridor's own
     * test above), and the class name is the name's own words
     * capitalised with the stamp beside them.
     */
    public function testTheGrammarAcceptsItsOwnEdges(): void
    {
        self::assertSame(
            '20261106143000-enable-2fa-login.php',
            MigrationTemplate::fileName('enable 2fa login', self::VERSION),
        );
        self::assertSame(
            '20261106143000-init.php',
            MigrationTemplate::fileName('init', self::VERSION),
        );
        self::assertSame(
            str_repeat('a ', 63) . 'aa',
            MigrationTemplate::canonicalName(str_repeat('a ', 63) . 'aa'),
        );
        self::assertSame(
            'Init20261106143000',
            $this->classDeclaredBy(MigrationTemplate::content('init', self::VERSION)),
        );
        self::assertSame(
            'Enable2faLogin20261106143000',
            $this->classDeclaredBy(MigrationTemplate::content('enable 2fa login', self::VERSION)),
        );
    }

    /**
     * The class name a generated file declares, read back from the
     * content's own declaration line.
     */
    private function classDeclaredBy(string $content): string
    {
        self::assertSame(1, preg_match('/^final class (\w+) implements/m', $content, $matches));

        return $matches[1];
    }
}
