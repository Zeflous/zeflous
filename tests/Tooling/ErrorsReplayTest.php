<?php

declare(strict_types=1);

namespace Zef\Test\Tooling;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Test\Support\Tooling\CommandOutcome;
use Zef\Test\Support\Tooling\ErrorsReplay;

/**
 * The `errors:replay` verb as a pure outcome: every branch of its usage
 * grammar, its target and directory safety posture, its snapshot and
 * request-surface validation -- and the live replay itself against a
 * real local HTTP server that records what it received, so the
 * reconstructed request (method, path, query, body, the header
 * filtering and the `X-Zef-Replay` marker) is verified on the wire, not
 * by re-deriving it.
 *
 * @internal
 */
#[CoversClass(ErrorsReplay::class)]
final class ErrorsReplayTest extends TestCase
{
    private const string HOST = '127.0.0.1';

    /**
     * The replay directory's own path.
     */
    private string $replayDirectory;

    /**
     * The recording server's document root.
     */
    private string $documentRoot;

    /**
     * The server process, when one was spawned.
     *
     * @var null|resource
     */
    private $server;

    private int $port = 0;

    private string $previousReplayDirectory;

    #[Override]
    protected function setUp(): void
    {
        $token = bin2hex(random_bytes(8));
        $this->replayDirectory = sys_get_temp_dir() . '/zef-replay-dir-' . $token;
        $this->documentRoot = sys_get_temp_dir() . '/zef-replay-docroot-' . $token;
        mkdir($this->replayDirectory, 0o755, true);
        mkdir($this->documentRoot, 0o755, true);
        file_put_contents($this->documentRoot . '/capture.php', <<<'PHP'
            <?php
            $captured = [
                'method' => $_SERVER['REQUEST_METHOD'],
                'uri' => $_SERVER['REQUEST_URI'],
                'body' => file_get_contents('php://input'),
                'headers' => [],
            ];
            foreach ($_SERVER as $key => $value) {
                if (str_starts_with($key, 'HTTP_')) {
                    $captured['headers'][strtolower(str_replace('_', '-', substr($key, 5)))] = $value;
                }
            }
            file_put_contents(__DIR__ . '/captured.jsonl', json_encode($captured) . "\n", FILE_APPEND);
            echo 'replayed-ok';
            PHP);
        $this->previousReplayDirectory = (string) getenv('ZEF_ERROR_REPLAY_DIR');
        putenv('ZEF_ERROR_REPLAY_DIR=');
    }

    #[Override]
    protected function tearDown(): void
    {
        putenv('ZEF_ERROR_REPLAY_DIR=' . $this->previousReplayDirectory);
        $this->stopServer();
        foreach ((array) glob($this->documentRoot . '/*') as $file) {
            if (!\is_string($file) || !is_file($file)) {
                continue;
            }

            unlink($file);
        }

        rmdir($this->documentRoot);
        foreach ((array) glob($this->replayDirectory . '/*') as $file) {
            if (!\is_string($file) || !is_file($file)) {
                continue;
            }

            unlink($file);
        }

        rmdir($this->replayDirectory);
    }

    public function testAMissingIdAnswersTheUsageDefect(): void
    {
        $outcome = ErrorsReplay::run([]);

        self::assertSame(2, $outcome->exitCode);
        self::assertSame('', $outcome->stdout);
        self::assertSame(
            "Usage: bin/zef errors:replay <id> <replay-directory> <http://127.0.0.1:port>\n",
            $outcome->stderr,
        );
    }

    public function testAnOffGrammarIdAnswersTheUsageDefect(): void
    {
        $outcome = ErrorsReplay::run(['not-thirty-two-hex-characters', $this->replayDirectory, 'http://127.0.0.1:9']);

        self::assertSame(2, $outcome->exitCode);
        self::assertSame(
            "Usage: bin/zef errors:replay <id> <replay-directory> <http://127.0.0.1:port>\n",
            $outcome->stderr,
        );
    }

    public function testTheReplayDirectoryEnvironmentSpellingIsNotASecondArgument(): void
    {
        $this->startServer();
        putenv('ZEF_ERROR_REPLAY_DIR=' . $this->replayDirectory);

        // The grammar's own positions: an empty directory argument is a
        // usage defect even when the environment spells one -- the env
        // doctrine (KDD-ES6) rides the absent-argument path only, which
        // the three-argument shell spelling cannot reach without the
        // target following it.
        $outcome = ErrorsReplay::run([bin2hex(random_bytes(16)), '', $this->baseUrl()]);

        self::assertSame(2, $outcome->exitCode);
        self::assertSame(
            "Usage: bin/zef errors:replay <id> <replay-directory> <http://127.0.0.1:port>\n",
            $outcome->stderr,
        );
    }

