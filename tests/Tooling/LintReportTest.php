<?php

declare(strict_types=1);

namespace Zef\Test\Tooling;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Test\Support\Tooling\LintReport;
use Zef\Test\Support\Tooling\ToolingException;

/**
 * @internal
 */
#[CoversClass(LintReport::class)]
final class LintReportTest extends TestCase
{
    private string $root;

    #[Override]
    protected function setUp(): void
    {
        $this->root = (string) tempnam(sys_get_temp_dir(), 'zef-lint-');
        unlink($this->root);
        mkdir($this->root . '/src/sub', 0o777, true);
        mkdir($this->root . '/alpha', 0o777, true);
        // The sort()-removal mutant's kill deliberately does NOT rest on
        // this file's creation preceding A.php: a hash-ordered directory
        // (the ext4 the CI runners mount) answers readdir by name hash,
        // not by creation order, so a single directory's iteration order
        // can coincide with the sorted expectation by luck alone. The
        // deterministic kill lives one level up, in the DIRECTORIES
        // argument itself -- see the cross-directory test below.
        file_put_contents($this->root . '/src/Z.php', '<?php');
        file_put_contents($this->root . '/src/A.php', '<?php');
        file_put_contents($this->root . '/src/notes.txt', 'not php');
        file_put_contents($this->root . '/src/sub/B.php', '<?php');
        file_put_contents($this->root . '/alpha/First.php', '<?php');
    }

    #[Override]
    protected function tearDown(): void
    {
        unlink($this->root . '/src/Z.php');
        unlink($this->root . '/src/A.php');
        unlink($this->root . '/src/notes.txt');
        unlink($this->root . '/src/sub/B.php');
        unlink($this->root . '/alpha/First.php');
        rmdir($this->root . '/src/sub');
        rmdir($this->root . '/src');
        rmdir($this->root . '/alpha');
        rmdir($this->root);
    }

    public function testCollectsOnlyPhpFilesRecursively(): void
    {
        $lintReport = LintReport::collectPhpFiles($this->root, ['src']);

        self::assertSame(
            [$this->root . '/src/A.php', $this->root . '/src/Z.php', $this->root . '/src/sub/B.php'],
            $lintReport->files,
        );
        self::assertSame(3, $lintReport->count());
    }

    public function testTheCollectedFilesAreSortedAcrossTheDirectoriesThemselves(): void
    {
        // The directories arrive in the audited order -- the roster the
        // caller hands over, which no filesystem chooses: 'src' first,
        // 'alpha' second, while 'alpha' sorts before 'src'. The
        // sort()-removal mutant answers the directory order (every
        // src/ file, THEN alpha/First.php), so the first element alone
        // separates it from the sorted answer on every filesystem,
        // whatever readdir order each single directory happens to
        // answer -- the kill rests on the input array alone.
        $lintReport = LintReport::collectPhpFiles($this->root, ['src', 'alpha']);

        self::assertSame(
            [
                $this->root . '/alpha/First.php',
                $this->root . '/src/A.php',
                $this->root . '/src/Z.php',
                $this->root . '/src/sub/B.php',
            ],
            $lintReport->files,
        );
    }

    /**
     * A configured directory that does not exist refuses the collection:
     * a missing directory shrinks the corpus the gate lints, and a
     * shrunken corpus is a green that lies -- the honest answer names the
     * directory that is gone.
     */
    public function testRefusesAMissingDirectory(): void
    {
        try {
            LintReport::collectPhpFiles($this->root, ['does-not-exist']);
            self::fail('A missing lint corpus directory must raise a ToolingException.');
        } catch (ToolingException $toolingException) {
            self::assertSame(
                'The lint corpus directory "' . $this->root . '/does-not-exist" does not exist'
                    . ' -- a shrunken corpus must fail loudly, not lint less.',
                $toolingException->getMessage(),
            );
        }
    }
}
