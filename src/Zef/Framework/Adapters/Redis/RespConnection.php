<?php

declare(strict_types=1);

namespace Zef\Framework\Adapters\Redis;

use InvalidArgumentException;

/**
 * The Redis client's one face: one command -- verb and arguments --
 * carried to the server, one reply decoded back, the request and
 * response grammars the encoder and decoder own composed over the
 * socket corridor and its buffered reader. One connection, one
 * lifetime: the stream opens at construction and closes when the
 * corridor is released, and a stream that dies is a refusal every
 * later command answers -- the reconnect policy belongs to the
 * deployment's own glue, never to the wire.
 *
 * @phpstan-import-type Reply from RespReplyDecoder
 */
final readonly class RespConnection
{
    private RespSocket $respSocket;

    private RespCommandEncoder $respCommandEncoder;

    private RespReplyDecoder $respReplyDecoder;

    /**
     * @param string $hostname       the server's address
     * @param int    $port           the server's port
     * @param float  $timeoutSeconds the connect and read deadline
     *
     * @throws InvalidArgumentException when the address, port or
     *                                  deadline refuses, or the engine refuses the
     *                                  connection
     */
    public function __construct(
        string $hostname,
        int $port,
        float $timeoutSeconds = 5.0,
    ) {
        if ($hostname === '') {
            throw new InvalidArgumentException(
                'A Redis connection must carry a non-empty hostname.',
            );
        }

        if ($port < 1 || $port > 65535) {
            throw new InvalidArgumentException(
                'A Redis connection port must be between 1 and 65535, got '
                . $port . '.',
            );
        }

        if ($timeoutSeconds <= 0.0) {
            throw new InvalidArgumentException(
                \sprintf(
                    'A Redis connection timeout must be above zero, got %s.',
                    $timeoutSeconds,
                ),
            );
        }

        $this->respSocket = new RespSocket($hostname, $port, $timeoutSeconds);
        $respReader = new RespReader($this->respSocket);
        $this->respCommandEncoder = new RespCommandEncoder();
        $this->respReplyDecoder = new RespReplyDecoder(
            $respReader,
            new RespBulkReply($respReader),
        );
    }

    public function __destruct()
    {
        $this->respSocket->close();
    }

    /**
     * Carries one command and answers its decoded reply.
     *
     * @param int|string ...$arguments the verb first, its arguments after
     *
     * @return Reply the reply's own shape
     *
     * @throws InvalidArgumentException when the command is empty, the
     *                                  wire refuses, or the server answers an error
     */
    public function command(int|string ...$arguments): array|int|string|null
    {
        $this->respSocket->write(
            $this->respCommandEncoder->encode(array_values($arguments)),
        );

        return $this->respReplyDecoder->nextReply();
    }
}
