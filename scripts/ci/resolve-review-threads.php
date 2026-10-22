<?php

declare(strict_types=1);

/**
 * Thin CLI entry point that resolves the *finished* review conversations on a
 * pull request so the branch ruleset's `required_review_thread_resolution` rule
 * stops blocking the merge.
 *
 * All decision logic lives in `Zef\Framework\Tooling\ReviewThreadResolver`
 * (unit-tested, fully covered); this file only wires the real GraphQL transport
 * and maps the result to an exit code, so it stays a few lines long.
 *
 * Usage: php scripts/ci/resolve-review-threads.php <pull-request-number>
 *
 * Exit codes:
 *   0  every finished conversation was resolved (or there was none)
 *   1  a resolve attempt failed, or the payload could not be read
 *   2  the pull-request number argument is missing or not a positive integer
 */

use Zef\Framework\Tooling\ReviewThreadResolver;
use Zef\Framework\Tooling\ToolingException;

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

$number = $argv[1] ?? '';

if (!ctype_digit($number) || (int) $number < 1) {
    fwrite(STDERR, 'Usage: php scripts/ci/resolve-review-threads.php <pull-request-number>' . PHP_EOL);
    exit(2);
}

$token = getenv('GH_TOKEN');

if (!\is_string($token) || $token === '') {
    fwrite(STDERR, 'GH_TOKEN is not set; cannot call the GitHub GraphQL API.' . PHP_EOL);
    exit(1);
}

/**
 * Runs a GraphQL query/mutation through curl and returns the raw JSON body.
 *
 * The token never rides the command line (the process list would read it for
 * the request's lifetime): curl reads it from a 0600 config file the call
 * owns and unlinks in `finally`, while the payload crosses stdin. The config
 * also carries the exchange's own timeouts, so a hung connection refuses
 * instead of stalling the sweep.
 *
 * @param array<string, mixed> $variables
 */
$graphql = static function (string $query, array $variables) use ($token): string {
    $payload = json_encode(['query' => $query, 'variables' => $variables], \JSON_THROW_ON_ERROR);

    $config = tempnam(sys_get_temp_dir(), 'zef-graphql-');

    if ($config === false) {
        throw new ToolingException('Could not stage the GitHub GraphQL config file.');
    }

    $config .= '.curl';
    file_put_contents(
        $config,
        "header = \"Authorization: Bearer {$token}\"\n"
        . "header = \"Content-Type: application/json\"\n"
        . "header = \"Accept: application/vnd.github+json\"\n"
        . "max-time = 30\n"
        . "connect-timeout = 10\n",
    );
    chmod($config, 0o600);

    $command = \sprintf(
        'curl -fsSL --config %s -X POST --data-binary @- %s',
        escapeshellarg($config),
        escapeshellarg('https://api.github.com/graphql'),
    );

    try {
        $process = proc_open(
            $command,
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
        );

        if (!\is_resource($process)) {
            throw new ToolingException('Could not start curl for the GitHub GraphQL API.');
        }

        fwrite($pipes[0], $payload);
        fclose($pipes[0]);

        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        if ($exitCode !== 0 || !\is_string($stdout) || $stdout === '') {
            throw new ToolingException('The GitHub GraphQL API call failed (curl exit ' . $exitCode . ').');
        }

        return $stdout;
    } finally {
        if (is_file($config)) {
            unlink($config);
        }
    }
};

try {
    $resolution = new ReviewThreadResolver($graphql)->resolve((int) $number);
} catch (ToolingException $toolingException) {
    fwrite(STDERR, $toolingException->getMessage() . PHP_EOL);
    exit(1);
}

fwrite(STDOUT, $resolution->message() . PHP_EOL);

exit($resolution->failed === 0 ? 0 : 1);
