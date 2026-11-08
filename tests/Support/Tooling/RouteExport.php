<?php

declare(strict_types=1);

namespace Zef\Test\Support\Tooling;

use Throwable;

/**
 * The `bin/zef route:openapi` / `route:postman` verb: the route
 * export family's whole answer, lifted out of the CLI's joined
 * branch (#717's structural step, the route export family's slice).
 *
 * The verb owns the family's joined argument grammar and its
 * refusals: the positional configuration directory both spellings
 * carry; the `--title=` flag both spellings require (a value judged
 * against the 256-character bound with the empty spelling a defect,
 * never a default -- the last spelling wins); the `--version=` and
 * `--scheme=` flags that belong to `route:openapi` alone (mis-scoped
 * spellings on `route:postman` answer the usage, and the scheme is
 * judged against the http/https vocabulary with `https` the default);
 * and every invalid required value answers its own named refusal
 * followed by the usage, exactly the words the joined branch spelled.
 * The answer itself is the inspection boot's own router ({@see
 * RouteInspectionBoot::router()}) rendered by the two export units
 * ({@see RouteOpenApiExport} the OpenAPI 3.1 document, {@see
 * RoutePostmanExport} the Postman v2.1 collection) -- the verb never
 * re-derives what the boot answers, and a refused boot surfaces with
 * the boot's own chain under the export's own refusal word.
 *
 * The grammar is the exit-code vocabulary the whole CLI answers: `0`
 * the export answered, `1` an operational refusal (the boot refusing
 * the configuration), `2` a usage defect (the missing or flag-shaped
 * positional, the unresolved directory, the unknown or mis-scoped
 * flag, the invalid required value or scheme). The answer gathers as
 * a {@see CommandOutcome} so the verb stays pure and the dispatch
 * owns the terminals.
 */
final class RouteExport
{
    /**
     * Runs the verb over the command spelling and the arguments the
     * operator spelled after it.
     *
     * @param non-empty-string $command   the family's own command name
     *                                    (`route:openapi` or
     *                                    `route:postman`) -- the grammar's
     *                                    shape selector, the same branch
     *                                    condition the joined branch read
     * @param list<string>     $arguments the raw arguments after the
     *                                    command's own name: the
     *                                    configuration directory
     *                                    positional and the family's flags
     */
    public static function run(string $command, array $arguments): CommandOutcome
    {
        $exportUsage = 'Usage: bin/zef route:openapi <config-directory> --title=<title> --version=<api-version>'
            . " [--scheme=http|https]\n"
            . "       bin/zef route:postman <config-directory> --title=<title>\n";

        $configDirectory = $arguments[0] ?? '';

        if ($configDirectory === '' || str_starts_with($configDirectory, '--')) {
            return CommandOutcome::refused(2, $exportUsage);
        }

        $resolved = realpath($configDirectory);
        if ($resolved === false || !is_dir($resolved)) {
            return CommandOutcome::refused(2, "Configuration directory does not exist: {$configDirectory}\n");
        }

        $title = null;
        $version = null;
        $scheme = 'https';

        foreach (\array_slice($arguments, 1) as $argument) {
            if (str_starts_with($argument, '--title=')) {
                $title = mb_substr($argument, mb_strlen('--title='));
            } elseif ($command === 'route:openapi' && str_starts_with($argument, '--version=')) {
                $version = mb_substr($argument, mb_strlen('--version='));
            } elseif ($command === 'route:openapi' && str_starts_with($argument, '--scheme=')) {
                $scheme = mb_substr($argument, mb_strlen('--scheme='));
            } else {
                return CommandOutcome::refused(2, $exportUsage);
            }
        }

        if (
            $title === null
            || $title === ''
            || mb_strlen($title) > 256
            || preg_match('/[\x00-\x1f\x7f]/', $title) === 1
        ) {
            return CommandOutcome::refused(2, "Invalid --title= value.\n" . $exportUsage);
        }

        if ($command === 'route:postman') {
            try {
                return CommandOutcome::answered(
                    RoutePostmanExport::rendered(RouteInspectionBoot::router($resolved), $title) . "\n",
                );
            } catch (Throwable $throwable) {
                return CommandOutcome::refused(1, 'The export refused: ' . $throwable->getMessage() . "\n");
            }
        }

        if (
            $version === null
            || $version === ''
            || mb_strlen($version) > 256
            || preg_match('/[\x00-\x1f\x7f]/', $version) === 1
        ) {
            return CommandOutcome::refused(2, "Invalid --version= value.\n" . $exportUsage);
        }

        if (!\in_array($scheme, ['http', 'https'], true)) {
            return CommandOutcome::refused(2, "Invalid --scheme= value.\n" . $exportUsage);
        }

        try {
            return CommandOutcome::answered(
                RouteOpenApiExport::rendered(
                    RouteInspectionBoot::router($resolved),
                    $title,
                    $version,
                    $scheme,
                ) . "\n",
            );
        } catch (Throwable $throwable) {
            return CommandOutcome::refused(1, 'The export refused: ' . $throwable->getMessage() . "\n");
        }
    }
}
