<?php

declare(strict_types=1);

namespace Zef\Framework\Tooling;

/**
 * Syntax-lint gate.
 *
 * The actual `php -l` invocation is injected as a callable so the gate logic is
 * pure and testable; the CLI wires the real process runner.
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
