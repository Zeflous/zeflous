<?php

declare(strict_types=1);

namespace Zef\Test\Support\Tooling;

use Throwable;
use Zef\Framework\Config\Routing\RouteMatchExplanation;

/**
 * The `bin/zef route:list` / `route:test` / `route:tree` verb: the
 * route inspection family's whole answer, lifted out of the CLI's
 * joined branch (#717's structural step, the route inspection
 * family's slice).
 *
 * The verb owns the family's joined argument grammar and its
 * refusals: the positional configuration directory every spelling
 * carries; `route:test`'s two further positionals (the request method
 * judged by the token grammar, the request path judged by its slash,
 * length and control-byte rules); the flag loop whose offset the
 * `route:test` spelling shifts (the `--host=`, `--accept=` and
 * `--version=` values belong to `route:test` alone, each judged
 * against its own bound -- 253, 256, 256 -- with the empty spelling
 * a defect, never a default); and the `--json` flag the two list
 * forms answer but `route:tree` refuses -- the flag collects by
 * prefix in one pass and is judged by exact spelling in the second,
 * so `--jsonx` answers the usage, not the JSON form. The answer
 * itself is the inspection boot's own router ({@see
 * RouteInspectionBoot::router()}) rendered by the family's
 * presentation units ({@see RouteListText}/{@see RouteListJson},
 * {@see RouteTreeText}, {@see RouteMatchText}/{@see RouteMatchJson}
 * over {@see RouteMatchAnswer}) -- the verb never re-derives what
 * the boot answers, and a refused boot surfaces with the boot's own
 * chain, exactly the words the joined branch spelled.
 *
 * The grammar is the exit-code vocabulary the whole CLI answers: `0`
 * the answer (a listing, a tree, a found match), `1` an operational
 * refusal (the boot refusing the configuration) or the honest miss
 * (`route:test`'s verdict -- the miss is an answer, carried on
 * STDOUT with the exit code the composition's callers test), `2` a
 * usage defect (the missing or flag-shaped positional, the invalid
 * method or path, the unknown or mis-scoped flag, the over-bound
 * flag value). The answer gathers as a {@see CommandOutcome} so the
 * verb stays pure and the dispatch owns the terminals.
 */
final class RouteInspection
{
    /**
     * Runs the verb over the command spelling and the arguments the
     * operator spelled after it.
     *
     * @param non-empty-string $command   the family's own command name
     *                                    (`route:list`, `route:test` or
     *                                    `route:tree`) -- the grammar's
     *                                    shape selector, the same branch
     *                                    condition the joined branch read
     * @param list<string>     $arguments the raw arguments after the
     *                                    command's own name: the
     *                                    configuration directory
     *                                    positional, `route:test`'s method
     *                                    and path positionals, and the
     *                                    family's flags
     */
    public static function run(string $command, array $arguments): CommandOutcome
    {
        $routeUsage = "Usage: bin/zef route:list <config-directory> [--json]\n"
            . '       bin/zef route:test <config-directory> <method> <path>'
            . " [--host=<host>] [--accept=<value>] [--version=<value>] [--json]\n"
            . "       bin/zef route:tree <config-directory>\n";

        $configDirectory = $arguments[0] ?? '';

        if ($configDirectory === '' || str_starts_with($configDirectory, '--')) {
            return CommandOutcome::refused(2, $routeUsage);
        }

        $resolved = realpath($configDirectory);
        if ($resolved === false || !is_dir($resolved)) {
            return CommandOutcome::refused(2, "Configuration directory does not exist: {$configDirectory}\n");
        }

        $method = '';
        $path = '';
        $host = null;
        $accept = null;
        $version = null;
        $matchHost = null;
        $matchAccept = null;
        $matchVersion = null;
        $asJson = false;

        if ($command === 'route:test') {
            $method = $arguments[1] ?? '';
            $path = $arguments[2] ?? '';

            if (preg_match('/^[!#$%&\'*+.^_`|~0-9A-Za-z-]{1,32}$/D', $method) !== 1) {
                return CommandOutcome::refused(2, "Invalid request method: {$method}\n");
            }

            if (
                $path === '' || !str_starts_with($path, '/') || mb_strlen($path) > 2048
                || preg_match('/[\x00-\x1f\x7f]/', $path) === 1
            ) {
                return CommandOutcome::refused(
                    2,
                    "The request path must start with a slash and carry no control bytes.\n",
                );
            }
        }

        $routeFlags = [];

        foreach (\array_slice($arguments, $command === 'route:test' ? 3 : 1) as $argument) {
            if (str_starts_with($argument, '--json')) {
                $routeFlags[] = $argument;
            } elseif ($command === 'route:test' && str_starts_with($argument, '--host=')) {
                $host = mb_substr($argument, mb_strlen('--host='));
            } elseif ($command === 'route:test' && str_starts_with($argument, '--accept=')) {
                $accept = mb_substr($argument, mb_strlen('--accept='));
            } elseif ($command === 'route:test' && str_starts_with($argument, '--version=')) {
                $version = mb_substr($argument, mb_strlen('--version='));
            } else {
                return CommandOutcome::refused(2, $routeUsage);
            }
        }

        foreach ($routeFlags as $routeFlag) {
            if ($routeFlag !== '--json' || $command === 'route:tree') {
                return CommandOutcome::refused(2, $routeUsage);
            }

            $asJson = true;
        }

        if ($command === 'route:test') {
            if ($host === '') {
                return CommandOutcome::refused(2, "Invalid --host= value.\n");
            }

            if (
                $host !== null
                && (mb_strlen($host) > 253 || preg_match('/[\x00-\x1f\x7f]/', $host) === 1)
            ) {
                return CommandOutcome::refused(2, "Invalid --host= value.\n");
            }

            if ($accept === '') {
                return CommandOutcome::refused(2, "Invalid --accept= value.\n");
            }

            if (
                $accept !== null
                && (mb_strlen($accept) > 256 || preg_match('/[\x00-\x1f\x7f]/', $accept) === 1)
            ) {
                return CommandOutcome::refused(2, "Invalid --accept= value.\n");
            }

            if ($version === '') {
                return CommandOutcome::refused(2, "Invalid --version= value.\n");
            }

            if (
                $version !== null
                && (mb_strlen($version) > 256 || preg_match('/[\x00-\x1f\x7f]/', $version) === 1)
            ) {
                return CommandOutcome::refused(2, "Invalid --version= value.\n");
            }

            // The validated simulation flags, captured where the guards
            // narrowed them: the values the simulated request carries.
            $matchHost = $host;
            $matchAccept = $accept;
            $matchVersion = $version;
        }

        try {
            $router = RouteInspectionBoot::router($resolved);

            if ($command === 'route:list') {
                return CommandOutcome::answered(
                    $asJson
                        ? RouteListJson::rendered($router) . "\n"
                        : RouteListText::table($router),
                );
            }

            if ($command === 'route:tree') {
                return CommandOutcome::answered(RouteTreeText::rendered($router));
            }

            $answer = RouteMatchAnswer::answered($router, $method, $path, $matchHost, $matchAccept, $matchVersion);

            return new CommandOutcome(
                $answer->outcome === RouteMatchExplanation::OUTCOME_FOUND ? 0 : 1,
                $asJson
                    ? RouteMatchJson::rendered($answer) . "\n"
                    : RouteMatchText::rendered($answer),
            );
        } catch (Throwable $throwable) {
            return CommandOutcome::refused(1, 'The route boot refused: ' . $throwable->getMessage() . "\n");
        }
    }
}
