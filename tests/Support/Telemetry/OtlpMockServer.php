<?php

declare(strict_types=1);

namespace Zef\Test\Support\Telemetry;

use RuntimeException;

/**
 * The OTLP loopback mock collector: the real HTTP endpoint the
 * exporter's transport delivers to -- the engine's own built-in
 * development server (`php -S`) over an ephemeral loopback port,
 * running the router fixture the same directory owns.
 *
 * The reference's own suite reached for a Python mock collector on
 * the integration tier; this port keeps the tier flat and the
 * dependency count at zero: the mock is the same PHP binary the
 * suite itself runs under, the port is the kernel's ephemeral
 * choice (parsed from the server's own startup banner), and the
 * readiness poll is a socket connect against the loopback -- the
 * same address the exporter's own stream transport will use.
 *
 * Behaviour is declared through the environment the constructor
 * shapes: the answered status, the body dump file (one appended
 * line per request -- the payloads the exporter sent), the request
 * counter file (the retry lane's observable), the leading 5xx
 * count (the transient failures the lane must retry past), and the
 * answer delay (the slow collector the exchange's timeout bounds).
 *
 * The server is stopped in {@see self::stop()}, the process and
 * its ephemeral port released -- the helper owns the process it
 * started, the same ownership discipline every double in this
 * directory carries.
 *
 * @internal
 */
final class OtlpMockServer
{
    /** @var null|resource the development server process */
    private $process;

    private string $baseUrl = '';

    private string $dumpFile = '';

    private string $countFile = '';

    /**
     * @param int $status    the status every answered request carries
     * @param int $failFirst the leading requests answered with 500 --
     *                       the transient rejection the lane retries
     * @param int $delayMs   the milliseconds every answer is held
     *                       back for
     */
    public function __construct(
        private readonly int $status = 200,
        private readonly int $failFirst = 0,
        private readonly int $delayMs = 0,
    ) {
    }

    /**
     * Starts the server and answers its base URL -- the endpoint the
     * exporter's transport POSTs to, `/v1/traces` appended by the
     * exporter's own signal-path rule.
     *
     * The command is the array form, not the shell form: `proc_open`
     * wraps a shell string in `/bin/sh -c`, and a signal sent to the
     * wrapper kills the wrapper while the server itself survives as
     * an orphan -- the array form execs the binary directly, so the
     * process this helper owns IS the server its shutdown signals.
     */
    public function start(): string
    {
        $dumpFile = tempnam(sys_get_temp_dir(), 'otlp-dump-');
        $this->dumpFile = $dumpFile === false ? '' : $dumpFile;

        $countFile = tempnam(sys_get_temp_dir(), 'otlp-count-');
        $this->countFile = $countFile === false ? '' : $countFile;

        $serverLog = tempnam(sys_get_temp_dir(), 'otlp-server-');
        $logFile = $serverLog === false ? '' : $serverLog;
        $router = __DIR__ . '/otlp-mock-router.php';

        $environment = [
            'OTLP_STATUS' => (string) $this->status,
            'OTLP_DUMP' => $this->dumpFile,
            'OTLP_COUNT' => $this->countFile,
            'OTLP_FAIL_FIRST' => (string) $this->failFirst,
            'OTLP_DELAY_MS' => (string) $this->delayMs,
        ];

        $process = proc_open(
            [\PHP_BINARY, '-S', '127.0.0.1:0', $router],
            [0 => ['pipe', 'r'], 1 => ['file', '/dev/null', 'w'], 2 => ['file', $logFile, 'w']],
            $pipes,
            __DIR__,
            $this->environment($environment),
        );
        $this->process = $process === false ? null : $process;

        if ($this->process === null) {
            throw new RuntimeException('the OTLP mock server did not start');
        }

        $banner = $this->bannerFrom($logFile);

        if ($banner === null) {
            $this->stop();

            throw new RuntimeException('the OTLP mock server did not report its port');
        }

        $port = $banner;
        $this->baseUrl = 'http://127.0.0.1:' . $port;

        $this->awaitReadiness('127.0.0.1', $port);

        return $this->baseUrl;
    }

    /**
     * The payloads the server received, one entry per request -- the
     * bodies the exporter's transport sent, in arrival order.
     *
     * @return list<string>
     */
    public function receivedBodies(): array
    {
        if ($this->dumpFile === '' || !is_file($this->dumpFile)) {
            return [];
        }

        $lines = array_filter(
            explode("\n", (string) file_get_contents($this->dumpFile)),
            static fn (string $line): bool => $line !== '',
        );

        return array_values($lines);
    }

    /**
     * The requests the server received -- the retry lane's
     * observable, one increment per attempt.
     */
    public function requests(): int
    {
        if ($this->countFile === '' || !is_file($this->countFile)) {
            return 0;
        }

        return (int) file_get_contents($this->countFile);
    }

    /**
     * Stops the server and releases its files: the helper owns the
     * process it started.
     */
    public function stop(): void
    {
        if (!\is_resource($this->process)) {
            $this->releaseFiles();

            return;
        }

        proc_terminate($this->process);
        proc_close($this->process);
        $this->process = null;
        $this->releaseFiles();
    }

    /**
     * Releases the dump and counter files: the helper owns every
     * artefact it created.
     */
    private function releaseFiles(): void
    {
        foreach ([$this->dumpFile, $this->countFile] as $file) {
            if ($file === '' || !is_file($file)) {
                continue;
            }

            unlink($file);
        }
    }

    /**
     * Polls the server's own log until the startup banner names the
     * ephemeral port -- the kernel's choice, which only the banner
     * can report.
     */
    private function bannerFrom(string $serverLog): ?int
    {
        $deadline = microtime(true) + 5.0;

        while (microtime(true) < $deadline) {
            $log = is_file($serverLog) ? (string) file_get_contents($serverLog) : '';

            if (preg_match('/127\.0\.0\.1:(\d+)/', $log, $match) === 1) {
                return (int) $match[1];
            }

            usleep(10_000);
        }

        return null;
    }

    /**
     * Polls the loopback address until the server accepts a socket
     * -- the readiness the first delivery attempt needs.
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

        throw new RuntimeException('the OTLP mock server never became ready');
    }

    /**
     * Shapes the child environment over the current one: the mock's
     * own knobs layered over whatever the suite runs under.
     *
     * @param array<string, string> $overrides
     *
     * @return array<string, string>
     */
    private function environment(array $overrides): array
    {
        $environment = getenv();

        foreach ($overrides as $name => $value) {
            $environment[$name] = $value;
        }

        return $environment;
    }
}
