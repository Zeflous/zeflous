<?php

declare(strict_types=1);

namespace Zef\Test\Support\Tooling;

use Throwable;
use Zef\Framework\Http\SecurityHeaderAudit;

/**
 * The `bin/zef http:dump` / `http:headers` verb: the HTTP inspection
 * command family's whole answer, lifted out of the CLI's joined
 * branch (#717's structural step, the http inspection family's
 * slice).
 *
 * The verb owns the family's joined argument grammar and its
 * refusals: the positional configuration directory, method and path
 * every spelling carries (the method judged by the token grammar's
 * /D anchor, the path by the slash/2048-character/control-byte
 * rules); the `--host=` (defaulting to localhost), `--scheme=`
 * (defaulting to http, judged against the http/https vocabulary)
 * and `--body=` flags both spellings answer; the `--header=` flags
 * (at most sixteen, each spelling `Name: value` with a printable
 * value of at most 4096 bytes); and the `--json` flag that belongs
 * to `http:headers` alone -- a mis-scoped `--json` on `http:dump`
 * answering the usage. The answers themselves are the family's own
 * units -- {@see HttpInspectionBoot::answered()} for the simulated
 * exchange, {@see HttpDumpText::rendered()} for the raw dump, and
 * the security-header audit's own verdict for `http:headers` -- the
 * verb never re-derives what the units answer. The audit call is
 * the sanctioned seam the framework's `@internal` boundary asked
 * for: the verb class is the CLI's in-project consumer, the same
 * seam {@see MigrationsBoot} rides for the database family's own
 * internal folds.
 *
 * The grammar is the exit-code vocabulary the whole CLI answers: `0`
 * the answer, `1` an operational refusal (the boot refusing the
 * simulation, the audit failing its verdict), `2` a usage defect
 * (the missing or flag-shaped positional, the invalid method or
 * path, the unknown or mis-scoped flag, the invalid host, scheme or
 * header spelling). The answer gathers as a {@see CommandOutcome}
 * so the verb stays pure and the dispatch owns the terminals.
 */
