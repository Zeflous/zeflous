<?php

declare(strict_types=1);

namespace Zef\Test\Tooling;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Tooling\LintGate;
use Zef\Framework\Tooling\LintReport;

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

    public function testSkipsMissingDirectories(): void
    {
        self::assertSame(0, LintReport::collectPhpFiles('/nonexistent-root', ['src'])->count());
    }

    public function testSkipsAMissingDirectoryButKeepsScanningTheRest(): void
    {
        $root = $this->makeTree();

        try {
            $report = LintReport::collectPhpFiles($root, ['missing', 'src']);

            self::assertSame(2, $report->count());
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