    public function testAnHttpsTargetIsAUsageDefect(): void
    {
        $outcome = ErrorsReplay::run(
            [$this->id(), $this->replayDirectory, 'https://127.0.0.1:9'],
        );

        self::assertSame(2, $outcome->exitCode);
        self::assertSame(
            "Replay target must be a plain HTTP URL on localhost, 127.0.0.1 or ::1.\n",
            $outcome->stderr,
        );
    }

    public function testAForeignHostTargetIsAUsageDefect(): void
    {
        $outcome = ErrorsReplay::run(
            [$this->id(), $this->replayDirectory, 'http://example.com:8080'],
        );

        self::assertSame(2, $outcome->exitCode);
        self::assertSame(
            "Replay target must be a plain HTTP URL on localhost, 127.0.0.1 or ::1.\n",
            $outcome->stderr,
        );
    }

    public function testATargetWithUserinfoIsAUsageDefect(): void
    {
        $outcome = ErrorsReplay::run(
            [$this->id(), $this->replayDirectory, 'http://user:pass@127.0.0.1:9'],
        );

        self::assertSame(2, $outcome->exitCode);
        self::assertSame(
            "Replay target must be a plain HTTP URL on localhost, 127.0.0.1 or ::1.\n",
            $outcome->stderr,
        );
    }

    public function testATargetWithAQueryIsAUsageDefect(): void
    {
        $outcome = ErrorsReplay::run(
            [$this->id(), $this->replayDirectory, 'http://127.0.0.1:9?x=1'],
        );

        self::assertSame(2, $outcome->exitCode);
        self::assertSame(
            "Replay target must be a plain HTTP URL on localhost, 127.0.0.1 or ::1.\n",
            $outcome->stderr,
        );
    }

    public function testATargetWithAFragmentIsAUsageDefect(): void
    {
        $outcome = ErrorsReplay::run(
            [$this->id(), $this->replayDirectory, 'http://127.0.0.1:9#frag'],
        );

        self::assertSame(2, $outcome->exitCode);
        self::assertSame(
            "Replay target must be a plain HTTP URL on localhost, 127.0.0.1 or ::1.\n",
            $outcome->stderr,
        );
    }

    public function testATargetWithAPathIsAUsageDefect(): void
    {
        $outcome = ErrorsReplay::run(
            [$this->id(), $this->replayDirectory, 'http://127.0.0.1:9/some/path'],
        );

        self::assertSame(2, $outcome->exitCode);
        self::assertSame(
            "Replay target must be a plain HTTP URL on localhost, 127.0.0.1 or ::1.\n",
            $outcome->stderr,
        );
    }

    public function testAMissingReplayDirectoryIsAUsageDefect(): void
    {
        $outcome = ErrorsReplay::run(
            [$this->id(), sys_get_temp_dir() . '/zef-replay-absent-' . bin2hex(random_bytes(8)), 'http://127.0.0.1:9'],
        );

        self::assertSame(2, $outcome->exitCode);
        self::assertSame("Replay directory does not exist or is unsafe.\n", $outcome->stderr);
    }

    public function testASymlinkedReplayDirectoryIsAUsageDefect(): void
    {
        $target = sys_get_temp_dir() . '/zef-replay-real-' . bin2hex(random_bytes(8));
        $link = sys_get_temp_dir() . '/zef-replay-link-' . bin2hex(random_bytes(8));
        mkdir($target, 0o755, true);
        symlink($target, $link);

        try {
            $outcome = ErrorsReplay::run([$this->id(), $link, 'http://127.0.0.1:9']);

            self::assertSame(2, $outcome->exitCode);
            self::assertSame("Replay directory does not exist or is unsafe.\n", $outcome->stderr);
        } finally {
            unlink($link);
            rmdir($target);
        }
    }

    public function testTheDocumentRootItselfIsAnUnsafeReplayDirectory(): void
    {
        $previous = $_SERVER['DOCUMENT_ROOT'] ?? null;
        $_SERVER['DOCUMENT_ROOT'] = $this->documentRoot;

        try {
            $outcome = ErrorsReplay::run([$this->id(), $this->documentRoot, 'http://127.0.0.1:9']);

            self::assertSame(2, $outcome->exitCode);
            self::assertSame("Replay directory does not exist or is unsafe.\n", $outcome->stderr);
        } finally {
            if ($previous === null) {
                unset($_SERVER['DOCUMENT_ROOT']);
            } else {
                $_SERVER['DOCUMENT_ROOT'] = $previous;
            }
        }
    }