final class HttpInspection
{
    /**
     * Runs the verb over the command spelling and the arguments the
     * operator spelled after it.
     *
     * @param non-empty-string $command   the family's own command name
     *                                    (`http:dump` or `http:headers`)
     *                                    -- the grammar's shape selector,
     *                                    the same branch condition the
     *                                    joined branch read
     * @param list<string>     $arguments the raw arguments after the
     *                                    command's own name: the
     *                                    configuration directory,
     *                                    method and path positionals,
     *                                    and the family's flags
     */
    public static function run(string $command, array $arguments): CommandOutcome
    {
        $httpUsage = 'Usage: bin/zef http:dump <config-directory> <method> <path>'
            . " [--host=<host>] [--scheme=http|https] [--header=<Name: Value>]... [--body=<text>]\n"
            . '       bin/zef http:headers <config-directory> <method> <path>'
            . " [--host=<host>] [--scheme=http|https] [--header=<Name: Value>]... [--body=<text>] [--json]\n";

        $configDirectoryArgument = $arguments[0] ?? '';
        $method = $arguments[1] ?? '';
        $path = $arguments[2] ?? '';

        if ($configDirectoryArgument === '' || str_starts_with($configDirectoryArgument, '--')) {
            return CommandOutcome::refused(2, $httpUsage);
        }

        $resolvedConfigDirectory = realpath($configDirectoryArgument);
        if ($resolvedConfigDirectory === false || !is_dir($resolvedConfigDirectory)) {
            return CommandOutcome::refused(
                2,
                "Configuration directory does not exist: {$configDirectoryArgument}\n",
            );
        }

        if (preg_match('/^[!#$%&\'*+.^_`|~0-9A-Za-z-]{1,32}$/D', $method) !== 1) {
            return CommandOutcome::refused(2, "Invalid request method: {$method}\n");
        }

        if ($path === '' || !str_starts_with($path, '/') || mb_strlen($path) > 2048
            || preg_match('/[\x00-\x1f\x7f]/', $path) === 1) {
            return CommandOutcome::refused(
                2,
                "The request path must start with a slash and carry no control bytes.\n",
            );
        }

        $host = 'localhost';
        $scheme = 'http';
        $headerArguments = [];
        $body = '';
        $asJson = false;

        foreach (\array_slice($arguments, 3) as $argument) {
            if (str_starts_with($argument, '--host=')) {
                $host = mb_substr($argument, mb_strlen('--host='));
            } elseif (str_starts_with($argument, '--scheme=')) {
                $scheme = mb_substr($argument, mb_strlen('--scheme='));
            } elseif (str_starts_with($argument, '--header=')) {
                $headerArguments[] = mb_substr($argument, mb_strlen('--header='));
            } elseif (str_starts_with($argument, '--body=')) {
                // The offset's own spelling, isolated on its own line so the
                // equivalence its ASCII prefix earns stays scoped to it --
                // the extraction's own rewrite stays under the mutator,
                // killed by the substituted-form test.
                $bodyOffset = mb_strlen('--body=');
                $body = mb_substr($argument, $bodyOffset);
            } elseif ($argument === '--json' && $command === 'http:headers') {
                $asJson = true;
            } else {
                return CommandOutcome::refused(2, $httpUsage);
            }
        }

        if ($host === '' || mb_strlen($host) > 253 || preg_match('/[\x00-\x1f\x7f]/', $host) === 1) {
            return CommandOutcome::refused(2, "Invalid --host= value.\n");
        }

        if (!\in_array($scheme, ['http', 'https'], true)) {
            return CommandOutcome::refused(2, "The --scheme= flag accepts http or https.\n");
        }

        if (\count($headerArguments) > 16) {
            return CommandOutcome::refused(2, "At most 16 --header= flags are accepted.\n");
        }

        $headerPairs = [];
        foreach ($headerArguments as $headerArgument) {
            $colon = mb_strpos($headerArgument, ':');
            $headerName = $colon === false ? '' : mb_substr($headerArgument, 0, $colon);
            $headerValue = $colon === false ? '' : mb_ltrim(mb_substr($headerArgument, $colon + 1), ' ');

            if (preg_match('/^[!#$%&\'*+.^_`|~0-9A-Za-z-]+$/D', $headerName) !== 1
                || $headerValue === ''
                || mb_strlen($headerValue, '8bit') > 4096
                || preg_match('/[\x00-\x08\x0a-\x1f\x7f]/', $headerValue) === 1) {
                return CommandOutcome::refused(
                    2,
                    'Each --header= flag must spell `Name: value`'
                    . " with a printable value of at most 4096 bytes.\n",
                );
            }

            $headerPairs[] = [$headerName, $headerValue];
        }

        if (mb_strlen($body, '8bit') > 8192) {
            return CommandOutcome::refused(2, "The --body= flag accepts at most 8192 bytes.\n");
        }

        try {
            $exchange = HttpInspectionBoot::answered(
                $resolvedConfigDirectory,
                $method,
                $path,
                $host,
                $scheme,
                $headerPairs,
                $body,
            );

            if ($command === 'http:dump') {
                return CommandOutcome::answered(HttpDumpText::rendered($exchange));
            }

            // The scheme grammar above refused every spelling outside the
            // http/https vocabulary, and the boot builds the exchange's URI
            // from that validated spelling -- the audit's own source of
            // truth is never the empty string.
            $exchangeScheme = $exchange->scheme();
            \assert($exchangeScheme !== '');

            $audit = SecurityHeaderAudit::over(
                $exchange->response,
                $exchangeScheme,
            );

            return new CommandOutcome(
                $audit->passed() ? 0 : 1,
                $asJson
                    ? HeaderAuditJson::rendered($audit) . "\n"
                    : HeaderAuditText::table($audit),
            );
        } catch (Throwable $throwable) {
            return CommandOutcome::refused(
                1,
                'The HTTP inspection refused: ' . $throwable->getMessage() . "\n",
            );
        }
    }
}
