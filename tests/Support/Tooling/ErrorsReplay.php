<?php

declare(strict_types=1);

namespace Zef\Test\Support\Tooling;

/**
 * The `bin/zef errors:replay` verb: one recorded error snapshot replayed
 * against a plain-HTTP local target -- the operator's own reproduction
 * tool, lifted out of the CLI's per-branch spellings so the family's
 * discipline lives in one reviewable place (#717's structural step).
 *
 * The grammar is the exit-code vocabulary the whole CLI answers: `0` the
 * replay completed, `1` an operational refusal (the snapshot missing,
 * unreadable or not replayable; the local request itself failing), `2` a
 * usage defect. Every refusal names itself on STDERR, the answer and the
 * status line land on STDOUT -- gathered as a {@see CommandOutcome} so
 * the verb stays pure and the dispatch owns the terminals.
 *
 * The safety posture is the family's own: the target must be a plain HTTP
 * URL on `127.0.0.1`, `localhost` or `::1` (no userinfo, query, fragment
 * or non-root path), the replay directory must exist, be a real directory
 * and sit outside the public document root, the snapshot must be a real
 * non-symlink file with owner-only permissions, and the reconstructed
 * request drops every hop-by-hop and credential-shaped header while
 * capping header values and the body's replay share. The replayed request
 * carries the `X-Zef-Replay: 1` marker so the receiving side can tell a
 * replay from a live request.
 */
