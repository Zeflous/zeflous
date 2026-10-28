<?php

declare(strict_types=1);

namespace Zef\Test\Support\Adapters\Redis;

use RuntimeException;

/**
 * The Redis mock server's spawner: the real RESP server the wire's
 * integration tier speaks to -- a plain PHP process over an
 * ephemeral loopback port, started with the array-form command so
 * the process this helper owns IS the server its shutdown signal
 * stops (the {@see \Zef\Test\Support\Telemetry\OtlpMockServer}
 * discipline), its address reported through a file the spawner
 * polls, and its readiness confirmed by the same loopback socket a
 * real client will connect through.
 *
 * The server's command semantics live in {@see RedisMockCommands};
 * the serving loop in {@see RedisMockServerLoop}. Behaviour needs
 * no knobs here: the mock answers the real semantics of the command
 * subset every time, and the tests that need failures arm them on
 * the client's own side.
 *
 * @internal
 */
final class RedisMockServer
{
    /** @var null|resource the server process */
    private $process;

    private string $reportFile = '';

    private string $host = '';

    private int $port = 0;

    /**
     * Starts the server and answers its address -- the endpoint the
     * wire's clients connect to.
     *
     * @throws RuntimeException when the server does not start, does
     *                          not report its address, or never becomes ready
     */
    public function start(): void
    {
        $reportFile = tempnam(sys_get_temp_dir(), 'redis-mock-');
        $this->reportFile = $reportFile === false ? '' : $reportFile;

        $serverLog = tempnam(sys_get_temp_dir(), 'redis-mock-log-');
        $logFile = $serverLog === false ? '' : $serverLog;
        $script = __DIR__ . '/redis-mock-server.php';

        $process = proc_open(
            [\PHP_BINARY, $script, $this->reportFile],
            [0 => ['pipe', 'r'], 1 => ['file', '/dev/null', 'w'], 2 => ['file', $logFile, 'w']],
            $pipes,
            __DIR__,
        );
        $this->process = $process === false ? null : $process;

        if ($this->process === null) {
            $this->releaseFiles();

            throw new RuntimeException('the Redis mock server did not start');
        }

        $address = $this->addressFrom($this->reportFile);

        if ($address === null) {
            $this->stop();

            throw new RuntimeException('the Redis mock server did not report its address');
        }

        [$this->host, $this->port] = $address;
        $this->awaitReadiness($this->host, $this->port);
    }

    /**
     * The host the server listens on -- the loopback the spawner
     * asked for.
     */
    public function host(): string
    {
        return $this->host;
    }

    /**
     * The port the kernel chose for the server -- ephemeral, never a
     * port another test run holds.
     */
    public function port(): int
    {
        return $this->port;
    }

    /**
     * Stops the server and releases its files: the helper owns the
     * process it started.
     */
    public function stop(): void
    {
        if (\is_resource($this->process)) {
            proc_terminate($this->process);
            proc_close($this->process);
            $this->process = null;
        }

        $this->releaseFiles();
    }

    /**
     * Polls the report file until the server names its address -- the
     * kernel's own choice, which only the server can report.
     *
     * @return null|array{string, int}
     */
    private function addressFrom(string $reportFile): ?array
    {
        $deadline = microtime(true) + 5.0;

        while (microtime(true) < $deadline) {
            $report = $reportFile === '' || !is_file($reportFile)
                ? ''
                : (string) file_get_contents($reportFile);

            if (preg_match('/^(127\.0\.0\.1):(\d+)$/', $report, $match) === 1) {
                return [$match[1], (int) $match[2]];
            }

            usleep(10_000);
        }

        return null;
    }

    /**
     * Polls the loopback address until the server accepts a socket
     * -- the readiness the first client needs.
     */
    private function awaitReadiness(string $host, int $port): void
    {
        $deadline = microtime(true) + 5.0;

        while (microtime(true) < $deadline) {
            $socket = @fsockopen($host, $port, $errorCode, $errorString, 0.2);

            if (\is_resource($socket)) {
                fclose($socket);

                return;
            }

            usleep(10_000);
        }

        $this->stop();

        throw new RuntimeException('the Redis mock server never became ready');
    }

    /**
     * Releases the report file: the helper owns every artefact it
     * created.
     */
    private function releaseFiles(): void
    {
        if ($this->reportFile === '' || !is_file($this->reportFile)) {
            return;
        }

        unlink($this->reportFile);
    }
}
