<?php

declare(strict_types=1);

namespace Zef\Framework\Adapters\Redis;

use InvalidArgumentException;

/**
 * The RESP wire's own byte corridor: one stream to one server,
 * opened once at construction (the refusal naming the address the
 * engine refused), its read deadline refined explicitly, the write
 * discipline that keeps writing past the partial write the engine
 * may answer, and the raw chunk read every buffered discipline above
 * pulls from -- refused when the read fails, ended when the stream
 * answers nothing, the two honest answers a reply mid-flight can
 * meet.
 *
 * The refusal family is the one the persistence port declares --
 * {@see \Zef\Framework\Event\DeferredDeliveryStore} answers
 * {@see InvalidArgumentException} from every verb, and the file
 * adapter's own lane already spells its I/O failures that way: a
 * wire the store composes refuses in the same vocabulary, so the
 * port's caller never meets a second family to catch.
 */
final class RespSocket
{
    /** @var resource the stream the engine answered for the server connection */
    private $stream;

    /**
     * @param string $hostname       the server's address, non-empty
     * @param int    $port           the server's port, 1..65535
     * @param float  $timeoutSeconds the connect and read deadline, strictly above zero
     *
     * @throws InvalidArgumentException when the engine refuses the
     *                                  connection
     */
    public function __construct(
        string $hostname,
        int $port,
        float $timeoutSeconds,
    ) {
        $errorCode = 0;
        $errorString = '';
        $stream = fsockopen($hostname, $port, $errorCode, $errorString, $timeoutSeconds);

        if ($stream === false) {
            throw new InvalidArgumentException(
                "The Redis server at '{$hostname}:{$port}' refused the"
                . " connection: {$errorString} ({$errorCode}).",
            );
        }

        $this->stream = $stream;
        stream_set_timeout(
            $this->stream,
            (int) floor($timeoutSeconds),
            (int) round(($timeoutSeconds - floor($timeoutSeconds)) * 1_000_000.0),
        );
    }

    /**
     * Carries one command's bytes whole -- the partial write the
     * engine may answer is this loop's own reason to exist.
     *
     * @throws InvalidArgumentException when the engine refuses the write
     */
    public function write(string $bytes): void
    {
        while ($bytes !== '') {
            $carried = fwrite($this->stream, $bytes);

            if ($carried === false || $carried === 0) {
                throw new InvalidArgumentException(
                    'The Redis connection cannot write a command.',
                );
            }

            $bytes = mb_substr($bytes, $carried, null, '8bit');
        }
    }

    /**
     * The next chunk the engine delivers -- the raw read the buffered
     * disciplines above this corridor pull from, one fread of at most
     * the corridor's own chunk bound.
     *
     * @throws InvalidArgumentException when the read is refused or
     *                                  the stream ends
     */
    public function readChunk(): string
    {
        $chunk = fread($this->stream, 8192);

        if ($chunk === false) {
            throw new InvalidArgumentException(
                'The Redis connection cannot read a reply.',
            );
        }

        if ($chunk === '') {
            throw new InvalidArgumentException(
                'The Redis connection ended before the reply was complete.',
            );
        }

        return $chunk;
    }

    /**
     * Releases the stream the engine answered -- the corridor owns
     * the connection it opened, once per corridor: the destructor's
     * own verb, the stream copied out so the property never carries
     * the closed resource the engine leaves behind.
     */
    public function close(): void
    {
        $stream = $this->stream;
        fclose($stream);
    }
}
