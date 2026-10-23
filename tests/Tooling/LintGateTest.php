<?php

declare(strict_types=1);

namespace Zef\Test\Tooling;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Test\Support\Tooling\LintGate;
use Zef\Test\Support\Tooling\LintReport;
use Zef\Test\Support\Tooling\ToolingException;

/**
 * @internal
 */
#[CoversClass(LintGate::class)]
#[CoversClass(LintReport::class)]
final class LintGateTest extends TestCase
{
    public function testCollectsOnlyPhpFilesAndSortsThem(): void
    {
        $root = $this->makeTree();

        try {
            $report = LintReport::collectPhpFiles($root, ['src']);

            self::assertSame(2, $report->count());
            self::assertStringEndsWith('a.php', $report->files[0]);
            self::assertStringEndsWith('b.php', $report->files[1]);
        } finally {
            $this->removeTree($root);
        }
    }

    public function testRefusesAMissingRoot(): void
    {
        try {
            LintReport::collectPhpFiles('/nonexistent-root', ['src']);
            self::fail('A missing lint corpus root must raise a ToolingException.');
        } catch (ToolingException $toolingException) {
            self::assertSame(
                'The lint corpus directory "/nonexistent-root/src" does not exist'
                    . ' -- a shrunken corpus must fail loudly, not lint less.',
                $toolingException->getMessage(),
            );
        }
    }

    /**
     * The refusal names the FIRST missing directory and stops: the corpus
     * must not half-lint around a hole, so "missing" refuses before
     * "src" is ever walked.
     */
    public function testRefusesAMissingDirectoryBeforeScanningTheRest(): void
    {
        $root = $this->makeTree();

        try {
            LintReport::collectPhpFiles($root, ['missing', 'src']);
            self::fail('A missing lint corpus directory must raise a ToolingException.');
        } catch (ToolingException $toolingException) {
            self::assertSame(
                'The lint corpus directory "' . $root . '/missing" does not exist'
                    . ' -- a shrunken corpus must fail loudly, not lint less.',
                $toolingException->getMessage(),
            );
        } finally {
            $this->removeTree($root);
        }
    }

    public function testGatePassesWhenEveryFileParses(): void
    {
        $lintReport = LintReport::collectPhpFiles($this->makeTree(), ['src']);
        $gateResult = new LintGate(static fn (string $file): bool => true)->evaluate($lintReport);

        self::assertTrue($gateResult->passed);
        self::assertStringContainsString('Lint OK: 2 PHP files checked', $gateResult->message);
    }

    public function testGateFailsWhenAFileDoesNotParse(): void
    {
        $lintReport = LintReport::collectPhpFiles($this->makeTree(), ['src']);
        $gateResult = new LintGate(static fn (string $file): bool => !str_ends_with($file, 'b.php'))
            ->evaluate($lintReport)
        ;

        self::assertFalse($gateResult->passed);
        self::assertStringContainsString('Syntax errors detected in 1 file(s)', $gateResult->message);
    }

    /**
     * An empty corpus is a failure, not a pass: a lint that checked nothing
     * certifies nothing. A present directory with no PHP files reaches
     * this arm even though the collector's own refusal (a directory that
     * is gone) never fired.
     */
    public function testGateFailsOnAnEmptyCorpus(): void
    {
        $root = (string) tempnam(sys_get_temp_dir(), 'zef-empty-');
        unlink($root);
        mkdir($root . '/src', 0o777, true);

        try {
            $lintReport = LintReport::collectPhpFiles($root, ['src']);
            $gateResult = new LintGate(static fn (string $file): bool => true)->evaluate($lintReport);

            self::assertFalse($gateResult->passed);
            self::assertSame(
                'Lint gate FAILED: no PHP files were found to lint -- an empty corpus certifies nothing.',
                $gateResult->message,
            );
        } finally {
            rmdir($root . '/src');
            rmdir($root);
        }
    }

    private function makeTree(): string
    {
        $root = (string) tempnam(sys_get_temp_dir(), 'zef-lint-');
        unlink($root);
        mkdir($root . '/src', 0o777, true);
        file_put_contents($root . '/src/b.php', '<?php');
        file_put_contents($root . '/src/a.php', '<?php');
        file_put_contents($root . '/src/notes.txt', 'ignore me');

        return $root;
    }

    private function removeTree(string $root): void
    {
        unlink($root . '/src/a.php');
        unlink($root . '/src/b.php');
        unlink($root . '/src/notes.txt');
        rmdir($root . '/src');
        rmdir($root);
    }
}
