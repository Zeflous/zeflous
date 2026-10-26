<?php

declare(strict_types=1);

namespace Zef\Test\Tooling;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Test\Support\Tooling\GateRunner;
use Zef\Test\Support\Tooling\ToolingException;

/**
 * @internal
 */
#[CoversClass(GateRunner::class)]
final class GateRunnerTest extends TestCase
{
    private string $root;

    #[Override]
    protected function setUp(): void
    {
        $this->root = (string) tempnam(sys_get_temp_dir(), 'zef-gate-');
        unlink($this->root);
        mkdir($this->root . '/build/infection', 0o777, true);
        // The lint corpus's five configured directories, all present: a
        // missing corpus directory refuses the lint gate outright (a
        // shrunken corpus must fail loudly), so the synthetic root mirrors
        // the real repository's layout.
        foreach (['src', 'tests', 'benchmarks', 'scripts', 'bin'] as $corpus) {
            mkdir($this->root . '/' . $corpus, 0o777, true);
        }

        file_put_contents($this->root . '/src/Example.php', '<?php');
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->removeTree($this->root);
        putenv('GITHUB_BASE_REF');
        putenv('GITHUB_REF');
    }

    public function testCoverageGateReadsTheCloverReport(): void
    {
        file_put_contents(
            $this->root . '/build/clover.xml',
            '<coverage><project><metrics statements="10" coveredstatements="10"/></project></coverage>',
        );

        $gateResult = $this->runner()->run('coverage', ['90']);

        self::assertTrue($gateResult->passed);
        self::assertStringContainsString('Coverage gate PASSED', $gateResult->message);
    }

    public function testMutationGateReadsTheInfectionReport(): void
    {
        file_put_contents(
            $this->root . '/build/infection/infection.json',
            '{"stats":{"msi":100,"coveredCodeMsi":100,"mutationCodeCoverage":100}}',
        );

        self::assertTrue($this->runner()->run('mutation', ['100'])->passed);
    }

    public function testCoverageGateUsesTheProvidedThreshold(): void
    {
        file_put_contents(
            $this->root . '/build/clover.xml',
            '<coverage><project><metrics statements="10" coveredstatements="6"/></project></coverage>',
        );

        $gateResult = $this->runner()->run('coverage', ['50']);

        self::assertTrue($gateResult->passed);
        self::assertStringContainsString('required: 50.00%', $gateResult->message);
    }

    public function testMutationGateUsesTheProvidedFloor(): void
    {
        file_put_contents(
            $this->root . '/build/infection/infection.json',
            '{"stats":{"msi":60,"coveredCodeMsi":60,"mutationCodeCoverage":100}}',
        );

        $gateResult = $this->runner()->run('mutation', ['50']);

        self::assertTrue($gateResult->passed);
        self::assertStringContainsString('floor: 50.00%', $gateResult->message);
    }

    public function testBaselineGatePassesWithoutABaselineFile(): void
    {
        self::assertTrue($this->runner()->run('baseline', [])->passed);
    }

    public function testBaselineGateFailsWithANonEmptyBaseline(): void
    {
        $baseline = "parameters:\n    ignoreErrors:\n        - message: '#x#'\n";
        file_put_contents($this->root . '/phpstan-baseline.neon', $baseline);

        self::assertFalse($this->runner()->run('baseline', [])->passed);
    }

    public function testAuditGateUsesTheAllowlistFromComposerJson(): void
    {
        file_put_contents(
            $this->root . '/composer.json',
            '{"config":{"audit":{"ignore":["a/b"]}}}',
        );

        $runner = $this->runner(static fn (string $command): array => [
            0,
            '{"advisories":{"a/b":[{}]},"abandoned":{}}',
        ]);

        self::assertTrue($runner->run('audit', [])->passed);
    }

    public function testAuditGateFailsWhenThePayloadIsEmpty(): void
    {
        $runner = $this->runner(static fn (string $command): array => [0, '']);

        try {
            $runner->run('audit', []);
            self::fail('An empty audit payload must fail the gate.');
        } catch (ToolingException $toolingException) {
            self::assertStringContainsString('produced no JSON payload', $toolingException->getMessage());
        }
    }

