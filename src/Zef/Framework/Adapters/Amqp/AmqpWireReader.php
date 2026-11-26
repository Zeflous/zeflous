<?php

declare(strict_types=1);

namespace Zef\Framework\Adapters\Amqp;

use InvalidArgumentException;

/**
 * The AMQP payload's reading half: the walk over one method's
 * argument bytes, one primitive at a time, refusing the moment the
 * bytes end before the field the caller asked for does -- a frame
 * whose arguments end early is a broker speaking past the grammar,
 * never a value to guess. The payload's own length is read once, at
 * construction, the walk's own bound. The boolean bit reads its own
 * octet: every method the family READS carries at most one flag, so
 * the lone-bit octet is the only packing the read side ever spells.
 */
final class AmqpWireReader
{
    private int $offset = 0;

    private readonly int $length;

    public function __construct(
        private readonly string $payload,
    ) {
        $this->length = mb_strlen($payload, '8bit');
    }

    /**
     * One unsigned byte.
     *
     * @throws InvalidArgumentException when the payload ends before the octet does
     */
    public function octet(): int
    {
        return \ord($this->next(1, 'an octet'));
    }

    /**
     * One unsigned sixteen-bit integer, big-endian.
     *
     * @throws InvalidArgumentException when the payload ends before the short does
     */
    public function short(): int
    {
        return AmqpUnpack::integer('n', $this->next(2, 'a short'));
    }

    /**
     * One unsigned thirty-two-bit integer, big-endian.
     *
     * @throws InvalidArgumentException when the payload ends before the long does
     */
    public function long(): int
    {
        return AmqpUnpack::integer('N', $this->next(4, 'a long'));
    }

    /**
     * One unsigned sixty-four-bit integer, big-endian.
     *
     * @throws InvalidArgumentException when the payload ends before the longlong does
     */
    public function longlong(): int
    {
        return AmqpUnpack::integer('J', $this->next(8, 'a longlong'));
    }

    /**
     * One length-prefixed string of at most 255 bytes.
     *
     * @throws InvalidArgumentException when the payload ends before the shortstr does
     */
    public function shortstr(): string
    {
        return $this->next($this->octet(), 'a shortstr');
    }

    /**
     * One boolean flag -- the lone-bit octet every method the family
     * reads carries.
     *
     * @throws InvalidArgumentException when the payload ends before the bit does
     */
    public function bit(): bool
    {
        return $this->next(1, 'a bit') !== "\x00";
    }

    /**
     * The next field's own bytes -- the one place the truncation
     * refusal lives, so every primitive shares it.
     *
     * @param int    $length   the field's own byte width
     * @param string $spelling the field's own name, for the refusal
     *
     * @throws InvalidArgumentException when the payload ends before the field does
     */
    private function next(int $length, string $spelling): string
    {
        if ($length < 0 || $this->offset + $length > $this->length) {
            throw new InvalidArgumentException(
                'The AMQP method arguments end before ' . $spelling . ' does.',
            );
        }

        $bytes = mb_substr($this->payload, $this->offset, $length, '8bit');
        $this->offset += $length;

        return $bytes;
    }
}
