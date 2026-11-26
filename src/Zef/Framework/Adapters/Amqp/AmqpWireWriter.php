<?php

declare(strict_types=1);

namespace Zef\Framework\Adapters\Amqp;

use InvalidArgumentException;

/**
 * The AMQP payload's writing half: the primitive fields every method
 * argument list is composed from -- the four integer widths sharing
 * one bound guard (the refusal spelling the width's own name
 * carries), the two length-prefixed strings, and the raw corridor the
 * field-table type keys and the boolean bit field's octet ride on.
 * Pure accumulation: the caller asks for the bytes when the argument
 * list is whole.
 */
final class AmqpWireWriter
{
    private string $bytes = '';

    /**
     * One unsigned byte.
     */
    public function octet(int $value): self
    {
        return $this->append('C', $value, 0xff, 'octet');
    }

    /**
     * One unsigned sixteen-bit integer, big-endian -- the channel
     * id, the class and method ids, the negotiated ceilings.
     */
    public function short(int $value): self
    {
        return $this->append('n', $value, 0xffff, 'short');
    }

    /**
     * One unsigned thirty-two-bit integer, big-endian -- the frame
     * size, the message counts.
     */
    public function long(int $value): self
    {
        return $this->append('N', $value, 0xffffffff, 'long');
    }

    /**
     * One unsigned sixty-four-bit integer, big-endian -- the
     * delivery tag, the body size.
     */
    public function longlong(int $value): self
    {
        return $this->append('J', $value, \PHP_INT_MAX, 'longlong');
    }

    /**
     * One length-prefixed string of at most 255 bytes -- the queue
     * and routing-key names, the locale and mechanism spellings.
     */
    public function shortstr(string $value): self
    {
        $length = mb_strlen($value, '8bit');

        if ($length > 0xff) {
            throw new InvalidArgumentException(
                'An AMQP shortstr must carry at most 255 bytes, got '
                . $length . '.',
            );
        }

        return $this->octet($length)->raw($value);
    }

    /**
     * One length-prefixed string of at most four gigabytes -- the
     * SASL response the handshake spells.
     */
    public function longstr(string $value): self
    {
        return $this->long(mb_strlen($value, '8bit'))->raw($value);
    }

    /**
     * Appends raw bytes -- the corridor the length prefixes already
     * counted, the field-table type keys and the boolean bit field's
     * octet ride on.
     */
    public function raw(string $bytes): self
    {
        $this->bytes .= $bytes;

        return $this;
    }

    /**
     * The argument list's own bytes.
     */
    public function bytes(): string
    {
        return $this->bytes;
    }

    /**
     * One bounded integer appended in its own binary format -- the
     * single place the integer family's bounds refuse, the width's
     * own name and ceiling spelled.
     *
     * @throws InvalidArgumentException when the value sits outside
     *                                  the width's own bounds
     */
    private function append(string $format, int $value, int $ceiling, string $spelling): self
    {
        if ($value < 0 || $value > $ceiling) {
            throw new InvalidArgumentException(
                'An AMQP ' . $spelling . ' must sit between 0 and ' . $ceiling
                . ', got ' . $value . '.',
            );
        }

        $this->bytes .= pack($format, $value);

        return $this;
    }
}