    public function testAuditGateKeepsEveryAllowlistEntry(): void
    {
        file_put_contents(
            $this->root . '/composer.json',
            '{"config":{"audit":{"ignore":["a/b","c/d"]}}}',
        );

        $runner = $this->runner(static fn (string $command): array => [
            0,
            '{"advisories":{"a/b":[{}],"c/d":[{}]},"abandoned":{}}',
        ]);

        self::assertTrue($runner->run('audit', [])->passed);
    }

    public function testAuditGateToleratesAMissingComposerJson(): void
    {
        $runner = $this->runner(static fn (string $command): array => [0, '{"advisories":{"a/b":[{}]}}']);

        self::assertFalse($runner->run('audit', [])->passed);
    }

    public function testAuditGateToleratesANonArrayConfigSection(): void
    {
        // A present-but-non-array 'config' must be treated as "no allow-list"
        // rather than tripping a type error while walking the composer.json
        // tree. Reading a key off a string without the guard would raise, so
        // this pins the fail-safe branch of the traversal.
        file_put_contents($this->root . '/composer.json', '{"config":"not-an-array"}');

        $runner = $this->runner(static fn (string $command): array => [0, '{"advisories":{"a/b":[{}]}}']);

        // Empty allow-list => the advisory is not exempt => the gate fails.
        self::assertFalse($runner->run('audit', [])->passed);
    }

    public function testLintGatePassesWhenTheProcessSucceeds(): void
    {
        $runner = $this->runner(static fn (string $command): array => [0, 'No syntax errors']);

        self::assertTrue($runner->run('lint', [])->passed);
    }

    /**
     * The duplication arm's four verdicts: a clean run passes, a clone
     * run fails naming the pair, and a run whose exit code disagrees
     * with its payload (either direction) is refused outright instead
     * of being read as whichever half looks greener.
     */
    public function testDuplicationGatePassesACleanRun(): void
    {
        $runner = $this->runner(function (string $command): array {
            self::assertSame(
                'cd ' . escapeshellarg($this->root)
                    . ' && vendor/bin/phpcpd src --exclude src/Contracts/Psr'
                    . ' --log-pmd php://stdout --quiet 2>/dev/null',
                $command,
            );

            return [
                0,
                '<?xml version="1.0" encoding="UTF-8"?>'
                . '<pmd-cpd xmlns="https://systemsdk.github.io/phpcpd/report" version="1.0.0"/>',
            ];
        });

        $gateResult = $runner->run('duplication', []);

        self::assertTrue($gateResult->passed);
        self::assertStringContainsString('Duplication gate PASSED', $gateResult->message);
    }

    public function testDuplicationGateFailsACloneRun(): void
    {
        $runner = $this->runner(fn (string $command): array => [
            1,
            '<?xml version="1.0" encoding="UTF-8"?>'
            . '<pmd-cpd xmlns="https://systemsdk.github.io/phpcpd/report" version="1.0.0">'
            . '<duplication lines="38" tokens="110" exact="false">'
            . '<file line="19" endline="57" path="' . $this->root . '/src/Http/A.php"/>'
            . '<file line="27" endline="65" path="' . $this->root . '/src/Http/B.php"/>'
            . '</duplication>'
            . '</pmd-cpd>',
        ]);

        $gateResult = $runner->run('duplication', []);

        self::assertFalse($gateResult->passed);
        self::assertStringContainsString('src/Http/A.php:19-57 <-> src/Http/B.php:27-65', $gateResult->message);
    }

    public function testDuplicationGateRefusesARunThatDidNotComplete(): void
    {
        $runner = $this->runner(static fn (string $command): array => [2, '']);

        $this->expectException(ToolingException::class);
        $this->expectExceptionMessageIsOrContains(
            'The copy-paste detector did not complete a normal run (exit 2), '
            . 'so the duplication gate cannot read a verdict.',
        );

        $runner->run('duplication', []);
    }