    public function testADirectoryInsideTheDocumentRootIsAnUnsafeReplayDirectory(): void
    {
        $inside = $this->documentRoot . '/inside';
        mkdir($inside, 0o755, true);
        $previous = $_SERVER['DOCUMENT_ROOT'] ?? null;
        $_SERVER['DOCUMENT_ROOT'] = $this->documentRoot;

        try {
            $outcome = ErrorsReplay::run([$this->id(), $inside, 'http://127.0.0.1:9']);

            self::assertSame(2, $outcome->exitCode);
            self::assertSame("Replay directory does not exist or is unsafe.\n", $outcome->stderr);
        } finally {
            if ($previous === null) {
                unset($_SERVER['DOCUMENT_ROOT']);
            } else {
                $_SERVER['DOCUMENT_ROOT'] = $previous;
            }

            rmdir($inside);
        }
    }

    public function testAMissingSnapshotIsAnOperationalRefusal(): void
    {
        $outcome = ErrorsReplay::run([$this->id(), $this->replayDirectory, 'http://127.0.0.1:9']);

        self::assertSame(1, $outcome->exitCode);
        self::assertSame("Replay snapshot does not exist or is unsafe.\n", $outcome->stderr);
    }

    public function testASnapshotWithLaxPermissionsIsAnOperationalRefusal(): void
    {
        $id = $this->snapshot($this->successfulSnapshot());
        chmod($this->replayDirectory . '/' . $id . '.json', 0o644);

        $outcome = ErrorsReplay::run([$id, $this->replayDirectory, 'http://127.0.0.1:9']);

        self::assertSame(1, $outcome->exitCode);
        self::assertSame("Replay snapshot does not exist or is unsafe.\n", $outcome->stderr);
    }

    public function testASnapshotOfInvalidJsonIsAnOperationalRefusal(): void
    {
        $id = $this->snapshot('not json');

        $outcome = ErrorsReplay::run([$id, $this->replayDirectory, 'http://127.0.0.1:9']);

        self::assertSame(1, $outcome->exitCode);
        self::assertSame("Replay snapshot is invalid JSON.\n", $outcome->stderr);
    }

    public function testAnUnknownMethodIsAnUnsafeSnapshot(): void
    {
        $id = $this->snapshot(array_merge($this->successfulSnapshot(), ['method' => 'TRACE']));

        $outcome = ErrorsReplay::run([$id, $this->replayDirectory, 'http://127.0.0.1:9']);

        self::assertSame(1, $outcome->exitCode);
        self::assertSame(
            "Snapshot is not safe or replayable; the original body was omitted.\n",
            $outcome->stderr,
        );
    }

    public function testAPathWithoutALeadingSlashIsAnUnsafeSnapshot(): void
    {
        $id = $this->snapshot(array_merge($this->successfulSnapshot(), ['path' => 'capture.php']));

        $outcome = ErrorsReplay::run([$id, $this->replayDirectory, 'http://127.0.0.1:9']);

        self::assertSame(1, $outcome->exitCode);
        self::assertSame(
            "Snapshot is not safe or replayable; the original body was omitted.\n",
            $outcome->stderr,
        );
    }

    public function testAPathWithControlBytesIsAnUnsafeSnapshot(): void
    {
        $id = $this->snapshot(array_merge($this->successfulSnapshot(), ['path' => "/cap\x1bture.php"]));

        $outcome = ErrorsReplay::run([$id, $this->replayDirectory, 'http://127.0.0.1:9']);

        self::assertSame(1, $outcome->exitCode);
        self::assertSame(
            "Snapshot is not safe or replayable; the original body was omitted.\n",
            $outcome->stderr,
        );
    }

    public function testAQueryWithCarriageReturnsIsAnUnsafeSnapshot(): void
    {
        $id = $this->snapshot(array_merge($this->successfulSnapshot(), ['query' => "a=1\r\nb: 2"]));

        $outcome = ErrorsReplay::run([$id, $this->replayDirectory, 'http://127.0.0.1:9']);

        self::assertSame(1, $outcome->exitCode);
        self::assertSame(
            "Snapshot is not safe or replayable; the original body was omitted.\n",
            $outcome->stderr,
        );
    }

    public function testANonReplayableBodyWithAMutatingMethodIsAnUnsafeSnapshot(): void
    {
        $id = $this->snapshot(array_merge(
            $this->successfulSnapshot(),
            ['method' => 'POST', 'body_replayable' => false, 'body' => 'secret'],
        ));

        $outcome = ErrorsReplay::run([$id, $this->replayDirectory, 'http://127.0.0.1:9']);

        self::assertSame(1, $outcome->exitCode);
        self::assertSame(
            "Snapshot is not safe or replayable; the original body was omitted.\n",
            $outcome->stderr,
        );
    }

