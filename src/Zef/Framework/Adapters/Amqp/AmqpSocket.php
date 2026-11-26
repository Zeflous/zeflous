<?php

declare(strict_types=1);

namespace Zef\Framework\Adapters\Amqp;

use InvalidArgumentException;

/**
 * The AMQP wire's own byte corridor: one stream to one broker,
 * opened once at construction (the refusal naming the address the
 * engine refused), its read deadline refined explicitly, the write
 * discipline that keeps writing past the partial write the engine
 * may answer, and the raw chunk read every buffered discipline above
 * pulls from -- refused when the read fails, ended when the stream
 * answers nothing, the two honest answers a frame mid-flight can
 * meet.
 *
 * The refusal family is the one the persistence port declares --
 * {@see \Zef\Framework\Event\DeferredDeliveryStore} answers
 * {@see InvalidArgumentException} from every verb, and the Redis
 * answer's own wire already spells its I/O failures that way: a
 * wire the store composes refuses in the same vocabulary, so the
 * port's caller never meets a second family to catch. One
 * connection, one lifetime: the reconnect policy belongs to the
 * deployment's own glue, never to the wire (the Redis doctrine,
 * carried verbatim -- a dead wire is a refusal every later verb
 * answers, and the broker's own session boundary answers the crash
 * window the claim holds spell).
 */
final class AmqpSocket
{
    /** @var resource the stream the engine answered for the broker connection */
    private $stream;

    /**
     * @throws InvalidArgumentException when the engine refuses the
     *                                  connection -- the address itself already answered its
     *                                  own refusals at its own construction
     */
    public function __construct(
        AmqpAddress $amqpAddress,
    ) {
        $errorCode = 0;
        $errorString = '';
        $stream = fsockopen(
            $amqpAddress->hostname(),
            $amqpAddress->port(),
            $errorCode,
            $errorString,
            $amqpAddress->timeoutSeconds(),
        );

        if ($stream === false) {
            throw new InvalidArgumentException(
                "The AMQP broker at '" . $amqpAddress->hostname() . ':'
                . $amqpAddress->port() . "' refused the connection: "
                . $errorString . ' (' . $errorCode . ').',
            );
        }

        $this->stream = $stream;
        stream_set_timeout(
            $this->stream,
            $amqpAddress->timeoutWholeSeconds(),
            $amqpAddress->timeoutMicroseconds(),
        );
    }

    /**
     * Carries one frame's bytes whole -- the remainder the engine
     * has not yet taken is the loop's own state, each pass handing
     * the untaken tail forward until nothing is left, so the
     * partial write the engine may answer is answered by another
     * write, never a truncated frame.
     *
     * @throws InvalidArgumentException when the engine refuses the write
     */
    public function write(string $bytes): void
    {
        while ($bytes !== '') {
            $bytes = $this->carry($bytes);
        }
    }

    /**
     * One write pass: the bytes the engine took answered, the tail
     * it has not yet taken handed back -- the partial write the
     * engine may answer is the write loop's own reason to exist.
     *
     * @throws InvalidArgumentException when the engine refuses the write
     */
    private function carry(string $bytes): string
    {
        $carried = fwrite($this->stream, $bytes);

        if ($carried === false || $carried === 0) {
            throw new InvalidArgumentException(
                'The AMQP connection cannot write a frame.',
            );
        }

        return mb_substr($bytes, $carried, null, '8bit');
    }

    /**
     * The next chunk the engine delivers -- the raw read the buffered
     * disciplines above this corridor pull from, one fread of at most
     * the corridor's own chunk bound, the two honest answers a frame
     * mid-flight can meet refused in one place: the refused read, and
     * the stream that answered nothing.
     *
     * @throws InvalidArgumentException when the read is refused or
     *                                  the stream ends
     */
    public function readChunk(): string
    {
        $chunk = fread($this->stream, 8192);

        if ($chunk === false || $chunk === '') {
            throw new InvalidArgumentException($chunk === false
                ? 'The AMQP connection cannot read a frame.'
                : 'The AMQP connection ended before the frame was complete.');
        }

        return $chunk;
    }

    /**
     * Releases the stream the engine answered -- the corridor owns
     * the connection it opened, once per corridor: the destructor's
     * own verb, the stream copied out so the property never carries
     * the closed resource the engine leaves behind. A dropped
     * connection is the broker's own requeue signal: the messages
     * the holds spell requeue, the next drainer's walk answers
     * them -- the crash window the at-least-once doctrine owns.
     */
    public function close(): void
    {
        $stream = $this->stream;
        fclose($stream);
    }
}