final class ErrorsReplay
{
    /**
     * Runs the verb over the arguments the operator spelled after it.
     *
     * @param list<string> $arguments the raw arguments after the verb's
     *                                own name: the snapshot id, the replay
     *                                directory (defaulting to
     *                                `ZEF_ERROR_REPLAY_DIR` when absent)
     *                                and the plain-HTTP local target
     */
    public static function run(array $arguments): CommandOutcome
    {
        $id = $arguments[0] ?? '';
        $directory = $arguments[1] ?? (self::replayDirectoryEnvironment() ?? '');
        $baseUrl = $arguments[2] ?? '';

        if (preg_match('/^[a-f0-9]{32}$/D', $id) !== 1 || $directory === '' || $baseUrl === '') {
            return CommandOutcome::refused(
                2,
                "Usage: bin/zef errors:replay <id> <replay-directory> <http://127.0.0.1:port>\n",
            );
        }

        $base = parse_url($baseUrl);
        $allowedHosts = ['127.0.0.1', 'localhost', '::1'];
        $targetHost = \is_array($base) ? mb_strtolower(mb_trim(($base['host'] ?? ''), '[]')) : '';
        if (
            !\is_array($base)
            || ($base['scheme'] ?? '') !== 'http'
            || !\in_array($targetHost, $allowedHosts, true)
            || isset($base['user'])
            || isset($base['pass'])
            || isset($base['query'])
            || isset($base['fragment'])
            || !\in_array($base['path'] ?? '', ['', '/'], true)
        ) {
            return CommandOutcome::refused(
                2,
                "Replay target must be a plain HTTP URL on localhost, 127.0.0.1 or ::1.\n",
            );
        }

        $resolvedDirectory = realpath($directory);
        $documentRoot = self::documentRootSpelling($_SERVER['DOCUMENT_ROOT'] ?? null);
        $resolvedDocumentRoot = $documentRoot !== '' ? realpath($documentRoot) : false;
        if (
            !\is_string($resolvedDirectory)
            || !is_dir($resolvedDirectory)
            || is_link($directory)
            || (\is_string($resolvedDocumentRoot)
                && ($resolvedDirectory === $resolvedDocumentRoot
                    || str_starts_with(
                        $resolvedDirectory,
                        mb_rtrim($resolvedDocumentRoot, \DIRECTORY_SEPARATOR) . \DIRECTORY_SEPARATOR,
                    )))
        ) {
            return CommandOutcome::refused(2, "Replay directory does not exist or is unsafe.\n");
        }

        $snapshotPath = $resolvedDirectory . \DIRECTORY_SEPARATOR . $id . '.json';
        $snapshotPermissions = is_file($snapshotPath) ? fileperms($snapshotPath) : false;
        if (
            is_link($snapshotPath)
            || !is_file($snapshotPath)
            || !is_readable($snapshotPath)
            || $snapshotPermissions === false
            || ($snapshotPermissions & 0o777) !== 0o600
        ) {
            return CommandOutcome::refused(1, "Replay snapshot does not exist or is unsafe.\n");
        }

        $snapshot = json_decode((string) file_get_contents($snapshotPath), true);
        if (!\is_array($snapshot)) {
            return CommandOutcome::refused(1, "Replay snapshot is invalid JSON.\n");
        }

        $rawMethod = $snapshot['method'] ?? '';
        $method = mb_strtoupper(\is_string($rawMethod) ? $rawMethod : '');
        $allowedMethods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS'];
        $requestPath = $snapshot['path'] ?? '';
        $query = $snapshot['query'] ?? '';
        $body = $snapshot['body'] ?? '';
        $bodyReplayable = $snapshot['body_replayable'] ?? false;
        $surfaceSafe = \is_string($requestPath)
            && str_starts_with($requestPath, '/')
            && preg_match('/[\x00-\x1F\x7F]/', $requestPath) !== 1
            && \is_string($query)
            && preg_match('/[\r\n]/', $query) !== 1
            && \is_string($body)
            && \is_bool($bodyReplayable)
            && ($bodyReplayable || \in_array($method, ['GET', 'HEAD', 'OPTIONS'], true));
        if (
            !\in_array($method, $allowedMethods, true)
            || $surfaceSafe === false
        ) {
            return CommandOutcome::refused(1, "Snapshot is not safe or replayable; the original body was omitted.\n");
        }

        $headers = [];
        $storedHeaders = \is_array($snapshot['headers'] ?? null) ? $snapshot['headers'] : [];
        foreach ($storedHeaders as $name => $values) {
            if (
                !\is_string($name)
                || preg_match('/^[A-Za-z0-9-]{1,80}$/D', $name) !== 1
                || \in_array(
                    mb_strtolower($name),
                    ['host', 'content-length', 'connection', 'transfer-encoding', 'set-cookie'],
                    true,
                )
                || preg_match('/authorization|cookie|secret|token|api[_-]?key|csrf/i', $name) === 1
                || !\is_array($values)
            ) {
                continue;
            }

            foreach (\array_slice($values, 0, 10) as $value) {
                if (!\is_string($value) || preg_match('/[\r\n]/', $value) === 1) {
                    continue;
                }

                $headers[] = $name . ': ' . mb_substr($value, 0, 512);
            }
        }

        $headers[] = 'X-Zef-Replay: 1';

        $target = mb_rtrim($baseUrl, '/') . $requestPath . ($query !== '' ? '?' . $query : '');
        $context = stream_context_create([
            'http' => [
                'method' => $method,
                'header' => implode("\r\n", $headers),
                'content' => $body,
                'ignore_errors' => true,
                'follow_location' => 0,
                'max_redirects' => 0,
                'timeout' => 2,
            ],
        ]);
        $responseBody = file_get_contents($target, false, $context);
        $statusLine = $http_response_header[0] ?? 'HTTP status unavailable';
        if (\is_string($responseBody)) {
            return CommandOutcome::answered($statusLine . "\n" . mb_substr($responseBody, 0, 4096) . "\n");
        }

        // The status line lands on STDOUT even on the local request's own
        // refusal -- the original branch wrote it before checking the body,
        // and an operator diagnosing a dead target still reads what the
        // transport answered.
        return new CommandOutcome(1, $statusLine . "\n", "Local replay request failed.\n");
    }

    /**
     * The replay directory the environment names, when it does.
     */
    private static function replayDirectoryEnvironment(): ?string
    {
        $replayDirectory = getenv('ZEF_ERROR_REPLAY_DIR');

        return \is_string($replayDirectory) && $replayDirectory !== '' ? $replayDirectory : null;
    }

    /**
     * The document root the SAPI spelled, when it spelled one: the replay
     * directory's containment wall reads it, and a non-string member is the
     * empty spelling -- the wall simply does not arm.
     *
     * @param mixed $value the SAPI-supplied member
     */
    private static function documentRootSpelling(mixed $value): string
    {
        return \is_string($value) ? $value : '';
    }
}
