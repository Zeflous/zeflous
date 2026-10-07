<?php

declare(strict_types=1);

namespace Zef\Test\Tooling;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Tooling\MutationGate;
use Zef\Framework\Tooling\MutationReport;
use Zef\Framework\Tooling\ToolingException;

/**
 * @internal
 */
#[CoversClass(MutationGate::class)]
#[CoversClass(MutationReport::class)]
final class MutationGateTest extends TestCase
{
    public function testParsesTheInfectionMetrics(): void
    {
        $mutationReport = MutationReport::fromInfectionJson(
            '{"stats":{"msi":100,"coveredCodeMsi":99.5,"mutationCodeCoverage":98}}',
        );

        self::assertSame(100.0, $mutationReport->msi);
        self::assertSame(99.5, $mutationReport->coveredMsi);
        self::assertSame(98.0, $mutationReport->codeCoverage);
    }

    public function testThrowsWhenTheJsonIsInvalid(): void
    {
        try {
            MutationReport::fromInfectionJson('not json');
            self::fail('Invalid JSON must raise a ToolingException.');
            // Pin the parse failure: otherwise the invalid-JSON path falls
            // through to the "no statistics" guard, whose own exception would
            // keep the test green and mask a removed throw.
        } catch (ToolingException $toolingException) {
            self::assertSame('Unable to parse the Infection JSON report.', $toolingException->getMessage());
        }
    }

    public function testThrowsWhenStatsAreMissing(): void
    {
        $this->expectException(ToolingException::class);

        MutationReport::fromInfectionJson('{"foo":1}');
    }

    public function testThrowsWhenARequiredMetricIsMissing(): void
    {
        try {
            MutationReport::fromInfectionJson('{"stats":{"msi":100,"coveredCodeMsi":100}}');
            self::fail('A missing required metric must raise a ToolingException.');
        } catch (ToolingException $toolingException) {
            self::assertSame(
                'The Infection report is missing the "mutationCodeCoverage" metric.',
                $toolingException->getMessage(),
            );
        }
    }

    public function testThrowsWhenTheMsiMetricIsMissing(): void
    {
        // Every required metric must be checked, so this asserts the specific
        // missing-metric name: dropping the 'msi' entry from the required list
        // would otherwise be masked by the later "not numeric" guard.
        try {
            MutationReport::fromInfectionJson('{"stats":{"coveredCodeMsi":100,"mutationCodeCoverage":100}}');
            self::fail('A missing MSI metric must raise a ToolingException.');
        } catch (ToolingException $toolingException) {
            self::assertSame(
                'The Infection report is missing the "msi" metric.',
                $toolingException->getMessage(),
            );
        }
    }

    public function testThrowsWhenAMetricIsNotNumeric(): void
    {
        $this->expectException(ToolingException::class);

        MutationReport::fromInfectionJson(
            '{"stats":{"msi":"abc","coveredCodeMsi":100,"mutationCodeCoverage":100}}',
        );
    }

    public function testAcceptsNumericStringMetrics(): void
    {
        $mutationReport = MutationReport::fromInfectionJson(
            '{"stats":{"msi":"100","coveredCodeMsi":"100","mutationCodeCoverage":"100"}}',
        );

        self::assertSame(100.0, $mutationReport->msi);
    }

    public function testFromFileReadsTheReport(): void
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'zef-infection-');
        file_put_contents($path, '{"stats":{"msi":100,"coveredCodeMsi":100,"mutationCodeCoverage":100}}');

        try {
            self::assertSame(100.0, MutationReport::fromInfectionJsonFile($path)->msi);
        } finally {
            unlink($path);
        }
    }

    public function testFromFileThrowsWhenTheFileIsMissing(): void
    {
        $this->expectException(ToolingException::class);

        MutationReport::fromInfectionJsonFile('/nonexistent/infection.json');
    }

    public function testGatePassesAtTheFloor(): void
    {
        $gateResult = new MutationGate(100.0)->evaluate($this->report(100.0, 100.0));

        self::assertTrue($gateResult->passed);
        self::assertStringStartsWith('Mutation score:', $gateResult->message);
        self::assertStringContainsString('Mutation-score floor PASSED', $gateResult->message);
    }

    public function testGateFailsWhenMsiIsBelowTheFloor(): void
    {
        $gateResult = new MutationGate(100.0)->evaluate($this->report(99.0, 100.0));

        self::assertFalse($gateResult->passed);
        // Exact message: the figures must precede the verdict, so a mutated
        // concatenation order is detected instead of being tolerated by two
        // substring checks that either order would satisfy.
        self::assertSame(
            'Mutation score: MSI 99.00% / covered MSI 100.00% (code coverage 100.00%, floor: 100.00%)'
            . ' -> Mutation-score floor FAILED.',
            $gateResult->message,
        );
    }

    public function testGateFailsWhenCoveredMsiIsBelowTheFloor(): void
    {
        self::assertFalse(new MutationGate(100.0)->evaluate($this->report(100.0, 99.0))->passed);
    }

    private function report(float $msi, float $coveredMsi): MutationReport
    {
        return MutationReport::fromInfectionJson(\sprintf(
            '{"stats":{"msi":%s,"coveredCodeMsi":%s,"mutationCodeCoverage":100}}',
            $msi,
            $coveredMsi,
        ));
    }
}