    public function testTheLiveReplayCarriesTheMarkerTheMethodThePathAndTheBody(): void
    {
        $this->startServer();
        $id = $this->snapshot([
            'method' => 'POST',
            'path' => '/capture.php',
            'query' => 'shape=round',
            'body' => 'the-replayed-body',
            'body_replayable' => true,
            'headers' => ['X-Custom' => ['kept-value']],
        ]);

        $outcome = $this->runVerb([$id, $this->replayDirectory, $this->baseUrl()]);

        self::assertSame(0, $outcome->exitCode);
        self::assertSame('', $outcome->stderr);
        self::assertStringContainsString('HTTP/1.1 200 OK', $outcome->stdout);
        self::assertStringContainsString('replayed-ok', $outcome->stdout);

        $captured = $this->capturedRequest();
        self::assertSame('POST', $captured['method']);
        self::assertSame('/capture.php?shape=round', $captured['uri']);
        self::assertSame('the-replayed-body', $captured['body']);
        self::assertSame('1', $captured['headers']['x-zef-replay']);
        self::assertSame('kept-value', $captured['headers']['x-custom']);
    }

    public function testANonReplayableBodyOnAGetStillReplays(): void
    {
        $this->startServer();
        $id = $this->snapshot([
            'method' => 'GET',
            'path' => '/capture.php',
            'query' => '',
            'body' => '',
            'body_replayable' => false,
            'headers' => [],
        ]);

        $outcome = $this->runVerb([$id, $this->replayDirectory, $this->baseUrl()]);

        self::assertSame(0, $outcome->exitCode);
        $captured = $this->capturedRequest();
        self::assertSame('GET', $captured['method']);
        self::assertSame('/capture.php', $captured['uri']);
        self::assertSame('', $captured['body']);
    }

    public function testHopByHopAndCredentialShapedHeadersNeverReachTheTarget(): void
    {
        $this->startServer();
        $id = $this->snapshot([
            'method' => 'GET',
            'path' => '/capture.php',
            'query' => '',
            'body' => '',
            'body_replayable' => true,
            'headers' => [
                'Host' => ['evil.example'],
                'Content-Length' => ['999'],
                'Connection' => ['keep-alive'],
                'Transfer-Encoding' => ['chunked'],
                'Set-Cookie' => ['a=b'],
                'Authorization' => ['Bearer not-yours'],
                'Cookie' => ['session=not-yours'],
                'X-Secret' => ['kept-out'],
                'Api-Key' => ['kept-out'],
                'CSRF-Token' => ['kept-out'],
                'X-Kept' => ['rides'],
            ],
        ]);

        $outcome = $this->runVerb([$id, $this->replayDirectory, $this->baseUrl()]);

        self::assertSame(0, $outcome->exitCode);
        $captured = $this->capturedRequest();
        self::assertSame('rides', $captured['headers']['x-kept']);
        self::assertArrayNotHasKey('authorization', $captured['headers']);
        self::assertArrayNotHasKey('cookie', $captured['headers']);
        self::assertArrayNotHasKey('x-secret', $captured['headers']);
        self::assertArrayNotHasKey('api-key', $captured['headers']);
        self::assertArrayNotHasKey('csrf-token', $captured['headers']);
    }

    public function testOnlyTheFirstTenValuesOfARepeatedHeaderRide(): void
    {
        $this->startServer();
        $id = $this->snapshot([
            'method' => 'GET',
            'path' => '/capture.php',
            'query' => '',
            'body' => '',
            'body_replayable' => true,
            'headers' => [
                'X-Repeated' => [
                    'one', 'two', 'three', 'four', 'five', 'six',
                    'seven', 'eight', 'nine', 'ten', 'eleven',
                ],
            ],
        ]);

        $outcome = $this->runVerb([$id, $this->replayDirectory, $this->baseUrl()]);

        self::assertSame(0, $outcome->exitCode);
        self::assertSame(
            'one, two, three, four, five, six, seven, eight, nine, ten',
            $this->capturedRequest()['headers']['x-repeated'],
        );
    }

