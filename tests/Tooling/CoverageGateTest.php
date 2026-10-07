<?php

declare(strict_types=1);

namespace Zef\Test\Tooling;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Tooling\CoverageGate;
use Zef\Framework\Tooling\CoverageReport;
use Zef\Framework\Tooling\ToolingException;

/**
 * @internal
 */
#[CoversClass(CoverageGate::class)]
#[CoversClass(CoverageReport::class)]
final class CoverageGateTest extends TestCase
{
    public function testPercentageIsZeroWhenThereAreNoStatements(): void
    {
        self::assertSame(0.0, $this->report(0, 0)->percentage());
    }

    public function testPercentageIsComputedFromCoveredStatements(): void
    {
        self::assertSame(50.0, $this->report(10, 5)->percentage());
        self::assertSame(100.0, $this->report(10, 10)->percentage());
    }

    public function testGatePassesWhenCoverageMeetsTheThreshold(): void
    {
        $gateResult = new CoverageGate(90.0)->evaluate($this->report(10, 9));

        self::assertTrue($gateResult->passed);
        self::assertStringStartsWith('Line coverage:', $gateResult->message);
        self::assertStringContainsString('Line coverage: 90.00%', $gateResult->message);
        self::assertStringContainsString('Coverage gate PASSED', $gateResult->message);
    }

    public function testGatePassesExactlyAtTheThreshold(): void
    {
        self::assertTrue(new CoverageGate(50.0)->evaluate($this->report(10, 5))->passed);
    }

    public function testGateFailsBelowTheThreshold(): void
    {
        $gateResult = new CoverageGate(90.0)->evaluate($this->report(10, 8));

        self::assertFalse($gateResult->passed);
        // Assert the exact message, not just substrings: the coverage figures
        // must precede the verdict, so a mutated concatenation order (both
        // fragments swapped) is detected rather than tolerated by two
        // independent substring checks that either order would satisfy.
        self::assertSame(
            'Line coverage: 80.00% (required: 90.00%) -> Coverage gate FAILED.',
            $gateResult->message,
        );
    }

    public function testFromCloverFileReadsTheMetrics(): void
    {
        $path = $this->writeTempFile(
            '<coverage><project><metrics statements="20" coveredstatements="15"/></project></coverage>',
        );

        try {
            $report = CoverageReport::fromCloverFile($path);

            self::assertSame(20, $report->statements);
            self::assertSame(15, $report->coveredStatements);
            self::assertSame(75.0, $report->percentage());
        } finally {
            unlink($path);
        }
    }

    public function testFromCloverFileThrowsWhenTheFileIsMissing(): void
    {
        try {
            CoverageReport::fromCloverFile('/nonexistent/clover.xml');
            self::fail('A missing coverage report must raise a ToolingException.');
            // Pin the specific message: the "report not found" guard must be the
            // one that fires. Without it the invalid path falls through to the
            // later XML-parse guard, whose own ToolingException would keep the
            // test green and hide a removed throw.
        } catch (ToolingException $toolingException) {
            self::assertSame(
                'Coverage report not found: /nonexistent/clover.xml',
                $toolingException->getMessage(),
            );
        }
    }

    public function testFromCloverFileRestoresTheLibxmlErrorState(): void
    {
        $path = $this->writeTempFile(
            '<coverage><project><metrics statements="1" coveredstatements="1"/></project></coverage>',
        );

        try {
            CoverageReport::fromCloverFile($path);

            self::assertFalse(libxml_use_internal_errors());
        } finally {
            unlink($path);
        }
    }

    public function testFromCloverFileThrowsWhenTheXmlIsMalformed(): void
    {
        $path = $this->writeTempFile('this is not xml');

        try {
            $this->expectException(ToolingException::class);

            CoverageReport::fromCloverFile($path);
        } finally {
            unlink($path);
        }
    }

    public function testFromCloverFileThrowsWhenMetricsAreAbsent(): void
    {
        $path = $this->writeTempFile('<coverage><project/></coverage>');

        try {
            $this->expectException(ToolingException::class);

            CoverageReport::fromCloverFile($path);
        } finally {
            unlink($path);
        }
    }

    private function report(int $statements, int $covered): CoverageReport
    {
        $path = $this->writeTempFile(\sprintf(
            '<coverage><project><metrics statements="%d" coveredstatements="%d"/></project></coverage>',
            $statements,
            $covered,
        ));

        try {
            return CoverageReport::fromCloverFile($path);
        } finally {
            unlink($path);
        }
    }

    private function writeTempFile(string $contents): string
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'zef-clover-');
        file_put_contents($path, $contents);

        return $path;
    }
}