    public function testDuplicationGateRefusesAnExitCodeThatDisagreesWithThePayload(): void
    {
        $runner = $this->runner(fn (string $command): array => [
            0,
            '<?xml version="1.0" encoding="UTF-8"?>'
            . '<pmd-cpd xmlns="https://systemsdk.github.io/phpcpd/report" version="1.0.0">'
            . '<duplication lines="38" tokens="110" exact="false">'
            . '<file line="19" endline="57" path="' . $this->root . '/src/A.php"/>'
            . '</duplication>'
            . '</pmd-cpd>',
        ]);

        $this->expectException(ToolingException::class);
        $this->expectExceptionMessageIsOrContains(
            'The copy-paste detector exit code and its PMD payload disagree, '
            . 'so the duplication gate cannot pick a verdict.',
        );

        $runner->run('duplication', []);
    }

    public function testDuplicationGateRefusesACloneExitCodeWithACleanPayload(): void
    {
        $runner = $this->runner(static fn (string $command): array => [
            1,
            '<?xml version="1.0" encoding="UTF-8"?>'
            . '<pmd-cpd xmlns="https://systemsdk.github.io/phpcpd/report" version="1.0.0"/>',
        ]);

        $this->expectException(ToolingException::class);
        $this->expectExceptionMessageIsOrContains(
            'The copy-paste detector exit code and its PMD payload disagree, '
            . 'so the duplication gate cannot pick a verdict.',
        );

        $runner->run('duplication', []);
    }

    /**
     * The mess arm mirrors the duplication arm's contract with phpmd's
     * own exit semantics (0 clean / 2 violations).
     */
    public function testMessGatePassesACleanRun(): void
    {
        $runner = $this->runner(function (string $command): array {
            self::assertSame(
                'cd ' . escapeshellarg($this->root)
                    . ' && vendor/bin/phpmd analyze src --format=json --ruleset=phpmd.xml'
                    . " --exclude='*src/Contracts/Psr/*' --no-progress 2>/dev/null",
                $command,
            );

            return [0, '{"version":"3.0.0","files":[]}'];
        });

        $gateResult = $runner->run('mess', []);

        self::assertTrue($gateResult->passed);
        self::assertStringContainsString('Mess gate PASSED', $gateResult->message);
    }

    public function testMessGateFailsAViolatingRun(): void
    {
        $runner = $this->runner(fn (string $command): array => [
            2,
            '{"version":"3.0.0","files":[{"file":"' . $this->root . '/src/A.php","violations":['
            . '{"beginLine":12,"rule":"EvalExpression","description":"eval()"}]}]}',
        ]);

        $gateResult = $runner->run('mess', []);

        self::assertFalse($gateResult->passed);
        self::assertStringContainsString('EvalExpression x1 (src/A.php:12)', $gateResult->message);
    }

    public function testMessGateRefusesARunThatDidNotComplete(): void
    {
        $runner = $this->runner(static fn (string $command): array => [1, '']);

        $this->expectException(ToolingException::class);
        $this->expectExceptionMessageIsOrContains(
            'The mess detector did not complete a normal run (exit 1), '
            . 'so the mess gate cannot read a verdict.',
        );

        $runner->run('mess', []);
    }

    public function testMessGateRefusesAnExitCodeThatDisagreesWithThePayload(): void
    {
        $runner = $this->runner(static fn (string $command): array => [
            0,
            '{"version":"3.0.0","files":[{"file":"src/A.php","violations":['
            . '{"beginLine":12,"rule":"EvalExpression","description":"eval()"}]}]}',
        ]);

        $this->expectException(ToolingException::class);
        $this->expectExceptionMessageIsOrContains(
            'The mess detector exit code and its JSON payload disagree, '
            . 'so the mess gate cannot pick a verdict.',
        );

        $runner->run('mess', []);
    }

    public function testMessGateRefusesAViolatingExitCodeWithACleanPayload(): void
    {
        $runner = $this->runner(static fn (string $command): array => [2, '{"version":"3.0.0","files":[]}']);

        $this->expectException(ToolingException::class);
        $this->expectExceptionMessageIsOrContains(
            'The mess detector exit code and its JSON payload disagree, '
            . 'so the mess gate cannot pick a verdict.',
        );

        $runner->run('mess', []);
    }

    public function testLintGateFailsWhenTheProcessFails(): void
    {
        $runner = $this->runner(static fn (string $command): array => [255, 'Parse error']);

        self::assertFalse($runner->run('lint', [])->passed);
    }

