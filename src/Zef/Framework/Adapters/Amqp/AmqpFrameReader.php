<?php

declare(strict_types=1);

namespace Zef\Framework\Adapters\Amqp;

use InvalidArgumentException;

/**
 * The AMQP frame reader: the buffered walk over the socket's chunks
 * that answers one whole frame at a time -- the seven header bytes
 * (type, channel, size), the payload the size bounds, and the end
 * byte the protocol demands after every frame. A frame whose type
 * the protocol does not spell, whose end byte is not the 0xCE the
 * grammar pins, or whose bytes the stream ended before completing,
 * is refused: a wire that speaks past the grammar is never a frame
 * to guess at. The heartbeat frame is not this fold's to skip --
 * skipping is the await discipline's own judgment, because only
 * the awaiting fold knows what was awaited when the heartbeat
 * arrived.
 */
final class AmqpFrameReader
{
    private string $buffer = '';

    public function __construct(
        private AmqpSocket $amqpSocket,
    ) {
    }

    /**
     * The next whole frame the broker carries.
     *
     * @throws InvalidArgumentException when the frame's type or end
     *                                  byte refuses, or the stream ends before the frame completes
     */
    public function frame(): AmqpFrame
    {
        $header = $this->bytes(7);
        $type = \ord($header[0]);
        $channel = AmqpUnpack::integer('n', mb_substr($header, 1, 2, '8bit'));
        $size = AmqpUnpack::integer('N', mb_substr($header, 3, 4, '8bit'));

        if (!\in_array($type, [
            AmqpFrame::TYPE_METHOD,
            AmqpFrame::TYPE_CONTENT_HEADER,
            AmqpFrame::TYPE_CONTENT_BODY,
            AmqpFrame::TYPE_HEARTBEAT,
        ], true)) {
            throw new InvalidArgumentException(
                'The AMQP frame is refused: the frame type ' . $type
                . ' is not one the protocol spells (1 method, 2 content'
                . ' header, 3 content body, 4 heartbeat).',
            );
        }

        $payload = $this->bytes($size);

        if ($this->bytes(1) !== "\xce") {
            throw new InvalidArgumentException(
                'The AMQP frame is refused: the end byte is not the 0xce'
                . ' the protocol demands after every frame.',
            );
        }

        return new AmqpFrame($type, $channel, $payload);
    }

    /**
     * The next exact byte count the discipline above demands -- the
     * buffer refilled from the socket's own chunks until the demand
     * is met, the engine's short chunk the buffering's own reason to
     * exist.
     *
     * @throws InvalidArgumentException when the stream ends before the bytes arrive
     */
    private function bytes(int $length): string
    {
        while (mb_strlen($this->buffer, '8bit') < $length) {
            $this->buffer .= $this->amqpSocket->readChunk();
        }

        $bytes = mb_substr($this->buffer, 0, $length, '8bit');
        $this->buffer = mb_substr($this->buffer, $length, null, '8bit');

        return $bytes;
    }
}
