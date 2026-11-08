<?php

declare(strict_types=1);

namespace Zef\Test\Support\Tooling;

/**
 * The errors family's shared argument parse: the one loop both log verbs
 * spell over the arguments the operator handed the CLI, lifted out of
 * `bin/zef`'s joined branch (#717's structural step).
 *
 * The grammar the family answers: an optional `--request-id=<id>` filter
 * (the id corridor `[A-Za-z0-9._:-]{1,128}`, non-empty -- an invalid or
 * empty spelling is a usage defect, never a silently-ignored flag), an
 * optional `--confirm` marker the clear verb alone reads, and at most one
 * positional path naming the JSONL log directly. The loop mirrors the
 * original joined spelling exactly: a `--confirm` token is never taken
 * for the positional path, and the last positional wins -- the posture
 * the subprocess tests pin.
 */
final class ErrorsLogArguments
{
    /**
     * Parses the log verbs' shared argument surface.
     *
     * @param list<string> $arguments the raw arguments after the verb's own
     *                                name
     *
     * @return array{0: ?string, 1: ?string} the request-id filter (null when
     *                                       absent) and the positional log
     *                                       path (null when absent)
     *
     * @throws ToolingException when a `--request-id=` spelling is invalid
     */
    public static function parsed(array $arguments): array
    {
        $requestIdFilter = null;
        $positionalPath = null;
        foreach ($arguments as $argument) {
            if (str_starts_with($argument, '--request-id=')) {
                $candidate = mb_substr($argument, mb_strlen('--request-id='));
                if ($candidate === '' || preg_match('/^[A-Za-z0-9._:-]{1,128}$/D', $candidate) !== 1) {
                    throw new ToolingException('Invalid request ID filter.');
                }

                $requestIdFilter = $candidate;

                continue;
            }

            if ($argument === '--confirm') {
                continue;
            }

            $positionalPath = $argument;
        }

        return [$requestIdFilter, $positionalPath];
    }
}