    /**
     * A threshold that is not numeric is an operator error the gate must
     * refuse rather than coerce: `(float) 'abc'` answers 0.0, and a floor
     * of zero can never fail -- the coercion would silently retire the
     * gate. The refusal fires before the report is even read.
     */
    public function testTheCoverageThresholdMustBeNumeric(): void
    {
        try {
            $this->runner()->run('coverage', ['abc']);
            self::fail('A non-numeric coverage threshold must raise a ToolingException.');
        } catch (ToolingException $toolingException) {
            self::assertSame(
                'The gate threshold must be numeric, "abc" is not.',
                $toolingException->getMessage(),
            );
        }
    }

    /**
     * The empty string is the operator-error shape a stray flag leaves
     * behind (`composer coverage:gate --` style invocations), and it is
     * not numeric either.
     */
    public function testTheMutationThresholdMustBeNumeric(): void
    {
        try {
            $this->runner()->run('mutation', ['']);
            self::fail('An empty mutation threshold must raise a ToolingException.');
        } catch (ToolingException $toolingException) {
            self::assertSame(
                'The gate threshold must be numeric, "" is not.',
                $toolingException->getMessage(),
            );
        }
    }

    /**
     * The documented defaults stay numeric when the argument is absent:
     * the coverage floor defaults to 90 and the mutation floor to 100,
     * and neither default may be refused by the validation that guards
     * the explicit arguments.
     */
    public function testTheDocumentedDefaultsRemainNumeric(): void
    {
        file_put_contents(
            $this->root . '/build/clover.xml',
            '<coverage><project><metrics statements="10" coveredstatements="10"/></project></coverage>',
        );
        file_put_contents(
            $this->root . '/build/infection/infection.json',
            '{"stats":{"msi":100,"coveredCodeMsi":100,"mutationCodeCoverage":100}}',
        );

        self::assertTrue($this->runner()->run('coverage', [])->passed);
        self::assertTrue($this->runner()->run('mutation', [])->passed);
    }

    public function testWorkflowConcurrencyGateAuditsTheWorkflowDirectory(): void
    {
        mkdir($this->root . '/.github/workflows', 0o777, true);
        file_put_contents(
            $this->root . '/.github/workflows/ci-strict.yml',
            "concurrency:\n  group: ci-strict-\${{ github.sha }}\n",
        );

        try {
            $gateResult = $this->runner()->run('workflow-concurrency', []);

            self::assertTrue($gateResult->passed);
            self::assertSame('Workflow concurrency gate PASSED: 1 workflow(s) audited.', $gateResult->message);
        } finally {
            unlink($this->root . '/.github/workflows/ci-strict.yml');
            rmdir($this->root . '/.github/workflows');
            rmdir($this->root . '/.github');
        }
    }

    public function testRoadmapDocGateAuditsTheDocuments(): void
    {
        // The document audit is judged on a pull-request lane: the fixture
        // root is not a git repository and carries no ROADMAP-STATUS.md pin,
        // so on a main lane (an empty GITHUB_BASE_REF and refs/heads/main,
        // the shape the runner exports on push and workflow_dispatch) the
        // collector would rightly claim the lane and the pin-less fixture
        // would fail closed. The pin-freshness verdict has its own dedicated
        // battery; this test pins the lane so the document rules are judged
        // on their own, whatever lane the ambient process runs on.
        putenv('GITHUB_BASE_REF=main');

        file_put_contents(
            $this->root . '/ROADMAP-HISTORY.md',
            "# History\n\n| A | B |\n|---|---|\n| 1 | 2 |\n",
        );

        try {
            $gateResult = $this->runner()->run('roadmap', []);

            self::assertTrue($gateResult->passed);
            self::assertSame(
                'Roadmap document gate PASSED: 1 document(s) audited'
                    . ' (pin freshness not judged: the lane is not validating main).',
                $gateResult->message,
            );
        } finally {
            unlink($this->root . '/ROADMAP-HISTORY.md');
        }
    }