    public function testAValueWithCarriageReturnsNeverRidesAndLongValuesAreCapped(): void
    {
        $this->startServer();
        $id = $this->snapshot([
            'method' => 'GET',
            'path' => '/capture.php',
            'query' => '',
            'body' => '',
            'body_replayable' => true,
            'headers' => [
                'X-Bad' => ["a\r\nb"],
                'X-Long' => [str_repeat('v', 600)],
            ],
        ]);

        $outcome = $this->runVerb([$id, $this->replayDirectory, $this->baseUrl()]);

        self::assertSame(0, $outcome->exitCode);
        $captured = $this->capturedRequest();
        self::assertArrayNotHasKey('x-bad', $captured['headers']);
        self::assertSame(512, mb_strlen($captured['headers']['x-long']));
    }

    public function testADeadTargetAnswersTheStatusLineAndTheLocalFailure(): void
    {
        $id = $this->snapshot($this->successfulSnapshot());

        $outcome = $this->runVerb([$id, $this->replayDirectory, 'http://127.0.0.1:1']);

        self::assertSame(1, $outcome->exitCode);
        self::assertSame("HTTP status unavailable\n", $outcome->stdout);
        self::assertSame("Local replay request failed.\n", $outcome->stderr);
    }

    /**
     * Runs the verb while the transport's own diagnostics (the PHP stream
     * layer's content-type notice, the dead-target warning) stay the
     * environment's words, never the suite's verdicts.
     *
     * @param list<string> $arguments the raw arguments after the verb's name
     */
    private function runVerb(array $arguments): CommandOutcome
    {
        set_error_handler(static fn (): bool => true);

        try {
            return ErrorsReplay::run($arguments);
        } finally {
            restore_error_handler();
        }
    }

    /**
     * Starts the recording server and waits until its socket answers.
     */
    private function startServer(): void
    {
        $this->port = random_int(20000, 39999);
        $server = proc_open(
            ['php', '-S', self::HOST . ':' . $this->port, '-t', $this->documentRoot],
            [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']],
            $pipes,
        );
        if ($server === false) {
            self::fail('The recording server could not be spawned.');
        }

        $this->server = $server;

        for ($attempt = 0; $attempt < 50; ++$attempt) {
            $connection = @fsockopen(self::HOST, $this->port, $errno, $errstr, 0.1);
            if (\is_resource($connection)) {
                fclose($connection);

                return;
            }

            usleep(10000);
        }

        self::fail('The recording server did not come up.');
    }

    /**
     * Stops the recording server, when one was spawned.
     */
    private function stopServer(): void
    {
        if ($this->server === null || !\is_resource($this->server)) {
            return;
        }

        proc_terminate($this->server);
        proc_close($this->server);
        $this->server = null;
    }

    /**
     * The recording server's base URL.
     */
    private function baseUrl(): string
    {
        return 'http://' . self::HOST . ':' . $this->port;
    }

    /**
     * The last request the recording server captured.
     *
     * @return array{method: string, uri: string, body: string, headers: array<string, string>}
     */
    private function capturedRequest(): array
    {
        $captured = (string) file_get_contents($this->documentRoot . '/captured.jsonl');
        $lines = array_values(array_filter(explode("\n", $captured), static fn (string $line): bool => $line !== ''));
        $request = json_decode($lines[\count($lines) - 1], true);
        if (!\is_array($request)) {
            self::fail('The recording server answered no capturable request.');
        }

        $headers = [];
        foreach (\is_array($request['headers'] ?? null) ? $request['headers'] : [] as $name => $value) {
            if (!\is_string($name) || !\is_string($value)) {
                continue;
            }

            $headers[$name] = $value;
        }

        return [
            'method' => \is_string($request['method'] ?? null) ? $request['method'] : '',
            'uri' => \is_string($request['uri'] ?? null) ? $request['uri'] : '',
            'body' => \is_string($request['body'] ?? null) ? $request['body'] : '',
            'headers' => $headers,
        ];
    }

    /**
     * A valid snapshot id: the 32-hex-characters spelling the corridor demands.
     */
    private function id(): string
    {
        return str_repeat('ab', 16);
    }

    /**
     * Writes a snapshot with owner-only permissions and answers its id.
     *
     * @param array<string, mixed>|string $payload the snapshot's payload
     */
    private function snapshot(array|string $payload): string
    {
        $id = bin2hex(random_bytes(16));
        $path = $this->replayDirectory . '/' . $id . '.json';
        file_put_contents($path, \is_string($payload) ? $payload : json_encode($payload, \JSON_THROW_ON_ERROR));
        chmod($path, 0o600);

        return $id;
    }

    /**
     * @return array<string, mixed> a snapshot the live replay accepts
     */
    private function successfulSnapshot(): array
    {
        return [
            'method' => 'GET',
            'path' => '/capture.php',
            'query' => '',
            'body' => '',
            'body_replayable' => true,
            'headers' => [],
        ];
    }
}
