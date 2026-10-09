<?php

declare(strict_types=1);

namespace Zef\Test\Tooling;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Tooling\LintReport;

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
        file_put_contents($this->root . '/src/A.php', '<?php');
        file_put_contents($this->root . '/src/notes.txt', 'not php');
        file_put_contents($this->root . '/src/sub/B.php', '<?php');
    }

    #[Override]
    protected function tearDown(): void
    {
        unlink($this->root . '/src/A.php');
        unlink($this->root . '/src/notes.txt');
        unlink($this->root . '/src/sub/B.php');
        rmdir($this->root . '/src/sub');
        rmdir($this->root . '/src');
        rmdir($this->root);
    }

    public function testCollectsOnlyPhpFilesRecursively(): void
    {
        $lintReport = LintReport::collectPhpFiles($this->root, ['src']);

        self::assertSame(
            [$this->root . '/src/A.php', $this->root . '/src/sub/B.php'],
            $lintReport->files,
        );
        self::assertSame(2, $lintReport->count());
    }

    public function testSkipsMissingDirectories(): void
    {
        $lintReport = LintReport::collectPhpFiles($this->root, ['does-not-exist']);

        self::assertSame([], $lintReport->files);
        self::assertSame(0, $lintReport->count());
    }
}