    public function testSmokeGateRunsThePersistentWorkerSmoke(): void
    {
        $gateResult = $this->runner()->run('smoke', []);

        self::assertTrue($gateResult->passed);
        self::assertStringStartsWith('Persistent-worker smoke:', $gateResult->message);
        self::assertStringContainsString('20000 iterations', $gateResult->message);
        self::assertStringContainsString('budget 2000000', $gateResult->message);
        self::assertStringContainsString('Persistent-worker smoke PASSED', $gateResult->message);
    }

    public function testSmokeGateUsesTheConfiguredIterationsAndBudget(): void
    {
        $gateRunner = new GateRunner(
            $this->root,
            static fn (string $command): array => [0, ''],
            5,
            2_000_000,
        );

        $gateResult = $gateRunner->run('smoke', []);

        self::assertTrue($gateResult->passed);
        self::assertStringContainsString('5 iterations', $gateResult->message);
        self::assertStringContainsString('budget 2000000', $gateResult->message);
        self::assertStringContainsString('Persistent-worker smoke PASSED', $gateResult->message);
    }

    public function testZeroDependencyGatePassesOnACleanManifest(): void
    {
        file_put_contents(
            $this->root . '/composer.json',
            '{"require":{"php":"^8.4"},"require-dev":{"phpunit/phpunit":"^13.4"}}',
        );
        file_put_contents(
            $this->root . '/composer.lock',
            '{"packages":[],"packages-dev":[{"name":"phpstan/phpstan"}]}',
        );

        $gateResult = $this->runner()->run('zero-deps', []);

        self::assertTrue($gateResult->passed);
        self::assertStringContainsString('Zero-dependency gate PASSED', $gateResult->message);
    }

    public function testZeroDependencyGateFailsOnAProductionPackage(): void
    {
        file_put_contents($this->root . '/composer.json', '{"require":{"php":"^8.4"}}');
        file_put_contents(
            $this->root . '/composer.lock',
            '{"packages":[{"name":"monolog/monolog"}]}',
        );

        $gateResult = $this->runner()->run('zero-deps', []);

        self::assertFalse($gateResult->passed);
        self::assertStringContainsString('declares 1 production package(s)', $gateResult->message);
    }

    public function testZeroDependencyGateFailsOnAnExtraRequirement(): void
    {
        file_put_contents(
            $this->root . '/composer.json',
            '{"require":{"php":"^8.4","psr/log":"^3.0"}}',
        );
        file_put_contents($this->root . '/composer.lock', '{"packages":[]}');

        $gateResult = $this->runner()->run('zero-deps', []);

        self::assertFalse($gateResult->passed);
        self::assertStringContainsString('expected exactly {"php": "^8.4"}', $gateResult->message);
    }

    public function testZeroDependencyGateThrowsWhenComposerJsonIsMissing(): void
    {
        file_put_contents($this->root . '/composer.lock', '{"packages":[]}');

        try {
            $this->runner()->run('zero-deps', []);
            self::fail('A missing composer.json must fail the gate closed.');
        } catch (ToolingException $toolingException) {
            self::assertStringContainsString('composer.json not found', $toolingException->getMessage());
        }
    }

    public function testUnknownGateThrows(): void
    {
        $this->expectException(ToolingException::class);

        $this->runner()->run('nope', []);
    }

    /**
     * @param null|(callable(string): array{0: int, 1: string}) $processRunner
     */
    private function runner(?callable $processRunner = null): GateRunner
    {
        $processRunner ??= static fn (string $command): array => [0, ''];

        return new GateRunner($this->root, $processRunner(...));
    }

    private function removeTree(string $root): void
    {
        $paths = [
            $root . '/build/infection/infection.json',
            $root . '/build/infection',
            $root . '/build/clover.xml',
            $root . '/build',
            $root . '/src/Example.php',
            $root . '/src',
            $root . '/tests',
            $root . '/benchmarks',
            $root . '/scripts',
            $root . '/bin',
            $root . '/.github/workflows',
            $root . '/.github',
            $root . '/phpstan-baseline.neon',
            $root . '/composer.json',
            $root . '/composer.lock',
        ];

        foreach ($paths as $path) {
            if (is_file($path)) {
                unlink($path);
            } elseif (is_dir($path)) {
                rmdir($path);
            }
        }

        rmdir($root);
    }
}
