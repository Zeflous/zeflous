<?php

declare(strict_types=1);

namespace Zef\Test\Support\Adapters\Redis;

/**
 * The Redis mock server's own event loop: one listener on an
 * ephemeral loopback port, every accepted client served
 * concurrently through a select loop, each connection's bytes
 * buffered until a whole command has arrived, the command applied
 * by {@see RedisMockCommands}, and the reply carried back on the
 * same stream. The loop reports its chosen address to the file the
 * spawner polls, runs until the spawner's shutdown signal lands,
 * and owns every connection it accepted -- a client that disconnects
 * mid-command is dropped with its partial bytes, never answered.
 *
 * @internal
 */
final class RedisMockServerLoop
{
    /** @var resource the listener the loop owns */
    private $listener;

    /**
     * The accepted clients the loop serves, keyed by their stream
     * identity.
     *
     * @var array<int, resource>
     */
    private array $clients = [];

    /**
     * Each client's undelivered bytes, keyed by the client's stream
     * identity.
     *
     * @var array<int, string>
     */
    private array $buffers = [];

    private readonly RedisMockCommands $redisMockCommands;

    public function __construct(
        private readonly string $reportFile,
    ) {
        $this->redisMockCommands = new RedisMockCommands();
    }

    /**
     * Runs the loop until the process is stopped -- the spawner owns
     * the process's lifetime -- or the select itself refuses, the
     * one honest exit a broken descriptor set answers: the loop ends,
     * the spawner's readiness or command exchange surfaces it, and
     * the test that needed the server fails loudly instead of
     * hanging on a dead one.
     */
    public function run(): void
    {
        $errorCode = 0;
        $errorString = '';
        $listener = stream_socket_server(
            'tcp://127.0.0.1:0',
            $errorCode,
            $errorString,
        );

        if ($listener === false) {
            return;
        }

        $this->listener = $listener;
        $address = stream_socket_get_name($listener, false);

        if ($address === false || $address === '') {
            return;
        }

        file_put_contents($this->reportFile, $address);

        while (true) {
            $read = [$this->listener];
            $write = [];
            $except = [];

            foreach ($this->clients as $client) {
                $read[] = $client;
            }

            $ready = stream_select($read, $write, $except, 0, 200_000);

            if ($ready === false) {
                return;
            }

            foreach ($read as $stream) {
                if ($stream === $this->listener) {
                    $this->accept();
                } else {
                    $this->receive($stream);
                }
            }
        }
    }

    /**
     * Accepts one waiting client -- the listener was selected ready,
     * so the accept answers immediately.
     */
    private function accept(): void
    {
        $client = stream_socket_accept($this->listener, 0);

        if ($client === false) {
            return;
        }

        $this->clients[(int) $client] = $client;
        $this->buffers[(int) $client] = '';
    }

    /**
     * Serves one selected client: the bytes the engine delivered are
     * buffered, every whole command found in the buffer is applied
     * and answered, and a client whose read answers nothing is
     * dropped with its partial bytes -- the loop owns the
     * connections it accepted.
     *
     * @param resource $stream
     */
    private function receive($stream): void
    {
        $identity = (int) $stream;
        $delivered = fread($stream, 8192);

        if ($delivered === false || $delivered === '') {
            $this->drop($identity);

            return;
        }

        $buffer = ($this->buffers[$identity] ?? '') . $delivered;
        $this->buffers[$identity] = '';

        while (true) {
            [$command, $buffer] = $this->nextCommand($buffer);

            if ($command === null) {
                break;
            }

            fwrite($stream, $this->redisMockCommands->apply($command));

            if (mb_strtoupper($command[0] ?? '') === 'QUIT') {
                $this->drop($identity);
                $buffer = '';

                break;
            }
        }

        $this->buffers[$identity] = $buffer;
    }

    /**
     * Drops one client and its partial bytes.
     */
    private function drop(int $identity): void
    {
        $client = $this->clients[$identity] ?? null;

        if ($client !== null) {
            fclose($client);
            unset($this->clients[$identity]);
        }

        unset($this->buffers[$identity]);
    }

    /**
     * The next whole command in the buffer, and the bytes that
     * remain after it -- null when the buffer holds less than one
     * whole command.
     *
     * @param string $buffer the bytes accumulated so far
     *
     * @return array{null|list<string>, string}
     */
    private function nextCommand(string $buffer): array
    {
        $headerUntil = mb_strpos($buffer, "\r\n", 0, '8bit');

        if ($headerUntil === false) {
            return [null, $buffer];
        }

        $header = mb_substr($buffer, 0, $headerUntil, '8bit');

        if ($header === '' || $header[0] !== '*' || mb_substr($header, 1, null, '8bit') === '') {
            return [null, ''];
        }

        $count = (int) mb_substr($header, 1, null, '8bit');
        $offset = $headerUntil + 2;
        $arguments = [];

        for ($index = 0; $index < $count; ++$index) {
            $lengthUntil = mb_strpos($buffer, "\r\n", $offset, '8bit');

            if ($lengthUntil === false) {
                return [null, $buffer];
            }

            $lengthHeader = mb_substr($buffer, $offset, $lengthUntil - $offset, '8bit');

            if ($lengthHeader === '' || $lengthHeader[0] !== '$') {
                return [null, ''];
            }

            $length = (int) mb_substr($lengthHeader, 1, null, '8bit');
            $payloadFrom = $lengthUntil + 2;
            $payloadUntil = $payloadFrom + $length + 2;

            if (mb_strlen($buffer, '8bit') < $payloadUntil) {
                return [null, $buffer];
            }

            $arguments[] = mb_substr($buffer, $payloadFrom, $length, '8bit');
            $offset = $payloadUntil;
        }

        return [$arguments, mb_substr($buffer, $offset, null, '8bit')];
    }
}
