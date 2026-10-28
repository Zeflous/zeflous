<?php

declare(strict_types=1);

namespace Zef\Framework\Adapters\Redis;

use InvalidArgumentException;

/**
 * The RESP wire's buffered read discipline: the bytes the engine
 * delivers ahead of the reads that asked for them, held until they
 * complete -- a line ends at the first CRLF wherever the engine
 * chose to split its chunks, and a measured run of bytes is complete
 * only when its whole length has arrived. The reassembly is the
 * reader's own reason to exist: a socket never promises a reply in
 * one read, and the grammar above this fold never asks twice.
 */
final class RespReader
{
    /** The bytes the engine delivered ahead of the reads that asked for them. */
    private string $buffer = '';

    public function __construct(
        private readonly RespSocket $respSocket,
    ) {
    }

    /**
     * The next line -- the bytes before the first CRLF, the CRLF
     * consumed, whatever the engine delivered after it buffered for
     * the reads that follow.
     *
     * @throws InvalidArgumentException when the stream dies or ends
     *                                  before a line completes
     */
    public function readLine(): string
    {
        $lineUntil = mb_strpos($this->buffer, "\r\n", 0, '8bit');

        if ($lineUntil === false) {
            $this->buffer .= $this->respSocket->readChunk();

            return $this->readLine();
        }

        $line = mb_substr($this->buffer, 0, $lineUntil, '8bit');
        $this->buffer = mb_substr($this->buffer, $lineUntil + 2, null, '8bit');

        return $line;
    }

    /**
     * The next measured run of bytes -- complete only when its whole
     * length has arrived, wherever the engine chose to split its
     * chunks.
     *
     * @throws InvalidArgumentException when the stream dies or ends
     *                                  before the run completes
     */
    public function readExact(int $length): string
    {
        if (mb_strlen($this->buffer, '8bit') < $length) {
            $this->buffer .= $this->respSocket->readChunk();

            return $this->readExact($length);
        }

        $run = mb_substr($this->buffer, 0, $length, '8bit');
        $this->buffer = mb_substr($this->buffer, $length, null, '8bit');

        return $run;
    }
}
