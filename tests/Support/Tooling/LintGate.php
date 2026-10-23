<?php

declare(strict_types=1);

namespace Zef\Test\Support\Tooling;

/**
 * Syntax-lint gate.
 *
 * The actual `php -l` invocation is injected as a callable so the gate logic is
 * pure and testable; the CLI wires the real process runner. An empty corpus
 * is a failure, not a pass: a lint that checked nothing certifies nothing,
 * and the corpus collector's own refusal ({@see LintReport}) is the primary
 * guard -- this arm is the defense that stands even when the corpus arrives
 * empty by construction rather than by a missing directory.
 */
final readonly class LintGate
{
    /**
     * @param callable(string): bool $syntaxChecker returns true when the file parses
     */
    public function __construct(private mixed $syntaxChecker)
    {
    }

    public function evaluate(LintReport $lintReport): GateResult
    {
        $failures = [];

        foreach ($lintReport->files as $file) {
            if (($this->syntaxChecker)($file)) {
                continue;
            }

            $failures[] = $file;
        }

        if ($lintReport->count() === 0) {
            return GateResult::failed(
                'Lint gate FAILED: no PHP files were found to lint -- an empty corpus certifies nothing.',
            );
        }

        if ($failures !== []) {
            return GateResult::failed(\sprintf(
                'Syntax errors detected in %d file(s): %s',
                \count($failures),
                implode(', ', $failures),
            ));
        }

        return GateResult::passed(\sprintf('Lint OK: %d PHP files checked.', $lintReport->count()));
    }
}
