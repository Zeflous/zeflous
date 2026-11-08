<?php

declare(strict_types=1);

namespace Zef\Test\Support\Tooling;

/**
 * A CLI verb's whole answer: the exit code the dispatch exits with, the
 * bytes the verb's own terminals carry -- STDOUT the answer and STDERR
 * the refusals -- gathered as data so the verb classes stay pure,
 * unit-testable and mutation-honest (a removed or altered write fails a
 * test, not a machine).
 *
 * The CLI's exit-code vocabulary is the contract: `0` the answer, `1` an
 * operational refusal, `2` a usage defect. The dispatch (`bin/zef`)
 * owns the terminals themselves: it flushes the two streams and exits
 * with the code, the one place in the tree `fwrite` on the process's own
 * terminals happens.
 */
final readonly class CommandOutcome
{
    /**
     * @param int    $exitCode the process exit code: 0 the answer, 1 an
     *                         operational refusal, 2 a usage defect
     * @param string $stdout   the bytes the verb answers on STDOUT
     * @param string $stderr   the bytes the verb's refusals carry on STDERR
     */
    public function __construct(
        public int $exitCode,
        public string $stdout = '',
        public string $stderr = '',
    ) {
    }

    /**
     * The answer-shaped outcome: exit 0 with the STDOUT answer.
     */
    public static function answered(string $stdout): self
    {
        return new self(0, $stdout);
    }

    /**
     * The refusal-shaped outcome: the code with the STDERR explanation.
     */
    public static function refused(int $exitCode, string $stderr): self
    {
        return new self($exitCode, '', $stderr);
    }
}
