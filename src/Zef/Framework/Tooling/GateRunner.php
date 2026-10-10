<?php

declare(strict_types=1);

namespace Zef\Framework\Tooling;

use Closure;
use Zef\Framework\Kernel;

/**
 * Wires the tooling gates to real I/O (files, processes).
 *
 * The gate *logic* lives in the individual gate classes; this runner is the only
 * place that touches the filesystem and the shell, and it takes the process
 * runner as a dependency so it can be exercised deterministically in tests.
 */
final readonly class GateRunner
{
    /**
     * @param Closure(string): array{0: int, 1: string} $processRunner
     */
    public function __construct(
        private string $root,
        private Closure $processRunner,
        private int $smokeIterations = 20000,
        private int $smokeMemoryBudget = 2_000_000,
    ) {
    }

    /**
     * @param list<string> $args
     */
    public function run(string $gate, array $args): GateResult
    {
        return match ($gate) {
            'coverage' => new CoverageGate((float) ($args[0] ?? '90'))
                ->evaluate(CoverageReport::fromCloverFile($this->root . '/build/clover.xml')),
            'zero-deps' => new ZeroDependencyGate()
                ->evaluate(ComposerManifest::fromFiles(
                    $this->root . '/composer.json',
                    $this->root . '/composer.lock',
                )),
            'mutation' => new MutationGate((float) ($args[0] ?? '100'))
                ->evaluate(MutationReport::fromInfectionJsonFile($this->root . '/build/infection/infection.json')),
            'baseline' => new BaselineGate()->evaluate($this->readBaseline()),
            'audit' => new DependencyAuditGate($this->readAuditAllowlist())
                ->evaluate(DependencyAudit::fromComposerAuditJson($this->auditPayload())),
            'lint' => new LintGate(fn (string $file): bool => $this->lintFile($file))
                ->evaluate(LintReport::collectPhpFiles($this->root, ['src', 'tests', 'benchmarks'])),
            'workflow-concurrency' => new WorkflowConcurrencyGate()
                ->evaluate(new WorkflowConcurrencyReport(
                    new WorkflowFileCollector($this->root)->collect(),
                )),
            'smoke' => $this->smoke(),
            default => throw new ToolingException(\sprintf('Unknown gate "%s".', $gate)),
        };
    }

    private function readBaseline(): ?string
    {
        $path = $this->root . '/phpstan-baseline.neon';

        if (!is_file($path)) {
            return null;
        }

        $contents = file_get_contents($path);

        return \is_string($contents) ? $contents : null;
    }

    /**
     * @return list<string>
     */
    private function readAuditAllowlist(): array
    {
        $configured = $this->composerAuditIgnore();

        if (!\is_array($configured)) {
            return [];
        }

        $allowlist = [];

        foreach ($configured as $entry) {
            if (!\is_string($entry)) {
                continue;
            }

            $allowlist[] = $entry;
        }

        return $allowlist;
    }

    private function composerAuditIgnore(): mixed
    {
        // arrayValue() already returns null for any non-array level, so the
        // lookup chain is fail-safe without an explicit is_array() guard here.
        // The guard is deliberately absent: as a redundant branch it was an
        // equivalent mutant no test could distinguish (undetectable under
        // mutation testing), so removing it keeps the MSI gate honest.
        $config = $this->arrayValue($this->readComposerJson(), 'config');
        $audit = $this->arrayValue($config, 'audit');

        return $this->arrayValue($audit, 'ignore');
    }

    private function readComposerJson(): mixed
    {
        $path = $this->root . '/composer.json';

        if (!is_file($path)) {
            return null;
        }

        $raw = file_get_contents($path);

        if (!\is_string($raw)) {
            return null;
        }

        return json_decode($raw, true);
    }

    private function arrayValue(mixed $value, string $key): mixed
    {
        // Expressed as a ternary rather than an early `return null;` guard: the
        // guard's removal is an equivalent mutant (a scalar/absent value yields
        // null either way through `?? null`), which no test could distinguish
        // and which therefore kept the MSI gate below 100. In this shape both
        // the branch choice and the looked-up value are observable, so the
        // traversal is fully detectable.
        return \is_array($value) ? $value[$key] ?? null : null;
    }

    private function auditPayload(): string
    {
        [, $stdout] = ($this->processRunner)(\sprintf(
            'cd %s && composer audit --format=json --locked --no-interaction 2>/dev/null',
            escapeshellarg($this->root),
        ));

        if (mb_trim($stdout) === '') {
            throw new ToolingException('Strict audit FAILED: composer audit produced no JSON payload.');
        }

        return $stdout;
    }

    private function lintFile(string $file): bool
    {
        [$exitCode] = ($this->processRunner)(\sprintf(
            '%s -l %s 2>&1',
            escapeshellarg(\PHP_BINARY),
            escapeshellarg($file),
        ));

        return $exitCode === 0;
    }

    private function smoke(): GateResult
    {
        $persistentWorkerSmoke = new PersistentWorkerSmoke($this->smokeIterations, $this->smokeMemoryBudget);
        $smokeResult = $persistentWorkerSmoke->run(new Kernel());

        if (!$persistentWorkerSmoke->passes($smokeResult)) {
            $failure = $persistentWorkerSmoke->message($smokeResult) . ' -> Persistent-worker smoke FAILED.';

            return GateResult::failed($failure);
        }

        $success = $persistentWorkerSmoke->message($smokeResult) . ' -> Persistent-worker smoke PASSED.';

        return GateResult::passed($success);
    }
}
